<?php

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
			AfrCronLogChannelSharedLogBuffer::getInstance()->viewLogs(2,false);
			//return '';
			return true;
		},
	];
};


return [
	AfrCliConstantsInterface::CLI_QA_REQUEST => $aActions,
//	AfrCliConstantsInterface::CLI_INLINE => [],
//	AfrCliConstantsInterface::CLI_CRON_JOB_REQUEST => [],
];