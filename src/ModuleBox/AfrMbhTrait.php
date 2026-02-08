<?php

namespace Autoframe\Core\ModuleBox;

use Autoframe\Core\Afr\Afr;
use Autoframe\Core\CliTools\AfrSysTempDir;
use Autoframe\Core\Container\AfrContainerFacade;
use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\Env\Exception\AfrEnvException;
use Autoframe\Core\Error\AfrError;
use Autoframe\Core\Event\Exception\AfrEventException;
use Autoframe\Core\FileSystem\CacheToPhpFile\AfrCachePhpFile;
use Autoframe\Core\ModuleBox\Exception\AfrModuleException;
use Autoframe\Core\ModuleBox\Exception\AfrModuleFunctionalityException;
use Autoframe\Core\Tenant\AfrTenant;
use Closure;

trait AfrMbhTrait
{

	public static bool $bDebug = false;
	//TODO:
	//TODO:
	//TODO:
	//TODO:
	//TODO: CACHE INSTANCE VIA OPIS!
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


	protected array $aModuleReplacementMap = []; //Maps base module FQCN => replacer module FQCN. [$sModuleReplaceBase][] = $ModFQCN;
	protected array $aModuleExtensionMap = []; //Maps base module FQCN => list of extender module FQCNs. [$sModuleExtensionBase][] = $ModFQCN;
	protected array $aModuleIsExtenderOfOtherModule = []; //reverse map [$ModFQCN] = $sModuleExtensionBase;
	protected array $aModuleIsReplacerOfOtherModule = [];//reverse map [$ModFQCN] = $sModuleReplaceBase;


	//resolved functionalities
	protected array $aWrapFunctionalitiesInstances = []; // map [$sWrapKey] = ['i' => $oResolvedFunctionality,'s' => $splId, ]
	protected array $aWrapFunctionalitiesInstancesSplMap = []; //map [$splId] = $sWrapKey;

	protected array $aFunctionalityConcreteFqcnAsSingletonMap = []; //map [$aFuncConf[self::sFuncConcreteFQCN]][$modFQCN][$sFuncInterfaceFqcn] = true;
	protected array $aBridgeFunctionalityOnCommonInstanceKeyMap = [];//map [$sFuncInterfaceFqcn . '+' . $sBridgeKey][$modFQCN] = true;
	protected array $aFunctionalityRelatedModulesByKeyMap = [];//map [$wrapKey][$modFQCN][$sFuncInterfaceFqcn] = $aFuncConf[self::sFuncConcreteFQCN];


	/** @throws AfrModuleException */
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

