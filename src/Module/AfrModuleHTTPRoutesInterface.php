<?php

namespace Autoframe\Core\Module;

interface AfrModuleHTTPRoutesInterface extends AfrModuleInterface
{
	const MODULE_HTTP_ROUTES_FILE = DIRECTORY_SEPARATOR . 'routesHTTP.php';
	public function registerHTTPRoutes(): int;

	public function xetHTTPSubRoutingPath(string $sSubRoutingPath = null): string;
	public function getModuleHTTPRoutesPath(): string;

}