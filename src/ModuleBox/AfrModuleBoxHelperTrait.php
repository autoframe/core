<?php

namespace Autoframe\Core\ModuleBox;

use Autoframe\Core\Afr\Afr;
use Autoframe\Core\Container\AfrContainerFacade;
use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\Env\Exception\AfrEnvException;
use Autoframe\Core\Error\AfrError;
use Autoframe\Core\ModuleBox\Exception\AfrModuleException;

trait AfrModuleBoxHelperTrait {

	public static bool $bDebug = false;
	protected bool $graphBuilt = false; //Lazy-build flag for $effectiveConfigs / replacement / extension maps.

	protected array $aRunTimeFunctionCache = [];
	/** @var AfrModuleInterface[] keyed by module FQCN */
	protected array $aModulesInstances = [];
	protected array $aPushedModules = []; // holds registered module (instance|FQCN|Closure) until lazy init / resolve
	/**
	 * Raw configuration per module FQCN as registered (module manifests + app overrides).
	 * Example: [
	 *   self::bDisabledModule       => bool,
	 *   self::snModuleReplaces       => string|null,
	 *   self::snModuleExtends        => string|null,
	 *   self::aFunctionalities=> [ interfaceFqcn => [ self::FQCN=>..., 'singleton'=>..., self::bExcludedFunctionality=>... ] ],
	 * ]
	 * @var array<string,array>
	 */
	protected array $aModuleConfigs = [];// holds registered modules config and acts as a base for effective configs
	protected array $aModuleEffectiveConfigs = []; //Effective configuration after applying extend/replace rules.
	protected array $aTempOrderedConfigKeys = []; //temp reorder dependency


	protected array $aModuleReplacementMap = []; //Maps base module FQCN => replacer module FQCN.
	protected array $aModuleExtensionMap = []; //Maps base module FQCN => list of extender module FQCNs.
	protected array $aModuleIsExtenderOfOtherModule = []; //reverse map
	protected array $aModuleIsReplacerOfOtherModule = [];//reverse map

	//resolved functionalities [$sWrapKey] = ['i' => $oResolvedFunctionality,'s' => $splId, ]
	protected array $aWrapFunctionalitiesInstances = [];
	protected array $aWrapFunctionalitiesInstancesSplMap = []; //[$splId] = $sWrapKey;

	protected array $aFunctionalityConcreteFqcnAsSingletonMap = []; //map
	protected array $aBridgeFunctionalityOnCommonInstanceKeyMap = [];//map
	protected array $aFunctionalityRelatedModulesByKeyMap = [];//map



	/**
	 *  Build effectiveConfigs, replacementMap and extensionMap lazily.
	 *  Applies the conceptual rules: disabled, replaced, extended, base configs.
	 * @return void
	 * @throws AfrEnvException
	 * @throws AfrModuleException
	 */
	protected function buildGraphIfNeeded(): void
	{
		if ($this->graphBuilt) return;//TODO file cache

		$this->aRunTimeFunctionCache =
		$this->aModuleReplacementMap =
		$this->aModuleExtensionMap =
		$this->aModuleIsReplacerOfOtherModule =
		$this->aModuleIsExtenderOfOtherModule =
		$this->aTempOrderedConfigKeys =
		$this->aModuleEffectiveConfigs =
		$this->aFunctionalityConcreteFqcnAsSingletonMap =
		$this->aBridgeFunctionalityOnCommonInstanceKeyMap =
		$this->aFunctionalityRelatedModulesByKeyMap = [];

		$this->buildGraphAutoRegisterMissingDependencyModules();
		// First pass: collect basic relations from raw config
		$this->buildGraphOrderedEffectiveConfigs();
		$this->buildGraphBridgeFunctionalityOnCommonInstanceKey();
		$this->buildGraphFunctionalityKeysAndParents();

		//SET IS RESOLVABLE
		foreach ($this->aModuleEffectiveConfigs as $sModFQCN => $aX) {
			$this->aModuleEffectiveConfigs[$sModFQCN][self::iResolvableModule] =
				$this->buildGraphSetResolvableModuleHelper($sModFQCN);
		}
		$this->graphBuilt = true;

	}



