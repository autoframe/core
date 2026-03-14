<?php

namespace Autoframe\Core\Database\Orm\Db;

use Autoframe\Core\Database\Connection\Exception\AfrDatabaseConnectionException;
use Autoframe\Core\Database\Orm\Cnx\AfrOrmCnxTrait;
use Autoframe\Core\Database\Orm\Exception\AfrOrmException;

trait AfrOrmDbMutateTrait
{
    use AfrOrmDbTrait, AfrOrmCnxTrait;

    /**
     * Orm db create.
     * @param $sCollation
     * @return bool
     * @throws AfrDatabaseConnectionException
     * @throws AfrOrmException
     */
    public static function _ORM_Db_Create($sCollation): bool
    {
        //        $oManager = static::_ORM_Cnx_Manager();
        $sDriver = static::_ORM_Cnx_Driver();
        $oPdo = static::_ORM_Cnx_Pdo();
        $aAliasInfo = static::_ORM_Cnx_AliasInfo();
        if(empty($aAliasInfo)){
            throw new AfrOrmException(
                'Please define a method for database name inside class [' .
                static::class . '] as follows: public static function _ORM_Db_Name(): string; ' .
                'For Sqlite return empty string'
            );
        }

        if($sDriver === 'mysql'){
            return false;
            // CREATE DATABASE IF NOT EXISTS `!!!!!!!!` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
/*
SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `12345678` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

SET FOREIGN_KEY_CHECKS=1;
COMMIT;
 * */

        }
        elseif ($sDriver === 'sqlite'){
            return false;
        }
        elseif ($sDriver === 'postgre'){
            //https://www.postgresql.org/docs/current/sql-createdatabase.html
            return false;
        }
        else{
            throw new AfrOrmException(
                'Please define a method for database name inside class [' .
                static::class . '] as follows: public static function _ORM_Db_Name(): string; ' .
                'For Sqlite return empty string'
            );
        }

    }
}
