<?php
declare(strict_types=1);

namespace Autoframe\Core\ModuleBox;


use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\Event\Exception\AfrEventException;
use Autoframe\Core\ModuleBox\Exception\AfrModuleException;
use Autoframe\Core\ModuleBox\Exception\AfrModuleFunctionalityException;
use Closure;

interface AfrModuleBoxInterface extends AfrModuleConstantsInterface
{
	/**
	 * Register a module instance and its configuration.
	 *
	 * @param AfrModuleInterface $oModule
	 * @param array $aModConfig Raw module config as loaded from manifest/app config
	 */
	public function registerModuleInstance(AfrModuleInterface $oModule, array $aModConfig = []): void;


	/**
	 * Register a module class name and its configuration.
	 *
	 * @param string $sFqcnModule
	 * @param array $aModConfig
	 */
	public function registerModuleFQCN(string $sFqcnModule, array $aModConfig = []): void;

	/**
	 * Register a module class name and its configuration.
	 *
	 * @param string $sFqcnModule
	 * @param Closure $oClosure
	 * @param array $aModConfig
	 */
	public function registerModuleUsingClosure(string $sFqcnModule, Closure $oClosure, array $aModConfig = []): void;


	/**
	 * @param array $aConfigFQCN
	 * @return void
	 */
	public function registerModuleFqcnListFromAppConfig(array $aConfigFQCN = []): void;

	/**
	 * Resolve a module by its FQCN, taking into account disabled/replace/extend rules.
	 *
	 * @param string $sModuleFqcn Base module FQCN (may be mapped to a replacer)
	 * @return AfrModuleInterface|null
	 */
	public function resolveModule(string $sModuleFqcn): ?AfrModuleInterface;

	/**
	 * Resolve functionality by interface FQCN.
	 *
	 * - Can return a single instance (if $single is true and one implementation is found).
	 * - Can return an array of instances (if multiple implementations exist and $single is false).
	 * - Falls back to the DI container if no module can provide it.
	 *
	 * @param string $sFuncInterfaceFqcn Interface to resolve
	 *
	 * @return object[]|null
	 */
	public function resolveFunctionalityGroup(
		string  $sFuncInterfaceFqcn
	);

	public function getModulesEffectiveConfigsList(): array;

	public function getFunctionalityList(): array;


	/**
	 * @param string $sFuncInterfaceFqcn
	 * @param string $sModuleFqcn
	 * @return object|null
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 * @throws AfrModuleException
	 * @throws AfrModuleFunctionalityException
	 */
	public function resolveFunctionalityByModuleFQCN(string $sFuncInterfaceFqcn, string $sModuleFqcn): ?object;

	public function getModuleEffectiveConfigs(string $sModuleFqcn): ?array;

	public function isResolvableModule(string $sModuleFqcn, bool $bCountReplacersAsTrue): bool;
	public function isResolvedModule(string $sModuleFqcn, bool $bCountReplacersAsTrue, bool $bCountEmptyInstanceAsResolved = false): bool;

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

	public function getFunctionalityEffectiveConfig(object $oFunctionalityInstance): ?array;
}