	protected function buildGraphAutoRegisterMissingDependencyModules(): void
	{
		$bRerun = false;
		foreach ($this->aModuleConfigs as $config) {
			foreach ([self::snModuleExtends, self::snModuleReplaces] as $sConfKey) {
				$sModuleExtensionReplaceBase = $config[$sConfKey] ?? null;
				if (
					$sModuleExtensionReplaceBase &&
					is_string($sModuleExtensionReplaceBase) &&
					!isset($this->aModuleConfigs[$sModuleExtensionReplaceBase])
				) {
					$this->aModuleConfigs[$sModuleExtensionReplaceBase] = [__FUNCTION__ => true]; //prevent infinite loops
					$bRerun = true;
					$this->registerModuleFQCN($sModuleExtensionReplaceBase);
				}
			}
		}
		if ($bRerun) $this->buildGraphAutoRegisterMissingDependencyModules();
	}


	/**
	 * @throws AfrModuleException
	 * @throws AfrEnvException
	 */
	protected function buildGraphSetResolvableModuleHelper(string $sModuleFqcn): int
	{
		if ($snReplacement = $this->getModuleReplacementMap($sModuleFqcn, false))
			return empty($this->buildGraphSetResolvableModuleHelper($snReplacement)) ? self::RESOLVE_NONE : self::RESOLVE_REPLACED;

		if (!empty($this->aModuleEffectiveConfigs[$sModuleFqcn][self::bDisabledModule])) return self::RESOLVE_NONE;
		return !empty($this->aPushedModules[$sModuleFqcn]) || !empty($this->aModulesInstances[$sModuleFqcn]) ? self::RESOLVE_DIRECT : self::RESOLVE_NONE;
	}