	/** @param string|AfrModuleInterface|Closure $module
	 * @throws AfrModuleException
	 */
	protected function pushModuleConfig($module, string $sFQCN, array $aConfig = []): void //K
	{
		$this->aPushedModules[$sFQCN] = $module; //push instance or fqcn for later resolving
		if (!class_exists($sFQCN) || !is_subclass_of($sFQCN, AfrModuleInterface::class)) {
			throw new AfrModuleException($sFQCN . ' is not a subclass of ' . AfrModuleInterface::class);
		}
		/** @var AfrModuleInterface $sFQCN */
		$aDefaultModuleConfig = $sFQCN::getDefaultModuleConfig();
		$aDefaultModuleConfig[self::aFunctionalities] ??= $sFQCN::getDefaultFunctionalitiesConfig();

		// Merge module-provided defaults (from code) with manifest/app config
		$aConfig = self::mergeConfig($aDefaultModuleConfig, $aConfig, true);

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
			'getModuleEffectiveConfigsBase' => $this->getModuleEffectiveConfigs($sModuleFqcn, true),
			'getModuleEffectiveConfigsReplacer' => $this->getModuleEffectiveConfigs($sModuleFqcn, false),
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

	public function getModuleEffectiveConfigs(string $sModuleFqcn, bool $bGetBaseConfigNotReplacer): ?array //K
	{
		$this->buildGraphIfNeeded();
		if (!$bGetBaseConfigNotReplacer && $sModuleFqcnReplace = $this->getModuleReplacementMap($sModuleFqcn))
			return $this->getModuleEffectiveConfigs($sModuleFqcnReplace, false);
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
			$mReturn = $mReturn($this->aModuleEffectiveConfigs[$sModuleFqcn] ?? []);
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
	 * @param string $sFuncInterfaceFqcn
	 * @param AfrModuleInterface $qModuleInstance
	 * @return object|null
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 * @throws AfrModuleException
	 * @throws AfrModuleFunctionalityException
	 */
	public function resolveFunctionalityByModuleInstance(string $sFuncInterfaceFqcn, AfrModuleInterface $qModuleInstance): ?object //K
	{
		return $this->resolveFunctionalityByModuleFQCN($sFuncInterfaceFqcn, get_class($qModuleInstance));
	}


	/** @throws AfrModuleException|AfrEnvException */
	protected function getFunctionalityConcreteByModuleAndInterface(string $sModuleFqcn, string $sFuncInterfaceFqcn, bool $bPlausibilityChecks = true): ?string //K
	{
		//	echo "\n\n getFunctionalityConcreteByModuleAndInterface($sModuleFqcn,$sFuncInterfaceFqcn,$bPlausibilityChecks);\n";

		if ($bPlausibilityChecks) {
			$this->buildGraphIfNeeded();
			if (empty($aModuleConfig = $this->aModuleEffectiveConfigs[$sModuleFqcn] ?? null)) return null;

			// Disabled module not considered for functionality resolution
			if (!empty($aModuleConfig[self::bDisabledModule]) || $this->getModuleReplacementMap($sModuleFqcn)) return null;

			if (empty($aFuncConfig = $aModuleConfig[self::aFunctionalities][$sFuncInterfaceFqcn] ?? null)) return null;

			// Excluded functionality is treated as non-existent in this module
			if (!empty($aFuncConfig[self::bExcludedFunctionality])) return null;
		} else {
			$aFuncConfig = $this->aModuleEffectiveConfigs[$sModuleFqcn][self::aFunctionalities][$sFuncInterfaceFqcn] ?? null;
		}
		//	echo "\n\n Resulting($sModuleFqcn, $sFuncInterfaceFqcn);\n\n";
		//	echo "\n aFuncConfig(".print_r($aFuncConfig[self::sFuncConcreteFQCN],true).");\n\n";

		//	print_r($aFuncConfig);
		//	print_r($aFuncConfig[self::sFuncConcreteFQCN]);
		if (!empty($aFuncConfig[self::sFuncConcreteFQCN]) && is_string($aFuncConfig[self::sFuncConcreteFQCN]))
			return $aFuncConfig[self::sFuncConcreteFQCN];

		return null;
	}

	/**
	 * @param string $sFuncInterfaceFqcn
	 * @param string $sModuleFqcn
	 * @param bool $bAutoResolveModule
	 * @return object|mixed|null
	 * @throws AfrContainerException
	 * @throws AfrEnvException
	 * @throws AfrEventException
	 * @throws AfrModuleException
	 * @throws AfrModuleFunctionalityException
	 */
	public function resolveFunctionalityByModuleFQCN(string $sFuncInterfaceFqcn, string $sModuleFqcn, bool $bAutoResolveModule = true): ?object //K
	{
//		echo "\n\n~~~~~~~~~~~~~~~\nRESOLVING FUNCTIONAL RELATIONS sModuleFqcn:$sModuleFqcn, sFuncInterfaceFqcn:$sFuncInterfaceFqcn bAutoResolveModule($bAutoResolveModule)\n\n\n";
		//MODULE IS REPLACED by another implementation, so we get the functionality from there,
		if ($snReplacement = $this->getModuleReplacementMap($sModuleFqcn))
			return $this->resolveFunctionalityByModuleFQCN($sFuncInterfaceFqcn, $snReplacement, $bAutoResolveModule);

		return $this->getFunctionalityWrap($sModuleFqcn, $sFuncInterfaceFqcn, $bAutoResolveModule);
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
	): ?object //K.99%+TEST
	{
		//TODO: mod replacer in conjuction with WrapKey CODE sFunctionalityWrapKey
		if (!$sWrapKey = $this->getFunctionalityWrapKey($moduleFqcn, $sFuncInterfaceFqcn)) return null;

		//TODO CHECK DOUBLE MESURE A.1 is_subclass_of
		$sFuncConcreteFqcn = $this->getFunctionalityConcreteByModuleAndInterface($moduleFqcn, $sFuncInterfaceFqcn, false);

//		debug_print_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);
		if ($sFuncConcreteFqcn !== $sFuncInterfaceFqcn && !is_subclass_of($sFuncConcreteFqcn, $sFuncInterfaceFqcn)) {
			throw new AfrModuleFunctionalityException("Interface $sFuncInterfaceFqcn is not implemented by $sFuncConcreteFqcn in module $moduleFqcn");
		}

		if ($bAutoResolveModule) $this->resolveModule($moduleFqcn); //On demand LAZY?

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

		//APPLY SETTINGS ONLY IF
		if ($oResolvedFunctionality instanceof AfrFunctionalityInterface) {
			$oResolvedFunctionality->attachFunctionalityEffectiveRunTimeParameters(
				$this->getFunctionalityEffectiveConfigByWrapKey($sWrapKey)
			);
		}

		if ($onSettingsClosure = $this->getFunctionalityApplySettingsClosure($sWrapKey)) {
			$onSettingsClosure($oResolvedFunctionality, $this->getFunctionalitySettingsByWrapKey($sWrapKey));
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
	): array //K.99%+TEST
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
	): array//K.99%+TEST
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





	///////////////////////////////////////////////////////////////////////////


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

		//	$this->aModulesInstances = $this->aWrapFunctionalitiesInstances = $this->aWrapFunctionalitiesInstancesSplMap = []; //WIll not be retested

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
		//	$sTempDir = AfrSysTempDir::sysGetTempDirAliasSubDir($this);
		//	$sTempDir = Afr::getTempDir(). DIRECTORY_SEPARATOR.'AfrModuleBox-RT.php';
		//	$sTempDir = AfrTenant::getTempDir(). DIRECTORY_SEPARATOR.'AfrModuleBox-RT.php';
		//TODO: closures serialize via OPIS
		//AfrCachePhpFile::getInstance();
		//AfrCachePhpFile::getInstance();
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
			if ($this->getModuleReplacementMap($modFQCN, false)) continue; //TODO: setez wrap key si in modul repalced???
//			if(!empty($this->aModuleReplacementMap[$modFQCN])) continue; //TODO: setez wrap key si in modul repalced???
			//TODO: mod replacer in conjuction with WrapKey CODE sFunctionalityWrapKey
			foreach ($mconfig[self::aFunctionalities] as $sFuncInterfaceFqcn => $aFuncConf) {
				if (!empty($aFuncConf[self::bExcludedFunctionality])) continue;
				$wrapKey = $this->makeFunctionalityWrapKey($modFQCN, $sFuncInterfaceFqcn, $aFuncConf[self::sFuncConcreteFQCN]);
				$this->aModuleEffectiveConfigs[$modFQCN][self::aFunctionalities][$sFuncInterfaceFqcn][self::sFunctionalityWrapKey] = $wrapKey;
				//SET PARENTS BY KEY
				$this->aFunctionalityRelatedModulesByKeyMap[$wrapKey][$modFQCN][$sFuncInterfaceFqcn] = $aFuncConf[self::sFuncConcreteFQCN]; //pile up by key
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


	protected static function mergeConfig(
		iterable $aOld,
		iterable $aNew,
		bool     $bImplicitInheritOffFlags,
		?bool    $bMergeFunctionalityIntKeys = null
	): array //K
	{
		is_array($aOld) or $aOld = iterator_to_array($aOld);
		is_array($aNew) or $aNew = iterator_to_array($aNew);

//		$aDebug = ['OLD' => $aOld, 'NEW' => $aNew];

		if (!$bImplicitInheritOffFlags) {
			foreach ([self::bDisabledModule, self::bExcludedFunctionality] as $key) {
				//Do not inherit explicitly the module disable flag if is not set by the extender
				//Do not inherit explicitly the functionality excluded flag if is not set by the extender
				if (!empty($aOld[$key]) && !array_key_exists($key, $aNew)) unset($aOld[$key]);
			}
		}

		if ($bMergeFunctionalityIntKeys === null) {
			$m = $aNew[self::bMergeFunctionalityIntKeys] ?? ($aOld[self::bMergeFunctionalityIntKeys] ?? null);
			if ($m !== null) $bMergeFunctionalityIntKeys = (bool) $m;
		}

		if ($aNew[self::bMergeFunctionalityFlushOldConfig] ?? null) {
			$aOld = [];
			unset($aNew[self::bMergeFunctionalityFlushOldConfig]);
		}

		foreach ($aNew as $k => $v) {
			//if (!isset($aOld[$k])) $aOld[$k] = $v;
			if (!array_key_exists($k, $aOld)) $aOld[$k] = $v;
			elseif (is_array($aOld[$k]) && is_array($v)) $aOld[$k] = static::mergeConfig($aOld[$k], $v, $bImplicitInheritOffFlags,$bMergeFunctionalityIntKeys);
			elseif ($bMergeFunctionalityIntKeys && is_int($k)) $aOld[] = $v;
			elseif (!$bMergeFunctionalityIntKeys && is_int($k)) $aOld[$k] = $v;
			else $aOld[$k] = $v;
		}
		//	echo "\n\n\n";		debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);		echo "\nmergeConfig " . print_r($aDebug + ['RESULTING:' => $aOld], true) . "\n\n\n\n\n\n\n\n\n\n";
		ksort($aOld); //keep the property order constant //TODO
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

	public function hadFlushInstances(bool $bFlushRegistered):self
	{
		$this->aModulesInstances = $this->aWrapFunctionalitiesInstances = $this->aWrapFunctionalitiesInstancesSplMap = [];
		if($bFlushRegistered) $this->aPushedModules = $this->aModuleConfigs = [];
		$this->graphBuilt = false;

		return $this;
	}

}