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

		foreach ($aConfig[self::aFunctionalities] as $sInterface => &$aFnCfg) {
			if (isset($aFnCfg[self::sFunctionalityWrapKey]))
				unset($aFnCfg[self::sFunctionalityWrapKey]); //do not preserve any external wrap keys
			if (isset($aFnCfg[self::sFuncConcreteFQCN]) && !is_string($aFnCfg[self::sFuncConcreteFQCN]))
				$aFnCfg[self::sFuncConcreteFQCN] = (string)$aFnCfg[self::sFuncConcreteFQCN];
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
		$r = $this->aModuleEffectiveConfigs[$sModuleFqcn][self::iResolvableModule] ?? self::RESOLVE_NONE;
		return $r === self::RESOLVE_DIRECT || ($bCountReplacersAsTrue && $r === self::RESOLVE_REPLACED);
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

	/** @throws AfrModuleException|AfrEnvException */
	public function getModuleFunctionalityResolvedList(string &$sModuleFqcn, bool $bCountReplacersAsTrue = true): ?array //K
	{
		if (!$this->isResolvableModule($sModuleFqcn, $bCountReplacersAsTrue)) return null;
//		if(!$this->isResolvedModule($sModuleFqcn,$bCountReplacersAsTrue)) return null;
		if ($bCountReplacersAsTrue && $sModuleFqcnReplace = $this->getModuleReplacementMap($sModuleFqcn)) {
			$sModuleFqcn = $sModuleFqcnReplace;
		}
		$aList = [];
		foreach ($this->aModuleEffectiveConfigs[$sModuleFqcn][self::aFunctionalities] as $sFnInterface => $aFnCfg) {
			if (!($sWrapKey = $aFnCfg[self::sFunctionalityWrapKey] ?? null)) {
				$aList[$sFnInterface] = 0;
				continue;
			}
			$aList[$sFnInterface] = array_key_exists($sWrapKey, $this->aWrapFunctionalitiesInstances) ?
				$this->aWrapFunctionalitiesInstances[$sWrapKey]['i'] : false;
		}
		return $aList;

	}


	/** @throws AfrModuleException|AfrEnvException */
	public function getModuleReplacementMap(string $sModuleFqcn, bool $bBuildGraphIfNeeded = true): ?string //K
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
	public function getFunctionalityEffectiveConfigByModuleInterface(string $sModuleFqcn, string $sFunctionalityInterface): ?array //K
	{
		if (empty($snKey = $this->getFunctionalityWrapKey($sModuleFqcn, $sFunctionalityInterface))) return null;
		return $this->getFunctionalityEffectiveConfigByWrapKey($snKey);
	}


	/** @throws AfrModuleException|AfrEnvException */
	public function getFunctionalityEffectiveConfig(object $oFunctionalityInstance): ?array//K
	{
		if (empty($snKey = $this->getFunctionalityWrapKeyByFuncInstance($oFunctionalityInstance))) return null;
		return $this->getFunctionalityEffectiveConfigByWrapKey($snKey);
	}

	/** @throws AfrModuleException|AfrEnvException */
	public function getFunctionalityEffectiveConfigByWrapKey(string $sWrapKey): ?array //K
	{
		$this->buildGraphIfNeeded();
		$aRunTimeKey = $this->makeRunTimeFunctionCacheKey(__FUNCTION__, [$sWrapKey]);
		if (array_key_exists($aRunTimeKey, $this->aRunTimeFunctionCache)) return $this->aRunTimeFunctionCache[$aRunTimeKey];

		$aFnConfig = null;
		foreach ($this->aModuleEffectiveConfigs as $sModFqcnLoop => $aModCfg) {
			//TODO: CHEKC daca este inlocuit, atunci nu dau merge???
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
	public function getFunctionalitySettingsByWrapKey(string $sWrapKey): ?array //K
	{
		$anConfig = $this->getFunctionalityEffectiveConfigByWrapKey($sWrapKey);
		$k = self::anFunctionalitySettings;
		return isset($anConfig[$k]) && is_array($anConfig[$k]) ? $anConfig[$k] : null;
	}

	/** @throws AfrModuleException|AfrEnvException */
	public function getFunctionalityApplySettingsClosure(string $sWrapKey): ?Closure //K
	{
		$anConfig = $this->getFunctionalityEffectiveConfigByWrapKey($sWrapKey);
		$k = self::onFunctionalityApplySettingsClosure;
		return isset($anConfig[$k]) && ($anConfig[$k] instanceof Closure) ? $anConfig[$k] : null;
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
	public function getFunctionalityList(): array //K
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
	protected function getFunctionalityConcreteByModuleAndInterface(string $sModuleFqcn, string $sFuncInterfaceFqcn, bool $bPlausibilityChecks = true): ?string //K
	{
		if ($bPlausibilityChecks) {
			$this->buildGraphIfNeeded();
			if (empty($aModuleConfig = $this->aModuleEffectiveConfigs[$sModuleFqcn] ?? null)) return null;

			// Disabled module not considered for functionality resolution
			if (!empty($aModuleConfig[self::bDisabledModule]) || $this->getModuleReplacementMap($sModuleFqcn)) return null;

			if (empty($aFuncConfig = $aModuleConfig[self::aFunctionalities][$sFuncInterfaceFqcn] ?? null)) return null;

			// Excluded functionality is treated as non-existent in this module
			if (!empty($aFuncConfig[self::bExcludedFunctionality])) return null;
		}

		if (!empty($aFuncConfig[self::sFuncConcreteFQCN]) && is_string($aFuncConfig[self::sFuncConcreteFQCN]))
			return $aFuncConfig[self::sFuncConcreteFQCN];

		return null;
	}


	/**
	 * @inheritDoc
	 */
	public function resolveFunctionalityByModuleFQCN(string $sFuncInterfaceFqcn, string $sModuleFqcn, bool $bAutoResolveModule = true): ?object //K
	{
		//MODULE IS REPLACED by another implementation, so we get the functionality from there,
		if ($snReplacement = $this->getModuleReplacementMap($sModuleFqcn))
			return $this->resolveFunctionalityByModuleFQCN($sFuncInterfaceFqcn, $snReplacement, $bAutoResolveModule);

		return $this->getFunctionalityWrap($sModuleFqcn, $sFuncInterfaceFqcn, $bAutoResolveModule);

		//	$sFuncConcrete = $this->getFunctionalityConcreteByModuleAndInterface($sModuleFqcn, $sFuncInterfaceFqcn);
		//	return $sFuncConcrete ? $this->getFunctionalityWrap($sModuleFqcn, $sFuncInterfaceFqcn, $sFuncConcrete) : null;
	}


	/**
	 * @param string $sFuncInterfaceFqcn
	 * @param string|null $sWhat
	 * @return string[]
	 * @throws AfrEnvException
	 * @throws AfrModuleException
	 */
	protected function groupModuleFunctionalityPropByInterface(string $sFuncInterfaceFqcn, string $sWhat): array //K
	{
		$sWhat = $sWhat ?: self::sFuncConcreteFQCN;
		$aFunctionalityWhat = [];
		//loop effective MODULE configs
		foreach ($this->aModuleEffectiveConfigs as $sModuleFqcn => $aModuleConfig) {
			// Disabled module not considered for functionality resolution
			if (!empty($aModuleConfig[self::bDisabledModule])) continue;

			if ($this->getModuleReplacementMap($sModuleFqcn)) continue;

			$aFuncConfig = $aModuleConfig[self::aFunctionalities][$sFuncInterfaceFqcn] ?? null;
			if ($aFuncConfig === null) continue;

			// Excluded functionality is treated as non-existent in this module
			if (!empty($aFuncConfig[self::bExcludedFunctionality])) continue;

			if (isset($aFuncConfig[$sWhat])) {
				$aFunctionalityWhat[$sModuleFqcn] = $aFuncConfig[$sWhat];
			}

		}
		return $aFunctionalityWhat;
	}


	/**
	 * @param array $aModuleFunctionalities
	 * @param string $sFuncInterfaceFqcn
	 * @return void
	 */
	protected function getFunctionalityWrapReverseEngineerInterface(array $aModuleFunctionalities, string &$sFuncInterfaceFqcn): void //K
	{
		if (!empty($aModuleFunctionalities[$sFuncInterfaceFqcn][self::sFuncConcreteFQCN])) return;
		elseif (class_exists($sFuncInterfaceFqcn)) {
			// this module does not contain this interface...
			// perhaps a concrete implementation was mistakenly given as interface
			$aMatchedConcretes = [];
			foreach ($aModuleFunctionalities as $sLoopInterface => $aLoopFnCfg) {
				if ($aLoopFnCfg[self::sFuncConcreteFQCN] === $sFuncInterfaceFqcn) $aMatchedConcretes[] = $sLoopInterface;
			}
			if (count($aMatchedConcretes) === 1) {
				$sFuncInterfaceFqcn = array_pop($aMatchedConcretes);
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
			}
		}
	}


	/** @throws AfrModuleException|AfrEnvException */
	public function getFunctionalityParentModulesFQCNsByWrapKey(string $sFunctionalityWrapKey): ?array //K
	{
		$aParentTree = $this->getFunctionalityRelatedModulesTreeByWrapKey($sFunctionalityWrapKey);
		return is_array($aParentTree) ? array_keys($aParentTree) : null;
	}


	/** @throws AfrModuleException|AfrEnvException */
	public function getFunctionalityParentModulesFQCNs(object $oFunctionalityInstance): ?array //K
	{
		$aParentTree = $this->getFunctionalityRelatedModulesTreeByWrapKey(
			(string)$this->getFunctionalityWrapKeyByFuncInstance($oFunctionalityInstance)
		);
		return is_array($aParentTree) ? array_keys($aParentTree) : null;
	}


	/** @throws AfrModuleException|AfrEnvException */
	public function getFunctionalityWrapKey(
		string $sModuleFqcn,
		string &$sFuncInterfaceFqcn
	): ?string
	{
		if (
			empty($sModuleFqcn) ||
			!empty($this->aModuleEffectiveConfigs[$sModuleFqcn][self::bDisabledModule]) ||
			empty($sFuncInterfaceFqcn) ||
			empty($aModuleFunctionalities = (array)$this->aModuleEffectiveConfigs[$sModuleFqcn][self::aFunctionalities] ?? [])
		) return null;

		//TODO: mod replacer in conjuction with WrapKey CODE sFunctionalityWrapKey
		if ($snReplacerModule = $this->getModuleReplacementMap($sModuleFqcn))
			return $this->getFunctionalityWrapKey($snReplacerModule, $sFuncInterfaceFqcn);

		$this->getFunctionalityWrapReverseEngineerInterface($aModuleFunctionalities, $sFuncInterfaceFqcn);
		if (empty($aModuleFunctionalities[$sFuncInterfaceFqcn])) return null;
		$sFuncConcreteFqcn = $this->getFunctionalityConcreteByModuleAndInterface($sModuleFqcn, $sFuncInterfaceFqcn);
		if (empty($sFuncConcreteFqcn)) return null;

		return $this->makeFunctionalityWrapKey($sModuleFqcn, $sFuncInterfaceFqcn, $sFuncConcreteFqcn);
	}

	protected function makeFunctionalityWrapKey(
		string $sModuleFqcn,
		string $sFuncInterfaceFqcn,
		string $sFuncConcreteFqcn
	): string //K
	{
		// A) Singleton concrete share between all modules
		if (!empty($this->aFunctionalityConcreteFqcnAsSingletonMap[$sFuncConcreteFqcn])) return $sFuncConcreteFqcn;

		// B) Bridge Instance share using common key between modules / functionalities
		$snBridgeKey = $this->aModuleEffectiveConfigs[$sModuleFqcn][self::aFunctionalities][$sFuncInterfaceFqcn][self::sBridgeFunctionalityOnCommonInstanceKey] ?? null;
		if ($snBridgeKey) {
			$sBridgeKeyInterface = $sFuncInterfaceFqcn . '+' . $snBridgeKey;
			if (!empty($this->aBridgeFunctionalityOnCommonInstanceKeyMap[$sBridgeKeyInterface]))
				return $sBridgeKeyInterface;
		}
		// C) Multiple interfaces implement the same concrete in current module
		if (!empty($this->aModuleEffectiveConfigs[$sModuleFqcn][self::aFunctionalities])) {
			$aMultipleInterfacesImplementTheSameConcreteInCurrentModule = [];
			foreach ($this->aModuleEffectiveConfigs[$sModuleFqcn][self::aFunctionalities] as $sLoopInterface => $aLoopFnCfg) {
				if ($aLoopFnCfg[self::sFuncConcreteFQCN] === $sFuncConcreteFqcn) {
					$aMultipleInterfacesImplementTheSameConcreteInCurrentModule[] = $sLoopInterface;
				}
			}
			if (count($aMultipleInterfacesImplementTheSameConcreteInCurrentModule) > 1) {
				return $sFuncConcreteFqcn . '@' . $sModuleFqcn . '@' .
					implode(',', $aMultipleInterfacesImplementTheSameConcreteInCurrentModule);
			}
		}
		//D) Standalone Functionality
		return $sFuncConcreteFqcn . '@' . $sModuleFqcn . '@' . $sFuncInterfaceFqcn; //simple key
	}

	/**
	 * @param string $moduleFqcn
	 * @param string $sFuncInterfaceFqcn
	 * @param bool $bAutoResolveModule
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
		bool   $bAutoResolveModule = true
	): ?object //K.95%
	{
		//TODO: mod replacer in conjuction with WrapKey CODE sFunctionalityWrapKey
		if (!$sWrapKey = $this->getFunctionalityWrapKey($moduleFqcn, $sFuncInterfaceFqcn)) return null;

		//TODO CHECK DOUBLE MESURE A.1 is_subclass_of
		$sFuncConcreteFqcn = $this->getFunctionalityConcreteByModuleAndInterface($moduleFqcn, $sFuncInterfaceFqcn, false);
		if ($sFuncConcreteFqcn !== $sFuncInterfaceFqcn && !is_subclass_of($sFuncConcreteFqcn, $sFuncInterfaceFqcn)) {
			throw new AfrModuleFunctionalityException("Interface $sFuncInterfaceFqcn is not implemented by $sFuncConcreteFqcn in module $moduleFqcn");
		}

		if ($bAutoResolveModule) $this->resolveModule($moduleFqcn); //once TODO really resolve?? LAZY?

		if (array_key_exists($sWrapKey, $this->aWrapFunctionalitiesInstances))
			return $this->aWrapFunctionalitiesInstances[$sWrapKey]['i'];


		try {
			$oResolvedFunctionality = $this->resolveUsingAppContainer($sFuncConcreteFqcn);
		} catch (\Throwable $e) {
			throw new AfrModuleFunctionalityException(
				"Unable to resolve Functionality using App Container `$sFuncConcreteFqcn`@($moduleFqcn [$sFuncInterfaceFqcn] )\n" .
				$e->getMessage(), $e->getCode(), $e);
		}

		//TODO CHECK DOUBLE MESURE A.2 instanceof
		if ($sFuncConcreteFqcn !== $sFuncInterfaceFqcn && !$oResolvedFunctionality instanceof $sFuncInterfaceFqcn) {
			throw new AfrModuleFunctionalityException(
				"Functionality `$sFuncConcreteFqcn`@[$moduleFqcn] is not a object implementing `$sFuncInterfaceFqcn`");
		}

		$splId = spl_object_id($oResolvedFunctionality);
		$this->aWrapFunctionalitiesInstances[$sWrapKey] = [
			'i' => $oResolvedFunctionality,
			's' => $splId, //parents + cfg?
		];
		$this->aWrapFunctionalitiesInstancesSplMap[$splId] = $sWrapKey;

		//		$aRelatedParents = $this->aFunctionalityRelatedModulesByKeyMap[$sWrapKey]??[];

		//APPLY SETTINGS ONLY IF 
		if ($oResolvedFunctionality instanceof AfrFunctionalityInterface) {
			$oResolvedFunctionality->attachFunctionalityEffectiveRunTimeParameters(
				$this->getFunctionalityEffectiveConfigByWrapKey($sWrapKey)
			);
		}

		if($onSettingsClosure = $this->getFunctionalityApplySettingsClosure($sWrapKey)){
			$anSettings = $this->getFunctionalitySettingsByWrapKey($sWrapKey);
			is_array($anSettings) ? $onSettingsClosure($anSettings) : $onSettingsClosure();
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
	 * @param bool $bAutoResolveRelatedModules
	 * @param array|null $aFunctionalityGroupForResolving
	 * @param bool $bFallbackOnEmptyToContainer
	 * @return object[]
	 * @throws AfrContainerException
	 * @throws AfrEnvException
	 * @throws AfrEventException
	 * @throws AfrModuleException
	 * @throws AfrModuleFunctionalityException
	 */
	public function resolveFunctionalityGroup(
		string $sFuncInterfaceFqcn,
		bool   $bAutoResolveRelatedModules = false,
		?array $aFunctionalityGroupForResolving = null,
		bool   $bFallbackOnEmptyToContainer = false
	): array //99% k
	{
		$this->buildGraphIfNeeded();
		$aFunctionalityGroupForResolving ??= $this->getFunctionalityGroupForResolving($sFuncInterfaceFqcn);

		$aReturnInstances = [];
		foreach ($aFunctionalityGroupForResolving as $sWrapKeyGroup => $aModInt) { //[$sModuleFqcn, $sFuncInterfaceFqcn]
			$onFunctionalityInstance = $this->getFunctionalityWrap($aModInt[0], $aModInt[1], $bAutoResolveRelatedModules);
			if (!empty($onFunctionalityInstance) && is_object($onFunctionalityInstance)) {
				$sWrapKey = $this->getFunctionalityWrapKeyByFuncInstance($onFunctionalityInstance);
				$aReturnInstances[$sWrapKey] = $onFunctionalityInstance;
			}
		}

		//fallback to container
		if ($bFallbackOnEmptyToContainer && empty($aReturnInstances) && class_exists($sFuncInterfaceFqcn)) {
			// No module implementations: try container (could return single or array depending on user binding)
			$aReturnInstances = [$sFuncInterfaceFqcn => $this->resolveUsingAppContainer($sFuncInterfaceFqcn)]; //todo force array | preffered
		}
		return $aReturnInstances;
	}

	/**
	 * @param string $sFuncInterfaceFqcn
	 * @param array $aExcludeModulesFQCNs
	 * @param array $aAllowedModulesFQCNs
	 * @param array $aExcludeFunctionalitiesFQCNs
	 * @param array $aAllowedFunctionalitiesFQCNs
	 * @return array
	 * @throws AfrEnvException
	 * @throws AfrModuleException
	 */
	public function getFunctionalityGroupForResolving(
		string $sFuncInterfaceFqcn,
		array  $aExcludeModulesFQCNs = [],
		array  $aAllowedModulesFQCNs = [],
		array  $aExcludeFunctionalitiesFQCNs = [],
		array  $aAllowedFunctionalitiesFQCNs = []
	): array
	{
		if (!empty($aAllowedModulesFQCNs)) $aExcludeModulesFQCNs = [];
		if (!empty($aAllowedFunctionalitiesFQCNs)) $aExcludeFunctionalitiesFQCNs = [];

		//get all available wraps, for unique instance filtration
		$aFunctionalityGroupByWrapKey = [];
		foreach ($this->aModuleEffectiveConfigs as $sModuleFqcn => $aModuleConfig) {
			// Disabled module not considered for functionality resolution
			if (!empty($aModuleConfig[self::bDisabledModule])) continue;
			if ($this->getModuleReplacementMap($sModuleFqcn)) continue;

			if ($aAllowedModulesFQCNs && !in_array($sModuleFqcn, $aAllowedModulesFQCNs)) continue;
			if ($aExcludeModulesFQCNs && in_array($sModuleFqcn, $aExcludeModulesFQCNs)) continue;

			if (($aFuncConfig = $aModuleConfig[self::aFunctionalities][$sFuncInterfaceFqcn] ?? null) === null) continue;
			// Excluded functionality is treated as non-existent in this module
			if (!empty($aFuncConfig[self::bExcludedFunctionality])) continue;

			if ($aAllowedFunctionalitiesFQCNs && !in_array($aFuncConfig[self::sFuncConcreteFQCN], $aAllowedFunctionalitiesFQCNs)) continue;
			if ($aExcludeFunctionalitiesFQCNs && in_array($aFuncConfig[self::sFuncConcreteFQCN], $aExcludeFunctionalitiesFQCNs)) continue;

			if (!empty($aFuncConfig[self::sFunctionalityWrapKey])) {
				//any module/interface is good enough for getFunctionalityWrap()
				$aFunctionalityGroupByWrapKey[$aFuncConfig[self::sFunctionalityWrapKey]] = [
					$sModuleFqcn,
					$sFuncInterfaceFqcn,
					$aFuncConfig[self::sFuncConcreteFQCN]
				];
			}
		}

		return $aFunctionalityGroupByWrapKey;
	}


}
