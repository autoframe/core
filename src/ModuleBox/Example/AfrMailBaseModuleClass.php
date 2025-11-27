<?php
declare(strict_types=1);

namespace Autoframe\Core\ModuleBox\Example;

use Autoframe\Core\ModuleBox\AfrModuleConstantsInterface;
use Autoframe\Core\ModuleBox\AfrModuleInterface;
use Autoframe\Core\ModuleBox\AfrModuleTrait;

class AfrMailBaseModuleClass implements AfrModuleInterface
{
	use AfrModuleTrait;

	public function registerModuleInstance(): void
	{
		// Could perform internal setup here.
	}

	public static function getDefaultFunctionalitiesConfig(): array
	{
		// Default: provide SMTP-based email sending as a singleton functionality.
		return [
			AfrEmailSenderInterface::class => [
				AfrModuleConstantsInterface::FQCN     => AfrSmtpEmailSenderClass::class,
				AfrModuleConstantsInterface::bSingleton => true,
				AfrModuleConstantsInterface::bExcludedFunctionality  => false,
			],
		];
	}
}
