<?php

namespace Autoframe\Core\Module;

interface AfrModuleHTTPRoutesInterface extends AfrModuleInterface
{
	//TODO: de verificat / probat merge cu parinte / dependinte!!!
	const MODULE_HTTP_ROUTES_FILE = DIRECTORY_SEPARATOR . 'routesHTTP.php';
	public function registerHTTPRoutes(): int;

	public function xetHTTPSubRoutingPath(string $sSubRoutingPath = null): string;
	public function getModuleHTTPRoutesPath(): string;

	public function getDependenciesHTTPRoutesFQCN(): array; //TODO: test?? aici fac numai listare cu get sau ce?

}