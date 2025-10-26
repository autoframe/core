<?php

namespace Autoframe\Core\Router\Contracts;

use Autoframe\Core\Http\Request\AfrRequestInterface;
use Closure;

interface AfrRouterInterface
{
	public function __invoke(AfrRequestInterface $oRequest, Closure $oClosureAfterRoute = null):int;
}