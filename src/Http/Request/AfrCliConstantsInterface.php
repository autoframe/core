<?php

namespace Autoframe\Core\Http\Request;

interface AfrCliConstantsInterface {
	const CLI_INLINE ='CLI_INLINE';

	const CLI_QA_REQUEST = 'CLI_QA';
	const CLI_CRON_JOB_REQUEST = 'CRON_JOB';

	const ROUTES_CLI_FILENAME = 'CLI_ROUTES.php';
	const CLI_INLINE_FILENAME = self::CLI_INLINE.'.php';
	const CLI_QA_REQUEST_FILENAME = self::CLI_QA_REQUEST.'.php';
	const CLI_CRON_JOB_REQUEST_FILENAME = self::CLI_CRON_JOB_REQUEST.'.php';
	const QA_ARGV_KEY = 'QA';

	const CRON_DAEMON_ARGV_KEY = 'CRON_DAEMON';

	const CRON_WORKER_ARGV_KEY = 'CRON_WORKER';
	const CRON_LIVE_LOGS_ARGV_KEY = 'CRON_LIVE_LOGS';

}