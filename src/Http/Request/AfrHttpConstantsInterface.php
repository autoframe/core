<?php

namespace Autoframe\Core\Http\Request;

interface AfrHttpConstantsInterface {
	const AllowedHTTPRequestMethods = ['GET', 'POST', 'HEAD', 'PUT', 'DELETE', 'OPTIONS', 'PATCH'];

	const GET ='GET';
	const POST ='POST';
	const HEAD ='HEAD';
	const PUT ='PUT';
	const DELETE ='DELETE';
	const OPTIONS ='OPTIONS';
	const PATCH ='PATCH';

	const HTTP_REQUEST = 'HTTP';
	const REST_API_REQUEST = 'REST_API';


	const MIDDLEWARE_ROUTE = 'MIDDLEWARE';
	const CODE_ROUTE = 'ROUTE';
	const AFTER_ROUTE = 'AFTER_ROUTE';
	const HTTP_ROUTE_TYPES = [self::MIDDLEWARE_ROUTE, self::CODE_ROUTE, self::AFTER_ROUTE];

	const ROUTES_HTTP_FILENAME = 'HTTP_ROUTES.php';
	const ROUTES_MIDDLEWARE_FILENAME = 'HTTP_MIDDLEWARE_ROUTES.php';
	const ROUTES_CODE_FILENAME = 'HTTP_CODE_ROUTES.php';
	const ROUTES_AFTER_FILENAME = 'HTTP_AFTER_ROUTES.php';

	const SUB_ROUTING_PATH = 'SUB_ROUTING_PATH';//from functionality config to mount when registerHTTPRoutesFromModule
}