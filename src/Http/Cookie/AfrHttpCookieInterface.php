<?php

namespace Autoframe\Core\Http\Cookie;

use Autoframe\Core\Http\Cookie\Manager\AfrHttpCookieManagerClass;

interface AfrHttpCookieInterface
{
    /**
     * Get cookie manager.
     * @return AfrHttpCookieManagerClass
     */
    public function getCookieManager(): AfrHttpCookieManagerClass;

    /**
     * Set.
     * @return bool
     */
    public function set(): bool;

    /**
     * Set if missing.
     * @return bool
     */
    public function setIfMissing(): bool;

    /**
     * Unset.
     * @return bool
     */
    public function unset(): bool;
}
