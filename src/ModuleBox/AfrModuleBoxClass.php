<?php
declare(strict_types=1);

namespace Autoframe\Core\ModuleBox;

use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;
use Autoframe\Core\Env\Exception\AfrEnvException;
use Autoframe\Core\Event\Exception\AfrEventException;
use Autoframe\Core\ModuleBox\Exception\AfrModuleException;
use Autoframe\Core\ModuleBox\Exception\AfrModuleFunctionalityException;
use Closure;


//TODO A) sub acelasi modul, mai multe interfete pot fi implementate de o clasa comuna, deci instanta comuna, deci merge config
//TODO A.2) ce se intampla atunci cand se extinde modulul cu configul? probabil se ia ca si bucati de interfata
//TODO B) daca o clasa trebuie sa fie functionalitate si ea nu are o interfata implementata, atunci se poate face
//TODO B.1) YES: incapsulare in  interfata noua + clasa cu forward parametrii la instanta
//TODO B.2) --se poate crea i interfata pentru clasa fn, dar interfata nu va fi implementata, deci break SOLID :(

/**
 * Core Module Box implementation
 *
 * - Manages registration of modules and their configs.
 * - Applies disabled / replace / extend / excluded functionality rules.
 * - Resolves modules and functionalities.
 */
class AfrModuleBoxClass extends AfrSingletonAbstractClass implements AfrModuleBoxInterface
{
	use AfrModuleBoxHelperTrait;

	public function registerModuleFQCN(string $sFqcnModule, array $aModConfig = []): void //K
	{
		$this->pushModuleConfig($sFqcnModule, $sFqcnModule, $aModConfig);
	}

	public function registerModuleUsingClosure(string $sFqcnModule, Closure $oClosure, array $aModConfig = []): void //K
	{
		$this->pushModuleConfig($oClosure, $sFqcnModule, $aModConfig);
	}

	public function registerModuleInstance(AfrModuleInterface $oModule, array $aModConfig = []): void //K
	{
		$this->pushModuleConfig($oModule, get_class($oModule), $aModConfig);
	}


	public function registerModuleFqcnListFromAppConfig(array $aConfigFQCN = []): void //K
	{
		foreach ($aConfigFQCN as $sKey => $aFqcnConfig) {
			if (is_string($sKey)) { //class name is as key
				$this->registerModuleFQCN($sKey, (array)$aFqcnConfig);
				continue;
			}
			if (empty($aFqcnConfig) || !is_array($aFqcnConfig)) continue;
			$sFqcn = array_shift($aFqcnConfig);
			$this->registerModuleFQCN($sFqcn, $aFqcnConfig);
		}
	}

	/** @param string|AfrModuleInterface|Closure $module */
	protected function pushModuleConfig($module, string $sFQCN, array $aConfig = []): void //K
	{
		$this->aPushedModules[$sFQCN] = $module; //push instance or fqcn for later resolving

		$aConfig[self::aFunctionalities] = self::mergeConfig(
		/** @var AfrModuleInterface $sFQCN */
			$sFQCN::getDefaultFunctionalitiesConfig(), // Merge module-provided defaults (from code) with manifest/app config
			(array)($aConfig[self::aFunctionalities] ?? []),
			true
		);

		// Normalize config with Defaults
		$aConfig = self::mergeConfig($sFQCN::getDefaultModuleConfig(), $aConfig, true);

		//Some Partial Config Already Exists
		if (is_array($this->aModuleConfigs[$sFQCN] ?? false) && count($this->aModuleConfigs[$sFQCN]) === 1) {
			$aConfig = self::mergeConfig($this->aModuleConfigs[$sFQCN], $aConfig, true);
		}

		foreach ($aConfig[self::aFunctionalities] as &$aFnCfg) {
			if (isset($aFnCfg[self::sFunctionalityWrapKey]))
				unset($aFnCfg[self::sFunctionalityWrapKey]); //do not preserve any external wrap keys
		}

		$this->aModuleConfigs[$sFQCN] = $aConfig;
		if (isset($this->aModuleConfigs[$sFQCN][self::iResolvableModule]))
			unset($this->aModuleConfigs[$sFQCN][self::iResolvableModule]); //will be set in buildGraphIfNeeded()
		if (($this->aModuleConfigs[$sFQCN][self::snModuleExtends] ?? null) === $sFQCN)
			unset($this->aModuleConfigs[$sFQCN][self::snModuleExtends]); //prevent self extension
		if (($this->aModuleConfigs[$sFQCN][self::snModuleReplaces] ?? null) === $sFQCN)
			unset($this->aModuleConfigs[$sFQCN][self::snModuleReplaces]); //prevent self replacing

		$this->graphBuilt = false; // Mark graph as dirty so it will be rebuilt lazily
	}

