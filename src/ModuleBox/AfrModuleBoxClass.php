<?php
declare(strict_types=1);

namespace Autoframe\Core\ModuleBox;

use Autoframe\Core\Afr\Afr;
use Autoframe\Core\Container\AfrContainerFacade;
use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;
use Autoframe\Core\Event\Exception\AfrEventException;
use Autoframe\Core\Exception\AfrException;
use Closure;

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
	protected array $aModulesInstances = [];
	protected array $aPushedModules = [];
	protected array $aWrapFunctionalitiesInstances = [];

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
	protected array $aModuleConfigs = [];

	/**
	 * Effective configuration after applying extend/replace rules.
	 *
	 * @var array<string,array>
	 */
	protected array $aModuleEffectiveConfigs = [];
	protected array $aFunctionalityEffectiveConfigs = [];

	/**
	 * Maps base module FQCN => replacer module FQCN.
	 *
	 * @var array<string,string>
	 */
	protected array $aModuleReplacementMap = [];

	/**
	 * Maps base module FQCN => list of extender module FQCNs.
	 *
	 * @var array<string,string[]>
	 */
	protected array $aModuleExtensionMap = [];

	/**
	 * Lazy-build flag for $effectiveConfigs / replacement / extension maps.
	 *
	 * @var bool
	 */
	protected bool $graphBuilt = false;

	protected array $aFunctionalityConcreteFqcnAsSingletonMap = [];

	public function registerModuleInstance(AfrModuleInterface $oModule, array $aConfig = []): void
	{
		$this->pushModuleConfig($oModule, $oModule::getModuleFQCN(), $aConfig);
	}


	/**
	 * @param string $sFqcnModule
	 * @param array $aModConfig
	 * @return void
	 */
	public function registerModuleFQCN(string $sFqcnModule, array $aModConfig = []): void
	{
		$this->pushModuleConfig($sFqcnModule, $sFqcnModule, $aModConfig);
	}


	/**
	 * @param string $sFqcnModule
	 * @param Closure $oClosure
	 * @param array $aModConfig
	 * @return void
	 */
	public function registerModuleUsingClosure(string $sFqcnModule, Closure $oClosure, array $aModConfig = []): void
	{
		$this->pushModuleConfig($oClosure, $sFqcnModule, $aModConfig);
	}

	/**
	 * @param array $aConfigFQCN
	 * @return void
	 */
	public function registerModuleFqcnListFromAppConfig(array $aConfigFQCN = []): void
	{
		foreach ($aConfigFQCN as $sKey => $aFqcnConfig) {
			$aFqcnConfig = (array)$aFqcnConfig;
			if (is_string($sKey)) { //class name is as key
				$this->registerModuleFQCN($sKey, $aFqcnConfig);
				continue;
			}
			if (empty($aFqcnConfig)) continue;
			$sFqcn = array_shift($aFqcnConfig);
			$this->registerModuleFQCN($sFqcn, $aFqcnConfig);
		}

	}

	/**
	 * @param string|AfrModuleInterface|Closure $module
	 * @param string $sFQCN
	 * @param array $aConfig
	 * @return void
	 */
	protected function pushModuleConfig($module, string $sFQCN, array $aConfig = []): void
	{
		$this->aPushedModules[$sFQCN] = /*$this->aModulesInstances[$sFQCN] =*/
			$module; //push instance or fqcn for resolving

		$aConfig[self::aFunctionalities] = self::mergeConfig(
			$module::getDefaultFunctionalitiesConfig(), // Merge module-provided defaults (from code) with manifest/app config
			(array)($aConfig[self::aFunctionalities] ?? [])
		);
		$this->aModuleConfigs[$sFQCN] = self::mergeConfig(
			$module::getDefaultModuleConfig(),
			$aConfig // Normalize config with Defaults
		);
		$this->graphBuilt = false; // Mark graph as dirty so it will be rebuilt lazily
	}

	/**
	 * @param string $sModuleFqcn
	 * @return AfrModuleInterface|null
	 * @throws AfrContainerException|AfrEventException
	 * @throws AfrModuleException
	 */
	public function resolveModule(string $sModuleFqcn): ?AfrModuleInterface
	{
		$this->buildGraphIfNeeded();

		if (!empty($this->aModuleReplacementMap[$sModuleFqcn])) { //replaced
			return $this->resolveModule($this->aModuleReplacementMap[$sModuleFqcn]);
		}

		$config = $this->aModuleEffectiveConfigs[$sModuleFqcn] ?? $this->aModuleConfigs[$sModuleFqcn] ?? [];
		// Disabled module cannot be resolved directly (per spec)
		if (!empty($config[self::bDisabledModule])) return null;


		if (array_key_exists($sModuleFqcn, $this->aModulesInstances)) { //already initiated as NULL|AfrModuleInterface
			return $this->aModulesInstances[$sModuleFqcn];
		}

		if (empty($this->aPushedModules[$sModuleFqcn])) return null; //nothing was registered as FQCN|instance|Closure
		$mReturn = $this->aPushedModules[$sModuleFqcn];

		if ($mReturn instanceof Closure) {//pushed as Closure
			$mReturn = $mReturn($config);
			if (empty($mReturn) || !($bClosureReturnedString = is_string($mReturn)) && !($mReturn instanceof AfrModuleInterface)) {
				throw new AfrModuleException("Unable to resolve Module `$sModuleFqcn` because of invalid Closure return");
			}
		}
		if (is_string($mReturn)) {//pushed as FQCN|Closure returning FQCN
			try {
				$mReturn = $this->resolveUsingAppContainer($mReturn);
			} catch (\Throwable $e) {
				throw new AfrModuleException(
					"Unable to resolve Module using App Container `$sModuleFqcn`@[$mReturn]\n" .
					$e->getMessage(), $e->getCode(), $e
				);
			}
		}

		if ($mReturn instanceof AfrModuleInterface) {
			$mReturn->registerModuleInstance();
			AfrModuleRelations::getInstance()->pushModuleInstance($mReturn);
			unset($this->aPushedModules[$sModuleFqcn]);//cleanup initial push
			return $this->aModulesInstances[$sModuleFqcn] = $mReturn;
		} elseif (!empty($bClosureReturnedString)) {
			throw new AfrModuleException("Unable to resolve using Closure for `$sModuleFqcn`");
		}
		return $this->aModulesInstances[$sModuleFqcn] = null;
	}


	//TODO: add interface
	public function resolveFunctionalityByModuleInstance(string $sFuncInterfaceFqcn, AfrModuleInterface $qModuleInstance): ?AfrFunctionalityInterface
	{
		return $this->resolveFunctionalityByModuleFQCN($sFuncInterfaceFqcn, get_class($qModuleInstance));
	}

	//TODO: add interface
	public function resolveFunctionalityByModuleFQCN(string $sFuncInterfaceFqcn, string $sModuleFQCN): ?AfrFunctionalityInterface
	{
		//MODULE IS REPLACED by another implementation, so we get the functionality from there,
		// because this module should not instanciate
		if (!empty($this->aModuleReplacementMap[$sModuleFQCN])) {
			return $this->resolveFunctionalityByModuleFQCN($sFuncInterfaceFqcn, $this->aModuleReplacementMap[$sModuleFQCN]);
		}
		//TODO instance cache here?? Nu cred, ca este in context + singleton + cheie
		$aModCfg = $this->getFunctionalityConcreteByInterface($sFuncInterfaceFqcn);
		foreach ($aModCfg as $moduleFqcn => $sFuncConcreteFqcn) {
			if ($moduleFqcn !== $sModuleFQCN) continue;
			return $this->getFunctionalityWrap($sModuleFQCN, $sFuncConcreteFqcn, $sFuncInterfaceFqcn);
		}
		return null;

	}


	/**
	 * @inheritDoc
	 */
	public function resolveFunctionality(
		string  $sFuncInterfaceFqcn,
		?string $preferredFqcn = null
	)
	{
		$mix = $this->resolveFunctionalityResolver($sFuncInterfaceFqcn, $preferredFqcn);
		if (is_array($mix)) {
			foreach ($mix as $m) {

			}
		}
		return $mix;
	}

	/**
	 * @param string $sFuncInterfaceFqcn
	 * @param string|null $preferredFqcnOnly
	 * @return AfrFunctionalityInterface[]
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 */
	protected function resolveFunctionalityResolver(
		string  $sFuncInterfaceFqcn,
		?string $preferredFqcnOnly = null
	): array
	{
		$aModCfg = $this->getFunctionalityConcreteByInterface($sFuncInterfaceFqcn);
		/*		$bSingletonModClass = false;
				foreach ($aModCfg as $moduleFqcn => $sFuncConcreteFqcn) {
					if (!empty($this->aFunctionalityFqcnAsSingletonMap[$sFuncConcreteFqcn][$moduleFqcn])) {
						$bSingletonModClass = true;
						break;
					}
				}*/
		//TODO: parent module when initing
		if (count($aModCfg) > 1) {
			// TODO: magie cu debug backtrace, ca daca vin din instanta de modul, sa iau componenta specifica acelui modul
		}
		if ($preferredFqcnOnly) {
			//reguli single | array???
		}
		if (!empty($aExcludedModulesFqcnsAsResolvers)) {
			//reguli single | array???
		}


		$aReturnInstances = [];
		foreach ($aModCfg as $moduleFqcn => $sFuncConcreteFqcn) {
			$onFunctionalityInstance = $this->getFunctionalityWrap($moduleFqcn, $sFuncConcreteFqcn,$sFuncInterfaceFqcn);
			if (!empty($onFunctionalityInstance)) {
				$aReturnInstances[$this->getFunctionalityWrapKey($sFuncConcreteFqcn, $moduleFqcn)] = $onFunctionalityInstance;
			}


		}

		if (!empty($aReturnInstances)) return $aReturnInstances;

		// No module implementations: try container (could return single or array depending on user binding)
		return [$sFuncInterfaceFqcn => $this->resolveUsingAppContainer($sFuncInterfaceFqcn)]; //todo force array | preffered
	}

	/*
		protected function resolveFunctionalityResolverSingleInstance(
			string  $interfaceFqcn,
			?string $preferredFqcn = null
		): ?AfrFunctionalityInterface
		{
			$aInstanceFqcnSeed = [];
			//loop effective MODULE configs
			foreach ($this->getFunctionalityConcreteByInterface($interfaceFqcn) as $moduleFqcn => $classFqcn) {
				// If $preferredFqcn is specified and does not match, skip unless we are collecting all
				if ($preferredFqcn !== null && $classFqcn !== $preferredFqcn) continue;

				// Instantiate via DI container to respect constructor dependencies
				//$instance = $this->resolveUsingAppContainer($classFqcn);
				$aInstanceFqcnSeed[$classFqcn] = $classFqcn;

				// For single resolution with no preference, return first match
				if ($preferredFqcn === null) return $this->resolveUsingAppContainer($classFqcn); // return $instance;

				if ($preferredFqcn !== null && $classFqcn === $preferredFqcn) return $this->resolveUsingAppContainer($classFqcn);// return $instance;

			}

			//return first possible implementation
			foreach ($aInstanceFqcnSeed as $sReturnFqcn) {
				return $this->resolveUsingAppContainer($sReturnFqcn);
			}
			// No module implementations: try container (could return single or array depending on user binding)
			return $this->resolveUsingAppContainer($interfaceFqcn); //todo force array | preffered
		}
	*/

	protected function getFunctionalityConcreteByInterface(string $sFuncInterfaceFqcn): array
	{
		$this->buildGraphIfNeeded();
		$aFunctionalityConcrete = [];
		//loop effective MODULE configs
		foreach ($this->aModuleEffectiveConfigs as $sModuleFqcn => $aModuleConfig) {
			// Disabled module not considered for functionality resolution
			if (!empty($aModuleConfig[self::bDisabledModule])) continue;

			//TODO: check handle module replacer check =>!!! wth, in effective config am si config de la modulul repalced!!!!
			if (!empty($this->aModuleReplacementMap[$sModuleFqcn])) continue;

			$aFuncConfig = $aModuleConfig[self::aFunctionalities][$sFuncInterfaceFqcn] ?? null;
			if ($aFuncConfig === null) continue;

			// Excluded functionality is treated as non-existent in this module
			if (!empty($aFuncConfig[self::bExcludedFunctionality])) continue;

			if (!empty($aFuncConfig[self::sConcreteFQCN]) && is_string($aFuncConfig[self::sConcreteFQCN])) {
				$aFunctionalityConcrete[$sModuleFqcn] = $aFuncConfig[self::sConcreteFQCN];
			}

		}
		return $aFunctionalityConcrete;
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

		$this->aModuleReplacementMap =
		$this->aModuleExtensionMap =
		$this->aModuleEffectiveConfigs =
		$this->aFunctionalityEffectiveConfigs =
		$this->aFunctionalityConcreteFqcnAsSingletonMap = [];

		// First pass: collect basic relations from raw config
		foreach ($this->aModuleConfigs as $fqcn => $config) {
			$replaces = $config[self::snModuleReplaces] ?? null;
			if (\is_string($replaces) && $replaces !== '') {
				// Spec: at most one replacer per base; if multiple found, last one wins (or treat as error).
				$this->aModuleReplacementMap[$replaces] = $fqcn;
			}

			$extends = $config[self::snModuleExtends] ?? null;
			if (\is_string($extends) && $extends !== '') {
				$this->aModuleExtensionMap[$extends] ??= [];
				$this->aModuleExtensionMap[$extends][] = $fqcn;
			}
		}
		//TODO
		// Second pass: build effectiveConfigs.
		// Base idea:
		// - Start from raw moduleConfigs.
		// - For each extender, merge its base config (original base) into extender config.
		// - Disable only affects resolvability, not ability to act as base for extenders.
		$this->aModuleEffectiveConfigs = $this->aModuleConfigs;

		foreach ($this->aModuleExtensionMap as $baseFqcn => $extenders) {
			foreach ($extenders as $extenderFqcn) {
				// Merge baseConfig into extenderConfig, extender wins on conflicts.
				// We explicitly do NOT use any replacer of the base as merge source.
				$this->aModuleEffectiveConfigs[$extenderFqcn] = self::mergeConfig(
					$this->aModuleConfigs[$baseFqcn] ?? [], //base config
					$this->aModuleEffectiveConfigs[$extenderFqcn] ?? $this->aModuleConfigs[$extenderFqcn] ?? []
				);
			}
		}

		// Finally, apply "excluded functionality" semantics:
		foreach ($this->aModuleEffectiveConfigs as $modFQCN => &$config) {
			if (!isset($config[self::aFunctionalities]) || !\is_array($config[self::aFunctionalities])) {
				$config[self::aFunctionalities] = [];
			}

			foreach ($config[self::aFunctionalities] as $sFuncInterfaceFqcn => $aFuncConf) {
				if (!empty($aFuncConf[self::bExcludedFunctionality]) || empty($aFuncConf[self::sConcreteFQCN])) {
					unset($config[self::aFunctionalities][$sFuncInterfaceFqcn]); //broken / skip
				} elseif (!empty($aFuncConf[self::bSingletonSpawn])) {
					//TODO: singleton instance in modul sau singleton in toate modulele pentru clasa concreta?
					// daca o mapez ca si singleton undeva, ea va trebui sa ramana singleton / bridge / semi container
					// PUN FLAG|env cum si ce, dar asta poate afecta coneziunea de functionare intre module
					// $this->aFunctionalityFqcnAsSingletonMap[$funcConfig[self::FQCN]][$sModFQCN] = $interfaceFqcn;
					$this->aFunctionalityConcreteFqcnAsSingletonMap[$aFuncConf[self::sConcreteFQCN]][$modFQCN] = $sFuncInterfaceFqcn;
				}
			}
		}

		foreach ($this->aModuleEffectiveConfigs as $sModFQCN => $aModCfg) {
			foreach ($aModCfg[self::aFunctionalities] as $sFuncInterfaceFqcn => $aFuncConf) {
				//push also interface cfg as an easy way to access it
				$this->aFunctionalityEffectiveConfigs[$sFuncInterfaceFqcn][$sModFQCN] = $aFuncConf;

				//force all concrete implementations to singleton
				if (!empty($this->aFunctionalityConcreteFqcnAsSingletonMap[$aFuncConf[self::sConcreteFQCN]])) {
					$aFuncConf[self::bSingletonSpawn] = true;
					$aFuncConf[self::bSingletonSpawn . 'ForceByModules'] =
						array_keys($this->aFunctionalityConcreteFqcnAsSingletonMap[$aFuncConf[self::sConcreteFQCN]]);
				}
				//push concrete cfg
				$this->aFunctionalityEffectiveConfigs[$aFuncConf[self::sConcreteFQCN]][$sModFQCN] = $aFuncConf;
			}
		}

		$this->graphBuilt = true;
	}


	protected static function mergeConfig(iterable $aOld, iterable $aNew): array
	{
		is_array($aOld) or $aOld = iterator_to_array($aOld);
		foreach ($aNew as $k => $v)
			if (!isset($aOld[$k])) $aOld[$k] = $v;
			elseif (is_array($aOld[$k]) && is_array($v)) $aOld[$k] = static::mergeConfig($aOld[$k], $v);
			elseif (is_int($k)) $aOld[] = $v;
			else $aOld[$k] = $v;
		return $aOld;
	}

	public function getEffectiveModulesList(): array
	{
		$this->buildGraphIfNeeded();
		return $this->aModuleEffectiveConfigs;
	}

	public function getEffectiveFunctionalitiesList(): array
	{
		$this->buildGraphIfNeeded();
		return $this->aFunctionalityEffectiveConfigs;
	}

	protected function getFunctionalityWrapKey(string $sFuncConcreteFqcn, string $moduleFqcn): string
	{
		//	$this->aFunctionalityFqcnAsSingletonMap[$aFuncConf[self::sConcreteFQCN]][$modFQCN] = $sFuncInterfaceFqcn;
		//	return (!empty($this->aFunctionalityFqcnAsSingletonMap[$sFuncConcreteFqcn]) ? $moduleFqcn . '|' : '') . $sFuncConcreteFqcn;
		return $sFuncConcreteFqcn.(!empty($this->aFunctionalityConcreteFqcnAsSingletonMap[$sFuncConcreteFqcn]) ? '' : '@'.$moduleFqcn);
	}

	/**
	 * @param string $moduleFqcn
	 * @param string $sFuncConcreteFqcn
	 * @param string|null $sFuncInterfaceFqcn
	 * @return AfrFunctionalityInterface|null
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 * @throws AfrModuleException
	 * @throws AfrModuleFunctionalityException
	 */
	protected function getFunctionalityWrap(
		string $moduleFqcn,
		string $sFuncConcreteFqcn,
		string $sFuncInterfaceFqcn = null
	): ?AfrFunctionalityInterface
	{
		if ($sFuncInterfaceFqcn && !is_subclass_of($sFuncConcreteFqcn, $sFuncInterfaceFqcn)) {
			throw new AfrModuleFunctionalityException("Interface $sFuncInterfaceFqcn is not implemented by $sFuncConcreteFqcn in module $moduleFqcn");
		}


//	if (empty($this->aModulesInstances[$moduleFqcn])) {
		$this->resolveModule($moduleFqcn);
		//IF SINGLETIN??? functionalitati stackable / not stackble
		// CHECK conflict logic: filterEffectiveModuleConfigsForFunctionalityInterface
		// CHECK conflict logic: filterEffectiveModuleConfigsForFunctionalityInterface
		// CHECK conflict logic: filterEffectiveModuleConfigsForFunctionalityInterface
		// CHECK conflict logic: filterEffectiveModuleConfigsForFunctionalityInterface
		// CHECK conflict logic: filterEffectiveModuleConfigsForFunctionalityInterface
		//TODO: daca vreau sa extind un modul, si folosesc numai extensia, atunci de ce extantiez si baza obligatoriu?
		//TODO: daca vreau sa extind un modul, si folosesc numai extensia, atunci de ce extantiez si baza obligatoriu?
		//TODO: daca vreau sa extind un modul, si folosesc numai extensia, atunci de ce extantiez si baza obligatoriu?
		//TODO: daca vreau sa extind un modul, si folosesc numai extensia, atunci de ce extantiez si baza obligatoriu?
		//TODO: daca vreau sa extind un modul, si folosesc numai extensia, atunci de ce extantiez si baza obligatoriu?
		//	}
		$key = $this->getFunctionalityWrapKey($sFuncConcreteFqcn, $moduleFqcn);
		if (!array_key_exists($key, $this->aFunctionalityConcreteFqcnAsSingletonMap)) {
			//TODO: SINGLETON CONCRETE|SINGLETON|INTERFACE
			//TODO: SINGLETON CONCRETE|SINGLETON|INTERFACE
			//TODO: SINGLETON CONCRETE|SINGLETON|INTERFACE
			//TODO: SINGLETON CONCRETE|SINGLETON|INTERFACE
			//TODO: SINGLETON CONCRETE|SINGLETON|INTERFACE
			//TODO: SINGLETON CONCRETE|SINGLETON|INTERFACE
			//TODO: SINGLETON CONCRETE|SINGLETON|INTERFACE
			//TODO: SINGLETON CONCRETE|SINGLETON|INTERFACE
			//TODO: SINGLETON CONCRETE|SINGLETON|INTERFACE
			//TODO: SINGLETON CONCRETE|SINGLETON|INTERFACE
			//TODO: SINGLETON CONCRETE|SINGLETON|INTERFACE
			//TODO: SINGLETON CONCRETE|SINGLETON|INTERFACE
			//TODO: SINGLETON CONCRETE|SINGLETON|INTERFACE
			//TODO: SINGLETON CONCRETE|SINGLETON|INTERFACE
			//TODO: SINGLETON CONCRETE|SINGLETON|INTERFACE
			//TODO: SINGLETON CONCRETE|SINGLETON|INTERFACE
			//TODO: SINGLETON CONCRETE|SINGLETON|INTERFACE
			//TODO: SINGLETON CONCRETE|SINGLETON|INTERFACE
			//TODO: SINGLETON CONCRETE|SINGLETON|INTERFACE
			$this->aWrapFunctionalitiesInstances[$key] = null; //init key
			//empty($this->aWrapFunctionalitiesInstances[$key])
			try {
				/** @var $oResolvedFunctionality AfrFunctionalityInterface */
				$oResolvedFunctionality = $this->resolveUsingAppContainer($sFuncConcreteFqcn);
				$oResolvedFunctionality->attachParentModuleFQCN($sFuncConcreteFqcn);
				//TODO store into $instances as static!!!!!!!
				$this->aWrapFunctionalitiesInstances[$key] = $oResolvedFunctionality;
				AfrModuleRelations::getInstance()->pushFunctionalityInstance($this->aWrapFunctionalitiesInstances[$key]);

			} catch (\Throwable $e) {
				throw new AfrModuleFunctionalityException(
					"Unable to resolve Functionality using App Container `$sFuncConcreteFqcn`@[$moduleFqcn]\n" .
					$e->getMessage(), $e->getCode(), $e);
			}
		}
		return $this->aWrapFunctionalitiesInstances[$key] ?? null;
	}
}