	/**
	 * @throws AfrModuleException
	 * @throws AfrEnvException
	 */
	protected function buildGraphOrderedEffectiveConfigs(): void
	{
		/*
		 * 10.5 Replacement -- Detailed Semantics

		When the configuration states that ModuleB replaces ModuleA:

		-   ModuleB does not automatically inherit functionalities or
			configuration from ModuleA. Replacement is a resolution-level alias,
			not inheritance.
		-   When ModuleA is requested via Module Box, the result is an instance
			of ModuleB (or ModuleB's functionality), according to wiring.
		-   Only instances of ModuleB are generated by Module Box when resolving
			ModuleA. ModuleA is not instantiated by Module Box for that
			resolution.
		-   These rules apply even if ModuleA is disabled: resolving ModuleA via
			Module Box still yields ModuleB if the replacement relationship is
			defined.
		*/
		$this->buildGraphSetExtensionReplaceBases($this->aModuleConfigs);//unordered

		$aRemainingToOrder = [];
		foreach ($this->aModuleConfigs as $fqcn => $config) $aRemainingToOrder[$fqcn] = true;

		foreach ($this->aModuleConfigs as $fqcn => $config)
			$this->buildGraphOrderedEffectiveConfigsReferencesOrder($fqcn, $aRemainingToOrder);

		foreach ($this->aTempOrderedConfigKeys as $fqcn => $x)
			$this->aModuleEffectiveConfigs[$fqcn] = $this->aModuleConfigs[$fqcn];

		$this->buildGraphSetExtensionReplaceBases($this->aModuleEffectiveConfigs);//ordered

		if (static::$bDebug) {
			echo "\naOrderedEffectiveConfigKeys\n";
			print_r($this->aTempOrderedConfigKeys);
		}


		//merge extenders config with base
		foreach ($this->aModuleExtensionMap as $baseFqcn => $aExtenders) {
			foreach ($aExtenders as $extenderFqcn) {
				// Merge baseConfig into extenderConfig, extender wins on conflicts.
				// We explicitly do NOT use any replacer of the base as merge source.
				$aBaseConfig = $this->aModuleEffectiveConfigs[$baseFqcn];
				$aExtenderConfig = $this->aModuleEffectiveConfigs[$extenderFqcn];
				//Do not inherit explicitly the module disable flag in is not set by the extender
				//if (!empty($aBaseConfig[self::bDisabledModule]) && !array_key_exists(self::bDisabledModule, $aExtenderConfig)) unset($aBaseConfig[self::bDisabledModule]);
				$this->aModuleEffectiveConfigs[$extenderFqcn] = self::mergeConfig($aBaseConfig, $aExtenderConfig, false);
			}
		}

		$this->aTempOrderedConfigKeys = [];//done, clear
		//$this->aModuleIsReplacerOfOtherModule =	$this->aModuleIsExtenderOfOtherModule =	$this->aModuleExtensionMap = [];//TODO TEST done, clear

		// - Disable only affects resolvability, not ability to act as base for extenders.
		// - The config merge is done above this point, so below we can clean up the unwanted configs
		// Finally, apply "excluded functionality" semantics:
		foreach ($this->aModuleEffectiveConfigs as $modFQCN => &$config) {
			if (!empty($config[self::bDisabledModule]) || !isset($config[self::aFunctionalities]) || !is_array($config[self::aFunctionalities])) {
				$config[self::aFunctionalities] = [];
			}
			foreach ($config[self::aFunctionalities] as $sFuncInterfaceFqcn => $aFuncConf) {
				if (
					!empty($aFuncConf[self::bExcludedFunctionality]) ||
					empty($aFuncConf[self::sFuncConcreteFQCN]) ||
					!is_string($aFuncConf[self::sFuncConcreteFQCN])
				) {
					unset($config[self::aFunctionalities][$sFuncInterfaceFqcn]); //broken / skip
				} elseif (!empty($aFuncConf[self::bSingletonFunctionalityWithMergedSettings])) {
					$this->aFunctionalityConcreteFqcnAsSingletonMap[$aFuncConf[self::sFuncConcreteFQCN]][$modFQCN][$sFuncInterfaceFqcn] = true;
				}
			}
		}
	}


	protected function buildGraphBridgeFunctionalityOnCommonInstanceKey(): void
	{
		// Set Bridge (singleton) functionality instances
		foreach ($this->aModuleEffectiveConfigs as $modFQCN => $mconfig) {
			foreach ($mconfig[self::aFunctionalities] as $sFuncInterfaceFqcn => $aFuncConf) {
				if (empty($aFuncConf[self::sBridgeFunctionalityOnCommonInstanceKey])) continue;
				if (!is_string($sBridgeKey = $aFuncConf[self::sBridgeFunctionalityOnCommonInstanceKey])) {
					$this->aModuleEffectiveConfigs[$modFQCN][self::aFunctionalities][$sFuncInterfaceFqcn][self::sBridgeFunctionalityOnCommonInstanceKey] = false;
					continue;
				}
				$this->aBridgeFunctionalityOnCommonInstanceKeyMap[$sFuncInterfaceFqcn . '+' . $sBridgeKey][$modFQCN] = true;
			}
		}

	}

	protected function buildGraphFunctionalityKeysAndParents(): void
	{
		//SET INSTANCE WRAP KEY
		foreach ($this->aModuleEffectiveConfigs as $modFQCN => $mconfig) {
			if (!empty($mconfig[self::bDisabledModule])) continue;
//			if(!$this->isResolvableModule($modFQCN,true)) continue; //TODO: setez wrap key si in modul repalced???
//			if($this->getModuleReplacementMap($modFQCN,false)) continue; //TODO: setez wrap key si in modul repalced???
			if(!empty($this->aModuleReplacementMap[$modFQCN])) continue; //TODO: setez wrap key si in modul repalced???
			foreach ($mconfig[self::aFunctionalities] as $sFuncInterfaceFqcn => $aFuncConf) {
				if (!empty($aFuncConf[self::bExcludedFunctionality])) continue;
				$k = $this->getFunctionalityWrapKey($modFQCN, $sFuncInterfaceFqcn, $aFuncConf[self::sFuncConcreteFQCN]);
				$this->aModuleEffectiveConfigs[$modFQCN][self::aFunctionalities][$sFuncInterfaceFqcn][self::sFunctionalityWrapKey] = $k;
				//SET PARENTS BY KEY
				$this->aFunctionalityRelatedModulesByKeyMap[$k][$modFQCN][$sFuncInterfaceFqcn] = $aFuncConf[self::sFuncConcreteFQCN]; //pile up by key
			}
		}
		//getFunctionalityEffectiveConfig(object $oFunctionalityInstance): ?array
		//  getFunctionalityEffectiveConfigByWrapKey($snKey) ?array
	}


