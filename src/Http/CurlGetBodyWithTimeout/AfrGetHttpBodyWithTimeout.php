<?php

namespace Autoframe\Core\Http\CurlGetBodyWithTimeout;

class AfrGetHttpBodyWithTimeout
{
	public const OVERHEAD_MS = 50;
	public const MAX_REDIRECTS = 3;

	/** Tracks the last HTTP status code seen (final after redirects when available) */
	protected static ?int $inLastHttpStatus = null;

	/**
	 * Return the last HTTP status code captured by viaCurl/viaStreams.
	 * Returns null if no request has been made yet or the status couldn't be determined.
	 */
	public static function getLastHttpStatus(): ?int
	{
		return self::$inLastHttpStatus;
	}

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
		// reset status for this new request
		self::$inLastHttpStatus = null;

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
			// 'Connection: close',
		];

		$opts = [
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => false,
			CURLOPT_NOBODY => false,
			CURLOPT_FOLLOWLOCATION => !ini_get('open_basedir') && !ini_get('safe_mode'),
			CURLOPT_MAXREDIRS => self::MAX_REDIRECTS,
			// Max time to establish the connection (DNS resolve + TCP connect + TLS handshake) in milliseconds.
			CURLOPT_CONNECTTIMEOUT_MS => $connectTimeoutMs + self::OVERHEAD_MS,
			// Same as CURLOPT_CONNECTTIMEOUT_MS but in seconds.
			CURLOPT_CONNECTTIMEOUT => max(1, (int)ceil(($connectTimeoutMs + self::OVERHEAD_MS) / 1000)),
			// Max total time for the entire request in milliseconds (connection + redirects + data transfer + callbacks)
			CURLOPT_TIMEOUT_MS => $connectTimeoutMs + self::OVERHEAD_MS * self::MAX_REDIRECTS,
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

		// Capture final HTTP code (after redirects when FOLLOWLOCATION is on)
		$code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		self::$inLastHttpStatus = ($code > 0) ? $code : null;

		curl_close($ch);
		return $body;
	}

	/** ---------- Streams path ---------- */
	protected static function viaStreams(string $url, int $connectTimeoutMs, int $redirectsLeft, bool $verifySSL)
	{
		$headers = [
			'Accept: */*',
			'Accept-Encoding: identity',
			// 'Connection: close',
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
				'ignore_errors' => true, // so we can read body even on 4xx/5xx
				'timeout' => max(1, (int)ceil(($connectTimeoutMs + self::OVERHEAD_MS) / 1000)),
				'follow_location' => 0, // we handle redirects manually
			],
			'ssl' => $sslContext,
		]);

		$fp = @fopen($url, 'r', false, $context);
		if ($fp === false) {
			// When fopen fails, PHP may still populate $http_response_header
			$hdrs = $GLOBALS['http_response_header'] ?? [];
			$status = self::parseStatusCodeFromHeaders($hdrs);
			if ($status !== null) {
				self::$inLastHttpStatus = $status;
			}

			$redirectUrl = self::extractHeaderValue($hdrs, 'Location');
			if ($redirectUrl && $redirectsLeft > 0) {
				return self::viaStreams($redirectUrl, $connectTimeoutMs, $redirectsLeft - 1, $verifySSL);
			}
			return false;
		}

		stream_set_timeout($fp, 0, 0); // no read timeout on content read
		$body = stream_get_contents($fp);
		$meta = stream_get_meta_data($fp);
		fclose($fp);

		$wrapperHeaders = $meta['wrapper_data'] ?? ($GLOBALS['http_response_header'] ?? []);
		// Capture the final status line (last HTTP/x.y ... we see)
		$status = self::parseStatusCodeFromHeaders($wrapperHeaders);
		if ($status !== null) {
			self::$inLastHttpStatus = $status;
		}

		// Handle manual redirect chain if needed (we disabled auto-follow)
		if (self::isRedirectStatus($status ?? 0)) {
			$redirectUrl = self::extractHeaderValue($wrapperHeaders, 'Location');
			if ($redirectUrl && $redirectsLeft > 0) {
				// follow; final call will overwrite lastHttpStatus with final code
				return self::viaStreams($redirectUrl, $connectTimeoutMs, $redirectsLeft - 1, $verifySSL);
			}
		}

		// Decode if server disregarded "identity" and still compressed
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

	/**
	 * Parse the last HTTP status code present in a set of wrapper headers.
	 * Future-proof:
	 *  - Accepts HTTP/<major>[.<minor>] with any digits (HTTP/1.1, HTTP/2, HTTP/3, HTTP/3.1, HTTP/10, etc.)
	 *  - Accepts optional reason phrase
	 *  - Handles FastCGI "Status: 200 OK"
	 *  - Handles Shoutcast "ICY 200 OK"
	 */
	protected static function parseStatusCodeFromHeaders(array $headers): ?int
	{
		$last = null;
		foreach ($headers as $line) {
			$line = trim((string)$line);
			if (preg_match('~^HTTP/\s*\d+(?:\.\d+)?\s+(\d{3})\b~i', $line, $m)) {
				//  HTTP/1.1 200 OK         HTTP/3.1 200 Something
				$last = (int)$m[1]; // Standard & future HTTP versions: HTTP/<digits>[.<digits>] <code> [reason]
			} elseif (preg_match('~^Status:\s*(\d{3})\b~i', $line, $m)) {
				$last = (int)$m[1]; // FastCGI/CGI style:  Status: 404 Not Found
			} elseif (preg_match('~^ICY\s+(\d{3})\b~i', $line, $m)) {
				$last = (int)$m[1]; // Shoutcast/ICY:  ICY 200 OK
			}
		}
		return $last;
	}

	/** True if status is a redirect (3xx) */
	protected static function isRedirectStatus(int $code): bool
	{
		return $code >= 300 && $code < 400;
	}
}
