<?php

namespace Autoframe\Core\Database\Orm\Ent;

trait AfrOrmEntTrait
{
    /**
     * Orm ent location.
     */
    public static function _ORM_Ent_Location(): array
    {
        return [
            static::_ORM_Cnx_Alias(),
            static::_ORM_Db_Name(),
            static::_ORM_Tbl_Name(),
        ];
    }

    /**
     * Orm ent location key.
     */
    public static function _ORM_Ent_LocationKey(): string
    {
        return implode(static::GLUE, static::_ORM_Ent_Location());
    }


    /**
     * Orm ent fqcn.
     */
    public static function _ORM_Ent_FQCN(): string
    {
        return static::class;
    }

    //todo load from blueprint or:
    //todo save, persist, interface, etc
}
