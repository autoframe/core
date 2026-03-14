<?php

namespace Autoframe\Core\Database\Orm\Action;

use Autoframe\Core\Database\Connection\AfrDbConnectionManagerFacade;
use Autoframe\Core\Database\Connection\Exception\AfrDatabaseConnectionException;
use Autoframe\Core\Database\Orm\Blueprint\AfrOrmBlueprintInterface;
use Autoframe\Core\Database\Orm\Exception\AfrOrmException;

class ConvertFacade implements AfrOrmBlueprintInterface
{
	/**
	 * With conn alias.
	 * @param string $sAlias
	 * @return ConvertInterface
	 * @throws AfrDatabaseConnectionException|AfrOrmException
	 */
	public static function withConnAlias(string $sAlias): ConvertInterface
	{
		/** @var ConvertInterface $sFQCN */
		$sFQCN = AfrDbConnectionManagerFacade::getInstance()->resolveFacadeUsingAlias(static::class, $sAlias);
		return $sFQCN::getInstanceWithConnAlias($sAlias);
	}
}