	/**
	 * @throws AfrModuleException
	 * @throws AfrEnvException
	 */
	protected function buildGraphOrderedEffectiveConfigsReferencesOrder(
		string $fqcn,
		array  &$aRemainingToOrder
	): void
	{
		$sExtends = $this->aModuleIsExtenderOfOtherModule[$fqcn] ?? null;
		$sReplaces = $this->aModuleIsReplacerOfOtherModule[$fqcn] ?? null;

		if (isset($aRemainingToOrder[$fqcn])) {
			unset($aRemainingToOrder[$fqcn]);
		} elseif (!isset($this->aTempOrderedConfigKeys[$fqcn])) {
			//Handle silently and proceed with potentially corrupted module configuration
			$sInfo = 'Circular Module Configuration Dependency (recursive loop) INFO:';
			$sInfo .= "\nModule: $fqcn;\n";
			$sInfo .= $sExtends ? "Extends: $sExtends;\n" : '';
			$sInfo .= $sReplaces ? "Replaces: $sReplaces;\n" : '';
			$sInfo .= !empty($r = $this->aModuleReplacementBaseMap[$fqcn] ?? null) ?
				"Replaced by: " . (is_array($r) ? implode('; ', $r) : $r) . ";\n" : '';
			$sInfo .= !empty($m = $this->aModuleExtensionMap[$fqcn] ?? null) ?
				"Extended by: " . (is_array($m) ? implode('; ', $m) : $m) . ";\n" : '';
			if (Afr::app() && Afr::app()->env()->isProduction()) {
				error_log($sInfo);
				AfrError::error_log($sInfo); //TODO
				return;
			}
			throw new AfrModuleException($sInfo);
		}

		if (isset($this->aTempOrderedConfigKeys[$fqcn])) return;

		if ($sExtends && !isset($this->aTempOrderedConfigKeys[$sExtends])) {
			$this->buildGraphOrderedEffectiveConfigsReferencesOrder(
				$sExtends,
				$aRemainingToOrder
			);
		}
		if ($sReplaces && !isset($this->aTempOrderedConfigKeys[$sReplaces])) {
			$this->buildGraphOrderedEffectiveConfigsReferencesOrder(
				$sReplaces,
				$aRemainingToOrder
			);
		}
		$this->aTempOrderedConfigKeys[$fqcn] = !static::$bDebug ? true :
			(!empty($this->aModuleReplacementBaseMap[$fqcn]) ? 'r' : '-') .
			(!empty($this->aModuleExtensionMap[$fqcn]) ? 'e' : '-') .
			(!empty($sReplaces) ? "R[$sReplaces]" : '-') .
			(!empty($sExtends) ? " E[$sExtends]" : '-');

	}


