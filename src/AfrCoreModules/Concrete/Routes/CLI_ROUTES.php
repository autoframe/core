<?php

use Autoframe\Core\CliTools\AfrCliHttpDetect;
use Autoframe\Core\Cron\AfrCronJob;
use Autoframe\Core\Http\Request\AfrCliConstantsInterface;

use Autoframe\Core\Afr\Afr;
use Autoframe\Core\CliTools\AfrCliTextColors;
use Autoframe\Core\Cron\Log\Channel\AfrCronLogChannelSharedLogBuffer;
use Autoframe\Core\Env\AfrEnv;
use Autoframe\Core\Tenant\AfrTenant;


$aActions = [];
//debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
$aActions['initTenantFileSystem'] = function () {
	return [
		'Init Tenant File System Directories' => function () {
			if (count($r = AfrTenant::initFileSystem(false)) > 0) {
				AfrCliTextColors::getInstance()
					->textAppend("\n 🌟 \t Messages:\n ")
					->colorRedLight(implode("\n", $r) . "\n")
					->textPrint();
				return '';
			}
			return true;
		},
	];
};

$aActions['clearCache'] = function () {
//	debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS); die;
	$sEnvCacheFile = Afr::app() ? Afr::app()->env()->getCacheFileName() : AfrEnv::getInstance()->getCacheFileName();
	$sTxt = ' cache file AfrEnv->getCacheFileName: ' . basename($sEnvCacheFile);
	if (is_file($sEnvCacheFile)) {
		$k = 'Clear' . $sTxt;
		$v = function () use ($sEnvCacheFile) {
			return unlink($sEnvCacheFile);
		};
	} else {
		$k = AfrCliTextColors::getInstance()
			->styleItalic(true)
			->colorGrayDark('Not found' . $sTxt)
			->styleDefaultAllBgColor()
			->textGet();
		$v = false;
	}
	return [$k => $v,];
};

$aActions['cronJobs'] = function () {

	return [
		'View live logs' => function () {
			AfrCronLogChannelSharedLogBuffer::getInstance()->viewLogs(2, false);
			//return '';
			return true;
		},
	];
};

$aActions['Tenant'] = function () {
	return [
		'List all tenant aliases:' => function () {
			return 'Listing all tenant aliases:' . PHP_EOL .
				implode(PHP_EOL, array_keys(Afr::app()::getAllTenants()));
		}, 'List CLI variants:' => function () {
			$r = '';
			foreach (AfrCronJob::getReplaceMatrix() as $k => $v) $r.= PHP_EOL. $k."=\t".$v;
			//TODO: confirm and update the use cases
			return
				'php bootstrap.php examples... '.PHP_EOL.
				'php index.php '.AfrCliConstantsInterface::QA_ARGV_KEY.' ❰ Navigable menu ❱'.PHP_EOL.
				'php index.php '.AfrCliConstantsInterface::QA_ARGV_KEY.'=Tenant -T=www ❰ Opens "Tenant" menu on AfrCliQaRouter❱'.PHP_EOL.
				"php index.php ".AfrCliConstantsInterface::CLI_EXECUTE_ARGV_KEY.'="echo 22;"  -T=www'.' ❰ eval(...) ❱'.PHP_EOL.
				"php index.php ".AfrCliConstantsInterface::CLI_INVOKE_ARGV_KEY.'=Autoframe.Core.Tenant.AfrPrintTenantNameInCli'.
				' ❰ calls cliInvoke() method on Autoframe\Core\Tenant\AfrPrintTenantNameInCli class ❱'.PHP_EOL.
				"php index.php ".AfrCliConstantsInterface::CRON_LIVE_LOGS_ARGV_KEY.'=5'.' ❰ seconds.timeout of 5 seconds between reads ❱'.PHP_EOL.
				"php index.php ".AfrCliConstantsInterface::CRON_DAEMON_ARGV_KEY.' ❰ open cron daemon ❱'.PHP_EOL.
				"php index.php ".AfrCliConstantsInterface::CRON_WORKER_ARGV_KEY.'=base64...'.' ❰ call daemon worker ❱'.PHP_EOL.
				"\n\tCron job sources constants from AfrCronJob::getReplaceMatrix():".$r.PHP_EOL;

		},
	];
};


return [
	AfrCliConstantsInterface::CLI_QA_ROUTES_STACK => $aActions,
	//closure having associative keys
	AfrCliConstantsInterface::CLI_CLOSURE_ROUTES_STACK => [ 'afr'=>function ($rq=null) { return true;},],
//	AfrCliConstantsInterface::CLI_CRON_JOB_REQUEST => [],
];