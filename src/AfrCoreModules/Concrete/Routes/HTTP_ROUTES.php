<?php

use Autoframe\Core\Afr\Afr;
use Autoframe\Core\Http\Request\AfrHttpConstantsInterface;

// registerHTTPRoutesFromModule($aMix, $sBasePathMount);
// function registerMiddlewareRoute(array $methods, string $pattern, $fn, array $routeOptions = [])
//debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);die;
return [
	AfrHttpConstantsInterface::MIDDLEWARE_ROUTE => [
		'frameworkSignature' => [['GET', 'POST'], '/.*', function () {
		//	die('Afr: V'.Afr::V);
			@header('Afr: V'.Afr::V);
		}, []],

		'testMiddleware' => [['GET', 'POST'], '/.*', function () {
			@header('Afr-Middleware: X'.Afr::V);
		}, ['OptionMXX']],
	],
	AfrHttpConstantsInterface::CODE_ROUTE => [
		'welcomeTest' => [['GET', 'POST'], '/.*', function () {
			echo 'WELCOME! args: '.print_r(func_get_args(), true).PHP_EOL.'<br>';
			echo(__FILE__ . ':' . __LINE__).PHP_EOL.'<br>';
		//	print_r(Afr::app()->router()->debugRoutes(false));
		}, ['Option1']],
	],
	AfrHttpConstantsInterface::AFTER_ROUTE => [],
];