	/** @throws AfrModuleException|AfrEnvException */
	public function isResolvableModule(string $sModuleFqcn, bool $bCountReplacersAsTrue): bool //K
	{
		$this->buildGraphIfNeeded();
		$iResolvableModule = $this->aModuleEffectiveConfigs[$sModuleFqcn][self::iResolvableModule] ?? self::RESOLVE_NONE;
		if ($iResolvableModule === self::RESOLVE_DIRECT) return true;
		if ($iResolvableModule === self::RESOLVE_REPLACED && $bCountReplacersAsTrue) return true;
		return false;
	}

	/** @throws AfrModuleException|AfrEnvException */
	public function isResolvedModule(string $sModuleFqcn, bool $bCountReplacersAsTrue, bool $bCountEmptyInstanceAsResolved = false): bool //K
	{
		$this->buildGraphIfNeeded();
		if ($bCountReplacersAsTrue && $snReplacement = $this->getModuleReplacementMap($sModuleFqcn)) {
			return $this->isResolvedModule($snReplacement, true);
		}
		return $bCountEmptyInstanceAsResolved ?
			array_key_exists($sModuleFqcn, $this->aModulesInstances) :
			($this->aModulesInstances[$sModuleFqcn] ?? null) instanceof AfrModuleInterface;
	}

	/** @throws AfrModuleException|AfrEnvException */
	public function isDisabledModule(string $sModuleFqcn): ?bool //K
	{
		$this->buildGraphIfNeeded();
		return $this->aModuleEffectiveConfigs[$sModuleFqcn][self::bDisabledModule] ?? null;
	}

	/** @throws AfrModuleException|AfrEnvException */

	public function getModuleInfo(string $sModuleFqcn): ?array //K
	{
		$this->buildGraphIfNeeded();
		if (empty($this->aModuleEffectiveConfigs[$sModuleFqcn])) return null;
		return [
			'isDisabledModule' => $this->isDisabledModule($sModuleFqcn),
			'isResolvedModuleDirect' => $this->isResolvedModule($sModuleFqcn, false),
			'isResolvableModuleDirect' => $this->isResolvableModule($sModuleFqcn, false),
			'isResolvedModuleDirectOrReplacer' => $this->isResolvedModule($sModuleFqcn, true),
			'isResolvableModuleDirectOrReplacer' => $this->isResolvableModule($sModuleFqcn, true),
			'getModuleReplacementMap' => $this->getModuleReplacementMap($sModuleFqcn),
			'getModuleEffectiveConfigs' => $this->getModuleEffectiveConfigs($sModuleFqcn),
			'getModuleBaseConfigs' => $this->aModuleConfigs[$sModuleFqcn] ?? null,
			'aModuleExtensionMap' => $this->aModuleExtensionMap[$sModuleFqcn] ?? null,
			'sExtends' => $this->aModuleIsExtenderOfOtherModule[$sModuleFqcn] ?? null,
			'sReplaces' => $this->aModuleIsReplacerOfOtherModule[$sModuleFqcn] ?? null,
		];
	}


	/** @throws AfrModuleException|AfrEnvException */

	public function getModulesEffectiveConfigsList(): array //K
	{
		$this->buildGraphIfNeeded();
		return $this->aModuleEffectiveConfigs;
	}

	/** @throws AfrModuleException|AfrEnvException */

	public function getModuleEffectiveConfigs(string $sModuleFqcn): ?array //K
	{
		$this->buildGraphIfNeeded();
		return $this->aModuleEffectiveConfigs[$sModuleFqcn] ?? null;
	}


	/**
	 * @param string $sModuleFqcn
	 * @return AfrModuleInterface|null
	 * @throws AfrContainerException|AfrEventException
	 * @throws AfrModuleException|AfrEnvException
	 */
	public function resolveModule(string $sModuleFqcn): ?AfrModuleInterface //K
	{
		$this->buildGraphIfNeeded();

		// Replaced module, not directly resolvable
		if ($snReplacement = $this->getModuleReplacementMap($sModuleFqcn))
			return $this->resolveModule($snReplacement);

		// Disabled module cannot be resolved directly (per spec)
		if ($this->isDisabledModule($sModuleFqcn)) return null;

		if (empty($this->aPushedModules[$sModuleFqcn])) {
			return array_key_exists($sModuleFqcn, $this->aModulesInstances) ?
				$this->aModulesInstances[$sModuleFqcn] : // Already initiated as NULL|AfrModuleInterface
				null; // Nothing was registered as FQCN|instance|Closure
		}

		//resolve from registered / pushed config:
		$this->aModulesInstances[$sModuleFqcn] = null;
		$mReturn = $this->aPushedModules[$sModuleFqcn];
		unset($this->aPushedModules[$sModuleFqcn]);//cleanup initial push

		if ($mReturn instanceof Closure) {//pushed as Closure
			$mReturn = $mReturn($this->getModuleEffectiveConfigs($sModuleFqcn) ?? []);
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
		//	AfrModuleRelations::getInstance()->pushModuleInstance($mReturn);
			$this->aModulesInstances[$sModuleFqcn] = $mReturn;
		} elseif (!empty($bClosureReturnedString)) {
			throw new AfrModuleException("Unable to resolve using Closure for `$sModuleFqcn`");
		}

		return $this->aModulesInstances[$sModuleFqcn];
	}


