<?php

use Autoframe\Core\Router\Contracts\AfrRouterConstantsInterface;

return [
	// function registerMiddlewareRoute(array $methods, string $pattern, $fn, array $routeOptions = [])
	AfrRouterConstantsInterface::MIDDLEWARE_ROUTE => [
		'testMiddleware' => [['GET', 'POST'], '/.*', function () {
			die(__FILE__ . ':' . __LINE__);
		}, ['Option1']],
	],
	AfrRouterConstantsInterface::CODE_ROUTE => [],
	AfrRouterConstantsInterface::AFTER_ROUTE => [],
];