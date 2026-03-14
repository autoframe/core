<?php
declare(strict_types=1);

namespace Autoframe\Core\Http\Cookie\Manager;

use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\Event\Exception\AfrEventException;
use Autoframe\Core\Http\Cookie\AfrHttpCookie;
use Autoframe\Core\Http\Cookie\AfrHttpCookieInterface;
use Autoframe\Core\Http\Cookie\Exception\AfrHttpCookieException;
use Autoframe\Core\Http\Request\AfrRequestClass;

trait AfrHttpCookieManagerTrait
{
    public bool $bAutoDomainDotNotationForAllSubdomains = false;
    public bool $bAlwaysSetToMasterDomainRatherThanSubdomain = true;
    protected static array $aIndex = [];
    protected string $sDomainAutodetect;

    /**
     * Set cookie.
     * @param string $name
     * @param string $value
     * @param $iExpires_or_aOptions `0 = session; timestamp or arr [lifetime path domain secure httponly 'samesite' => 'Strict|Lax|None']
     * @param string $path
     * @param string $domain
     * @param bool $secure
     * @param bool $httponly
     * @param string $sameSite lax strict or ''
     * @return bool
     * @throws AfrHttpCookieException
     */
    public function setCookie(
        string $name,
        string $value = '',
               $iExpires_or_aOptions = 0,
        string $path = '',
        string $domain = '',
        bool   $secure = false,
        bool   $httponly = false,
        string $sameSite = ''
    ): bool
    {
        $cname = $this->fixCookieName($name);
        if (strlen($cname) < 1) {
            throw new AfrHttpCookieException('Invalid cookie name: "' . $name . '"');
        }
        $name = $cname;
        $aOptions = [
            'expires' => 0,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => $sameSite
        ];
        if (is_array($iExpires_or_aOptions)) {
            foreach ($iExpires_or_aOptions as $k => $v) {
                if (isset($aOptions[$k])) {
                    $aOptions[$k] = $v;
                }
            }
            //$aOptions = array_merge($aOptions, $iExpires_or_aOptions);
        }
        $aOptions['expires'] = $this->fixLifetime($iExpires_or_aOptions);
        $aOptions['path'] = (string)$aOptions['path'];
        $this->fixSubdomainAvailability($aOptions['domain']);
        $aOptions['secure'] = (bool)$aOptions['secure'];
        $aOptions['httponly'] = (bool)$aOptions['httponly'];
        $this->correctSameSite($aOptions['samesite'], $aOptions['secure']);

        if (PHP_VERSION_ID >= 70300) {
            foreach ($aOptions as $k => $v) {
                if (empty($v)) {
                    unset($aOptions[$k]);
                }
            }
            $bSetCookie = setcookie(
                $name,
                $value,
                $aOptions
            );
        } else {
            $bSetCookie = setcookie(
                $name,
                $value,
                $aOptions['expires'],
                $path . ($aOptions['samesite'] ? '; samesite=' . $aOptions['samesite'] : ''),
                $aOptions['domain'],
                $aOptions['secure'],
                $aOptions['httponly']
            );
        }

        if ($bSetCookie) {
            if (strlen($value)) {
                $_COOKIE[$name] = $value;
            } elseif (isset($_COOKIE[$name])) {
                unset($_COOKIE[$name]);
            }
        }
        return $bSetCookie;
    }


    /**
     * Unset cookie.
     * @param string $name
     * @param string $path
     * @param string $domain
     * @param bool $secure
     * @param bool $httponly
     * @param string $samesite
     * @return bool
     * @throws AfrHttpCookieException
     */
    public function unsetCookie(string $name,
                                string $path = '',
                                string $domain = '',
                                bool   $secure = false,
                                bool   $httponly = false,
                                string $samesite = ''
    ): bool
    {
        $this->unsetIndex($name);
        return $this->setCookie($name, '', 1, $path, $domain, $secure, $httponly, $samesite);
    }


    /**
     * Fix cookie name.
     * @param string $sName
     * @return string
     */
    public function fixCookieName(string $sName): string
    {
        return str_replace(['=', ',', ';', ' ', "\t", "\r", "\n", "\v", "\f"], '', $sName);
    }



    /**
     * Force expire all cookies.
     * @param array $aNames
     * @param $asPaths ['/','/myaccount/']
     * @param $asDomains ['.example.com']
     * @param string $sSameSite Strict|Lax|None|''
     * @param int $iMaxLimit
     * @return int
     * @throws AfrHttpCookieException
     */
    public function forceExpireAllCookies(
        array  $aNames = [],
               $asPaths = null,
               $asDomains = null,
        string $sSameSite = '',
        int    $iMaxLimit = 20
    ): int
    {
        if (empty($aNames) && !empty($_COOKIE)) {
            $aNames = array_keys($_COOKIE);
        }
        shuffle($aNames);
        $i = 0;
        foreach ($aNames as $sCookieName) {
            if ($i >= $iMaxLimit) {
                return $i;
            }
            $i += $this->forceExpireCookie($sCookieName, $asPaths, $asDomains, $sSameSite, $iMaxLimit);
        }
        return $i;
    }


