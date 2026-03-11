<?php

namespace Autoframe\Core\AfrCoreModules\Reusable;

use Autoframe\Core\Http\Request\AfrRequestInterface;
use Autoframe\Core\Router\AfrCliQaRouter;

trait AfrCliRoutesHelper {

	public function registerCliRoutes(AfrRequestInterface $oRequest = null, array $aFilterOnly = []): int
	{
		$aConfig = array_merge([
			self::CLI_INLINE => [],
			self::CLI_QA_REQUEST => [],
			self::CLI_CRON_JOB_REQUEST => [],
		], (array)$this->getCliRoutes()
		);
		$aCount = 0;
		foreach ($aConfig as $sType => $aRouteClusterInfo) {
			if (empty($aRouteClusterInfo) || !empty($aFilterOnly) && !in_array($sType, $aFilterOnly)) continue;
			if ($sType == self::CLI_QA_REQUEST) $aCount += $this->registerCliQa($aRouteClusterInfo);
			// src/AfrCoreModules/Concrete/AfrCronJobSource.php
			if ($sType == self::CLI_INLINE || $sType == self::CLI_CRON_JOB_REQUEST) die("TODO: IMPLEMENT $sType");
		}
		return $aCount;

	}

	protected function registerCliQa(array $aRouteClusterInfo, bool $bMergeQA = true): int
	{
		$iRegistered = 0;
		foreach ($aRouteClusterInfo as $sKeyCluster => $mStack) {
			// $mStack should be an array of closures OR Closure that returns array of closures
			$iRegistered += AfrCliQaRouter::addActionGroup($sKeyCluster, $mStack, $bMergeQA) ?? 0;
		}
		return $iRegistered;
	}


	public function getCliRoutes(): ?array
	{
		// define const SELF_DIR in the implementing class or replace with concrete method + __DIR__
		return AfrLoadConfigHelper::loadConfigFile(self::SELF_DIR, self::ROUTES_CLI_FILENAME, false);
	}
}