	protected function buildGraphSetExtensionReplaceBases(array $aModuleConfigs = null): void
	{
		$this->aModuleExtensionMap =
		$this->aModuleReplacementMap =
		$this->aModuleIsExtenderOfOtherModule =
		$this->aModuleIsReplacerOfOtherModule = [];
		$aModuleConfigs ??= $this->aModuleEffectiveConfigs ?? $this->aModuleConfigs;
		foreach ($aModuleConfigs as $fqcn => $config) {
			$sModuleExtensionBase = $config[self::snModuleExtends] ?? null;
			if (is_string($sModuleExtensionBase) && $sModuleExtensionBase !== '') {
				$this->aModuleExtensionMap[$sModuleExtensionBase] ??= [];
				$this->aModuleExtensionMap[$sModuleExtensionBase][] = $fqcn;
				$this->aModuleIsExtenderOfOtherModule[$fqcn] = $sModuleExtensionBase;
			}
			$sModuleReplaceBase = $config[self::snModuleReplaces] ?? null;
			if (is_string($sModuleReplaceBase) && $sModuleReplaceBase !== '') {
				// Spec: at most one replacer per base; if multiple found, last one wins
				$this->aModuleReplacementMap[$sModuleReplaceBase] ??= [];
				$this->aModuleReplacementMap[$sModuleReplaceBase][] = $fqcn;
				$this->aModuleIsReplacerOfOtherModule[$fqcn] = $sModuleReplaceBase;
			}
		}
	}







	protected function makeRunTimeFunctionCacheKey(string $fn, array $aFnArgs): string //K
	{
		$aRunTimeKey = $fn;
		foreach ($aFnArgs as $mArg) {
			if (is_bool($mArg)) $mArg = $mArg ? '1' : '0';
			elseif (is_null($mArg)) $mArg = 'NULL';
			elseif (is_int($mArg) || is_float($mArg)) $mArg = (string)$mArg;
			elseif (is_object($mArg)) $mArg = get_class($mArg);
			elseif (!is_string($mArg)) $mArg = serialize($mArg);
			$aRunTimeKey .= '@' . $mArg;
		}
		return $aRunTimeKey;
	}



	protected static function mergeConfig(iterable $aOld, iterable $aNew, bool $bImplicitInheritOffFlags): array //K
	{
		is_array($aOld) or $aOld = iterator_to_array($aOld);
		is_array($aNew) or $aNew = iterator_to_array($aNew);

		if (!$bImplicitInheritOffFlags) {
			foreach ([self::bDisabledModule, self::bExcludedFunctionality] as $key) {
				//Do not inherit explicitly the module disable flag if is not set by the extender
				//Do not inherit explicitly the functionality excluded flag if is not set by the extender
				if (!empty($aOld[$key]) && !array_key_exists($key, $aNew)) unset($aOld[$key]);
			}
		}

		foreach ($aNew as $k => $v) {
			//if (!isset($aOld[$k])) $aOld[$k] = $v;
			if (!array_key_exists($k, $aOld)) $aOld[$k] = $v;
			elseif (is_array($aOld[$k]) && is_array($v)) $aOld[$k] = static::mergeConfig($aOld[$k], $v, $bImplicitInheritOffFlags);
			elseif (is_int($k)) $aOld[] = $v;
			else $aOld[$k] = $v;
		}
		return $aOld;
	}



	/**
	 * @return object|mixed
	 * @throws AfrContainerException
	 */
	protected function resolveUsingAppContainer(string $sFQCN) //K
	{
		return Afr::app() ?
			Afr::app()->container()->get($sFQCN) :
			AfrContainerFacade::getContainer()->get($sFQCN);
	}



	/**
	 * @throws AfrModuleException
	 * @throws AfrEnvException
	 */
	protected function getModuleReplacementMap(string $sModuleFqcn, bool $bBuildGraphIfNeeded = true): ?string //K
	{
		if ($bBuildGraphIfNeeded) $this->buildGraphIfNeeded();

		if (!empty($this->aModuleReplacementMap[$sModuleFqcn])) {
			if (is_array($this->aModuleReplacementMap[$sModuleFqcn])) {
				return end($this->aModuleReplacementMap[$sModuleFqcn]) ?: null;
			} elseif (is_string($this->aModuleReplacementMap[$sModuleFqcn])) {
				return $this->aModuleReplacementMap[$sModuleFqcn] ?: null;
			}
		}
		return null;
	}


}