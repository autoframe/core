<?php

namespace Autoframe\Core\AfrCoreModules\Reusable;

use Autoframe\Core\Http\Request\AfrRequestInterface;
use Autoframe\Core\Router\AfrCliQaRouter;

trait AfrCliRoutesHelper
{

	/*
	 * 	public function registerCliRoutes(AfrRequestInterface $oRequest = null, array $aFilterOnly = []): int
		{
			$aConfig = array_merge([
				self::CLI_CLOSURE_ROUTES_STACK => [],
				self::CLI_QA_ROUTES_STACK => [],
				self::CLI_CRON_JOB_REQUEST => [],
			], (array)$this->getCliRoutes()
			);
			$iCount = 0;
			foreach ($aConfig as $sType => $aRouteClusterInfo) {
				if (empty($aRouteClusterInfo) || !empty($aFilterOnly) && !in_array($sType, $aFilterOnly)) continue;
				if ($sType == self::CLI_QA_ROUTES_STACK) $iCount += $this->registerCliQa($aRouteClusterInfo);
				// src/AfrCoreModules/Concrete/AfrCronJobSource.php
				if ($sType == self::CLI_CLOSURE_ROUTES_STACK || $sType == self::CLI_CRON_JOB_REQUEST) die("TODO: IMPLEMENT $sType");
			}
			return $iCount;

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

	*/
	/**
	 * Get cli routes.
	 */
	public function getCliRoutes(): ?array
	{
		// define const SELF_DIR in the implementing class or replace with concrete method + __DIR__
		$anCliRoutes = AfrLoadConfigHelper::loadConfigFile(self::SELF_DIR, self::ROUTES_CLI_FILENAME, false);
		return is_array($anCliRoutes) ? array_merge([
			self::CLI_CLOSURE_ROUTES_STACK => [],
			self::CLI_QA_ROUTES_STACK => [],
			self::CLI_CRON_JOB_REQUEST => [],
		], $anCliRoutes
		) : null;
	}
}
