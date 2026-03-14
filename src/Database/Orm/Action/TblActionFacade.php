<?php

namespace Autoframe\Core\Database\Orm\Action;

use Autoframe\Core\Database\Connection\AfrDbConnectionManagerFacade;
use Autoframe\Core\Database\Connection\Exception\AfrDatabaseConnectionException;

class TblActionFacade
{
	/**
	 * With conn alias and database and table.
	 * @param string $sConnAlias
	 * @param string $sDatabaseName
	 * @param string $sTableName
	 * @return TblActionInterface
	 * @throws AfrDatabaseConnectionException
	 */
	public static function withConnAliasAndDatabaseAndTable(
		string $sConnAlias,
		string $sDatabaseName,
		string $sTableName
	): TblActionInterface
	{
		/** @var TblActionInterface $sFQCN */
		$sFQCN = AfrDbConnectionManagerFacade::getInstance()->resolveFacadeUsingAlias(static::class, $sConnAlias);
		return $sFQCN::getInstanceWithConnAliasAndDatabaseAndTable($sConnAlias, $sDatabaseName, $sTableName);
	}

}
