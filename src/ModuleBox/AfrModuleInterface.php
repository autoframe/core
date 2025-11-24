<?php

declare(strict_types=1);

namespace Autoframe\Core\ModuleBox;

/**
 * Common module interface
 */
interface AfrModuleInterface extends AfrModuleConstantsInterface
{

	/**
	 * Called once when the module is registered in the Module Box.
	 * Can be used to perform internal module initialization.
	 */
	public function registerModuleInstance(): void;

	/**
	 * Optional: Provide functionality definitions that this module exposes, in configuration form.
	 *
	 * Example structure:
	 * [
	 *     SomeInterface::class => [
	 *         AfrModuleInterface::FQCN     => ConcreteClass::class,
	 *         AfrModuleInterface::bSingleton => true,
	 *         AfrModuleInterface::bExcludedFunctionality  => false,
	 *     ],
	 * ]
	 *
	 * These entries typically mirror what is defined in manifest/config files.
	 */
	public static function getDefaultFunctionalitiesConfig(): array;

	/**
	 * Contains the default module init flags
	 */
	public static function getDefaultModuleConfig(): array;

	/**
	 * The FQCN of this module.
	 */
	public static function getModuleFQCN(): string;

	/**
	 * The module's PHP namespace (for classes/config discovery).
	 */
	public static function getModuleNameSpace(): string;

	/**
	 * The absolute directory path where this module resides.
	 */
	public static function getModuleDirPath(): string;

	/**
	 * The absolute file path of the main module class.
	 */
	public static function getModuleClassFilePath(): string;

	/**
	 * A human-readable module name.
	 */
	public static function getModuleName(): string;

}
