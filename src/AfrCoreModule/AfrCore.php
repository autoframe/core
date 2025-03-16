<?php
namespace Autoframe\Core\AfrCoreModule;

use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;
use Autoframe\Core\Module\AfrModuleCLIRoutesInterface;
use Autoframe\Core\Module\AfrModuleCLIRoutesTrait;
use Autoframe\Core\Module\AfrModuleHTTPRoutesInterface;
use Autoframe\Core\Module\AfrModuleHTTPRoutesTrait;
use Autoframe\Core\Module\AfrModuleInterface;
use Autoframe\Core\Module\AfrModuleTrait;

class AfrCore extends AfrSingletonAbstractClass implements AfrModuleInterface, AfrModuleHTTPRoutesInterface, AfrModuleCLIRoutesInterface
{
	use AfrModuleTrait;
	use AfrModuleHTTPRoutesTrait;
	use AfrModuleCLIRoutesTrait;
}