<?php

namespace Autoframe\Core\Http\CurlGetBodyWithTimeout;

class AfrGetHttpBodyWithTimeout
{
	private const OVERHEAD_MS = 50;
	private const MAX_REDIRECTS = 3;

	/**
	 * Fetch the HTTP/HTTPS response body.
	 *
	 * @param string $sUrl The URL to fetch
	 * @param int $iMaxTimeoutMs Connect timeout in ms (read can exceed this)
	 * @param bool $bVerifySSL Verify SSL certs (default true). Set false for dev/test.
	 *
	 * @return string|false Response body or false on connection/transport failure
	 */
	public static function get(string $sUrl, int $iMaxTimeoutMs = 1000, bool $bVerifySSL = true)
	{
		if (!filter_var($sUrl, FILTER_VALIDATE_URL)) {
			return false;
		}
		$scheme = strtolower(parse_url($sUrl, PHP_URL_SCHEME) ?? '');
		if ($scheme !== 'http' && $scheme !== 'https') {
			return false;
		}

		$iMaxTimeoutMs = max(1, $iMaxTimeoutMs);

		if (function_exists('curl_init')) {
			return self::viaCurl($sUrl, $iMaxTimeoutMs, $bVerifySSL);
		}
		return self::viaStreams($sUrl, $iMaxTimeoutMs, self::MAX_REDIRECTS, $bVerifySSL);
	}

	/** ---------- cURL path ---------- */
	protected static function viaCurl(string $url, int $connectTimeoutMs, bool $verifySSL)
	{
		if (($ch = curl_init()) === false) {
			return false;
		}

		$headers = [
			'Accept: */*',
			'Accept-Encoding: identity',
	//		'Connection: close',
		];

		$opts = [
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => false,
			CURLOPT_NOBODY => false,
			CURLOPT_FOLLOWLOCATION => !ini_get('open_basedir') && !ini_get('safe_mode'),
			CURLOPT_MAXREDIRS => self::MAX_REDIRECTS,
			//Max time to establish the connection (DNS resolve + TCP connect + TLS handshake) in milliseconds.
			CURLOPT_CONNECTTIMEOUT_MS => $connectTimeoutMs + self::OVERHEAD_MS,
			//Same as CURLOPT_CONNECTTIMEOUT_MS but in seconds.
			CURLOPT_CONNECTTIMEOUT => max(1, (int)ceil(($connectTimeoutMs + self::OVERHEAD_MS) / 1000)),
			// Max total time for the entire request in milliseconds (connection + redirects + data transfer + callbacks)
			CURLOPT_TIMEOUT_MS => $connectTimeoutMs + self::OVERHEAD_MS*self::MAX_REDIRECTS,
			CURLOPT_ENCODING => '',
			CURLOPT_USERAGENT => self::userAgent(),
			CURLOPT_HTTPHEADER => $headers,
		];

		if (!$verifySSL) {
			$opts[CURLOPT_SSL_VERIFYPEER] = false;
			$opts[CURLOPT_SSL_VERIFYHOST] = 0;
		}

		if (!curl_setopt_array($ch, $opts)) {
			curl_close($ch);
			return false;
		}

		$body = curl_exec($ch);
		curl_close($ch);

		return $body;
	}

	/** ---------- Streams path ---------- */
	protected static function viaStreams(string $url, int $connectTimeoutMs, int $redirectsLeft, bool $verifySSL)
	{
		$headers = [
			'Accept: */*',
			'Accept-Encoding: identity',
		//	'Connection: close',
			'User-Agent: ' . self::userAgent(),
		];

		$sslContext = [
			'SNI_enabled' => true,
		];
		if ($verifySSL) {
			$sslContext['verify_peer'] = true;
			$sslContext['verify_peer_name'] = true;
		} else {
			$sslContext['verify_peer'] = false;
			$sslContext['verify_peer_name'] = false;
			$sslContext['allow_self_signed'] = true;
		}

		$context = stream_context_create([
			'http' => [
				'method' => 'GET',
				'protocol_version' => 1.1,
				'header' => implode("\r\n", $headers),
				'ignore_errors' => true,
				'timeout' => max(1, (int)ceil(($connectTimeoutMs + self::OVERHEAD_MS) / 1000)),
				'follow_location' => 0,
			],
			'ssl' => $sslContext,
		]);

		$fp = @fopen($url, 'r', false, $context);
		if ($fp === false) {
			$redirectUrl = self::extractHeaderValue(($http_response_header ?? []), 'Location');
			if ($redirectUrl && $redirectsLeft > 0) {
				return self::viaStreams($redirectUrl, $connectTimeoutMs, $redirectsLeft - 1, $verifySSL);
			}
			return false;
		}

		stream_set_timeout($fp, 0, 0); // no read timeout
		$body = stream_get_contents($fp);
		$meta = stream_get_meta_data($fp);
		fclose($fp);

		$wrapperHeaders = $meta['wrapper_data'] ?? ($http_response_header ?? []);
		$contentEncoding = self::extractHeaderValue($wrapperHeaders, 'Content-Encoding');
		if (is_string($body) && $contentEncoding) {
			$enc = strtolower($contentEncoding);
			if ($enc === 'gzip' || $enc === 'x-gzip') {
				$decoded = function_exists('gzdecode') ? @gzdecode($body) : $body;
				if ($decoded !== false) $body = $decoded;
			} elseif ($enc === 'deflate') {
				$decoded = function_exists('gzinflate') ? @gzinflate($body) : $body;
				if ($decoded !== false) $body = $decoded;
			}
		}

		return $body !== false ? $body : false;
	}

	protected static function userAgent(): string
	{
		return 'GetHttpBodyWithTimeout/1.2 (+https://autoframe.dev)';
	}

	protected static function extractHeaderValue(array $headers, string $name): ?string
	{
		$nameLower = strtolower($name);
		foreach ($headers as $h) {
			$p = explode(':', $h, 2);
			if (count($p) === 2 && strtolower(trim($p[0])) === $nameLower) {
				return trim($p[1]);
			}
		}
		return null;
	}
}
