<?php
declare(strict_types=1);

namespace Autoframe\Core\Http\Cookie\Manager;

interface AfrHttpCookieManagerInterface
{
    /**
     * Set cookie.
     * @param string $name
     * @param string $value
     * @param $iExpires_or_aOptions `0 = session; timestamp or arr [lifetime path domain secure httponly 'samesite' => 'Strict|Lax|None']
     * @param string $path
     * @param string $domain
     * @param bool $secure
     * @param bool $httponly
     * @param string $sameSite Lax Strict None or ''
     * @return bool
     */
    public function setCookie(string $name, string $value = '', $iExpires_or_aOptions = 0, string $path = '', string $domain = '', bool $secure = false, bool $httponly = false, string $sameSite = ''): bool;

    /**
     * Unset cookie.
     * @param string $name
     * @param string $path
     * @param string $domain
     * @param bool $secure
     * @param bool $httponly
     * @param string $samesite
     * @return bool
     */
    public function unsetCookie(string $name, string $path = '', string $domain = '', bool $secure = false, bool $httponly = false, string $samesite = ''): bool;
}
