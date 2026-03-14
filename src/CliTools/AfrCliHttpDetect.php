<?php


namespace Autoframe\Core\CliTools;

use Autoframe\Core\Afr\Afr;
use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\Env\Exception\AfrEnvException;
use Autoframe\Core\Event\Exception\AfrEventException;
use Autoframe\Core\Http\Header\AfrHttpHeader;
use Autoframe\Core\Http\Ip\AfrIp;
use Autoframe\Core\Http\Request\AfrRequestClass;

/**
 * Class AfrCliHttpDetect
 * Provides methods to detect if the application is being run from the command line interface (CLI) or an HTTP web server.
 */
class AfrCliHttpDetect
{
	protected static bool $bIsCliCache; //because http_response_code can involuntarily change

	/**
	 * Is cli.
	 */
	public static function isCli(AfrRequestClass $rq = null): bool
	{
		if ($rq) return $rq->isCli();

		if (!isset(self::$bIsCliCache)) {
			self::$bIsCliCache = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg' || http_response_code() === false || defined('STDIN'));
		}
		return self::$bIsCliCache;
		//php_sapi_name ~ ['cli', 'phpdbg', 'embed', 'apache', 'apache2handler', 'cgi-fcgi', 'cli-server', 'fpm-fcgi', 'litespeed'])]
	}

	/**
	 * Is http or https protocol request.
	 */
	public static function isHttpOrHttpsProtocolRequest(AfrRequestClass $rq = null): bool
	{
		$sProtocol = $rq ? $rq->getServerParam('SERVER_PROTOCOL') : ($_SERVER['SERVER_PROTOCOL'] ?? '');
		return substr(strtoupper((string)$sProtocol), 0, 4) === 'HTTP';
	}

	/**
	 * Is http.
	 */
	public static function isHttp(AfrRequestClass $rq = null): bool //todo test in load balancers and cloudflare, etc
	{
		$sRqm = $rq ? $rq->getServerParam('REQUEST_METHOD') : ($_SERVER['REQUEST_METHOD'] ?? '');
		return !static::isCli($rq) && (
				(int)static::isHttpOrHttpsProtocolRequest($rq) +
				(int)!empty($sRqm) > 1
			);
	}

	/**
	 * Check if the request is using a native unsecure HTTP protocol.
	 * @return bool Returns true if the request is via unsecure HTTP protocol, false otherwise.
	 */
	public static function isHttpNativeUnsecure(AfrRequestClass $rq = null): bool
	{
		return static::isHttpOrHttpsProtocolRequest($rq) && !static::isHttpsNative($rq) && !static::isHttpsForwarded($rq);
	}

	/**
	 * Is https native or forwarded.
	 */
	public static function isHttpsNativeOrForwarded(AfrRequestClass $rq = null): bool
	{
		return static::isHttpsNative($rq) || static::isHttpsForwarded($rq);
	}

	/**
	 * Checks if the current request was made over HTTPS.
	 * @return bool True if the request was made over HTTPS, false otherwise.
	 */
	public static function isHttpsNative(AfrRequestClass $rq = null): bool
	{
		if (!static::isHttpOrHttpsProtocolRequest($rq)) {
			return false;
		}
		return
			'https' === ($rq ? $rq->getServerParam('REQUEST_SCHEME') : ($_SERVER['REQUEST_SCHEME'] ?? '')) ||
			'on' === ($rq ? $rq->getServerParam('HTTPS') : ($_SERVER['HTTPS'] ?? '')) ||
			443 === (int)($rq ? $rq->getServerParam('SERVER_PORT') : ($_SERVER['SERVER_PORT'] ?? 0));

	}

