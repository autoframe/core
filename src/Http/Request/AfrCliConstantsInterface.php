<?php

namespace Autoframe\Core\Http\Request;

interface AfrCliConstantsInterface {

	// Register via AfrFnCliRoutes implements AfrCliRoutesContract  ❱ registerCliRoutes
	// AfrCliRoutesContract->registerCliRoutes->registerCliQa->AfrCliQaRouter::addActionGroup
	const CLI_QA_ROUTES_STACK = 'CLI_QA_STACK';
	const CLI_CRON_JOB_REQUEST = 'CRON_JOB_SOURCES';
	const CLI_CLOSURE_ROUTES_STACK ='CLI_CLOSURE_STACK';
	const ROUTES_CLI_FILENAME = 'CLI_ROUTES.php';


	// AfrCliConstantsInterface::CLI_QA_ROUTES_STACK => $aActions stacked in AfrCliQaRouter
	const CLI_QA_ROUTES_STACK_FILENAME = self::CLI_QA_ROUTES_STACK.'.php';

	//	AfrCliConstantsInterface::CLI_CLOSURE_ROUTES_STACK => [ 'afr'=>function ($rq=null) { return true;},],
	const CLI_CRON_JOB_REQUEST_FILENAME = self::CLI_CRON_JOB_REQUEST.'.php';
	const CLI_CLOSURE_ROUTES_STACK_FILENAME = self::CLI_CLOSURE_ROUTES_STACK.'.php';
	const QA_ARGV_KEY = 'QA'; // php index.php QA ❰ Opens menu on AfrCliQaRouter and reads from addActionGroup()❱

	const CRON_DAEMON_ARGV_KEY = 'CRON_DAEMON';

	const CRON_WORKER_ARGV_KEY = 'CRON_WORKER';
	const CRON_LIVE_LOGS_ARGV_KEY = 'CRON_LIVE_LOGS';

	//php index.php CLI_INVOKE=Autoframe.Core.Tenant.AfrPrintTenantNameInCli    FQCN escaped \\ by ./~
	// ❰ calls cliInvoke() method on Autoframe\Core\Tenant\AfrPrintTenantNameInCli class ❱
	const CLI_INVOKE_ARGV_KEY ='CLI_INVOKE'; // call cliInvoke() on FQCN escaped \\ by ./~
	const CLI_EXECUTE_ARGV_KEY ='CLI_EXECUTE'; // eval(value...)
	const CLI_CLOSURE_ROUTE_ARGV_KEY ='CLI_CLOSURE'; //from functionality cli config file / closure


}