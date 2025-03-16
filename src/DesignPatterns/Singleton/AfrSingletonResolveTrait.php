<?php

namespace Autoframe\Core\DesignPatterns\Singleton;

use Autoframe\Core\Container\AfrContainerFacade;
use Autoframe\Core\Container\Exception\AfrContainerException;

trait AfrSingletonResolveTrait {

	/**
	 * @param string $sClassFQCN
	 * @return \Closure|mixed|object|null
	 * @throws AfrContainerException
	 */
	protected static function getResolvedStatic(string $sClassFQCN)
	{
		$oResolved = AfrContainerFacade::getContainer()->get($sClassFQCN);
		if (is_object($oResolved)) {
			if ($oResolved instanceof \Closure) {
				return ($oResolved->bindTo(null,$sClassFQCN))($sClassFQCN);
			}
			return $oResolved;
		}
		return null;
	}
}