	/** @throws AfrModuleException|AfrEnvException */
	public function getFunctionalityEffectiveConfigsByModuleInterface(string $sModuleFqcn, string $sFunctionalityInterface): ?array //K
	{
		$sFuncConcreteFqcn = (string)$this->getFunctionalityConcreteByModuleAndInterface($sModuleFqcn, $sFunctionalityInterface);
		if (empty($sFuncConcreteFqcn) || empty($snKey = $this->getFunctionalityWrapKey($sModuleFqcn, $sFunctionalityInterface, $sFuncConcreteFqcn))) return null;
		return $this->getFunctionalityEffectiveConfigByWrapKey($snKey);
	}


	/** @throws AfrModuleException|AfrEnvException */
	public function getFunctionalityEffectiveConfig(object $oFunctionalityInstance): ?array//K
	{
		if (empty($snKey = $this->getFunctionalityWrapKeyByFuncInstance($oFunctionalityInstance))) return null;
		return $this->getFunctionalityEffectiveConfigByWrapKey($snKey);
	}

	/** @throws AfrModuleException|AfrEnvException */
	public function getFunctionalityEffectiveConfigByWrapKey(string $sWrapKey): ?array //K TODO CHEKC:
	{
		$this->buildGraphIfNeeded();
		$aRunTimeKey = $this->makeRunTimeFunctionCacheKey(__FUNCTION__, [$sWrapKey]);
		if (array_key_exists($aRunTimeKey, $this->aRunTimeFunctionCache)) return $this->aRunTimeFunctionCache[$aRunTimeKey];

		$aFnConfig = null;
		foreach ($this->aModuleEffectiveConfigs as $sModFqcnLoop => $aModCfg) {
			//TODO: daca este inlocuit, atunci nu dau merge???
			// FOLLOWUP: Modulele inlocuite sunt ignorate. Daca extind si inlocuiesc un modul,
			// atunci extender va avea ceva din config original, deci pot da skip la baza aici
			if (!empty($aModCfg[self::bDisabledModule]) || !empty($this->getModuleReplacementMap($sModFqcnLoop))) continue;

			foreach ($aModCfg[self::aFunctionalities] as $aLoopFnCfg) {
				$aFnConfig = $aLoopFnCfg[self::sFunctionalityWrapKey] === $sWrapKey &&
				empty($aLoopFnCfg[self::bExcludedFunctionality]) ?
					self::mergeConfig($aFnConfig ?? [], $aLoopFnCfg, false) : $aFnConfig;

			}
		}
		return $this->aRunTimeFunctionCache[$aRunTimeKey] = $aFnConfig;
	}

	/** @throws AfrModuleException|AfrEnvException */
	public function getFunctionalityRelatedModulesTree(object $oFunctionalityInstance): ?array //K
	{
		$this->buildGraphIfNeeded();
		return $this->getFunctionalityRelatedModulesTreeByWrapKey(
			(string)$this->getFunctionalityWrapKeyByFuncInstance($oFunctionalityInstance)
		);
	}

	/** @throws AfrModuleException|AfrEnvException */
	public function getFunctionalityRelatedModulesTreeByWrapKey(string $sWrapKey): ?array //K
	{
		$this->buildGraphIfNeeded();
		return empty($sWrapKey) ? null : ($this->aFunctionalityRelatedModulesByKeyMap[$sWrapKey] ?? null);
	}

	/** @throws AfrModuleException|AfrEnvException */
	public function getFunctionalityWrapKeyByFuncInstance(object $oFunctionalityInstance): ?string //K
	{
		$this->buildGraphIfNeeded();
		return $this->aWrapFunctionalitiesInstancesSplMap[spl_object_id($oFunctionalityInstance)] ?? null;
	}


	/** @throws AfrModuleException|AfrEnvException */
	public function getEffectiveFunctionalitiesList(): array //K
	{
		$this->buildGraphIfNeeded();
		$aFunctionalityEffectiveConfigs = [];
		foreach ($this->aModuleEffectiveConfigs as $sModFQCN => $aModCfg) {
			if (
				empty($aModCfg[self::aFunctionalities]) ||
				!empty($aModCfg[self::bDisabledModule]) ||
				!empty($this->getModuleReplacementMap($sModFQCN))
			) continue;


			foreach ($aModCfg[self::aFunctionalities] as $sFuncInterfaceFqcn => $aFuncConf) {
				if (!empty($aFuncConf[self::bExcludedFunctionality])) continue;
				$aFunctionalityEffectiveConfigs[$sFuncInterfaceFqcn][$sModFQCN] =
					$this->getFunctionalityEffectiveConfigByWrapKey($aFuncConf[self::sFunctionalityWrapKey]);
			}
		}
		return $aFunctionalityEffectiveConfigs;
	}