	/**
     * Force expire cookie.
	 * @param string $sName
	 * @param null $asPaths ['/','/myaccount/']
	 * @param null $asDomains ['.example.com']
	 * @param string $sSameSite Strict|Lax|None|''
	 * @param int $iMaxLimit
	 * @return int
	 * @throws AfrHttpCookieException
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 */
    public function forceExpireCookie(
        string $sName,
               $asPaths = null,
               $asDomains = null,
        string $sSameSite = '',
        int    $iMaxLimit = 20
    ): int
    {
        $bSecure = AfrRequestClass::getInstance()->isHttpsRequest();
        $this->correctSameSite($sSameSite, $bSecure);

        if (is_string($asPaths) && $asPaths) {
            $asPaths = [$asPaths];
        }
        if (empty($asPaths)) {
            $asPaths = $this->getPathVariations();
        }

        if (is_string($asDomains)) {
            $asDomains = [$asDomains];
        }
        if (empty($asDomains)) {
            $asDomains = $this->getDomainVariations();
        }
        $i = 0;
        foreach ($asDomains as $sDomain) {
            foreach ($asPaths as $sPath) {
                $this->unsetCookie(
                    $sName,
                    $sPath,
                    $sDomain,
                    $bSecure,
                    false,
                    $sSameSite
                );
                $i++;
                if ($i >= $iMaxLimit) {
                    return $i;
                }
            }
        }
        return $i;
    }

