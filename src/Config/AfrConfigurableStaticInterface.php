<?php
declare(strict_types=1);

namespace Autoframe\Core\Config;

interface AfrConfigurableStaticInterface
{
    /**
     * Apply afr static config.
     * @param bool $bForce
     * @return int
     */
    public static function applyAfrStaticConfig(bool $bForce = false): int;
}