	/**
	 * @inheritDoc
	 */
	public function resolveFunctionalityByModuleInstance(string $sFuncInterfaceFqcn, AfrModuleInterface $qModuleInstance): ?object //K
	{
		return $this->resolveFunctionalityByModuleFQCN($sFuncInterfaceFqcn, get_class($qModuleInstance));
	}


	/** @throws AfrModuleException|AfrEnvException */
	protected function getFunctionalityConcreteByModuleAndInterface(string $sModuleFqcn, string $sFuncInterfaceFqcn): ?string //K
	{
		$this->buildGraphIfNeeded();
		if (empty($aModuleConfig = $this->aModuleEffectiveConfigs[$sModuleFqcn] ?? null)) return null;

		// Disabled module not considered for functionality resolution
		if (!empty($aModuleConfig[self::bDisabledModule]) || $this->getModuleReplacementMap($sModuleFqcn)) return null;

		if (empty($aFuncConfig = $aModuleConfig[self::aFunctionalities][$sFuncInterfaceFqcn] ?? null)) return null;

		// Excluded functionality is treated as non-existent in this module
		if (!empty($aFuncConfig[self::bExcludedFunctionality])) return null;

		if (!empty($aFuncConfig[self::sFuncConcreteFQCN]) && is_string($aFuncConfig[self::sFuncConcreteFQCN]))
			return $aFuncConfig[self::sFuncConcreteFQCN];

		return null;
	}



	/**
	 * @inheritDoc
	 */
	public function resolveFunctionalityByModuleFQCN(string $sFuncInterfaceFqcn, string $sModuleFqcn): ?object //K
	{
		//MODULE IS REPLACED by another implementation, so we get the functionality from there,
		$this->buildGraphIfNeeded();
		if ($snReplacement = $this->getModuleReplacementMap($sModuleFqcn))
			return $this->resolveFunctionalityByModuleFQCN($sFuncInterfaceFqcn, $snReplacement);

		return $this->getFunctionalityWrap($sModuleFqcn, $sFuncInterfaceFqcn);

		//	$sFuncConcrete = $this->getFunctionalityConcreteByModuleAndInterface($sModuleFqcn, $sFuncInterfaceFqcn);
		//	return $sFuncConcrete ? $this->getFunctionalityWrap($sModuleFqcn, $sFuncInterfaceFqcn, $sFuncConcrete) : null;
	}


	/**
	 * @param string $sFuncInterfaceFqcn
	 * @return string[]
	 * @throws AfrEnvException
	 * @throws AfrModuleException
	 */
	protected function getFunctionalityConcreteByInterface(string $sFuncInterfaceFqcn): array //K
	{
		$aFunctionalityConcrete = [];
		//loop effective MODULE configs
		foreach ($this->aModuleEffectiveConfigs as $sModuleFqcn => $aModuleConfig) {
			// Disabled module not considered for functionality resolution
			if (!empty($aModuleConfig[self::bDisabledModule])) continue;

			if ($this->getModuleReplacementMap($sModuleFqcn)) continue;

			$aFuncConfig = $aModuleConfig[self::aFunctionalities][$sFuncInterfaceFqcn] ?? null;
			if ($aFuncConfig === null) continue;

			// Excluded functionality is treated as non-existent in this module
			if (!empty($aFuncConfig[self::bExcludedFunctionality])) continue;

			if (!empty($aFuncConfig[self::sFuncConcreteFQCN]) && is_string($aFuncConfig[self::sFuncConcreteFQCN])) {
				$aFunctionalityConcrete[$sModuleFqcn] = $aFuncConfig[self::sFuncConcreteFQCN];
			}

		}
		return $aFunctionalityConcrete;
	}