    /**
     * @return string[]
     */
    protected function getPathVariations(): array
    {
        $sPath = !empty($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';

        $aPathParts = ['/'];
        $sPath = explode('?', $sPath)[0];
        if (substr($sPath, -1, 1) === '/') {
            $sPath .= 'virtualFileToThreatCookieDirsCorrectly.txt';
        }
        $aPathInfo = pathinfo($sPath);
        if (!empty($aPathInfo['dirname'])) {
            $sPath = trim($aPathInfo['dirname'], '\/');
        } else {
            $sPath = '';
        }
        $sWalkThrough = '/';
        foreach (explode('/', $sPath) as $sPathPart) {
            if ($sPathPart) {
                $sWalkThrough .= $sPathPart . '/';
                $aPathParts[] = $sWalkThrough;
            }
        }
        return $aPathParts;
    }

    /**
     * @return string[]
     */
    protected function getDomainVariations(): array
    {
        $sFullHostname = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        if (!$sFullHostname || filter_var(explode(':', $sFullHostname)[0], FILTER_VALIDATE_IP)) {
            return [''];
        }
        $aHostNames = [];
        $aFullHostname = explode('.', trim($sFullHostname, '.'));
        $iCountHostParts = count($aFullHostname);
        if ($iCountHostParts > 1) {
            $sWalkThrough = '.' . $aFullHostname[$iCountHostParts - 1];
            for ($i = $iCountHostParts - 2; $i >= 0; $i--) {
                $sWalkThrough = $aFullHostname[$i] . $sWalkThrough;
                if (!$this->bAutoDomainDotNotationForAllSubdomains) {
                    $aHostNames[] = $sWalkThrough;
                }
                $sWalkThrough = '.' . $sWalkThrough;
                if ($this->bAutoDomainDotNotationForAllSubdomains) {
                    $aHostNames[] = $sWalkThrough;
                }
            }
        }
        $aHostNames[] = $sFullHostname;
        $aHostNames = array_unique($aHostNames);
        krsort($aHostNames);
        return $aHostNames;
    }

    /**
     * Domain name autodetect.
     * @return string
     */
    public function domainNameAutodetect(): string
    {
        if (!isset($this->sDomainAutodetect)) {
            //$sHostName = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
            $sHostName = !empty($_SERVER['HTTP_HOST']) ? explode(':', $_SERVER['HTTP_HOST'])[0] : '';
            if ($sHostName && filter_var($sHostName, FILTER_VALIDATE_IP)) {//ip hostname
                $this->sDomainAutodetect = '';
            } elseif ($sHostName) {//string hostname
                $aHostName = explode('.', $sHostName);
                if (count($aHostName) > 1) {
                    $aHostName = array_slice($aHostName, -2, 2); //remove all but last 2 segments
                }
                $sHostName = implode('.', $aHostName);
                if (count($aHostName) > 1 && $this->bAutoDomainDotNotationForAllSubdomains) {
                    $sHostName = '.' . $sHostName;
                }
                $this->sDomainAutodetect = $sHostName;
            } else {
                $this->sDomainAutodetect = ''; //fallback to blank and let the browser decide
            }
        }
        return $this->sDomainAutodetect;
    }

    /**
     * Get same site options.
     * @return string[]
     */
    public function getSameSiteOptions(): array
    {
        return ['Lax', 'Strict', 'None', ''];
    }

    /**
     * Set index.
     * @param AfrHttpCookieInterface $oCookie
     * @return void
     */
    public function setIndex(AfrHttpCookieInterface $oCookie): void
    {
        self::$aIndex[$oCookie->sName] = $oCookie;
    }

    /**
     * Get all indexes.
     * @return AfrHttpCookieInterface[]
     */
    public function getAllIndexes(): array
    {
        return self::$aIndex;
    }

    /**
     * Unset index.
     * @param string $sCookieName
     * @return void
     */
    public function unsetIndex(string $sCookieName): void
    {
        if ($this->issetIndex($sCookieName)) {
            unset(self::$aIndex[$sCookieName]);
        }
    }

    /**
     * Isset index.
     * @param string $sCookieName
     * @return bool
     */
    public function issetIndex(string $sCookieName): bool
    {
        return isset(self::$aIndex[$sCookieName]);
    }

    /**
     * Assume all http cookies.
     * @return AfrHttpCookieInterface[]
     * @throws AfrHttpCookieException
     */
    public function assumeAllHttpCookies(): array
    {
        if (!empty($_COOKIE)) {
            foreach ($_COOKIE as $sCookieName => $sVal) {
                $this->assumeHttpCookie($sCookieName);
            }
        }
        return $this->getAllIndexes();
    }

	/**
     * Assume http cookie.
	 * @param string $sCookieName
	 * @return false|AfrHttpCookieInterface
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 * @throws AfrHttpCookieException
	 */
    public function assumeHttpCookie(string $sCookieName)
    {
        if (isset($_COOKIE[$sCookieName]) && $_COOKIE[$sCookieName]) {
            if (!$this->issetIndex($sCookieName)) {
                $oCookie = new AfrHttpCookie(
                    $sCookieName,
                    $_COOKIE[$sCookieName],
                    $this->assumeLifetime,
                    $this->assumePath,
                    $this->assumeDomain,
                    $this->assumeSameSite,
                    $this->assumeHttpOnly,
                    $this->assumeSecure,
                );
                $oCookie->bAssumed = true;
                self::$aIndex[$sCookieName] = $oCookie;
            }
            return self::$aIndex[$sCookieName];
        }
        return false;
    }

    /**
     * SameSite :  'Lax', 'Strict', 'None', ''
     * @var string
     */
    public string $assumeSameSite = '';

    /**
     * '.domain.com' for all subdomains
     * 'www.domain.com' for only one subdomain
     * '.' for all auto generated subdomains
     * '' for the current domain
     * @var string
     */
    public string $assumeDomain = '.';

    /**
     * Path '/' for all domain or subdomain
     * @var string
     */
    public string $assumePath = '/';

    /**
     * If true, the cookie is not accessible from js
     * @var bool
     */
    public bool $assumeHttpOnly = false;

    /**
     * HTTPS secure only
     * @var bool
     */
    public bool $assumeSecure = true;

    /**
     * For browser session cookie, use 0
     * Else time() + seconds into the future
     * @var int
     */
    public int $assumeLifetime = 0;

    /**
     * @param $iExpires_or_aOptions
     * @return int
     */
    protected function fixLifetime($iExpires_or_aOptions): int
    {
        $lifetime = 0;
        if (isset($iExpires_or_aOptions['expires'])) {
            $lifetime = (int)$iExpires_or_aOptions['expires'];
        } elseif (is_numeric($iExpires_or_aOptions)) {
            $lifetime = (int)$iExpires_or_aOptions;
        }
        return $lifetime < 0 ? 2 : $lifetime;
    }



    /**
     * @param $sDomain
     * @return void
     */
    protected function fixSubdomainAvailability(&$sDomain): void
    {
        if (!is_string($sDomain)) {
            $sDomain = (string)$sDomain;
        }
        $sDomain = explode(':', $sDomain)[0]; //get rig of port
        $bIsIp = filter_var($sDomain, FILTER_VALIDATE_IP);
        if ($this->bAlwaysSetToMasterDomainRatherThanSubdomain && $sDomain && !$bIsIp) {
            $aFullHostname = explode('.', trim($sDomain, '.'));
            $iCountHostParts = count($aFullHostname);
            if ($iCountHostParts > 1) {
                $sDomain = $aFullHostname[$iCountHostParts - 2] . '.' . $aFullHostname[$iCountHostParts - 1];
            }
        }
        if ($this->bAutoDomainDotNotationForAllSubdomains) {
            if (
                $sDomain &&
                substr($sDomain, 0, 1) !== '.' &&
                substr_count($sDomain, '.') > 1 &&
                !$bIsIp
            ) {
                $sDomain = '.' . $sDomain;
            }
        }
    }

    /**
     * Input parameter is forced to Lax Strict None or ''
     * @param $sSameSite
     * @param bool $bSecure
     * @return void
     */
    protected function correctSameSite(&$sSameSite, bool $bSecure): void
    {
        if (!$bSecure) {
            $sSameSite = '';
        } else {
            $sSameSite = ucwords(strtolower((string)$sSameSite));
            if (!in_array($sSameSite, $this->getSameSiteOptions())) {
                $sSameSite = '';
            }
        }
    }
}
