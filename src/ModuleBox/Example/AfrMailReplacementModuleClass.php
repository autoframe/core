<?php
declare(strict_types=1);

namespace Autoframe\Core\ModuleBox\Example;

use Autoframe\Core\ModuleBox\AfrModuleConstantsInterface;
use Autoframe\Core\ModuleBox\AfrModuleInterface;
use Autoframe\Core\ModuleBox\AfrModuleTrait;

/**
 * Replacement module: AfrMailReplacementModuleClass.php
 *
 * This module REPLACES AfrMailBaseModuleClass.
 * When you resolve the base module via AfrModuleBoxClass::resolveModule(AfrMailBaseModuleClass::class),
 * you get an instance of this class. It also provides a different email sender implementation.
 */
class AfrMailReplacementModuleClass implements AfrModuleInterface
{
	use AfrModuleTrait;

	public function registerModuleInstance(): void
	{
		// Replacement-specific initialization if needed.
	}

	public static function getDefaultFunctionalitiesConfig(): array
	{
		// This module redefines the email sender with a log-only implementation.
		return [
			AfrEmailSenderInterface::class => [
				AfrModuleConstantsInterface::sFuncConcreteFQCN     => AfrLogOnlyEmailSenderClass::class,
				AfrModuleConstantsInterface::bSingletonFunctionalityWithMergedSettings => true,
				AfrModuleConstantsInterface::bExcludedFunctionality  => false,
			],
		];
	}
}