	/**
	 * @param array $aModuleFunctionalities
	 * @param string $sFuncInterfaceFqcn
	 * @param string|null $sFuncConcreteFqcn
	 * @return void
	 */
	protected function getFunctionalityWrapReverseEngineerInterface(array $aModuleFunctionalities, string &$sFuncInterfaceFqcn, string &$sFuncConcreteFqcn = null): void //K
	{
		if (!empty($aModuleFunctionalities[$sFuncInterfaceFqcn]) )  return;
		elseif (class_exists($sFuncInterfaceFqcn)) {
			// this module does not contain this interface...
			// perhaps a concrete implementation was mistakenly given as interface
			$aMatchedConcretes = [];
			foreach ($aModuleFunctionalities as $sLoopInterface => $aLoopFnCfg) {
				if ($aLoopFnCfg[self::sFuncConcreteFQCN] === $sFuncInterfaceFqcn) $aMatchedConcretes[] = $sLoopInterface;
			}
			if (count($aMatchedConcretes) === 1) {
				$sFuncInterfaceFqcn = array_pop($aMatchedConcretes);
				$sFuncConcreteFqcn = null;
			}
		}
		if (empty($aModuleFunctionalities[$sFuncInterfaceFqcn]) && interface_exists($sFuncInterfaceFqcn)) {
			// still this module does not contain this interface...
			// perhaps the given interface `$sFuncInterfaceFqcn` extended the module fn original interface
			$aMatchedSubclasses = [];
			foreach ($aModuleFunctionalities as $sLoopInterface => $aLoopFnCfg) {
				if (is_subclass_of($sFuncInterfaceFqcn, $sLoopInterface)) $aMatchedSubclasses[] = $sLoopInterface;
			}
			if (count($aMatchedSubclasses) === 1) {
				$sFuncInterfaceFqcn = array_pop($aMatchedSubclasses);
				$sFuncConcreteFqcn = null;
			}
		}
		if (empty($aModuleFunctionalities[$sFuncInterfaceFqcn]) && class_exists($sFuncInterfaceFqcn)) {
			// last try for concrete class extenders, in order to reverse engineer the interface
			$aMatchedConcreteSubclass = [];
			foreach ($aModuleFunctionalities as $sLoopInterface => $aLoopFnCfg) {
				if (is_subclass_of($sFuncInterfaceFqcn, $aLoopFnCfg[self::sFuncConcreteFQCN])) $aMatchedConcreteSubclass[] = $sLoopInterface;
			}
			if (count($aMatchedConcreteSubclass) === 1) {
				$sFuncInterfaceFqcn = array_pop($aMatchedConcreteSubclass);
				$sFuncConcreteFqcn = null;
			}
		}
	}




	public function getWrapKeyModuleParentsFQCNs(string $sFunctionalityWrapKey): ?array //K
	{
		$this->buildGraphIfNeeded();
		return $this->aFunctionalityRelatedModulesByKeyMap[$sFunctionalityWrapKey]??null;
	}




	protected function getFunctionalityWrapKey(
		string $moduleFqcn,
		string $sFuncInterfaceFqcn,
		string $sFuncConcreteFqcn
	): string //K
	{
		// A) Singleton concrete share between all modules
		if (!empty($this->aFunctionalityConcreteFqcnAsSingletonMap[$sFuncConcreteFqcn])) return $sFuncConcreteFqcn;

		// B) Bridge Instance share using common key between modules / functionalities
		$snBridgeKey = $this->aModuleEffectiveConfigs[$moduleFqcn][self::aFunctionalities][$sFuncInterfaceFqcn][self::sBridgeFunctionalityOnCommonInstanceKey] ?? null;
		if ($snBridgeKey) {
			$sBridgeKeyInterface = $sFuncInterfaceFqcn . '+' . $snBridgeKey;
			if (!empty($this->aBridgeFunctionalityOnCommonInstanceKeyMap[$sBridgeKeyInterface]))
				return $sBridgeKeyInterface;
		}
		// C) Multiple interfaces implement the same concrete in current module
		if (!empty($this->aModuleEffectiveConfigs[$moduleFqcn][self::aFunctionalities])) {
			$aMultipleInterfacesImplementTheSameConcreteInCurrentModule = [];
			foreach ($this->aModuleEffectiveConfigs[$moduleFqcn][self::aFunctionalities] as $sLoopInterface => $aLoopFnCfg) {
				if ($aLoopFnCfg[self::sFuncConcreteFQCN] === $sFuncConcreteFqcn) {
					$aMultipleInterfacesImplementTheSameConcreteInCurrentModule[] = $sLoopInterface;
				}
			}
			if (count($aMultipleInterfacesImplementTheSameConcreteInCurrentModule) > 1) {
				return $sFuncConcreteFqcn . '@' . $moduleFqcn . '@' .
					implode(',', $aMultipleInterfacesImplementTheSameConcreteInCurrentModule);
			}
		}
		//D) Standalone Functionality
		return $sFuncConcreteFqcn . '@' . $moduleFqcn . '@' . $sFuncInterfaceFqcn; //simple key
	}

