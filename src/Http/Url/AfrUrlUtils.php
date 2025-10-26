<?php

namespace Autoframe\Core\Http\Url;

use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;

class AfrUrlUtils extends AfrSingletonAbstractClass
{

	/**
	 * @param string $user
	 * @param string $pass
	 * @param string $sUrl
	 * @return false|string
	 */
	public function getNtlmLinkContents(string $user, string $pass, string $sUrl) //TODO: test on ares
	{
		return file_get_contents(...array_merge(
			[
				$this->addNtlmCredentialsToUrl($user, $pass, $sUrl),
				false
			],
			array_slice(func_get_args(), 2) //overload with $context, $offset and $length
		));
	}

	public function addNtlmCredentialsToUrl(string $user, string $pass, string $sUrl): string
	{
		$aParts = explode('://', $sUrl);
		$aParts[0] .= urlencode($user) . ':' . urlencode($pass) . '@';
		return implode('://', $aParts);
	}
	/**
	 * @param string $sUrl
	 * @return string https://hostname.com or https://username:password@hostname:9090
	 */
	public function getUrlSchemeHostUpToPath(string $sUrl): string
	{
		return implode(
			'/',
			array_slice(
				explode('/', $sUrl, 5),
				0,
				4
			)
		);
	}

	/**
	 * @param string $sUrl
	 * @return bool
	 */
	public function isUrlProtocolHttps(string $sUrl): bool
	{
		return strtolower(substr($sUrl, 0, 6)) === 'https:';
	}

	public function isUrlProtocolHttp(string $sUrl): bool
	{
		return strtolower(substr($sUrl, 0, 5)) === 'http:';
	}

	public function isUrlProtocolFtp(string $sUrl): bool
	{
		return strtolower(substr($sUrl, 0, 4)) === 'ftp:';
	}




}