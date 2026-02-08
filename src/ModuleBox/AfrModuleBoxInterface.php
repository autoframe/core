<?php
declare(strict_types=1);

namespace Autoframe\Core\ModuleBox;


use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\Env\Exception\AfrEnvException;
use Autoframe\Core\Event\Exception\AfrEventException;
use Autoframe\Core\ModuleBox\Exception\AfrModuleException;
use Autoframe\Core\ModuleBox\Exception\AfrModuleFunctionalityException;
use Closure;

interface AfrModuleBoxInterface extends AfrModuleConstantsInterface
{
	/** @throws AfrModuleException */
	public function registerModuleFQCN(string $sFqcnModule, array $aModConfig = []): void;

	public function registerModuleUsingClosure(string $sFqcnModule, Closure $oClosure, array $aModConfig = []): void;

	public function registerModuleInstance(AfrModuleInterface $oModule, array $aModConfig = []): void;

	public function registerModuleFqcnListFromAppConfig(array $aConfigFQCN = []): void;

	/** @throws AfrModuleException|AfrEnvException */
	public function isResolvableModule(string $sModuleFqcn, bool $bCountReplacersAsTrue): bool;

	/** @throws AfrModuleException|AfrEnvException */
	public function isResolvedModule(string $sModuleFqcn, bool $bCountReplacersAsTrue, bool $bCountEmptyInstanceAsResolved = false): bool;

	/** @throws AfrModuleException|AfrEnvException */
	public function isDisabledModule(string $sModuleFqcn): ?bool;

	/** @throws AfrModuleException|AfrEnvException */
	public function getModuleInfo(string $sModuleFqcn): ?array;

	/** @throws AfrModuleException|AfrEnvException */
	public function getModulesEffectiveConfigsList(): array;

	/** @throws AfrModuleException|AfrEnvException */
	public function getModuleEffectiveConfigs(string $sModuleFqcn, bool $bGetBaseConfigNotReplacer): ?array;

	/** @throws AfrModuleException|AfrEnvException */
	public function getModuleFunctionalityResolvedList(string &$sModuleFqcn, bool $bCountReplacersAsTrue = true): ?array;

	/** @throws AfrModuleException|AfrEnvException */
	public function getModuleReplacementMap(string $sModuleFqcn, bool $bBuildGraphIfNeeded = true): ?string;

	/**
	 * @param string $sModuleFqcn
	 * @return AfrModuleInterface|null
	 * @throws AfrContainerException|AfrEventException
	 * @throws AfrModuleException|AfrEnvException
	 */
	public function resolveModule(string $sModuleFqcn): ?AfrModuleInterface;

	/** @throws AfrModuleException|AfrEnvException */
	public function getFunctionalityEffectiveConfigByModuleInterface(string $sModuleFqcn, string $sFunctionalityInterface): ?array;

	/** @throws AfrModuleException|AfrEnvException */
	public function getFunctionalityEffectiveConfig(object $oFunctionalityInstance): ?array;

	/** @throws AfrModuleException|AfrEnvException */
	public function getFunctionalityEffectiveConfigByWrapKey(string $sWrapKey): ?array;

	/** @throws AfrModuleException|AfrEnvException */
	public function getFunctionalitySettingsByWrapKey(string $sWrapKey): ?array;

	/** @throws AfrModuleException|AfrEnvException */
	public function getFunctionalityApplySettingsClosure(string $sWrapKey): ?Closure;

	/** @throws AfrModuleException|AfrEnvException */
	public function getFunctionalityRelatedModulesTree(object $oFunctionalityInstance): ?array;

	/** @throws AfrModuleException|AfrEnvException */
	public function getFunctionalityRelatedModulesTreeByWrapKey(string $sWrapKey): ?array;

	/** @throws AfrModuleException|AfrEnvException */
	public function getFunctionalityWrapKeyByFuncInstance(object $oFunctionalityInstance): ?string;

	/** @throws AfrModuleException|AfrEnvException */
	public function getFunctionalityList(): array;

	/**
	 * @param string $sFuncInterfaceFqcn
	 * @param AfrModuleInterface $qModuleInstance
	 * @return object|null
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 * @throws AfrModuleException
	 * @throws AfrModuleFunctionalityException
	 */
	public function resolveFunctionalityByModuleInstance(string $sFuncInterfaceFqcn, AfrModuleInterface $qModuleInstance): ?object;

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
	public function resolveFunctionalityByModuleFQCN(string $sFuncInterfaceFqcn, string $sModuleFqcn, bool $bAutoResolveModule = true): ?object;

	/** @throws AfrModuleException|AfrEnvException */
	public function getFunctionalityParentModulesFQCNsByWrapKey(string $sFunctionalityWrapKey): ?array;

	/** @throws AfrModuleException|AfrEnvException */
	public function getFunctionalityParentModulesFQCNs(object $oFunctionalityInstance): ?array;

	/** @throws AfrModuleException|AfrEnvException */
	public function getFunctionalityWrapKey(string $sModuleFqcn, string &$sFuncInterfaceFqcn): ?string;

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
	public function resolveFunctionalityGroup(string $sFuncInterfaceFqcn, bool $bAutoResolveRelatedModules = false, ?array $aFunctionalityGroupForResolving = null, bool $bFallbackOnEmptyToContainer = false): array;

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
	public function getFunctionalityGroupForResolving(string $sFuncInterfaceFqcn, array $aExcludeModulesFQCNs = [], array $aAllowedModulesFQCNs = [], array $aExcludeFunctionalitiesFQCNs = [], array $aAllowedFunctionalitiesFQCNs = []): array;

}