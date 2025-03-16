<?php

namespace Autoframe\Core\Module;

interface AfrModuleCLIRoutesInterface extends AfrModuleInterface
{
	const MODULE_CLI_QA_ROUTES_FILE = DIRECTORY_SEPARATOR . 'routesCLI.php';
	public function registerCLIRoutes(): int;

	public function getModuleCLIRoutesPath(): string;

}