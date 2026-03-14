<?php
declare(strict_types=1);

namespace Autoframe\Core\ModuleBox;


use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\Env\Exception\AfrEnvException;
use Autoframe\Core\Event\Exception\AfrEventException;
use Autoframe\Core\FileSystem\CacheToPhpFile\AfrCachePhpFileToArray;
use Autoframe\Core\ModuleBox\Exception\AfrModuleException;
use Autoframe\Core\ModuleBox\Exception\AfrModuleFunctionalityException;
use Autoframe\Core\Tenant\AfrDefaultTenantConfigsInterface;
use Closure;

interface AfrModuleBoxInterface extends AfrModuleConstantsInterface, AfrDefaultTenantConfigsInterface
{
	/** @throws AfrModuleException */
	/**
	 * Register module fqcn.
	 */
	public function registerModuleFQCN(string $sFqcnModule, array $aModConfig = []): void;

	/**
	 * Register module using closure.
	 */
	public function registerModuleUsingClosure(string $sFqcnModule, Closure $oClosure, array $aModConfig = []): void;

	/**
	 * Register module instance.
	 */
	public function registerModuleInstance(AfrModuleInterface $oModule, array $aModConfig = []): void;

	/**
	 * Register module fqcn list from app config.
	 */
	public function registerModuleFqcnListFromAppConfig(array $aConfigFQCN, bool $bCache, bool $bLoadFrameworkConfig = true): void;

	/** @throws AfrModuleException|AfrEnvException */
	/**
	 * Is resolvable module.
	 */
	public function isResolvableModule(string $sModuleFqcn, bool $bCountReplacersAsTrue): bool;

	/** @throws AfrModuleException|AfrEnvException */
	/**
	 * Is resolved module.
	 */
	public function isResolvedModule(string $sModuleFqcn, bool $bCountReplacersAsTrue, bool $bCountEmptyInstanceAsResolved = false): bool;

	/** @throws AfrModuleException|AfrEnvException */
	/**
	 * Is disabled module.
	 */
	public function isDisabledModule(string $sModuleFqcn): ?bool;

	/** @throws AfrModuleException|AfrEnvException */
	/**
	 * Get module info.
	 */
	public function getModuleInfo(string $sModuleFqcn): ?array;

	/** @throws AfrModuleException|AfrEnvException */
	/**
	 * Get modules effective configs list.
	 */
	public function getModulesEffectiveConfigsList(): array;

	/** @throws AfrModuleException|AfrEnvException */
	/**
	 * Get module effective configs.
	 */
	public function getModuleEffectiveConfigs(string $sModuleFqcn, bool $bGetBaseConfigNotReplacer): ?array;

	/** @throws AfrModuleException|AfrEnvException */
	/**
	 * Get module functionality resolved list.
	 */
	public function getModuleFunctionalityResolvedList(string &$sModuleFqcn, bool $bCountReplacersAsTrue = true): ?array;

	/** @throws AfrModuleException|AfrEnvException */
	/**
	 * Get module replacement map.
	 */
	public function getModuleReplacementMap(string $sModuleFqcn, bool $bBuildGraphIfNeeded = true): ?string;

	/**
	 * Resolve module.
	 * @param string $sModuleFqcn
	 * @return AfrModuleInterface|null
	 * @throws AfrContainerException|AfrEventException
	 * @throws AfrModuleException|AfrEnvException
	 */
	public function resolveModule(string $sModuleFqcn): ?AfrModuleInterface;

	/** @throws AfrModuleException|AfrEnvException */
	/**
	 * Get functionality effective config by module interface.
	 */
	public function getFunctionalityEffectiveConfigByModuleInterface(string $sModuleFqcn, string $sFunctionalityInterface): ?array;

	/** @throws AfrModuleException|AfrEnvException */
	/**
	 * Get functionality effective config.
	 */
	public function getFunctionalityEffectiveConfig(object $oFunctionalityInstance): ?array;

	/** @throws AfrModuleException|AfrEnvException */
	/**
	 * Get functionality effective config by wrap key.
	 */
	public function getFunctionalityEffectiveConfigByWrapKey(string $sWrapKey): ?array;

	/** @throws AfrModuleException|AfrEnvException */
	/**
	 * Get functionality settings by wrap key.
	 */
	public function getFunctionalitySettingsByWrapKey(string $sWrapKey): ?array;

	/** @throws AfrModuleException|AfrEnvException */
	/**
	 * Get functionality settings by func instance.
	 */
	public function getFunctionalitySettingsByFuncInstance(object $oFunctionalityInstance): ?array;


	/** @throws AfrModuleException|AfrEnvException */
	/**
	 * Get functionality apply settings closure.
	 */
	public function getFunctionalityApplySettingsClosure(string $sWrapKey): ?Closure;

	/** @throws AfrModuleException|AfrEnvException */
	/**
	 * Get functionality related modules tree.
	 */
	public function getFunctionalityRelatedModulesTree(object $oFunctionalityInstance): ?array;

	/** @throws AfrModuleException|AfrEnvException */
	/**
	 * Get functionality related modules tree by wrap key.
	 */
	public function getFunctionalityRelatedModulesTreeByWrapKey(string $sWrapKey): ?array;

	/** @throws AfrModuleException|AfrEnvException */
	/**
	 * Get functionality wrap key by func instance.
	 */
	public function getFunctionalityWrapKeyByFuncInstance(object $oFunctionalityInstance): ?string;

	/** @throws AfrModuleException|AfrEnvException */
	/**
	 * Get functionality list.
	 */
	public function getFunctionalityList(): array;

	/**
	 * Resolve functionality by module instance.
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
	 * Resolve functionality by module fqcn.
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
	/**
	 * Get functionality parent modules fqcns by wrap key.
	 */
	public function getFunctionalityParentModulesFQCNsByWrapKey(string $sFunctionalityWrapKey): ?array;

	/** @throws AfrModuleException|AfrEnvException */
	/**
	 * Get functionality parent modules fqcns.
	 */
	public function getFunctionalityParentModulesFQCNs(object $oFunctionalityInstance): ?array;

	/** @throws AfrModuleException|AfrEnvException */
	/**
	 * Get functionality wrap key.
	 */
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
	 * Get functionality group for resolving.
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

	/** @throws AfrEnvException */

	/**
	 * Xet cache seconds.
	 */
	public function xetCacheSeconds(int $iCacheSeconds = null): int;
	/** @throws AfrEnvException */

	/**
	 * Xet cache flag.
	 */
	public function xetCacheFlag(bool $bTenantCache = null): bool;

	/**
	 * Set cache.
	 */
	public function setCache(): bool;

	/**
	 * Load from cache.
	 */
	public function loadFromCache(): ?bool;

	/**
	 * Hard flush instances.
	 */
	public function hardFlushInstances(bool $bFlushRegistered): self;



}
