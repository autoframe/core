<?php

namespace Autoframe\Core\Database\Orm\Tbl;

use Autoframe\Core\Database\Orm\Blueprint\AfrOrmBlueprintInterface;
use Autoframe\Core\Database\Orm\Exception\AfrOrmException;

trait AfrOrmTblTrait
{
    /**
     * Orm tbl name.
     * @return string Table name
     * @throws AfrOrmException
     */
    public static function _ORM_Tbl_Name(): string
    {
        //todo load from blueprint or:
        if(isset(static::$aTBLBlueprint) && !empty(static::$aTBLBlueprint[AfrOrmBlueprintInterface::TBL_NAME])){
            //            AfrDbBlueprint::dbBlueprint();
            return static::$aTBLBlueprint[AfrOrmBlueprintInterface::TBL_NAME];
        }

        if (rand(1, 2)) { //prevent code sniffer unreachable statement
            throw new AfrOrmException(
                'Please define a method for table name inside class [' .
                static::class . '] as follows: public static function _ORM_Tbl_Name(): string'
            );
        }
        return '';
    }

}
