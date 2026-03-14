<?php

namespace Autoframe\Core\Http\Ip;

use Autoframe\Core\Afr\Afr;
use Autoframe\Core\CliTools\AfrCliHttpDetect;
use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;
use Autoframe\Core\Http\Request\AfrRequestClass;

class AfrIp extends AfrSingletonAbstractClass
{
	const UNKNOWN_IP = 'UNKNOWN_IP';
	protected array $aRealIpAddr;
	protected array $aTrustedProxies; // AFR_TRUSTED_PROXIES_IPS_LIST = 127.0.0.1,192.168.0.1, CF-IP
	protected array $aTrustedIpHeadersBehindLbOrProxy; // AFR_TRUSTED_PROXIES_IP_HEADER_LIST = HTTP_X_FORWARDED_FOR,HTTP_X_FORWARDED,HTTP_FORWARDED_FOR,HTTP_FORWARDED,HTTP_CF_CONNECTING_IP,HTTP_TRUE_CLIENT_IP,HTTP_CLIENT_IP

	/**
	 * Get trusted ip headers behind lb or proxy.
	 */
	public function getTrustedIpHeadersBehindLbOrProxy(): array
	{
		if (!isset($this->aTrustedIpHeadersBehindLbOrProxy)) {
			$this->aTrustedIpHeadersBehindLbOrProxy = $this->envListToArray(
			//'HTTP_X_FORWARDED_FOR,HTTP_X_FORWARDED,HTTP_FORWARDED_FOR,HTTP_FORWARDED,HTTP_CF_CONNECTING_IP,HTTP_TRUE_CLIENT_IP,HTTP_CLIENT_IP'
				Afr::app()->env()->getEnv(
					'AFR_TRUSTED_PROXIES_IP_HEADER_LIST',
					'HTTP_CF_CONNECTING_IP'
				)
			);
		}
		return $this->aTrustedIpHeadersBehindLbOrProxy;
	}


	/**
	 * Get trusted proxies ips.
	 */
	public function getTrustedProxiesIps(): array
	{
		if (!isset($this->aTrustedProxies)) {
			$this->aTrustedProxies = $this->envListToArray(
				Afr::app()->env()->getEnv('AFR_TRUSTED_PROXIES_IPS_LIST', '')
			);
		}
		return $this->aTrustedProxies;
	}


	/**
	 * Get real client ip addr.
	 * @param AfrRequestClass|null $rq
	 * @return string
	 */
	public function getRealClientIpAddr(AfrRequestClass $rq = null): string //TODO: add request support
	{
		$sCacheKey = $rq ? '#' . spl_object_id($rq) : 'SERVER';
		if ($sRealIpAddr = $this->xetRealIpAddrCache($sCacheKey)) {
			return $sRealIpAddr;
		}
		if (AfrCliHttpDetect::isCli($rq)) {
			return $this->xetRealIpAddrCache($sCacheKey, 'CLI@' . php_uname('n'));
		}
		$aSv = $rq ? $rq->getAllServerParams() : $_SERVER;
		if (AfrCliHttpDetect::isBehindLoadBalancerOrReverseProxy()) {
			foreach (
				$this->getTrustedIpHeadersBehindLbOrProxy() //TODO: test
				/*[
				         'HTTP_X_FORWARDED_FOR',
				         'HTTP_X_FORWARDED',
				         'HTTP_FORWARDED_FOR',
				         'HTTP_FORWARDED',
				         'HTTP_CF_CONNECTING_IP',
				         'HTTP_TRUE_CLIENT_IP',
				         'HTTP_CLIENT_IP',
			         ]*/
				as $header) {
				if (!empty($aSv[$header]) && $this->isIpV4OrIpV6($aSv[$header])) {
					return $this->xetRealIpAddrCache($sCacheKey, $aSv[$header]);
				}
			}
		}

		if ($this->isIpV4OrIpV6($aSv['REMOTE_ADDR'] ?? '')) {
			return $this->xetRealIpAddrCache($sCacheKey, $aSv['REMOTE_ADDR']);
		}
		return $this->xetRealIpAddrCache($sCacheKey, self::UNKNOWN_IP);
	}

	private function xetRealIpAddrCache(string $key, string $ip = null): ?string
	{
		if ($ip) {
			return $this->aRealIpAddr[$key] = $ip;
		}
		return $this->aRealIpAddr[$key] ?? null;
	}


	/**
	 * Is ip v4 or ip v6.
	 */
	public function isIpV4OrIpV6(string $ip): bool { return (bool)filter_var($ip, FILTER_VALIDATE_IP); }

	/**
	 * Is ip v4.
	 */
	public function isIpV4(string $ip): bool { return (bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4); }

	/**
	 * Is ip v6.
	 */
	public function isIpV6(string $ip): bool { return (bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6); }

//0000:0000:0000:0000:0000:0000:192.168.111.111 ipv6+tunnel 46 chars
//2001:0db8:85a3:0000:0000:8a2e:0370:7334 ipv6 40 chars

// Uncompress an IPv6 address --- @param ip adresse IP IPv6 x d'compresser ---  @return ip adresse IP IPv6 d'compress'
//XXXX:XXXX:XXXX:XXXX:XXXX:XXXX:AAA.BBB.CCC.DDD //45 chrs
//0123:4567:89ab:cdef:0123:4567:89ab:cdef //39 chrs

	/**
	 * Uncompressed ip v6.
	 * @param string $ip
	 * @return string
	 */
	public function uncompressedIpV6(string $ip): string
	{
		if ($this->isIpV6($ip) && strpos($ip, "::") !== false) {
			$aParts = array_filter(explode(':', $ip));
			$iNewSize = 8 - count($aParts);
			$newIp = [];
			foreach ($aParts as $val) {
				if ($val === '') {
					$newIp = array_merge($newIp, array_fill(0, $iNewSize + 1, '0'));
				} else {
					$newIp[] = $val;
				}
			}
			$ip = implode(':', $newIp);
		}
		return $ip;
	}

	/**
	 * Expand ip v6.
	 * @param string $ip Eg. fe80:01::af0
	 * @return string  Eg. fe80:0001:0000:0000:0000:0000:0000:0af0
	 */
	public function expandIpV6(string $ip): string
	{
		if (!$this->isIpV6($ip)) {
			return $ip;
		}
		if (($expanded = inet_pton($ip)) === false) {
			return $ip;
		}
		return implode(':', str_split(bin2hex($expanded), 4));
	}


	/**
	 * Compresses the given IPv6 address by removing consecutive blocks of zeros.
	 * @param string $ip The IPv6 address to compress.
	 * @return string The compressed IPv6 address with consecutive blocks of zeros removed.
	 */
	public function compressIpV6(string $ip): string
	{
		return $this->isIpV6($ip) ? inet_ntop(inet_pton($ip)) : $ip;

	}

	protected function envListToArray(string $sList): array
	{
		return $sList ? array_filter(array_map('trim', explode(',', $sList)), 'strlen') : [];
	}

}
