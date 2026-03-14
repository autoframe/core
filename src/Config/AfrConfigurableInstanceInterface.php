<?php
declare(strict_types=1);

namespace Autoframe\Core\Config;

interface AfrConfigurableInstanceInterface
{
    /**
     * Apply afr instance config.
     * @param bool $bForce
     * @return int
     */
    public function applyAfrInstanceConfig(bool $bForce = false): int;

    /**
     * Apply afr instance config static.
     * @param bool $bForce
     * @return int
     */
    public static function applyAfrInstanceConfigStatic(bool $bForce = false): int;

}
