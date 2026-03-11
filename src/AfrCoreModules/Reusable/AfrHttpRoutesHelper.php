<?php

namespace Autoframe\Core\AfrCoreModules\Reusable;

use Autoframe\Core\Afr\Afr;
use Autoframe\Core\Event\AfrEvent;

trait AfrHttpRoutesHelper {
	/** @inheritDoc */
	public function registerHttpRoutes(): int
	{
		return Afr::app()->router()->registerHTTPRoutesFromModule(
			array_merge([
				self::MIDDLEWARE_ROUTE => [],
				self::CODE_ROUTE => [],
				self::AFTER_ROUTE => [],
			], (array)$this->getHttpRoutes()
			),
			$this->xetHTTPSubRoutingPath()
		);
	}


	public function getHttpRoutes(): ?array
	{
		// define const SELF_DIR in the implementing class or replace with concrete method + __DIR__
		return AfrLoadConfigHelper::loadConfigFile(self::SELF_DIR, self::ROUTES_HTTP_FILENAME, false);
	}

	/** @inheritDoc */

	public function __invoke(): int
	{
		return $this->registerHttpRoutes();
	}


	protected ?string $sSubRoutingPath = null;

	/** @inheritDoc */

	public function xetHTTPSubRoutingPath(string $sSubRoutingPath = null): string
	{
		if ($sSubRoutingPath !== null) {
			AfrEvent::dispatchEvent();
			if (strlen($sSubRoutingPath) > 0) {
				$sSubRoutingPath = '/' . trim($sSubRoutingPath, '/');
			}
			$this->sSubRoutingPath = $sSubRoutingPath;
		}
		if ($this->sSubRoutingPath === null) {
			$aJoinSubRouting = [];
			$sTenantSubRouting = trim((string)Afr::app()->env()->getEnv('SUB_ROUTING_PATH'), '/');
			$sFuncSubRouting = (Afr::app()->box()->getFunctionalityEffectiveConfig($this)[self::SUB_ROUTING_PATH] ?? '');
			if (strlen($sTenantSubRouting) > 0) $aJoinSubRouting[] = $sTenantSubRouting;
			if (strlen($sFuncSubRouting) > 0) $aJoinSubRouting[] = $sFuncSubRouting;
			if ($aJoinSubRouting) return '/' . implode('/', $aJoinSubRouting);
		}
		return (string)$this->sSubRoutingPath;
	}
}