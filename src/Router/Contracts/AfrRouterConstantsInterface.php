<?php

namespace Autoframe\Core\Router\Contracts;

use Autoframe\Core\Http\Request\AfrRequestClass;
use Autoframe\Core\Http\Request\AfrRequestInterface;

interface AfrRouterConstantsInterface {
	const MIDDLEWARE_ROUTE = 'Middleware';
	const CODE_ROUTE = 'Code';
	const AFTER_ROUTE = 'After';
	const HTTP_ROUTE_TYPES = [self::MIDDLEWARE_ROUTE, self::CODE_ROUTE, self::AFTER_ROUTE];

	const QA_ARGV_KEY = 'QA';
	const CRON_DAEMON_ARGV_KEY = 'CRON_DAEMON';

	const CRON_WORKER_ARGV_KEY = 'CRON_WORKER';
	const CRON_LIVE_LOGS_ARGV_KEY = 'CRON_LIVE_LOGS';



	const HTTP_REQUEST = 'HTTP';
	const REST_API_REQUEST = 'REST_API';
	const CLI_INLINE ='CLI_INLINE';

	const CLI_QA_REQUEST = 'CLI_QA';
	const CLI_CRON_JOB_REQUEST = 'CRON_JOB';
	const ALLOWED_METHODS_HTTP = AfrRequestInterface::AllowedHTTPRequestMethods;

	const GET ='GET';
	const POST ='POST';
	const HEAD ='HEAD';
	const PUT ='PUT';
	const DELETE ='DELETE';
	const OPTIONS ='OPTIONS';
	const PATCH ='PATCH';
}