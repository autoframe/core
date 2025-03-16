<?php

namespace Autoframe\Core\Router;

use Autoframe\Core\CliTools\AfrCliHttpDetect;
use Autoframe\Core\CliTools\AfrCliTextColors;
use Autoframe\Core\Env\AfrEnv;
use Autoframe\Core\Router\Contracts\AfrRouterCliInterface;
use Autoframe\Core\Tenant\AfrTenant;
use Autoframe\Core\Http\Request\AfrRequestInterface;
use Closure;

class CliCache implements AfrRouterCliInterface
{

	public function __invoke(AfrRequestInterface $oRequest = null, Closure $oClosureAfterRoute = null): int
	{
		return AfrCliRouterHelper::$iCliQaHandled;

	// todo remove ... mutat in src/Router/AfrCliRouterHelper.php
		if ($oRequest ? $oRequest->isCli() : AfrCliHttpDetect::isCli()) {
			$aMethods = array_diff(get_class_methods($this), ['__invoke', '__construct', 'getCollectedResultsFromRoutes']);
			$aOpt = $oRequest ? $oRequest->getOpt('', ['afrCli::']) : getopt('', ['afrCli::']);
			$sCliMethodToCall = $aOpt['afrCli'] ?? null;
			if ($sCliMethodToCall && in_array($sCliMethodToCall, $aMethods)) {
				$this->$sCliMethodToCall();
				//TODO: de adaugat else if @class@method
			} else {
				$aOptions = [];
				foreach ($this->getActions() as $sOption => $mStack) {
					$aOptions[$sOption] = function () use ($sOption, $mStack) {
						return AfrCliRouterHelper::handleCliQaStack($sOption, $mStack);
					};
				}
				AfrCliRouterHelper::handleCliQaStack(__CLASS__ . '@' . __FUNCTION__, $aOptions);
			}
		}
		return AfrCliRouterHelper::$iCliQaHandled;
	}


	public function getActions(): array
	{
		$aActions = [];

		$aActions['initTenantFileSystem'] = function () {
			return [
				'Init Tenant File System Directories' => function () {
					$r = AfrTenant::initFileSystem();
					if (count($r) > 0) {
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
			$sEnvCacheFile = AfrEnv::getInstance()->getCacheFileName();
			$sTxt = ' Cache for AfrEnv->readEnv: ' . basename($sEnvCacheFile);
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
			AfrCliRouterHelper::addActionGroup($sOption, $mStack,true);
		}
		return $aActions;
	}


	public function getCollectedResultsFromRoutes(): ?array
	{
		// TODO: Implement getCollectedResultsFromRoutes() method.
		return null;
	}
}