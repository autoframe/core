<?php
declare(strict_types=1);

namespace Autoframe\Core\ModuleBox;



interface AfrModuleBoxInterface extends AfrModuleConstantsInterface
{
	/**
	 * Register a module instance and its configuration.
	 *
	 * @param AfrModuleInterface $oModule
	 * @param array              $aConfig Raw module config as loaded from manifest/app config
	 */
	public function registerModuleInstance(AfrModuleInterface $oModule, array $aConfig = []): void;


	/**
	 * Register a module class name and its configuration.
	 *
	 * @param string $sFqcnModule
	 * @param array $aModConfig
	 */
	public function registerModuleFQCN(string $sFqcnModule, array $aModConfig = []): void;


	/**
	 * @param array $aConfigFQCN
	 * @return void
	 */
	public function registerModuleFqcnListFromAppConfig(array $aConfigFQCN = []): void;

	/**
	 * Resolve a module by its FQCN, taking into account disabled/replace/extend rules.
	 *
	 * @param string $moduleFqcn Base module FQCN (may be mapped to a replacer)
	 * @return AfrModuleInterface|null
	 */
	public function resolveModule(string $moduleFqcn): ?AfrModuleInterface;

	/**
	 * Resolve functionality by interface FQCN.
	 *
	 * - Can return a single instance (if $single is true and one implementation is found).
	 * - Can return an array of instances (if multiple implementations exist and $single is false).
	 * - Falls back to the DI container if no module can provide it.
	 *
	 * @param string      $interfaceFqcn Interface to resolve
	 * @param string|null $preferredFqcn When $single = true and multiple implementations exist,
	 *                                   this preferred concrete FQCN can be used to select one.
	 *
	 * @return AfrFunctionalityInterface|AfrFunctionalityInterface[]|object|object[]|null
	 */
	public function resolveFunctionality(
		string $interfaceFqcn,
		?string $preferredFqcn = null
	);
}
