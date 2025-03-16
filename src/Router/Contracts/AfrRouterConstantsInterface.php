<?php

namespace Autoframe\Core\Router\Contracts;

use Autoframe\Core\Http\Request\AfrRequestClass;
use Autoframe\Core\Http\Request\AfrRequestInterface;

interface AfrRouterConstantsInterface {
	const MIDDLEWARE_ROUTE = 'Middleware';
	const CODE_ROUTE = 'Code';
	const AFTER_ROUTE = 'After';
	const CONTROLLER_TYPES = [self::MIDDLEWARE_ROUTE, self::CODE_ROUTE, self::AFTER_ROUTE];

	const QA_ARGV_KEY = 'QA';

	const MVC_FOLDERS = [ //TODO: refactor in Autoframe\Core\Router\Module\thfModuleTools
		'Controller'=>'Controllers',
		'Model'=>'Models',
		'View'=>'Views',
		'Route'=>'Routes',
	];

	const HTTP_REQUEST = 'HTTP';
	const REST_API_REQUEST = 'REST_API';
	const CLI_INLINE ='CLI_INLINE';

	const CLI_QA_REQUEST = 'CLI_QA';
	const CLI_CRON_JOB_REQUEST = 'CRON_JOB';
	const ALL_HTTP_METHODS = 'GET|POST|PUT|DELETE|OPTIONS|PATCH|HEAD';
	//const ALLOWED_METHODS = ['GET','POST','PUT','DELETE','OPTIONS','PATCH','HEAD'];
	const ALLOWED_METHODS_HTTP = AfrRequestInterface::AllowedHTTPRequestMethods;

	const GET ='GET';
	const POST ='POST';
	const HEAD ='HEAD';
	const PUT ='PUT';
	const DELETE ='DELETE';
	const OPTIONS ='OPTIONS';
	const PATCH ='PATCH';
}