<?php

namespace Autoframe\Core\Database\Orm\Ent;

use Autoframe\Core\Database\Orm\Tbl\AfrOrmTblInterface;

interface AfrOrmEntInterface extends AfrOrmTblInterface
{
    /** @var string Glue string for cache and array index keys */
    const GLUE = '~~';

    /**
     * Orm ent location.
     */
    public static function _ORM_Ent_Location(): array;
    /**
     * Orm ent location key.
     */
    public static function _ORM_Ent_LocationKey(): string;
    /**
     * Orm ent fqcn.
     */
    public static function _ORM_Ent_FQCN(): string;
    /**
     * Save.
     */
    public static function save(): bool;
    /**
     * Persist.
     */
    public static function persist(): bool;
    /**
     * Get.
     */
    public static function get(): bool;
    /**
     * Hydrate.
     */
    public static function hydrate(): bool;

}
