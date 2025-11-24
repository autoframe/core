<?php
declare(strict_types=1);

namespace Autoframe\Core\ModuleBox;

use Autoframe\Core\Afr\Afr;
use Autoframe\Core\Container\AfrContainerFacade;
use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;

/**
 * Core Module Box implementation
 *
 * - Manages registration of modules and their configs.
 * - Applies disabled / replace / extend / excluded functionality rules.
 * - Resolves modules and functionalities.
 */
class AfrModuleBoxClass extends AfrSingletonAbstractClass implements AfrModuleBoxInterface
{
	/**
	 * @var AfrModuleInterface[] keyed by module FQCN
	 */
	protected array $modules = [];

	/**
	 * Raw configuration per module FQCN as registered (module manifests + app overrides).
	 *
	 * Example:
	 * [
	 *   self::bDisabledModule       => bool,
	 *   self::snModuleReplaces       => string|null,
	 *   self::snModuleExtends        => string|null,
	 *   self::aFunctionalities=> [ interfaceFqcn => [ self::FQCN=>..., 'singleton'=>..., self::bExcludedFunctionality=>... ] ],
	 * ]
	 *
	 * @var array<string,array>
	 */
	protected array $moduleConfigs = [];

	/**
	 * Effective configuration after applying extend/replace rules.
	 *
	 * @var array<string,array>
	 */
	protected array $effectiveConfigs = [];

	/**
	 * Maps base module FQCN => replacer module FQCN.
	 *
	 * @var array<string,string>
	 */
	protected array $replacementMap = [];

	/**
	 * Maps base module FQCN => list of extender module FQCNs.
	 *
	 * @var array<string,string[]>
	 */
	protected array $extensionMap = [];

	/**
	 * Lazy-build flag for $effectiveConfigs / replacement / extension maps.
	 *
	 * @var bool
	 */
	protected bool $graphBuilt = false;

	public function registerModuleInstance(AfrModuleInterface $oModule, array $aConfig = []): void
	{
		$this->pushModuleConfig($oModule, $oModule::getModuleFQCN(), $aConfig);
	}


	/**
	 * @param string|AfrModuleInterface $sFqcnModule
	 * @param array $aModConfig
	 * @return void
	 */
	public function registerModuleFQCN(string $sFqcnModule, array $aModConfig = []): void
	{
		$this->pushModuleConfig($sFqcnModule, $sFqcnModule, $aModConfig);
	}

	/**
	 * @param string|AfrModuleInterface $module
	 * @param string $sFQCN
	 * @param array $aConfig
	 * @return void
	 */
	protected function pushModuleConfig($module, string $sFQCN, array $aConfig): void
	{
		$this->modules[$sFQCN] = $module; //push instance or fqcn

		$aConfig[self::aFunctionalities] = self::mergeConfig(
			$module::getDefaultFunctionalitiesConfig(), // Merge module-provided defaults (from code) with manifest/app config
			(array)($aConfig[self::aFunctionalities] ?? [])
		);
		$this->moduleConfigs[$sFQCN] = self::mergeConfig(
			$module::getDefaultModuleConfig(),
			$aConfig // Normalize config with Defaults
		);
		$this->graphBuilt = false; // Mark graph as dirty so it will be rebuilt lazily
	}

	public function resolveModule(string $moduleFqcn): ?AfrModuleInterface
	{
		$this->buildGraphIfNeeded();

		$fqcn = $this->replacementMap[$moduleFqcn] ?? $moduleFqcn;

		if (empty($this->modules[$fqcn])) {
			return null;
		}

		$config = $this->effectiveConfigs[$fqcn] ?? $this->moduleConfigs[$fqcn] ?? [];

		if (!empty($config[self::bDisabledModule])) {
			// Disabled module cannot be resolved directly (per spec)
			return null;
		}

		$mReturn = $this->modules[$fqcn];
		if ($mReturn instanceof \Closure) {
			$mReturn = $mReturn($config);
			if ($mReturn instanceof AfrModuleInterface) {
				$mReturn->registerModuleInstance(); //init once
			}
			$this->modules[$fqcn] = $mReturn;
		}
		if (is_string($mReturn)) {
			/** @var AfrModuleInterface $mReturnInstance */
			$this->modules[$fqcn] = $mReturnInstance = $this->resolveUsingAppContainer($mReturn);
			if ($mReturnInstance instanceof AfrModuleInterface) {
				$mReturnInstance->registerModuleInstance(); //init once
			}
		}


		return $this->modules[$fqcn];//should be AfrModuleInterface or null
	}

