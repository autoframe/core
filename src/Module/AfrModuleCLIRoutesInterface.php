<?php

namespace Autoframe\Core\Module;

use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\Event\Exception\AfrEventException;
use Autoframe\Core\Module\Exception\AfrModuleException;

interface AfrModuleCLIRoutesInterface extends AfrModuleInterface
{
	const MODULE_CLI_QA_ROUTES_FILE = DIRECTORY_SEPARATOR . 'routesCLI.php';

	/**
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 * @throws AfrModuleException
	 * @throws \ReflectionException
	 */
	public function registerCLIRoutes(): int;

	/**
	 * @throws \ReflectionException
	 */
	public function getModuleCLIRoutesPath(): string;

}