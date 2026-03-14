<?php

namespace Autoframe\Core\Database\Orm\Action;

use Autoframe\Core\Database\Connection\AfrDbConnectionManagerFacade;
use Autoframe\Core\Database\Connection\Exception\AfrDatabaseConnectionException;

class CnxActionFacade
{
	/**
	 * With conn alias.
	 * @param string $sConnAlias
	 * @return CnxActionInterface
	 * @throws AfrDatabaseConnectionException
	 */
	public static function withConnAlias(string $sConnAlias): CnxActionInterface
	{
		/** @var CnxActionInterface $sFQCN */
		$sFQCN = AfrDbConnectionManagerFacade::getInstance()->resolveFacadeUsingAlias(static::class, $sConnAlias);
		return $sFQCN::getInstanceWithConnAlias($sConnAlias);
	}

}
