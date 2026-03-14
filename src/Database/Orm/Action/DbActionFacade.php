<?php

namespace Autoframe\Core\Database\Orm\Action;

use Autoframe\Core\Database\Connection\AfrDbConnectionManagerFacade;
use Autoframe\Core\Database\Connection\Exception\AfrDatabaseConnectionException;

class DbActionFacade
{
	/**
	 * With conn alias and database.
	 * @param string $sConnAlias
	 * @param string $sDatabaseName
	 * @return DbActionInterface
	 * @throws AfrDatabaseConnectionException
	 */
	public static function withConnAliasAndDatabase(string $sConnAlias, string $sDatabaseName): DbActionInterface
	{
		/** @var DbActionInterface $sFQCN */
		$sFQCN = AfrDbConnectionManagerFacade::getInstance()->resolveFacadeUsingAlias(static::class, $sConnAlias);
		return $sFQCN::getInstanceWithConnAliasAndDatabase($sConnAlias, $sDatabaseName);
	}

	/**
	 * Using cnxi and database.
	 * @param CnxActionInterface $oCnx
	 * @param string $sDatabaseName
	 * @return DbActionInterface
	 * @throws AfrDatabaseConnectionException
	 */
	public static function usingCnxiAndDatabase(CnxActionInterface $oCnx, string $sDatabaseName): DbActionInterface
	{
		return self::withConnAliasAndDatabase($oCnx->getNameConnAlias(), $sDatabaseName);
	}


}
