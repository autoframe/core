<?php

use Autoframe\Core\Router\Contracts\AfrRouterConstantsInterface;

$d = __DIR__ . DIRECTORY_SEPARATOR . 'Routes' . DIRECTORY_SEPARATOR;
return [
	AfrRouterConstantsInterface::CLI_CRON_JOB_REQUEST => [],
	AfrRouterConstantsInterface::CLI_INLINE => [],
	AfrRouterConstantsInterface::CLI_QA_REQUEST => (include $d . 'CLI.QA.php'),
];