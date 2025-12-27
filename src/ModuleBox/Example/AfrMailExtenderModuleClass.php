<?php
declare(strict_types=1);

namespace Autoframe\Core\ModuleBox\Example;

use Autoframe\Core\ModuleBox\AfrModuleConstantsInterface;
use Autoframe\Core\ModuleBox\AfrModuleInterface;
use Autoframe\Core\ModuleBox\AfrModuleTrait;

/**
 * Extender module: AfrMailExtenderModuleClass.php
 *
 * This module EXTENDS the base mail module and adds an extra functionality configuration.
 * According to your rules, when a module is both replaced and extended:
 *
 * Resolution of the base module FQCN returns the replacer.
 *
 * Extenders still merge against the original base config, not the replacer.
 */
class AfrMailExtenderModuleClass implements AfrModuleInterface
{
	use AfrModuleTrait;

	public function registerModuleInstance(): void
	{
		// Extender-specific initialization; could register additional hooks/routes, etc.
	}

	public static function getDefaultFunctionalitiesConfig(): array
	{
		// Extender adds or overrides functionality.
		// Here we show a scenario where the extender keeps the same interface
		// but could change the implementation or additional flags.
		return [
			AfrEmailSenderInterface::class => [
				// In this simple example, we keep the same concrete class
				// but in real life you might wrap or decorate it.
				AfrModuleConstantsInterface::sConcreteFQCN     => AfrSmtpEmailSenderClass::class,
				AfrModuleConstantsInterface::bSingletonFunctionalityWithMergedSettings => true,
				AfrModuleConstantsInterface::bExcludedFunctionality  => false,
			],
		];
	}
}
