<?php

namespace Autoframe\Core\Database\Orm\Migrate;

use Autoframe\Core\Database\Connection\AfrDbConnectionManagerFacade;
use Autoframe\Core\Database\Connection\Exception\AfrDatabaseConnectionException;
use Autoframe\Core\Database\Orm\Action\ConvertFacade as ConvertSwitch;
use Autoframe\Core\Database\Orm\Action\CnxActionFacade;
use Autoframe\Core\Database\Orm\Blueprint\AfrOrmBlueprintInterface;

class MigrateFromDb implements AfrOrmBlueprintInterface
{
    /**
     * Migrate all dbs from alias.
     * @param string $sAlias
     * @return void
     * @throws AfrDatabaseConnectionException
     */
    public static function migrateAllDbsFromAlias(string $sAlias)
    {
        $oAfrDatabase = CnxActionFacade::withConnAlias($sAlias);
        //The response array should contain the keys: self::DB_NAME, self::CHARSET, self::COLLATION
        foreach ($oAfrDatabase->cnxGetAllDatabaseNamesWithCharset() as $dbProperties) {
            $sCharset = $dbProperties[self::CHARSET];
            $sCollation = $dbProperties[self::COLLATION];
            $sDbName = $dbProperties[self::DB_NAME];
            if (in_array($sDbName, ['information_schema', 'performance_schema'])) {
                continue;
            }
            ConvertSwitch::withConnAlias($sAlias);

        }

    }
}