	/**
	 * @param string $moduleFqcn
	 * @param string $sFuncInterfaceFqcn
	 * @param string|null $sFuncConcreteFqcn
	 * @return object|null
	 * @throws AfrContainerException
	 * @throws AfrEnvException
	 * @throws AfrEventException
	 * @throws AfrModuleException
	 * @throws AfrModuleFunctionalityException
	 */
	protected function getFunctionalityWrap(
		string $moduleFqcn,
		string $sFuncInterfaceFqcn,
		string $sFuncConcreteFqcn = null
	): ?object //TODO
	{
		if (empty($moduleFqcn) || empty($sFuncInterfaceFqcn) || empty($this->aModuleEffectiveConfigs[$moduleFqcn][self::aFunctionalities])) return null;
		$aModuleFunctionalities = (array)$this->aModuleEffectiveConfigs[$moduleFqcn][self::aFunctionalities];

		$this->getFunctionalityWrapReverseEngineerInterface($aModuleFunctionalities, $sFuncInterfaceFqcn, $sFuncConcreteFqcn);
		if (empty($aModuleFunctionalities[$sFuncInterfaceFqcn]) )  return null;

		$sFuncConcreteFqcn ??= $this->getFunctionalityConcreteByModuleAndInterface($moduleFqcn, $sFuncInterfaceFqcn);
		if (empty($sFuncConcreteFqcn)) return null;
		if (!is_subclass_of($sFuncConcreteFqcn, $sFuncInterfaceFqcn)) {
			throw new AfrModuleFunctionalityException("Interface $sFuncInterfaceFqcn is not implemented by $sFuncConcreteFqcn in module $moduleFqcn");
		}

		$onModuleInstance = $this->resolveModule($moduleFqcn); //once TODO really resolve?? LAZY?
		$sWrapKey = $this->getFunctionalityWrapKey($moduleFqcn, $sFuncInterfaceFqcn, $sFuncConcreteFqcn);
		if (array_key_exists($sWrapKey, $this->aWrapFunctionalitiesInstances)) {
			return $this->aWrapFunctionalitiesInstances[$sWrapKey]['i'];
		}


		try {
			$oResolvedFunctionality = $this->resolveUsingAppContainer($sFuncConcreteFqcn);
		} catch (\Throwable $e) {
			throw new AfrModuleFunctionalityException(
				"Unable to resolve Functionality using App Container `$sFuncConcreteFqcn`@[$moduleFqcn]\n" .
				$e->getMessage(), $e->getCode(), $e);
		}


		if (!$oResolvedFunctionality instanceof $sFuncInterfaceFqcn) {
			throw new AfrModuleFunctionalityException(
				"Functionality `$sFuncConcreteFqcn`@[$moduleFqcn] is not a object implementing `$sFuncInterfaceFqcn`");
		}

		$splId = spl_object_id($oResolvedFunctionality);
		$this->aWrapFunctionalitiesInstances[$sWrapKey] = [
			'i' => $oResolvedFunctionality,
			's' => $splId, //parents + cfg?
		];
		$this->aWrapFunctionalitiesInstancesSplMap[$splId] = $sWrapKey;

		$aRealConfig = $this->getFunctionalityEffectiveConfigByWrapKey($sWrapKey);
		//TODO SETTINGS!!!!
		//TODO SETTINGS!!!!
		//TODO SETTINGS!!!!
		//TODO SETTINGS!!!!
		//TODO SETTINGS!!!!
		//TODO SETTINGS!!!!
		//TODO SETTINGS!!!!



//		AfrModuleRelations::getInstance()->pushFunctionalityInstance($this->aWrapFunctionalitiesInstances[$sWrapKey]);
		//foreach ($this->getConcreteFunctionalityModuleParentsHelperFunctionalityWrap($sFuncConcreteFqcn) as $sModuleParent) {
			//		$oResolvedFunctionality->attachParentModuleFQCN($sModuleParent);
	//	}
		//$this->aFunctionalityRelatedModulesByKeyMap[$k][$modFQCN][$sFuncInterfaceFqcn] = $aFuncConf[self::sFuncConcreteFQCN]; //pile up by key
		$aRelatedParents = $this->aFunctionalityRelatedModulesByKeyMap[$sWrapKey]??[];
		foreach ($aRelatedParents as $sModuleParent=>$aInterfConcrete) {
			//		$oResolvedFunctionality->attachParentModuleFQCN($sModuleParent);
		}
		if ($onModuleInstance) {
			//		$oResolvedFunctionality->attachParentModuleInstance($onModuleInstance);
		}


		return $this->aWrapFunctionalitiesInstances[$sWrapKey]['i'] ?? null;
	}






	/**
	 * Resolve functionality by interface FQCN.
	 *
	 * - Can return a single instance (if $single is true and one implementation is found).
	 * - Can return an array of instances (if multiple implementations exist and $single is false).
	 * - Falls back to the DI container if no module can provide it.
	 *
	 * @param string $sFuncInterfaceFqcn
	 * @param string|null $preferredFqcn
	 * @return object[]
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 * @throws AfrModuleException
	 * @throws AfrModuleFunctionalityException|AfrEnvException
	 */
	public function resolveFunctionality(
		string  $sFuncInterfaceFqcn,
		?string $preferredFqcn = null
	): array //60% k
	{
		$this->buildGraphIfNeeded();
		$aModCfg = $this->getFunctionalityConcreteByInterface($sFuncInterfaceFqcn);

		//TODO: parent module when initing
		if (count($aModCfg) > 1) {
			// TODO: magie cu debug backtrace, ca daca vin din instanta de modul, sa iau componenta specifica acelui modul
		}
		if ($preferredFqcn) {
			//reguli single | array???
		}
		if (!empty($aExcludedModulesFqcnsAsResolvers)) {
			//reguli single | array???
		}


		$aReturnInstances = [];
		foreach ($aModCfg as $moduleFqcn => $sFuncConcreteFqcn) {
			$onFunctionalityInstance = $this->getFunctionalityWrap($moduleFqcn, $sFuncInterfaceFqcn, $sFuncConcreteFqcn);
			if (!empty($onFunctionalityInstance) && is_object($onFunctionalityInstance)) {
				$sWrapKey = $this->getFunctionalityWrapKeyByFuncInstance($onFunctionalityInstance) ??
					$this->getFunctionalityWrapKey($moduleFqcn, $sFuncInterfaceFqcn, $sFuncConcreteFqcn);
				$aReturnInstances[$sWrapKey] = $onFunctionalityInstance;
			}
		}

		if (empty($aReturnInstances) && class_exists($sFuncInterfaceFqcn)) {
			// No module implementations: try container (could return single or array depending on user binding)
			$aReturnInstances = [$sFuncInterfaceFqcn => $this->resolveUsingAppContainer($sFuncInterfaceFqcn)]; //todo force array | preffered
		}
		return $aReturnInstances;
	}








//////////////////////////