	/**
	 * @inheritDoc
	 */
	public function resolveFunctionality(
		string  $interfaceFqcn,
		array   $context = [],
		bool    $bSingleImplementationExpected = false,
		?string $preferredFqcn = null
	)
	{
		$this->buildGraphIfNeeded();

		$instances = [];
		foreach ($this->effectiveConfigs as $moduleFqcn => $config) {
			if (empty($this->modules[$moduleFqcn])) continue;

			// Disabled module not considered for functionality resolution
			if (!empty($config[self::bDisabledModule])) continue;


			$funcConfig = $config[self::aFunctionalities][$interfaceFqcn] ?? null;
			if ($funcConfig === null) continue;

			// Excluded functionality is treated as non-existent in this module
			if (!empty($funcConfig[self::bExcludedFunctionality])) continue;


			$classFqcn = $funcConfig[self::FQCN] ?? null;
			if (!\is_string($classFqcn) || $classFqcn === '') continue;


			// If $preferredFqcn is specified and does not match, skip unless we are collecting all
			if ($bSingleImplementationExpected && $preferredFqcn !== null && $classFqcn !== $preferredFqcn) continue;


			// Instantiate via DI container to respect constructor dependencies
			$instance = $this->resolveUsingAppContainer($classFqcn);
			$instances[] = $instance;

			if ($bSingleImplementationExpected && $preferredFqcn === null) {
				// For single resolution with no preference, return first match
				return $instance;
			}

			if ($bSingleImplementationExpected && $preferredFqcn !== null && $classFqcn === $preferredFqcn) {
				return $instance;
			}
		}

		if ($bSingleImplementationExpected) {
			// No module implementation: fall back to container
			return $this->resolveUsingAppContainer($interfaceFqcn);
		}

		// For multi-instance resolution:
		if (!empty($instances)) {
			return $instances;
		}

		// No module implementations: try container (could return single or array depending on user binding)
		return $this->resolveUsingAppContainer($interfaceFqcn);
	}

	/**
	 * @param string $sFQCN
	 * @return object|mixed
	 * @throws AfrContainerException
	 */
	protected function resolveUsingAppContainer(string $sFQCN)
	{
		return Afr::app() ?
			Afr::app()->container()->get($sFQCN) :
			AfrContainerFacade::getContainer()->get($sFQCN);
	}

	/**
	 * Build effectiveConfigs, replacementMap and extensionMap lazily.
	 * Applies the conceptual rules: disabled, replaced, extended, base configs.
	 */
	protected function buildGraphIfNeeded(): void
	{
		if ($this->graphBuilt) return;

		$this->replacementMap = $this->extensionMap = $this->effectiveConfigs = [];

		// First pass: collect basic relations from raw config
		foreach ($this->moduleConfigs as $fqcn => $config) {
			$replaces = $config[self::snModuleReplaces] ?? null;
			if (\is_string($replaces) && $replaces !== '') {
				// Spec: at most one replacer per base; if multiple found, last one wins (or treat as error).
				$this->replacementMap[$replaces] = $fqcn;
			}

			$extends = $config[self::snModuleExtends] ?? null;
			if (\is_string($extends) && $extends !== '') {
				$this->extensionMap[$extends] ??= [];
				$this->extensionMap[$extends][] = $fqcn;
			}
		}

		// Second pass: build effectiveConfigs.
		// Base idea:
		// - Start from raw moduleConfigs.
		// - For each extender, merge its base config (original base) into extender config.
		// - Disable only affects resolvability, not ability to act as base for extenders.
		$this->effectiveConfigs = $this->moduleConfigs;

		foreach ($this->extensionMap as $baseFqcn => $extenders) {
			foreach ($extenders as $extenderFqcn) {
				// Merge baseConfig into extenderConfig, extender wins on conflicts.
				// We explicitly do NOT use any replacer of the base as merge source.
				$this->effectiveConfigs[$extenderFqcn] = self::mergeConfig(
					$this->moduleConfigs[$baseFqcn] ?? [], //base config
					$this->effectiveConfigs[$extenderFqcn] ?? $this->moduleConfigs[$extenderFqcn] ?? []
				);
			}
		}

		// Finally, apply "excluded functionality" semantics:
		foreach ($this->effectiveConfigs as $fqcn => &$config) {
			if (!isset($config[self::aFunctionalities]) || !\is_array($config[self::aFunctionalities])) {
				$config[self::aFunctionalities] = [];
			}

			foreach ($config[self::aFunctionalities] as $iface => $fConf) {
				if (!empty($fConf[self::bExcludedFunctionality])) {
					unset($config[self::aFunctionalities][$iface]);
				}
			}
		}
		unset($config); //TODO: test la ultima din loop

		$this->graphBuilt = true;
	}


	public static function mergeConfig(iterable $aOld, iterable $aNew): array
	{
		is_array($aOld) or $aOld = iterator_to_array($aOld);
		foreach ($aNew as $k => $v)
			if (!isset($aOld[$k])) $aOld[$k] = $v;
			elseif (is_array($aOld[$k]) && is_array($v)) $aOld[$k] = static::mergeConfig($aOld[$k], $v);
			elseif (is_int($k)) $aOld[] = $v;
			else $aOld[$k] = $v;
		return $aOld;
	}

}
