<?php

namespace Autoframe\Core\AfrCoreModules\FnContracts;

use Autoframe\Core\Afr\Afr;
use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\Env\Exception\AfrEnvException;
use Autoframe\Core\Event\AfrEvent;
use Autoframe\Core\Event\Exception\AfrEventException;
use Autoframe\Core\Exception\AfrException;
use Autoframe\Core\Http\Request\AfrRequestInterface;
use Autoframe\Core\ModuleBox\Exception\AfrModuleException;
use Autoframe\Core\Router\Contracts\AfrRouterConstantsInterface;
use Autoframe\Core\Http\Request\AfrHttpConstantsInterface;
use Autoframe\Core\Router\Exception\AfrRouterException;

interface AfrHttpRoutesContract extends AfrHttpConstantsInterface{
	//OPTIMISE LOADING: INCLUDE Target constant that returns an array with 1-3 subtypes:

	/**
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 * @throws AfrRouterException
	 * @throws AfrEnvException
	 * @throws AfrException
	 * @throws AfrModuleException
	 * @throws \ReflectionException
	 */
	public function __invoke():int;
	/**
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 * @throws AfrRouterException
	 * @throws AfrEnvException
	 * @throws AfrException
	 * @throws AfrModuleException
	 * @throws \ReflectionException
	 */
	public function registerHttpRoutes():int;
	public function getHttpRoutes():?array;
	/**
	 * @throws AfrModuleException
	 * @throws AfrEventException
	 * @throws AfrException
	 * @throws AfrContainerException
	 * @throws AfrEnvException
	 * @throws \ReflectionException
	 */
	public function xetHTTPSubRoutingPath(string $sSubRoutingPath = null): string; //TODO implement / test


}