	protected function getFunctionalityParents(
		string $sModuleFqcn,
		string $sFunctionalityInterface,
		string $sFuncConcreteFqcn,
		bool   $bFilterOutDisabled = true
	): ?array //DEPRECATED
	{
		//TODO: TEST
		$this->buildGraphIfNeeded();
		$aRunTimeKey = $this->makeRunTimeFunctionCacheKey(__FUNCTION__, func_get_args());
		if (array_key_exists($aRunTimeKey, $this->aRunTimeFunctionCache)) {
			return $this->aRunTimeFunctionCache[$aRunTimeKey];
		}

		$snBridgeKey = $this->aModuleEffectiveConfigs[$sModuleFqcn][self::aFunctionalities][$sFunctionalityInterface][self::sBridgeFunctionalityOnCommonInstanceKey] ?? null;
		if (!empty($this->aFunctionalityConcreteFqcnAsSingletonMap[$sFuncConcreteFqcn])) {
			$aParents = array_keys($this->aFunctionalityConcreteFqcnAsSingletonMap[$sFuncConcreteFqcn]);
		} elseif (!empty($snBridgeKey)) {
			$anBridgeParents = $this->aBridgeFunctionalityOnCommonInstanceKeyMap[$sFunctionalityInterface . '+' . $snBridgeKey] ?? null;
			$aParents = is_array($anBridgeParents) ? array_keys($anBridgeParents) : null;
		} else {
			$aParents = !empty($this->aModuleEffectiveConfigs[$sModuleFqcn]) ? [$sModuleFqcn] : null;
		}

		if ($aParents !== null && $bFilterOutDisabled) {
			$aValidatedParents = [];
			foreach ($aParents as $sModuleFqcnLoop) {
				$aModuleConfig = $this->aModuleEffectiveConfigs[$sModuleFqcnLoop] ?? null;
				$sLoopConcrete = $aModuleConfig[self::aFunctionalities][$sFunctionalityInterface][self::sFuncConcreteFQCN] ?? null;
				if (
					!empty($aModuleConfig) &&
					empty($aModuleConfig[self::bDisabledModule]) &&
					empty($this->getModuleReplacementMap($sModuleFqcnLoop)) &&
					empty($aModuleConfig[self::aFunctionalities][$sFunctionalityInterface][self::bExcludedFunctionality]) && (
						$sLoopConcrete === $sFuncConcreteFqcn ||
						is_subclass_of($sLoopConcrete, $sFunctionalityInterface)
					)) {
					$aValidatedParents[$sModuleFqcnLoop] = $this->isResolvableModule($sModuleFqcnLoop, true);
				}
			}
			return $this->aRunTimeFunctionCache[$aRunTimeKey] = $aValidatedParents;
		}
		return $this->aRunTimeFunctionCache[$aRunTimeKey] = $aParents;
	}


