<?php

namespace Autoframe\Core\Database\Orm\Db;

use Autoframe\Core\Database\Orm\Cnx\AfrOrmCnxInterface;

interface AfrOrmDbInterface extends AfrOrmCnxInterface
{
    /** @return string Database name. For Sqlite return empty string '' */
    /**
     * Orm db name.
     */
    public static function _ORM_Db_Name(): string;
}