	/**
	 * Check if the request has been forwarded over HTTPS based on headers such as X-Forwarded-Proto or X-Forwarded-SSL.
	 * @param AfrRequestClass|null $rq
	 * @return bool Returns true if the request is forwarded over HTTPS, false otherwise.
	 */
	public static function isHttpsForwarded(AfrRequestClass $rq = null): bool
	{
		if (!static::isHttpOrHttpsProtocolRequest($rq)) {
			return false;
		}

		return static::isBehindLoadBalancerOrReverseProxy() && !static::isHttpsNative($rq) && (
				('https' === strtolower($rq ? $rq->getServerParam('HTTP_X_FORWARDED_PROTO') : ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))) ||
				('on' === strtolower($rq ? $rq->getServerParam('HTTP_X_FORWARDED_SSL') : ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')))
			);
	}

	/**
	 * Is behind load balancer or reverse proxy.
	 */
	public static function isBehindLoadBalancerOrReverseProxy(): bool
	{
		$bBehindLb = false;
		if (Afr::app()) {
			try {
				$bBehindLb = Afr::app()->env()->getEnv('AFR_BEHIND_LOAD_BALANCER_OR_REVERSE_PROXY', false);
			} catch (\Throwable $e) {
			}
		}
		return $bBehindLb;
	}

	/**
	 * Is untrusted http request.
	 * @param AfrRequestClass|null $rq
	 * @param bool $bE500IfUntrusted
	 * @return bool
	 * @throws AfrContainerException
	 * @throws AfrEventException|AfrEnvException
	 */
	public static function isUntrustedHttpRequest(AfrRequestClass $rq = null, bool $bE500IfUntrusted = false): bool
	{
		if (static::isCli($rq)) return false;

		if (static::isBehindLoadBalancerOrReverseProxy()) {
			$bUntrusted= !in_array(
				AfrIp::getInstance()->getRealClientIpAddr($rq),
				AfrIp::getInstance()->getTrustedProxiesIps()
			);
			if($bUntrusted && $bE500IfUntrusted) AfrHttpHeader::getInstance()->e500Html('Untrusted http request detected!');
			return $bUntrusted;
		}
		return false;
	}


	/**
	 * Get debug requested full data.
	 * @param AfrRequestClass|null $rq
	 * @param bool $bBody
	 * @param bool $bHeaders
	 * @param bool $bServer
	 * @param bool $bSes
	 * @param bool $bEnv
	 * @param bool $bGlobals
	 * @return array
	 */
	public static function getDebugRequestedFullData(
		AfrRequestClass $rq = null,
		bool            $bBody = false,
		bool            $bHeaders = false,
		bool            $bServer = false,
		bool            $bSes = false,
		bool            $bEnv = false,
		bool            $bGlobals = false
	): array
	{

		return [
			'isCLi' => static::isCli($rq),
			'isHttp' => static::isHttp($rq),
			'isHttpsNative' => static::isHttpsNative($rq),
			'isHttpsForwarded' => static::isHttpsForwarded($rq),
			'isHttpOrHttpsProtocolRequest' => static::isHttpOrHttpsProtocolRequest($rq),
			'isHttpsNativeX' => static::isHttpsForwarded($rq),
			'php_sapi_name' => $rq ? $rq->getPhpSapi() : php_sapi_name(),
			//GET, HEAD, POST, PUT, PATCH, CONNECT, DELETE, OPTIONS, TRACE,

			'REQUEST_METHOD' => ($REQUEST_METHOD = $rq ? $rq->getServerParam('REQUEST_METHOD') : ($_SERVER['REQUEST_METHOD'] ?? null)),
			'REQUEST_URI' => $rq ? $rq->getServerParam('REQUEST_URI') : ($_SERVER['REQUEST_URI'] ?? null),
			'headers' => $bHeaders ? static::getServerRequestHeaders($rq) : null,
			'superglobals' => [
				'$_GET' => $rq ? $rq->getAllGetParams() : $_GET,
				'$_POST' => $rq ? $rq->getAllPostParams() : $_POST,
				'$_COOKIE' => $rq ? $rq->getAllCookieParams() : $_COOKIE,
				'$_FILES' => $rq ? $rq->getAllFileParams() : $_FILES,
				'$_REQUEST' => $rq ? $rq->getAllRequestParams() : $_REQUEST, //request_order This directive describes the order in which PHP registers GET, POST and Cookie variables into the _REQUEST array. Registration is done from left to right, newer values override older values.
				'$_SERVER' => $bServer ? (
				$rq ? $rq->getAllServerParams() : $_SERVER
				) : null,
				'$_ENV' => $bEnv ? $_ENV : null,
				'$_SESSION' => $bSes && isset($_SESSION) ? $_SESSION : null,
				'$GLOBALS' => $bGlobals ? $GLOBALS : null,
			],
			'body' => $bBody && $REQUEST_METHOD && $REQUEST_METHOD !== 'GET' ? ($rq ? $rq->getPhpInput() : file_get_contents('php://input')) : null,
		];
		/**
		 * request_order string(GP): This directive describes the order in which PHP registers GET, POST and Cookie variables into the _REQUEST array. Registration is done from left to right, newer values override older values.
		 * variables_order string(GPCS): Sets the order of the EGPCS (Environment, Get, Post, Cookie, and Server) variable parsing. For example, if variables_order is set to "SP" then PHP will create the superglobals $_SERVER and $_POST, but not create $_ENV, $_GET, and $_COOKIE. Setting to "" means no superglobals will be set.
		 */
	}


	/**
	 * Get server request headers.
	 */
	public static function getServerRequestHeaders(AfrRequestClass $rq = null): array
	{
		if ($rq === null && function_exists('apache_request_headers')) {
			$aHeaders = apache_request_headers();
			if (empty($aHeaders)) {
				$aHeaders = [];
			}
		} else {
			$aHeaders = [];
			$sPrefix = 'HTTP_';
			$iPrefixLen = strlen($sPrefix);
			foreach (($rq ? $rq->getAllServerParams() : $_SERVER) as $sServerKey => $sValue) {
				if (substr($sServerKey, 0, $iPrefixLen) === $sPrefix) {
					$sHeaderName = substr($sServerKey, $iPrefixLen);
					// do some nasty string manipulations to restore the original letter case
					// this should work in most cases
					$aHeaderNameParts = explode('_', trim($sHeaderName, ' _-'));
					foreach ($aHeaderNameParts as $ak_key => $ak_val) {
						$aHeaderNameParts[$ak_key] = ucfirst($ak_val);
					}
					$aHeaders[implode('-', $aHeaderNameParts)] = $sValue;
				}
			}
		}
		return $aHeaders;
	}


	/**
	 * Get request scheme host port.
	 */
	public static function getRequestSchemeHostPort(AfrRequestClass $rq = null): string
	{
		if (!static::isHttpOrHttpsProtocolRequest($rq)) {
			return $rq ? $rq->getPhpSapi() : php_sapi_name();
		}
		$iPort = (int)($rq ? $rq->getServerParam('SERVER_PORT', 0) : ($_SERVER['SERVER_PORT'] ?? 0));
		$sPort = $iPort === 0 ? '' : ':' . $iPort;
		$sHost = $rq ? $rq->getServerParam('HTTP_HOST', 'localhost') : ($_SERVER['HTTP_HOST'] ?? 'localhost');
		if (static::isHttpsNativeOrForwarded($rq)) {
			return 'https://' . $sHost . ($iPort == 443 ? '' : $sPort);
		} else {
			return 'http://' . $sHost . ($iPort == 80 ? '' : $sPort);
		}
	}

	/**
	 * Returns the HTTP protocol version as a float value from $_SERVER['SERVER_PROTOCOL']
	 * If SERVER_PROTOCOL key is not set, returns 0.0.
	 *
	 * @return float The HTTP protocol version as a float value.
	 */
	public static function getHttpProtocolVersion(AfrRequestClass $rq = null): float
	{
		return floatval(trim(strtoupper(
			$rq ? $rq->getServerParam('SERVER_PROTOCOL', '0.0') : ($_SERVER['SERVER_PROTOCOL'] ?? '0.0')
		), 'HTP/ '));
	}

	/**
	 * Get entry point.
	 */
	public static function getEntryPoint(
		AfrRequestClass $rq = null,
		bool            $bIncludeArgs = true,
		bool            $bWrapFilePathInQuotesIfItContainsSpaces = true
	): string
	{
		$sEntryPoint = array_slice(debug_backtrace(2), -1, 1)[0]['file'] ?? ($_SERVER['SCRIPT_FILENAME'] ?: '');
		if ($bWrapFilePathInQuotesIfItContainsSpaces && strpos($sEntryPoint, ' ') !== false) {
			$sEntryPoint = '"' . $sEntryPoint . '"';
		}

		if ($bIncludeArgs) {
			$aSerArgv = $rq ? $rq->getServerParam('argv', []) : ($_SERVER['argv'] ?? []);
			if (empty($aSerArgv)) return $sEntryPoint;

			$aArgv = array_slice($aSerArgv, 1);
			foreach ($aArgv as &$v) {
				if (strpos($v, ' ') !== false) {
					$mEqPos = strpos($v, '=');
					$v = ($mEqPos === false) ? escapeshellarg($v) : substr($v, 0, $mEqPos + 1) . escapeshellarg(substr($v, $mEqPos + 1));
					//$v = ($mEqPos === false) ? '"' . $v . '"' :	substr($v, 0, $mEqPos + 1) . '"' . substr($v, $mEqPos + 1) . '"';
				}
			}
			$sEntryPoint .= ' ' . implode(' ', $aArgv);
		}
		return $sEntryPoint;
	}

	/**
	 * https://ipinfo.io/188.24.165.200
	 * @param string $sUrl !!! Average response time is 150 MS!!! use with care
	 * @return false|string
	 */
	public static function detectExternalIpUsingThirdPartyUrl(string $sUrl = 'http://ipecho.net/plain')
	{
		return file_get_contents($sUrl);
	}


}