	/**
	 * @throws AfrModuleException
	 * @throws AfrEnvException
	 */
	protected function getFunctionalityEffectiveConfigsByModuleInterfaceOld(
		string $sModuleFqcn,
		string $sFunctionalityInterface,
		string $sFuncConcreteFqcn = null
	): ?array//DEPRECATED
	{
		//TODO: REFACTOR
		//TODO: REFACTOR
		//TODO: REFACTOR
		//TODO: REFACTOR
		//TODO: REFACTOR
		//TODO: REFACTOR
		//TODO: REFACTOR
		//TODO: REFACTOR

		$this->buildGraphIfNeeded();
		$sFuncConcreteFqcn ??= (string)$this->getFunctionalityConcreteByModuleAndInterface($sModuleFqcn, $sFunctionalityInterface);
		$aRunTimeKey = $this->makeRunTimeFunctionCacheKey(__FUNCTION__, [$sModuleFqcn, $sFunctionalityInterface, $sFuncConcreteFqcn]);
		if (array_key_exists($aRunTimeKey, $this->aRunTimeFunctionCache)) {
			return $this->aRunTimeFunctionCache[$aRunTimeKey];
		}

		$aFnConfig = null;
		//$this->getFunctionalityWrapKey($moduleFqcn, $sFuncInterfaceFqcn, $sFuncConcreteFqcn);
		if (!empty($this->aFunctionalityConcreteFqcnAsSingletonMap[$sFuncConcreteFqcn])) {
			//all effective configs merged
			foreach ($this->aModuleEffectiveConfigs as $sModuleFqcnLoop => $aModuleConfig) {
				if (
					$aModuleConfig[self::aFunctionalities][$sFunctionalityInterface][self::sFuncConcreteFQCN] ===
					$sFuncConcreteFqcn &&
					empty($aModuleConfig[self::bDisabledModule]) &&
					empty($this->getModuleReplacementMap($sModuleFqcnLoop)) &&
					empty($aModuleConfig[self::aFunctionalities][$sFunctionalityInterface][self::bExcludedFunctionality])
				) {
					$aFnConfig = self::mergeConfig(
						$aFnConfig ?? [],
						$aModuleConfig[self::aFunctionalities][$sFunctionalityInterface],
						false
					);
				}
			}
			if ($aFnConfig !== null) {
				$aFnConfig[self::sFunctionalityWrapKey] = $this->getFunctionalityWrapKey(
					$sModuleFqcn, $sFunctionalityInterface, $sFuncConcreteFqcn
				);
			}
			return $this->aRunTimeFunctionCache[$aRunTimeKey] = $aFnConfig;
		}

		$snBridgeKey = $this->aModuleEffectiveConfigs[$sModuleFqcn][self::aFunctionalities][$sFunctionalityInterface][self::sBridgeFunctionalityOnCommonInstanceKey] ?? null;
		$aBridgeModuleConfigs = !empty($snBridgeKey) ? array_keys(
			$this->aBridgeFunctionalityOnCommonInstanceKeyMap[$sFunctionalityInterface . '+' . $snBridgeKey] ?? []
		) : [];

		if (!empty($aBridgeModuleConfigs)) {
			foreach ($aBridgeModuleConfigs as $sLoopMod) {
				if (empty($aModuleConfig = $this->aModuleEffectiveConfigs[$sLoopMod] ?? null)) continue;
				if (
					empty($aModuleConfig[self::bDisabledModule]) &&
					empty($this->getModuleReplacementMap($sLoopMod)) &&
					empty($aModuleConfig[self::aFunctionalities][$sFunctionalityInterface][self::bExcludedFunctionality]) &&
					$aModuleConfig[self::aFunctionalities][$sFunctionalityInterface][self::sBridgeFunctionalityOnCommonInstanceKey] === $snBridgeKey
				) {
					$aFnConfig = self::mergeConfig(
						$aFnConfig ?? [],
						$aModuleConfig[self::aFunctionalities][$sFunctionalityInterface],
						false
					);
				}
			}
		} else {
			$aFnConfig = $this->aModuleEffectiveConfigs[$sModuleFqcn][self::aFunctionalities][$sFunctionalityInterface] ?? null;
		}

		if ($aFnConfig !== null && empty($aFnConfig[self::sFunctionalityWrapKey])) {
			$aFnConfig[self::sFunctionalityWrapKey] = $this->getFunctionalityWrapKey(
				$sModuleFqcn, $sFunctionalityInterface, $sFuncConcreteFqcn
			);
		}

		return $this->aRunTimeFunctionCache[$aRunTimeKey] = $aFnConfig;

	}




	protected function getConcreteFunctionalityModuleParentsHelperFunctionalityWrap(
		string $sFuncConcrete,
		bool   $bIncludeReplacedModules = false,
		bool   $bIncludeDisabledModules = false,
		bool   $bIncludeExcludedFunctionalities = false
	): array //DEPRECATED
	{



		//TODO: parinti:
		//singletoni: $aParinti = array_keys($this->aFunctionalityConcreteFqcnAsSingletonMap[$sFuncConcrete]);
		//simplu: notSIngleton si not $aBridgeFunctionalityOnCommonInstanceKeyMap


		$this->buildGraphIfNeeded();
		$aParents = [];
		//loop effective MODULE configs
		foreach ($this->aModuleEffectiveConfigs as $sModuleFqcn => $aModuleConfig) {
			// Disabled module not considered for functionality resolution
			if (!$bIncludeDisabledModules && !empty($aModuleConfig[self::bDisabledModule])) continue;

			if (!$bIncludeReplacedModules && $this->getModuleReplacementMap($sModuleFqcn)) continue;

			if (empty($aModuleConfig[self::aFunctionalities]) || !is_array(empty($aModuleConfig[self::aFunctionalities]))) continue;

			foreach ($aModuleConfig[self::aFunctionalities] as $aFuncConfig) {
				if (!$bIncludeExcludedFunctionalities && !empty($aFuncConfig[self::bExcludedFunctionality])) continue;
				if (!empty($aFuncConfig[self::sFuncConcreteFQCN]) && $sFuncConcrete === $aFuncConfig[self::sFuncConcreteFQCN]) {
					$aParents[] = $sModuleFqcn;
					break;
				}
			}
		}
		return $aParents;
	}








}
