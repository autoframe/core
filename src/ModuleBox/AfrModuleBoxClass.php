<?php
declare(strict_types=1);

namespace Autoframe\Core\ModuleBox;

use Autoframe\Core\Afr\Afr;
use Autoframe\Core\Arr\Compare\AfrArrCompareClass;
use Autoframe\Core\Container\AfrContainerFacade;
use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;
use Autoframe\Core\Env\Exception\AfrEnvException;
use Autoframe\Core\Error\AfrError;
use Autoframe\Core\Event\Exception\AfrEventException;
use Autoframe\Core\ModuleBox\Exception\AfrModuleException;
use Autoframe\Core\ModuleBox\Exception\AfrModuleFunctionalityException;
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
	public static bool $bDebug = false;
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
	protected array $aOrderedEffectiveConfigKeys = [];

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
	protected array $aModuleIsExtenderOfOtherModule = [];
	protected array $aModuleIsReplacerOfOtherModule = [];

	/**
	 * Lazy-build flag for $effectiveConfigs / replacement / extension maps.
	 *
	 * @var bool
	 */
	protected bool $graphBuilt = false;

	protected array $aFunctionalityConcreteFqcnAsSingletonMap = [];
	protected array $aBridgeFunctionalityInstanceWithParentMap = [];

	public function registerModuleInstance(AfrModuleInterface $oModule, array $aConfig = []): void
	{
		$this->pushModuleConfig($oModule, get_class($oModule), $aConfig);
		//$this->pushModuleConfig($oModule, $oModule::getModuleFQCN(), $aConfig);
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
		$this->aPushedModules[$sFQCN] = $module; //push instance or fqcn for resolving

		$aConfig[self::aFunctionalities] = self::mergeConfig(
		/** @var AfrModuleInterface $sFQCN */
			$sFQCN::getDefaultFunctionalitiesConfig(), // Merge module-provided defaults (from code) with manifest/app config
			(array)($aConfig[self::aFunctionalities] ?? [])
		);
		//TODO: structura default pentru fiecare interfata:
		/*
		 * [
			AfrEmailSenderInterface::class => [
				AfrModuleConstantsInterface::sFuncConcreteFQCN     => AfrSmtpEmailSenderClass::class,
				AfrModuleConstantsInterface::bSingletonFunctionalityWithMergedSettings => true,
				AfrModuleConstantsInterface::bBridgeFunctionalityInstanceWithParent  => false,
				AfrModuleConstantsInterface::bExcludedFunctionality  => false,
			TODO	AfrModuleConstantsInterface::aRunTimeParameters  => XXXXX,
			TODO	AfrModuleConstantsInterface::aRunTimeParameters  => XXXXX,
			TODO	AfrModuleConstantsInterface::aRunTimeParameters  => XXXXX,
			TODO	AfrModuleConstantsInterface::aRunTimeParameters  => XXXXX,
			TODO	AfrModuleConstantsInterface::aRunTimeParameters  => XXXXX,
			TODO	AfrModuleConstantsInterface::aRunTimeParameters  => XXXXX,
			TODO	AfrModuleConstantsInterface::aRunTimeParameters  => XXXXX,
			TODO	AfrModuleConstantsInterface::aRunTimeParameters  => XXXXX,
			],
		 * */

		// Normalize config with Defaults
		$aConfig = self::mergeConfig($sFQCN::getDefaultModuleConfig(), $aConfig);

		$bSomePartialConfigAlreadyExists =
			!empty($this->aModuleConfigs[$sFQCN]) &&
			is_array($this->aModuleConfigs[$sFQCN]) &&
			count($this->aModuleConfigs[$sFQCN]) < 2;

		$this->aModuleConfigs[$sFQCN] = $bSomePartialConfigAlreadyExists ?
			self::mergeConfig($this->aModuleConfigs[$sFQCN], $aConfig) :
			$aConfig;

		$this->graphBuilt = false; // Mark graph as dirty so it will be rebuilt lazily
	}


	public function getModuleEffectiveConfigs(string $sModuleFqcn): ?array
	{
		$this->buildGraphIfNeeded();
		return $this->aModuleEffectiveConfigs[$sModuleFqcn] ?? $this->aModuleConfigs[$sModuleFqcn] ?? null;
	}

	public function getFunctionalityEffectiveConfigs(string $sFunctionalityConcreteFqcn): ?array
	{
		$this->buildGraphIfNeeded();
		return $this->aFunctionalityEffectiveConfigs[$sFunctionalityConcreteFqcn] ?? null;
	}

	public function isResolvableModule(string $sModuleFqcn, bool $bCountReplacersAsTrue): bool
	{
		$this->buildGraphIfNeeded();
		$iResolvableModule = $this->aModuleEffectiveConfigs[$sModuleFqcn][self::iResolvableModule] ?? 0;
		if ($iResolvableModule === 1) return true;
		if ($iResolvableModule === 2 && $bCountReplacersAsTrue) return true;
		return false;
	}

	public function isResolvedModule(string $sModuleFqcn, bool $bCountReplacersAsTrue, bool $bCountEmptyInstanceAsResolved = false): bool
	{
		$this->buildGraphIfNeeded();
		if ($bCountReplacersAsTrue && $snReplacement = $this->getModuleReplacementMap($sModuleFqcn)) {
			return $this->isResolvedModule($snReplacement, true);
		}
		return $bCountEmptyInstanceAsResolved ?
			array_key_exists($sModuleFqcn, $this->aModulesInstances) :
			($this->aModulesInstances[$sModuleFqcn] ?? null) instanceof AfrModuleInterface;

	}

	protected function getModuleReplacementMap(string $sModuleFqcn): ?string
	{
		$this->buildGraphIfNeeded();
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
	 * @throws AfrModuleException
	 */
	public function resolveModule(string $sModuleFqcn): ?AfrModuleInterface
	{
		$this->buildGraphIfNeeded();

		if ($snReplacement = $this->getModuleReplacementMap($sModuleFqcn)) { //replaced
			return $this->resolveModule($snReplacement);
		}

		$config = $this->getModuleEffectiveConfigs($sModuleFqcn) ?? [];
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
	public function resolveFunctionalityByModuleFQCN(string $sFuncInterfaceFqcn, string $sModuleFqcn): ?AfrFunctionalityInterface
	{
		//MODULE IS REPLACED by another implementation, so we get the functionality from there,
		// because this module should not instanciate
		$this->buildGraphIfNeeded();
		if ($snReplacement = $this->getModuleReplacementMap($sModuleFqcn)) {
			return $this->resolveFunctionalityByModuleFQCN($sFuncInterfaceFqcn, $snReplacement);
		}
		//TODO instance cache here?? Nu cred, ca este in context + singleton + cheie
		return $this->getFunctionalityWrap(
			$sModuleFqcn,
			$this->getFunctionalityConcreteByInterface($sFuncInterfaceFqcn)[$sModuleFqcn] ?? '',
			$sFuncInterfaceFqcn
		);
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
			$onFunctionalityInstance = $this->getFunctionalityWrap($moduleFqcn, $sFuncConcreteFqcn, $sFuncInterfaceFqcn);
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


	protected function getConcreteFunctionalityModuleParents(
		string $sFuncConcrete,
		bool   $bIncludeReplacedModules = false,
		bool   $bIncludeDisabledModules = false,
		bool   $bIncludeExcludedFunctionalities = false
	): array
	{
		$this->buildGraphIfNeeded();
		$aParents = [];
		//loop effective MODULE configs
		foreach ($this->aModuleEffectiveConfigs as $sModuleFqcn => $aModuleConfig) {
			// Disabled module not considered for functionality resolution
			if (!$bIncludeDisabledModules && !empty($aModuleConfig[self::bDisabledModule])) continue;

			if (!$bIncludeReplacedModules && $this->getModuleReplacementMap($sModuleFqcn)) continue;

			if (empty($aModuleConfig[self::aFunctionalities]) || !is_array(empty($aModuleConfig[self::aFunctionalities]))) continue;

			foreach ($aModuleConfig[self::aFunctionalities] as $sFuncInterfaceFqcn => $aFuncConfig) {
				if (!$bIncludeExcludedFunctionalities && !empty($aFuncConfig[self::bExcludedFunctionality])) continue;
				if (!empty($aFuncConfig[self::sFuncConcreteFQCN]) && $sFuncConcrete === $aFuncConfig[self::sFuncConcreteFQCN]) {
					$aParents[] = $sModuleFqcn;
					break;
				}
			}
		}
		return $aParents;
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
		$this->aModuleIsExtenderOfOtherModule =
		$this->aModuleIsReplacerOfOtherModule =
		$this->aOrderedEffectiveConfigKeys =
		$this->aModuleEffectiveConfigs =
		$this->aFunctionalityEffectiveConfigs =
		$this->aFunctionalityConcreteFqcnAsSingletonMap =
		$this->aBridgeFunctionalityInstanceWithParentMap = [];

		$this->buildGraphAutoRegisterMissingDependencyModules();
		// First pass: collect basic relations from raw config
		$this->buildGraphOrderedEffectiveConfigs();


		// Set Bridge (singleton) functionality instances
		foreach ($this->aModuleEffectiveConfigs as $modFQCN => $mconfig) {
			foreach ($mconfig[self::aFunctionalities] as $sFuncInterfaceFqcn => $aFuncConf) {
				if (
					!empty($aFuncConf[self::bBridgeFunctionalityInstanceWithParent]) &&
					empty($this->aFunctionalityConcreteFqcnAsSingletonMap[$aFuncConf[self::sFuncConcreteFQCN]])
				) {
					//TODO: nu toate modulel fac bridge pe aceiasi instanta!!!
					//TODO: cheia se face intre parinte si extender sau intre parinte extender 1 extender 2?
					if ($this->functionalityInstanceCanBridgeWithParentHelper($sFuncInterfaceFqcn, $modFQCN)) {
						$this->aBridgeFunctionalityInstanceWithParentMap[$aFuncConf[self::sFuncConcreteFQCN]][$modFQCN] = $aFuncConf[self::snModuleExtends];
						//	$this->aBridgeFunctionalityInstanceWithParentMap[$aFuncConf[self::sFuncConcreteFQCN]][$modFQCN] = $sFuncInterfaceFqcn;
					} else {
						//incompatible bridging because of divergent functionality configs
						$this->aModuleEffectiveConfigs[$modFQCN][self::aFunctionalities][$sFuncInterfaceFqcn][self::bBridgeFunctionalityInstanceWithParent] = false;
					}
				}
			}
		}
		//TODO: PUPULARE FUNCTIONALITY EFFECTIVE CONFIGS + refactor referinte
		foreach ($this->aModuleEffectiveConfigs as $sModFQCN => $aModCfg) {
			foreach ($aModCfg[self::aFunctionalities] as $sFuncInterfaceFqcn => $aFuncConf) {
				//push also interface cfg as an easy way to access it
				$this->aFunctionalityEffectiveConfigs[$sFuncInterfaceFqcn][$sModFQCN] = $aFuncConf;

				//force all concrete implementations to singleton
				if (!empty($this->aFunctionalityConcreteFqcnAsSingletonMap[$aFuncConf[self::sFuncConcreteFQCN]])) {
					$aFuncConf[self::bSingletonFunctionalityWithMergedSettings] = true;
					$aFuncConf[self::aSingletonFunctionalityForceByModules] =
						array_keys($this->aFunctionalityConcreteFqcnAsSingletonMap[$aFuncConf[self::sFuncConcreteFQCN]]);
				}
				/*
				//TODO: nu toate modulel bac bridge pe aceiasi instanta!!! bBridgeFunctionalityForceByModules
				//force all concrete implementations to bridge like singleton shared instance
				if (!empty($this->aBridgeFunctionalityInstanceWithParentMap[$aFuncConf[self::sFuncConcreteFQCN]])) {
					$aFuncConf[self::bSingletonFunctionalityWithMergedSettings] = true;
					$aFuncConf[self::bBridgeFunctionalityForceByModules] =
						array_keys($this->aBridgeFunctionalityInstanceWithParentMap[$aFuncConf[self::sFuncConcreteFQCN]]);
				}*/

				//push concrete cfg
				$this->aFunctionalityEffectiveConfigs[$aFuncConf[self::sFuncConcreteFQCN]][$sModFQCN] = $aFuncConf;
			}
		}


		$this->graphBuilt = true;
		//SET IS RESOLVABLE
		foreach (array_keys($this->aModuleEffectiveConfigs) as $sModFQCN) {
			$this->aModuleEffectiveConfigs[$sModFQCN][self::iResolvableModule] =
				$this->isResolvableModuleHelper($sModFQCN);
		}


	}


	protected function buildGraphAutoRegisterMissingDependencyModules(): void
	{
		[$aModuleExtensionMap, $aModuleReplacementMap] = $this->buildGraphGetRawUnorderedExtendersReplacers();
		$aToCheck = array_merge(
			array_keys($aModuleReplacementMap), array_keys($aModuleExtensionMap)
		);
		$bRerun = false;
		foreach ($aToCheck as $fqcnToAutoload) {
			if (!isset($this->aModuleConfigs[$fqcnToAutoload])) {
				$this->aModuleConfigs[$fqcnToAutoload] = [__FUNCTION__ => true]; //prevent infinite loops
				$bRerun = true;
				$this->registerModuleFQCN($fqcnToAutoload);
			}
		}
		if ($bRerun) $this->buildGraphAutoRegisterMissingDependencyModules();
	}

	protected function buildGraphGetRawUnorderedExtendersReplacers(): array
	{
		$aModuleExtensionMap = $aModuleReplacementMap = [];
		foreach ($this->aModuleConfigs as $fqcn => $config) {
			$sModuleReplaceBase = $config[self::snModuleReplaces] ?? null;
			if ($sModuleReplaceBase === $fqcn) {//prevent self replacing
				$this->aModuleConfigs[$fqcn][self::snModuleReplaces] = $sModuleReplaceBase = null;
			}
			if (\is_string($sModuleReplaceBase) && $sModuleReplaceBase !== '') {
				// Spec: at most one replacer per base; if multiple found, last one wins
				//TODO: demapez din effective configs ceilalti replaceri + verific care este enabeled??
				//TODO: demapez din effective configs ceilalti replaceri + verific care este enabeled??
				//TODO: demapez din effective configs ceilalti replaceri + verific care este enabeled??
				$aModuleReplacementMap[$sModuleReplaceBase] ??= [];
				$aModuleReplacementMap[$sModuleReplaceBase][] = $fqcn;
				//$aModuleReplacementMap[$sModuleReplaceBase] = $fqcn;
			}
			$sModuleExtensionBase = $config[self::snModuleExtends] ?? null;
			if ($sModuleExtensionBase === $fqcn) {//prevent self extension
				$this->aModuleConfigs[$fqcn][self::snModuleExtends] = $sModuleExtensionBase = null;
			}
			if (\is_string($sModuleExtensionBase) && $sModuleExtensionBase !== '') {
				$aModuleExtensionMap[$sModuleExtensionBase] ??= [];
				$aModuleExtensionMap[$sModuleExtensionBase][] = $fqcn;
			}
		}
		return [$aModuleExtensionMap, $aModuleReplacementMap];
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

		$aQueueRemainingToOrder = [];
		foreach ($this->aModuleConfigs as $fqcn => $config) {
			$aQueueRemainingToOrder[$fqcn] = true;
			$sModuleExtensionBase = $config[self::snModuleExtends] ?? null;
			if (\is_string($sModuleExtensionBase) && $sModuleExtensionBase !== '') {
				$this->aModuleExtensionMap[$sModuleExtensionBase] ??= [];
				$this->aModuleExtensionMap[$sModuleExtensionBase][] = $fqcn;
				$this->aModuleIsExtenderOfOtherModule[$fqcn] = $sModuleExtensionBase;
			}
			$sModuleReplaceBase = $config[self::snModuleReplaces] ?? null;
			if (\is_string($sModuleReplaceBase) && $sModuleReplaceBase !== '') {
				// Spec: at most one replacer per base; if multiple found, last one wins
				$this->aModuleReplacementMap[$sModuleReplaceBase] ??= [];
				$this->aModuleReplacementMap[$sModuleReplaceBase][] = $fqcn;
				//$this->aModuleReplacementMap[$sModuleReplaceBase] = $fqcn;//TODO resolve Replacer
				$this->aModuleIsReplacerOfOtherModule[$fqcn] = $sModuleReplaceBase;
			}
		}
		$aRemainingToOrder = $aQueueRemainingToOrder;
		foreach ($aQueueRemainingToOrder as $fqcn => $b) {
			$this->buildGraphOrderedEffectiveConfigsReferencesOrder(
				$fqcn,
				$aRemainingToOrder
			);
		}
		if (static::$bDebug) {
			echo "\naOrderedEffectiveConfigKeys\n";
			print_r($this->aOrderedEffectiveConfigKeys);
		}

		foreach ($this->aOrderedEffectiveConfigKeys as $fqcn => $x) {
			$this->aModuleEffectiveConfigs[$fqcn] = $this->aModuleConfigs[$fqcn];
		}
		$this->aOrderedEffectiveConfigKeys = [];//done

		//merge extenders config with base
		foreach ($this->aModuleExtensionMap as $baseFqcn => $aExtenders) {
			foreach ($aExtenders as $extenderFqcn) {
				// Merge baseConfig into extenderConfig, extender wins on conflicts.
				// We explicitly do NOT use any replacer of the base as merge source.
				$aBaseConfig = $this->aModuleEffectiveConfigs[$baseFqcn];
				$aExtenderConfig = $this->aModuleEffectiveConfigs[$extenderFqcn];
				//Do not inherit explicitly the module disable flag in is not set by the extender
				if (!empty($aBaseConfig[self::bDisabledModule]) && !array_key_exists(self::bDisabledModule, $aExtenderConfig)) {
					unset($aBaseConfig[self::bDisabledModule]);
				}
				$this->aModuleEffectiveConfigs[$extenderFqcn] = self::mergeConfig($aBaseConfig, $aExtenderConfig);
			}
		}

		//TODO
		// - Disable only affects resolvability, not ability to act as base for extenders.

		// Finally, apply "excluded functionality" semantics:
		foreach ($this->aModuleEffectiveConfigs as $modFQCN => &$config) {
			if (!empty($config[self::bDisabledModule]) || !isset($config[self::aFunctionalities]) || !\is_array($config[self::aFunctionalities])) {
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
					$this->aFunctionalityConcreteFqcnAsSingletonMap[$aFuncConf[self::sFuncConcreteFQCN]][$modFQCN] = $sFuncInterfaceFqcn;
				}
			}
		}
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
		} elseif (!isset($this->aOrderedEffectiveConfigKeys[$fqcn])) {
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

		if (isset($this->aOrderedEffectiveConfigKeys[$fqcn])) return;

		if ($sExtends && !isset($this->aOrderedEffectiveConfigKeys[$sExtends])) {
			$this->buildGraphOrderedEffectiveConfigsReferencesOrder(
				$sExtends,
				$aRemainingToOrder
			);
		}
		if ($sReplaces && !isset($this->aOrderedEffectiveConfigKeys[$sReplaces])) {
			$this->buildGraphOrderedEffectiveConfigsReferencesOrder(
				$sReplaces,
				$aRemainingToOrder
			);
		}
		$this->aOrderedEffectiveConfigKeys[$fqcn] = true;
		if (static::$bDebug) {
			$this->aOrderedEffectiveConfigKeys[$fqcn] =
				(!empty($this->aModuleReplacementBaseMap[$fqcn]) ? 'r' : '-') .
				(!empty($this->aModuleExtensionMap[$fqcn]) ? 'e' : '-') .
				(!empty($sReplaces) ? "R[$sReplaces]" : '-') .
				(!empty($sExtends) ? " E[$sExtends]" : '-');
		}
	}

	protected function isResolvableModuleHelper(string $sModuleFqcn): int
	{
		if ($snReplacement = $this->getModuleReplacementMap($sModuleFqcn)) {
			return empty($this->isResolvableModuleHelper($snReplacement)) ? 0 : 2;
		}
		$config = $this->getModuleEffectiveConfigs($sModuleFqcn) ?? [];
		if (!empty($config[self::bDisabledModule])) return 0;
		return !empty($this->aModulesInstances[$sModuleFqcn]) || !empty($this->aPushedModules[$sModuleFqcn]) ? 1 : 0;
	}

	/**
	 * @param string $sFuncInterfaceFqcn
	 * @param string $sModuleFqcn
	 * @return bool
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 */
	protected function functionalityInstanceCanBridgeWithParentHelper(string $sFuncInterfaceFqcn, string $sModuleFqcn): bool
	{
		$parentModule = $this->aModuleEffectiveConfigs[$sModuleFqcn][self::snModuleExtends] ?? null;
		$aFuncConf = $this->aModuleEffectiveConfigs[$sModuleFqcn][self::aFunctionalities][$sFuncInterfaceFqcn] ?? null;
		if (empty($parentModule) || empty($aFuncConf)) return false;
		if (
			!empty($this->aModuleEffectiveConfigs[$parentModule][self::bDisabledModule]) ||
			empty($this->aModuleEffectiveConfigs[$parentModule][self::aFunctionalities][$sFuncInterfaceFqcn][self::sFuncConcreteFQCN])
		) return false;
		$aParentFuncConf = $this->aModuleEffectiveConfigs[$parentModule][self::aFunctionalities][$sFuncInterfaceFqcn];
		if ($aFuncConf[self::sFuncConcreteFQCN] !== $aParentFuncConf[self::sFuncConcreteFQCN]) return false; //other implementation

		//compare the settings to be the same:
		unset($aFuncConf[self::bBridgeFunctionalityInstanceWithParent]);
		unset($aParentFuncConf[self::bBridgeFunctionalityInstanceWithParent]);

		return AfrArrCompareClass::getInstance()->assertSameContents($aFuncConf, $aParentFuncConf);

	}

	protected static function mergeConfig(iterable $aOld, iterable $aNew): array
	{
		is_array($aOld) or $aOld = iterator_to_array($aOld);
		foreach ($aNew as $k => $v)
			//if (!isset($aOld[$k])) $aOld[$k] = $v;
			if (!array_key_exists($k, $aOld)) $aOld[$k] = $v;
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
		$sKey = $sFuncConcreteFqcn;
		if (!empty($this->aFunctionalityConcreteFqcnAsSingletonMap[$sFuncConcreteFqcn])) {
			return $sFuncConcreteFqcn;
		}
		if (!empty($this->aBridgeFunctionalityInstanceWithParentMap[$sFuncConcreteFqcn])) {
			//TODO: refactor dupa ce fac o structura corecta la aBridgeFunctionalityInstanceWithParentMap
			return $sFuncConcreteFqcn . '@' .
				implode('@', array_keys($this->aBridgeFunctionalityInstanceWithParentMap[$sFuncConcreteFqcn]));
		}
		return $sFuncConcreteFqcn . '@' . $moduleFqcn; //simple key
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
		if (empty($moduleFqcn) || empty($sFuncConcreteFqcn)) return null;
		if ($sFuncInterfaceFqcn && !is_subclass_of($sFuncConcreteFqcn, $sFuncInterfaceFqcn)) {
			throw new AfrModuleFunctionalityException("Interface $sFuncInterfaceFqcn is not implemented by $sFuncConcreteFqcn in module $moduleFqcn");
		}


//	if (empty($this->aModulesInstances[$moduleFqcn])) {
		$onModuleInstance = $this->resolveModule($moduleFqcn);
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
		//	if (!array_key_exists($key, $this->aFunctionalityConcreteFqcnAsSingletonMap)) {
		if (array_key_exists($key, $this->aWrapFunctionalitiesInstances)) {
			return $this->aWrapFunctionalitiesInstances[$key] ?? null;
		}

		try {
			$oResolvedFunctionality = $this->resolveUsingAppContainer($sFuncConcreteFqcn);
		} catch (\Throwable $e) {
			throw new AfrModuleFunctionalityException(
				"Unable to resolve Functionality using App Container `$sFuncConcreteFqcn`@[$moduleFqcn]\n" .
				$e->getMessage(), $e->getCode(), $e);
		}
		if (!$oResolvedFunctionality instanceof AfrFunctionalityInterface) {
			throw new AfrModuleFunctionalityException(
				"Functionality `$sFuncConcreteFqcn`@[$moduleFqcn] is not a instanceof `" . AfrFunctionalityInterface::class . "`");
		}
		$oResolvedFunctionality->attachFunctionalityEffectiveRunTimeParameters(
			$this->getFunctionalityEffectiveConfigs($sFuncConcreteFqcn)//TODO: REFACTOR
		);

		//TODO it can have more parents from $this->aFunctionalityConcreteFqcnAsSingletonMap !!!!!!!!!!!
		//	$this->aFunctionalityConcreteFqcnAsSingletonMap[$aFuncConf[self::sFuncConcreteFQCN]][$modFQCN] = $sFuncInterfaceFqcn;
		$this->aWrapFunctionalitiesInstances[$key] = $oResolvedFunctionality;
		AfrModuleRelations::getInstance()->pushFunctionalityInstance($this->aWrapFunctionalitiesInstances[$key]);
		foreach ($this->getConcreteFunctionalityModuleParents($sFuncConcreteFqcn) as $sModuleParent) {
			$oResolvedFunctionality->attachParentModuleFQCN($sModuleParent);
		}
		if ($onModuleInstance) {
			$oResolvedFunctionality->attachParentModuleInstance($onModuleInstance);
		}


		return $this->aWrapFunctionalitiesInstances[$key] ?? null;
	}
}
