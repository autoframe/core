<?php

use Autoframe\Core\Afr\Afr;
use Autoframe\Core\CliTools\AfrCliTextColors;
use Autoframe\Core\Env\AfrEnv;
use Autoframe\Core\Router\AfrCliRouterHelper;
use Autoframe\Core\Tenant\AfrTenant;

$aActions = [];

$aActions['initTenantFileSystem'] = function () {
	return [
		'Init Tenant File System Directories' => function () {
			if (count($r = AfrTenant::initFileSystem()) > 0) {
				AfrCliTextColors::getInstance()
					->textAppend("\n\t")
					->colorRed("Errors:\n" . implode("\n", $r) . "\n")
					->textPrint();
				return false;
			}
			return true;
		},
	];
};

$aActions['clearCache'] = function () {

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
foreach ($aActions as $sOption => $mStack) {
	AfrCliRouterHelper::addActionGroup($sOption, $mStack, true);
}
return $aActions;
