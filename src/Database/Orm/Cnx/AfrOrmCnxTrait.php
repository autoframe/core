<?php

namespace Autoframe\Core\Database\Orm\Cnx;


use Autoframe\Core\Database\Connection\AfrDbConnectionManagerFacade;
use Autoframe\Core\Database\Connection\AfrDbConnectionManagerInterface;
use Autoframe\Core\Database\Connection\Exception\AfrDatabaseConnectionException;
use Autoframe\Core\Database\Orm\Blueprint\AfrOrmBlueprintInterface;
use Autoframe\Core\Database\Orm\Exception\AfrOrmException;
use Autoframe\Core\Database\Orm\Blueprint\AfrDbBlueprint;
use PDO;

trait AfrOrmCnxTrait
{
    /**
     * Orm cnx alias.
     * @return string
     * @throws AfrOrmException
     */
    public static function _ORM_Cnx_Alias(): string
    {
        //todo load from blueprint or:
        if(isset(static::$aCNXBlueprint) && !empty(static::$aCNXBlueprint[AfrOrmBlueprintInterface::CON_ALIAS])){
            //            AfrDbBlueprint::dbBlueprint();
            return static::$aCNXBlueprint[AfrOrmBlueprintInterface::CON_ALIAS];
        }
        if(isset(static::$aDBBlueprint) && !empty(static::$aDBBlueprint[AfrOrmBlueprintInterface::CON_ALIAS])){
            //            AfrDbBlueprint::dbBlueprint();
            return static::$aDBBlueprint[AfrOrmBlueprintInterface::CON_ALIAS];
        }

        if (rand(1, 2)) { //prevent code sniffer unreachable statement hack :)
            throw new AfrOrmException(
                'Please define a method for connection alias inside class [' .
                static::class . '] as follows: public static function _ORM_Cnx_Alias(): string'
            );
        }
        return '';
    }

    /**
     * @return string get driver string from PDO DSN
     * Types: mysql, sqlite, pgsql, mssql, cubrid, sybase, dblib, firebird, ibm, informix, oci, odbc
     * @throws AfrDatabaseConnectionException
     */
    public static function _ORM_Cnx_Driver(): string
    {
        return static::_ORM_Cnx_Manager()->getDriverType(static::_ORM_Cnx_Alias());
    }

    /**
     * Orm cnx pdo.
     * @return PDO
     * @throws AfrDatabaseConnectionException
     */
    public static function _ORM_Cnx_Pdo(): PDO
    {
        return static::_ORM_Cnx_Manager()->getConnectionByAlias(static::_ORM_Cnx_Alias());
    }

    /**
     * @return array|null
     * @throws AfrOrmException
     * Array as follows: [
     * AfrDbConnectionManagerInterface::PDO_ARGS => $aPDOArgs,
     * AfrDbConnectionManagerInterface::INFO => $aInfo,
     * AfrDbConnectionManagerInterface::CONN_INDEX => $this->getConnectionIndex($aInfo),
     * AfrDbConnectionManagerInterface::CLOSURE => null|Closure,
     * AfrDbConnectionManagerInterface::FQCN_PDO => $sPdoClass,
     * ];
     */
    public static function _ORM_Cnx_AliasInfo(): ?array
    {
        return static::_ORM_Cnx_Manager()->getAliasInfo(static::_ORM_Cnx_Alias());
    }


    protected static function _ORM_Cnx_Manager(): AfrDbConnectionManagerInterface
    {
        return AfrDbConnectionManagerFacade::getInstance();
    }

}
