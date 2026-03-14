<?php

namespace Autoframe\Core\Http\Request;

use Autoframe\Core\Afr\Afr;
use Autoframe\Core\CliTools\AfrCliHttpDetect;
use Autoframe\Core\CliTools\AfrGetOpt;
use Autoframe\Core\Container\AfrContainerFacade;
use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;
use Autoframe\Core\Env\Exception\AfrEnvException;
use Autoframe\Core\Event\Exception\AfrEventException;
use Autoframe\Core\Exception\AfrException;
use Autoframe\Core\FileSystem\DirPath\AfrDirPathClass;
use Autoframe\Core\Http\Request\Exception\AfrHttpRequestException;
use Autoframe\Core\Tenant\AfrTenant;
use Autoframe\Core\AfrCoreModules\FnContracts\AfrHttpRoutesContract;
use Closure;


define('AFR_REQUEST_ORIGINAL_GET', $_GET ?? null);
define('AFR_REQUEST_ORIGINAL_POST', $_POST ?? null);
define('AFR_REQUEST_ORIGINAL_COOKIE', $_COOKIE ?? null);
define('AFR_REQUEST_ORIGINAL_REQUEST', $_REQUEST ?? null);
define('AFR_REQUEST_ORIGINAL_FILES', $_FILES ?? null);
define('AFR_REQUEST_ORIGINAL_SERVER', $_SERVER ?? null);


/**
 * @method float getHttpProtocolVersion()
 * @method string getRequestSchemeHostPort()
 * @method array getServerRequestHeaders()
 * @method bool isBehindLoadBalancerOrReverseProxy()
 * @method bool isHttpNativeUnsecure()
 * @method bool isHttpOrHttpsProtocolRequest()
 * @method bool isHttpsForwarded()
 * @method bool isHttpsNative()
 * @method bool isHttpsNativeOrForwarded()
 * @method bool isUntrustedHttpRequest()
 * @method bool isHttp()
 * @method array getDebugRequestedFullData(bool $bBody = false, bool $bHeaders = false, bool $bServer = false, bool $bSes = false, bool $bEnv = false, bool $bGlobals = false)
 */
class AfrRequestClass extends AfrSingletonAbstractClass implements AfrRequestInterface
{
	protected static AfrRequestInterface $oDefaultInstanceRequest;

	/**
	 * Get instance.
	 * @return AfrRequestInterface
	 * @throws AfrContainerException
	 * @throws AfrEnvException
	 * @throws AfrEventException|AfrException
	 */
	public static function getInstance(): AfrRequestInterface
	{
		if (empty(static::$oDefaultInstanceRequest)) {
			static::$oDefaultInstanceRequest =
				AfrCliHttpDetect::isCli() ?
					static::makeNewCliRequestInstance($_SERVER['argv']) :
					static::makeNewHttpRequestInstance();
			if (Afr::app()) {
				Afr::app()->setRequest(static::$oDefaultInstanceRequest);
			}
		}
		return static::$oDefaultInstanceRequest;
	}

	/**
	 * Make new cli request instance.
	 * @throws AfrContainerException
	 * @throws AfrEnvException
	 * @throws AfrEventException
	 */
	public static function makeNewCliRequestInstance(
		array  $aArgv,
		string $sPhpPath = null,
		array  $server = null,
		string $php_sapi_name = null
	): AfrRequestInterface
	{
		// todo !! note that getopt function does not actually update new received parameters !
		return static::setupCliRequest(
			static::getResolvedStatic(static::class) ?? new static(),
			$aArgv,
			$sPhpPath,
			$server,
			$php_sapi_name
		);
	}


	/**
	 * Make new cli request instance from command line.
	 * @param string $sCommandScriptLine /path/someScript.php -a="X" --argY
	 * @param array|null $server
	 * @param string|null $php_sapi_name
	 * @return AfrRequestInterface
	 * @throws AfrContainerException
	 * @throws AfrEnvException
	 * @throws AfrEventException
	 */
	public static function makeNewCliRequestInstanceFromCommandLine(
		string $sCommandScriptLine,
		array  $server = null,
		string $php_sapi_name = null
	): AfrRequestInterface
	{
		return static::makeNewCliRequestInstance(
			AfrGetOpt::getInstance()->tokenizeCliLineInput(
				trim($sCommandScriptLine),
				false
			), null, $server, $php_sapi_name
		);

	}

	/**
	 * Make new http request instance.
	 * @throws AfrContainerException
	 * @throws AfrEnvException
	 * @throws AfrEventException
	 * @throws AfrException
	 */
	public static function makeNewHttpRequestInstance(
		?array  $cookie = null,
		?array  $post = null,
		?array  $files = null,
		?array  $get = null,
		?array  $server = null,
		?array  $request = null,
		?string $php_sapi_name = null
	): AfrRequestInterface
	{
		return static::setupHttpRequest(
			static::getResolvedStatic(static::class) ?? new static(),
			$cookie,
			$post,
			$files,
			$get,
			$server,
			$request,
			$php_sapi_name
		);
	}

	/**
	 * @param string $sClassFQCN
	 * @return AfrRequestInterface|null
	 * @throws AfrContainerException
	 */
	protected static function getResolvedStatic(string $sClassFQCN): ?AfrRequestInterface
	{
		if (AfrContainerFacade::getContainer()->has($sClassFQCN)) {
			$oResolved = AfrContainerFacade::getContainer()->get($sClassFQCN);
			if (is_object($oResolved)) {
				if ($oResolved instanceof Closure) {
					return ($oResolved->bindTo(null, $sClassFQCN))($sClassFQCN);
				}
				return $oResolved;
			}
		}
		return null;
	}


	/**
	 * Set this request as app request instance.
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function setThisRequestAsAppRequestInstance(): AfrRequestInterface
	{
		Afr::app()->setRequest($this);
		return (static::$oDefaultInstanceRequest = $this);
	}

	/**
	 * Restore original app request instance.
	 * @return AfrRequestClass|AfrRequestInterface
	 * @throws AfrContainerException
	 */
	public function restoreOriginalAppRequestInstance(): AfrRequestInterface
	{
		$aOriginalRequest = Afr::app()->container()->get(AfrRequestInterface::class);
		Afr::app()->setRequest($aOriginalRequest);
		return $aOriginalRequest;
	}


	/**
	 * @param AfrRequestInterface $oInstance
	 * @param array $aArgv
	 * @param string|null $sPhpPath
	 * @param array|null $server
	 * @param string|null $php_sapi_name
	 * @return AfrRequestInterface
	 * @throws AfrContainerException
	 * @throws AfrEnvException
	 * @throws AfrEventException
	 */
	protected static function setupCliRequest(
		AfrRequestInterface $oInstance,
		array               $aArgv,
		string              $sPhpPath = null,
		array               $server = null,
		string              $php_sapi_name = null
	): AfrRequestInterface
	{
		// todo !! note that getopt function does not actually update new received parameters !
		//$oInstance = static::getResolvedStatic(static::class) ?? new static();
		$oInstance->bIsCli = true;
		$oInstance->get = $oInstance->post = $oInstance->files = $oInstance->cookie = $oInstance->request = [];
		$oInstance->server = $server ?? $_SERVER;
		foreach ($oInstance->server as $k => $v) {
			if (substr($k, 0, 5) == 'HTTP_') {
				unset($oInstance->server[$k]);
			}
		}
		$oInstance->php_sapi = $php_sapi_name ?? (AfrCliHttpDetect::isCli() ? (string)php_sapi_name() : 'cli');
		if (empty($oInstance->php_sapi) || !in_array($oInstance->php_sapi, ['cli', 'phpdbg'])) {
			$oInstance->php_sapi = 'cli';
		}

		$oInstance->server['PATH_TRANSLATED'] = $sPhpPath ?? AfrDirPathClass::getInstance()->realpath(
			AFR_REQUEST_ORIGINAL_SERVER['SCRIPT_FILENAME'], false
		);
		$oInstance->server['PHP_SELF'] ??= $oInstance->server['PATH_TRANSLATED'];
		$oInstance->server['SCRIPT_NAME'] ??= $oInstance->server['PATH_TRANSLATED'];

		$aArgv[0] = $oInstance->server['PATH_TRANSLATED']; //todo: add checks for [0] ends with .php
		$oInstance->server['argv'] = $aArgv;
		$oInstance->server['argc'] = count($aArgv);
		static::fixServer($oInstance);

		return $oInstance;
	}


	/**
	 * @param AfrRequestInterface $oInstance
	 * @param array|null $cookie
	 * @param array|null $post
	 * @param array|null $files
	 * @param array|null $get
	 * @param array|null $server
	 * @param array|null $request
	 * @param string|null $php_sapi_name
	 * @return AfrRequestInterface
	 * @throws AfrContainerException
	 * @throws AfrEnvException
	 * @throws AfrEventException
	 * @throws AfrException
	 */
	protected static function setupHttpRequest(
		AfrRequestInterface $oInstance,
		?array              $cookie = null,
		?array              $post = null,
		?array              $files = null,
		?array              $get = null,
		?array              $server = null,
		?array              $request = null,
		?string             $php_sapi_name = null
	): AfrRequestInterface
	{
		//$oInstance = static::getResolvedStatic(static::class) ?? new static();
		$oInstance->bIsCli = false;
		$oInstance->php_sapi = $php_sapi_name ?? (!AfrCliHttpDetect::isCli() ? php_sapi_name() : 'apache2handler');
		if (empty($oInstance->php_sapi) || in_array($oInstance->php_sapi, ['cli', 'phpdbg'])) {
			$oInstance->php_sapi = 'apache2handler';
		}

		$oInstance->get = $get ?? $_GET;
		$oInstance->post = $post ?? $_POST;
		$oInstance->files = $files ?? $_FILES;
		$oInstance->server = $server ?? $_SERVER;
		$oInstance->cookie = $cookie ?? $_COOKIE;

		if ($request === null) {
			$oInstance->request = [];
			//	static::fixRequest($oInstance);
			if (empty($oInstance->request)) {
				$oInstance->request = $_REQUEST ?? [];
			}
		} else {
			$oInstance->request = $request;
			//	static::fixRequest($oInstance);
		}
		$oInstance->server['SERVER_SIGNATURE'] ??= '<address>AFR</address>';
		$oInstance->server['SERVER_SOFTWARE'] ??= 'AFR';
		$oInstance->server['CONTEXT_PREFIX'] ??= '';

		static::fixServer($oInstance);

		$oInstance->server['PHP_SELF'] ??= AFR_REQUEST_ORIGINAL_SERVER['PHP_SELF']; // EG: /test.php/foo/bar
		$oInstance->server['SCRIPT_NAME'] ??= AFR_REQUEST_ORIGINAL_SERVER['SCRIPT_NAME'];// EG / /test.php

		if (AfrCliHttpDetect::isCli()) {
			//TODO: FOR CUSTOM REQUESTS, this will be set using setRouteFunction
			$oInstance->server['QUERY_STRING'] = empty($oInstance->get) ? '' : http_build_query($oInstance->get);
			$oInstance->server['REQUEST_URI'] ??= '/' . ($oInstance->server['QUERY_STRING'] ? '?' . $oInstance->server['QUERY_STRING'] : '');
			$oInstance->server['REQUEST_METHOD'] ??= (count($oInstance->post) + count($oInstance->files) > 0 ? 'POST' : 'GET');
			$oInstance->server['REQUEST_METHOD_ORIGINAL'] = $oInstance->server['REQUEST_METHOD'];
			$oInstance->server['SERVER_PROTOCOL'] ??= 'HTTP/1.1';
			$oInstance->server['GATEWAY_INTERFACE'] ??= 'CGI/1.1';
			$oInstance->server['SERVER_NAME'] ??= parse_url(AfrTenant::getProtocolHost(), PHP_URL_HOST) ?? 'localhost';
			$oInstance->server['SERVER_ADDR'] ??= '::1';
			$oInstance->server['SERVER_PORT'] ??= '80';
			$oInstance->server['REMOTE_ADDR'] ??= '::1';
			$oInstance->server['REMOTE_PORT'] ??= '48080';
			$oInstance->server['REMOTE_PORT'] ??= 'http';
		} else {
			static::populateHttpRoute(
				$oInstance,
				$oInstance->server['REQUEST_URI']
			);
		}

		static::fixRequest($oInstance);

		if (AfrCliHttpDetect::isCli()) {
			http_response_code(200); //emulate http test ok
		}

		return $oInstance;
	}

	/**
	 * @param AfrRequestClass $oInstance
	 * @param string $sRequestUriRoute
	 * @param string|null $sRequestMethod
	 * @param bool|null $bHttps
	 * @param float|null $fProtocolVersion
	 * @param string|null $sRemoteAddrClient
	 * @param null $sRemotePortClient
	 * @param string|null $sServerNameHost
	 * @param string|null $sServerAddrIp
	 * @param null $sServerPort
	 * @param string|null $sGatewayInterface
	 * @return AfrRequestClass|AfrRequestInterface
	 * @throws AfrException
	 */
	protected static function populateHttpRoute(
		AfrRequestInterface $oInstance,
		string              $sRequestUriRoute, // /route?g=1 also sets query string and request
		string              $sRequestMethod = null,
		bool                $bHttps = null, // REQUEST_SCHEME = https && HTTPS = on && SERVER_PORT != 80
		float               $fProtocolVersion = null, //1.1 from HTTP/1.1
		string              $sRemoteAddrClient = null, //Client.Ip
		                    $sRemotePortClient = null, //Client.Port
		string              $sServerNameHost = null, //www.aa.com
		string              $sServerAddrIp = null, //Server.IP
		                    $sServerPort = null, //80,443,etc
		string              $sGatewayInterface = null //'CGI/1.1' from SERVER_PROTOCOL
	): AfrRequestInterface
	{
		if ($oInstance->isCli()) {
			throw new AfrException('The current request is a CLI request, not a HTTP request!');
		}
		if (strlen($sRequestUriRoute) < 1 || substr($sRequestUriRoute, 0, 1) != '/') {
			throw new AfrException($sRequestUriRoute . ' is not a valid route.');
		}

		$aGet = [];
		if (($iQsPos = strpos($sRequestUriRoute, '?')) !== false) {
			parse_str(substr($sRequestUriRoute, $iQsPos + 1), $aGet);
		}
		$oInstance->get = $aGet;
		$oInstance->server['QUERY_STRING'] = empty($oInstance->get) ? '' : http_build_query($oInstance->get);
		$oInstance->server['REQUEST_URI'] = $sRequestUriRoute;


		if ($sRequestMethod) {
			$oInstance->server['REQUEST_METHOD'] = strtoupper($sRequestMethod);
		} else {
			$oInstance->server['REQUEST_METHOD'] ??= (count($oInstance->post) + count($oInstance->files) > 0 ? 'POST' : 'GET');
		}
		if ($oInstance->server['REQUEST_METHOD'] !== ($_SERVER['REQUEST_METHOD'] ?? '') &&
			!in_array($oInstance->server['REQUEST_METHOD'], AfrHttpRoutesContract::AllowedHTTPRequestMethods)) {
			throw new AfrException('Invalid HTTP request method: ' . $oInstance->server['REQUEST_METHOD']);
		}
		$oInstance->server['REQUEST_METHOD_ORIGINAL'] = $oInstance->server['REQUEST_METHOD'];


		if ($bHttps !== null) {
			$oInstance->server['REQUEST_SCHEME'] = $bHttps ? 'https' : 'http';
		} else {
			$oInstance->server['REQUEST_SCHEME'] ??= trim(strtolower(substr(AfrTenant::getProtocolHost(), 0, 5)), ':');
		}

		if ($oInstance->server['REQUEST_SCHEME'] === 'https') {
			$oInstance->server['HTTPS'] = 'on';
		} else {
			unset($oInstance->server['HTTPS']);
			$oInstance->server['REQUEST_SCHEME'] = 'http'; //fix any potential issues for tenant protocol host detection
		}

		if ($fProtocolVersion !== null) {
			$oInstance->server['SERVER_PROTOCOL'] = 'HTTP/' . $fProtocolVersion;

		} else {
			$oInstance->server['SERVER_PROTOCOL'] ??= 'HTTP/1.1';
		}

		if ($sGatewayInterface !== null) {
			$oInstance->server['GATEWAY_INTERFACE'] = $sGatewayInterface;
		} else {
			$oInstance->server['GATEWAY_INTERFACE'] ??= 'CGI/' . ($fProtocolVersion ?: '1.1');
		}

		if ($sServerNameHost) {
			$oInstance->server['SERVER_NAME'] = $sServerNameHost;
		} elseif (empty($oInstance->server['SERVER_NAME'])) {
			$sServerNameHost = (string)parse_url(AfrTenant::getProtocolHost(), PHP_URL_HOST);
			$oInstance->server['SERVER_NAME'] = ($sServerNameHost ?: 'localhost');
		}

		if ($sServerPort) {
			$oInstance->server['SERVER_PORT'] = (string)$sServerPort;
		} elseif (empty($oInstance->server['SERVER_PORT'])) {
			$iPort = (int)parse_url(AfrTenant::getProtocolHost(), PHP_URL_PORT);
			$oInstance->server['SERVER_PORT'] = (string)(
			$iPort ?: ($oInstance->server['REQUEST_SCHEME'] === 'https' ? '443' : '80')
			);
		}

		if ($sRemoteAddrClient !== null) {
			$oInstance->server['REMOTE_ADDR'] = $sRemoteAddrClient;
		} else {
			$oInstance->server['REMOTE_ADDR'] ??= '::1';
		}

		if ($sServerAddrIp !== null) {
			$oInstance->server['SERVER_ADDR'] = $sServerAddrIp;
		} else {
			$oInstance->server['SERVER_ADDR'] ??= '::1';
		}

		if ($sRemotePortClient !== null) {
			$oInstance->server['REMOTE_PORT'] = (string)$sRemotePortClient;
		} else {
			$oInstance->server['REMOTE_PORT'] ??= '41234';
		}

		static::fixRequest($oInstance);
		return $oInstance;
	}

	/**
	 * Convert to https.
	 * @param bool $bHttps
	 * @return AfrRequestClass|AfrRequestInterface
	 * @throws AfrHttpRequestException
	 */
	public function convertToHTTPS(bool $bHttps): AfrRequestInterface
	{
		if ($this->isCli()) {
			throw new AfrHttpRequestException('Cannot set HTTP / HTTPS to a CLI Request');
		}
		$this->server['REQUEST_SCHEME'] = $bHttps ? 'https' : 'http';
		if ($bHttps) {
			$this->server['HTTPS'] = 'on';
		} elseif (isset($this->server['HTTPS'])) {
			unset($this->server['HTTPS']);
		}
		return $this;
	}

	/**
	 * @param $oInstance
	 * @return void
	 */
	protected static function fixRequest($oInstance): void
	{
		$aRqOrderMap = [
			'G' => $oInstance->get,
			'P' => $oInstance->post,
			'C' => $oInstance->cookie,
			'S' => $oInstance->server,
			//	'E' => AfrEnv::getInstance()->getEnv('', []),
		];
		$request_order = ini_get('request_order') ?: 'GP';
		for ($i = 0; $i < strlen($request_order); $i++) {
			$oInstance->request = array_merge(
				$oInstance->request,
				$aRqOrderMap[substr($request_order, $i, 1)] ?? []
			);
		}
	}

	/**
	 * @param $oInstance
	 * @return void
	 * @throws AfrContainerException
	 * @throws AfrEnvException
	 * @throws AfrEventException
	 */
	protected static function fixServer($oInstance): void
	{
		$oInstance->server['CONTEXT_DOCUMENT_ROOT'] ??= Afr::app()->getAppBaseDirectory();
		$oInstance->server['DOCUMENT_ROOT'] ??= Afr::app()->getAppBaseDirectory();
		$oInstance->server['TENANT_DOCUMENT_ROOT'] ??= AfrTenant::getPublicHtmlDir();
		$oInstance->server['SERVER_ADMIN'] ??= Afr::app()->env()->getEnv('AFR_SERVER_ADMIN', 'postmaster@localhost');
		$oInstance->server['REQUEST_TIME_FLOAT'] ??= AFR_REQUEST_ORIGINAL_SERVER['REQUEST_TIME_FLOAT'];
		$oInstance->server['REQUEST_TIME'] ??= AFR_REQUEST_ORIGINAL_SERVER['REQUEST_TIME'];
		$oInstance->server['SCRIPT_FILENAME'] ??= AfrDirPathClass::getInstance()->realpath(
			AFR_REQUEST_ORIGINAL_SERVER['SCRIPT_FILENAME'], false
		);

	}


	/**
	 * Handle calls to inaccessible instance methods.
	 * @param string $method
	 * @param array $parameters
	 * @return mixed
	 * @throws AfrException
	 */
	public function __call(string $method, array $parameters)
	{
		if (in_array($method, [
			'getHttpProtocolVersion',
			'getRequestSchemeHostPort',
			'getServerRequestHeaders',
			'isBehindLoadBalancerOrReverseProxy',
			'isHttpNativeUnsecure',
			'isHttpOrHttpsProtocolRequest',
			'isHttpsForwarded',
			'isHttpsNative',
			'isHttpsNativeOrForwarded',
			'isUntrustedHttpRequest',
			'isHttp',
			'getDebugRequestedFullData'
		])) {
			return AfrCliHttpDetect::$method(...array_merge([$this], $parameters));
		}
		throw new AfrException("Method '$method' not found in class " . get_class($this));
	}

	/**
	 * Is cli.
	 */
	public function isCli(): bool
	{
		return $this->bIsCli;
	}

	/**
	 * Is http post request.
	 */
	public function isHttpPostRequest(): bool
	{
		return !$this->isCli() && $this->server['REQUEST_METHOD'] == 'POST';
	}

	/**
	 * Is http get request.
	 */
	public function isHttpGetRequest(): bool
	{
		return !$this->isCli() && $this->server['REQUEST_METHOD'] == 'GET';
	}

	/**
	 * Is http head request.
	 */
	public function isHttpHeadRequest(): bool
	{
		return !$this->isCli() && $this->server['REQUEST_METHOD'] == 'HEAD';
	}

	/**
	 * Is http put request.
	 */
	public function isHttpPutRequest(): bool
	{
		return !$this->isCli() && $this->server['REQUEST_METHOD'] == 'PUT';
	}

	/**
	 * Is http options request.
	 */
	public function isHttpOptionsRequest(): bool
	{
		return !$this->isCli() && $this->server['REQUEST_METHOD'] == 'OPTIONS';
	}

	/**
	 * Is http patch request.
	 */
	public function isHttpPatchRequest(): bool
	{
		return !$this->isCli() && $this->server['REQUEST_METHOD'] == 'PATCH';
	}

	/**
	 * Is http delete request.
	 */
	public function isHttpDeleteRequest(): bool
	{
		return !$this->isCli() && $this->server['REQUEST_METHOD'] == 'DELETE';
	}


	/**
	 * Get http request method.
	 */
	public function getHttpRequestMethod(): ?string
	{
		return $this->isCli() ? null : $this->server['REQUEST_METHOD'];
	}

	/**
	 * Get http request method original.
	 */
	public function getHttpRequestMethodOriginal(): ?string
	{
		return $this->isCli() ? null : $this->server['REQUEST_METHOD_ORIGINAL'];
	}

	/**
	 * Get http request uri.
	 */
	public function getHttpRequestUri(): ?string
	{
		return $this->isCli() ? null : $this->server['REQUEST_URI'];
	}


	/**
	 * Get cli args.
	 */
	public function getCliArgs(): ?array
	{
		return !$this->isCli() ? null : $this->server['argv'];
	}

	protected array $aMock = []; //request overload:
	protected string $php_sapi;
	protected array $get;
	protected array $post;
	protected array $files;
	protected array $server;
	protected array $cookie;
	protected array $request;
	protected bool $bIsCli;

	// Get the 'argv' parameter from SERVER
	/**
	 * Get argv param.
	 */
	public function getArgvParam(): array
	{
		return $this->server['argv'] ?? [];
	}

	/**
	 * Set argv param.
	 * @param array $argv
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function setArgvParam(array $argv): AfrRequestInterface
	{
		$this->server['argv'] = $argv;
		return $this;
	}

	// Get a GET parameter
	/**
	 * Get query param.
	 */
	public function getQueryParam(string $key, $default = null)
	{
		return $this->get[$key] ?? $default;
	}

	/**
	 * Set query param.
	 * @param string $key
	 * @param $value
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function setQueryParam(string $key, $value): AfrRequestInterface
	{
		$this->get[$key] = $value;
		return $this;
	}

	/**
	 * Unset query param.
	 * @param string $key
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function unsetQueryParam(string $key): AfrRequestInterface
	{
		unset($this->get[$key]);
		return $this;
	}

	// Check if a GET parameter exists
	/**
	 * Has query param.
	 */
	public function hasQueryParam(string $key): bool
	{
		return isset($this->get[$key]);
	}

	// Get a POST parameter
	/**
	 * Get post param.
	 */
	public function getPostParam(string $key, $default = null)
	{
		return $this->post[$key] ?? $default;
	}

	/**
	 * Set post param.
	 * @param string $key
	 * @param $value
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function setPostParam(string $key, $value): AfrRequestInterface
	{
		$this->post[$key] = $value;
		return $this;
	}

	/**
	 * Unset post param.
	 * @param string $key
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function unsetPostParam(string $key): AfrRequestInterface
	{
		unset($this->post[$key]);
		return $this;
	}

	// Check if a POST parameter exists
	/**
	 * Has post param.
	 */
	public function hasPostParam(string $key): bool
	{
		return isset($this->post[$key]);
	}

	// Get a FILES parameter
	/**
	 * Get file param.
	 */
	public function getFileParam(string $key, $default = null)
	{
		return $this->files[$key] ?? $default;
	}

	/**
	 * Set file param.
	 * @param string $key
	 * @param $value
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function setFileParam(string $key, $value): AfrRequestInterface
	{
		$this->files[$key] = $value;
		return $this;
	}

	/**
	 * Unset file param.
	 * @param string $key
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function unsetFileParam(string $key): AfrRequestInterface
	{
		unset($this->files[$key]);
		return $this;
	}

	// Check if a FILES parameter exists
	/**
	 * Has file param.
	 */
	public function hasFileParam(string $key): bool
	{
		return isset($this->files[$key]);
	}

	// Get a SERVER parameter
	/**
	 * Get server param.
	 */
	public function getServerParam(string $key, $default = null)
	{
		return $this->server[$key] ?? $default;
	}

	/**
	 * Set server param.
	 * @param string $key
	 * @param $value
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function setServerParam(string $key, $value): AfrRequestInterface
	{
		$this->server[$key] = $value;
		return $this;
	}

	/**
	 * Unset server param.
	 * @param string $key
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function unsetServerParam(string $key): AfrRequestInterface
	{
		unset($this->server[$key]);
		return $this;
	}

	// Check if a SERVER parameter exists
	/**
	 * Has server param.
	 */
	public function hasServerParam(string $key): bool
	{
		return isset($this->server[$key]);
	}

	// Get a COOKIE parameter
	/**
	 * Get cookie param.
	 */
	public function getCookieParam(string $key, $default = null)
	{
		return $this->cookie[$key] ?? $default;
	}

	/**
	 * Set cookie param.
	 * @param string $key
	 * @param $value
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function setCookieParam(string $key, $value): AfrRequestInterface
	{
		$this->cookie[$key] = $value;
		return $this;
	}

	/**
	 * Unset cookie param.
	 * @param string $key
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function unsetCookieParam(string $key): AfrRequestInterface
	{
		unset($this->cookie[$key]);
		return $this;
	}

	// Check if a COOKIE parameter exists
	/**
	 * Has cookie param.
	 */
	public function hasCookieParam(string $key): bool
	{
		return isset($this->cookie[$key]);
	}

	// Get all GET parameters
	/**
	 * Get all get params.
	 */
	public function getAllGetParams(): array
	{
		return $this->get;
	}

	// Get all POST parameters
	/**
	 * Get all post params.
	 */
	public function getAllPostParams(): array
	{
		return $this->post;
	}

	// Get all FILES parameters
	/**
	 * Get all file params.
	 */
	public function getAllFileParams(): array
	{
		return $this->files;
	}

	// Get all SERVER parameters
	/**
	 * Get all server params.
	 */
	public function getAllServerParams(): array
	{
		return $this->server;
	}

	// Get a REQUEST parameter
	/**
	 * Get request param.
	 */
	public function getRequestParam(string $key, $default = null)
	{
		return $this->request[$key] ?? $default;
	}

	/**
	 * Set request param.
	 * @param string $key
	 * @param $value
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function setRequestParam(string $key, $value): AfrRequestInterface
	{
		$this->request[$key] = $value;
		return $this;
	}

	/**
	 * Unset request param.
	 * @param string $key
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function unsetRequestParam(string $key): AfrRequestInterface
	{
		unset($this->request[$key]);
		return $this;
	}

	// Check if a REQUEST parameter exists
	/**
	 * Has request param.
	 */
	public function hasRequestParam(string $key): bool
	{
		return isset($this->request[$key]);
	}

	// Get all REQUEST parameters
	/**
	 * Get all request params.
	 */
	public function getAllRequestParams(): array
	{
		return $this->request;
	}

	// Get all COOKIE parameters
	/**
	 * Get all cookie params.
	 */
	public function getAllCookieParams(): array
	{
		return $this->cookie;
	}

	/**
	 * Set php input mock.
	 * @param string|null $php_input_mock
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function setPhpInputMock(?string $php_input_mock): AfrRequestInterface
	{
		if (($this->aMock['php://input'] = $this->isCli() ? null : $php_input_mock) === null) {
			unset($this->aMock['php://input']);
		}
		return $this;
	}

	/**
	 * Get php input.
	 */
	public function getPhpInput(): ?string
	{
		//TODO: https://www.php.net/manual/en/function.stream-wrapper-register.php
		if (isset($this->aMock['php://input'])) {
			return $this->aMock['php://input'];
		}
		if (AfrCliHttpDetect::isCli()) {
			return null;
		}
		return ($mData = file_get_contents('php://input')) === false ? null : $mData;
	}

	/**
	 * Set php stdin mock.
	 * @param string|null $php_stdin_mock
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function setPhpStdinMock(?string $php_stdin_mock = null): AfrRequestInterface
	{
		if (($this->aMock['php://stdin'] = $this->isCli() ? $php_stdin_mock : null) === null) {
			unset($this->aMock['php://stdin']);
		}
		return $this;
	}

	/**
	 * Get php stdin.
	 */
	public function getPhpStdin(): ?string
	{
		//TODO: https://www.php.net/manual/en/function.stream-wrapper-register.php
		return $this->aMock['php://stdin'] ?? (
		AfrCliHttpDetect::isCli() ?
			(($mData = file_get_contents('php://stdin')) === false ? null : $mData) :
			null
		);
	}


	/**
	 * Get php sapi.
	 */
	public function getPhpSapi(): string
	{
		return $this->php_sapi;
	}

	/**
	 * Set php sapi.
	 * @param string $php_sapi
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function setPhpSapi(string $php_sapi): AfrRequestInterface
	{
		$this->php_sapi = $php_sapi;
		return $this;
	}

	/**
	 * Getopt.
	 * @param string $short_options
	 * @param array $long_options
	 * @param int|null $rest_index
	 * @return array|false
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 * @throws AfrException
	 */
	public function getopt(string $short_options, array $long_options = [], int &$rest_index = null)
	{
		if (!$this->isCli()) {
			return false;
		}
		return AfrGetOpt::getInstance()->setArgvFromRequest($this)->getopt(
			$short_options,
			$long_options,
			$rest_index
		);
	}

	/**
	 * Getopt detect all args.
	 * @return array|false
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 * @throws AfrException
	 */
	public function getoptDetectAllArgs()
	{
		if (!$this->isCli()) {
			return false;
		}
		return AfrGetOpt::getInstance()->setArgvFromRequest($this)->getoptDetectAllArgs(null, true);
	}


	/**
	 * Detect argv key presence.
	 * @param string $sArgvKey
	 * @return array [true|false, null|$sValue];
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 * @throws AfrException
	 */
	public function detectArgvKeyPresence(string $sArgvKey): array
	{
		$bMatched = false;
		$sDetectVal = null;
		if ($this->isCli()) {

			$aAllArgs = AfrGetOpt::getInstance()->setArgvFromRequest($this)->getoptDetectAllArgs(null, true);
			return [array_key_exists($sArgvKey,$aAllArgs), $aAllArgs[$sArgvKey] ?? null];
			//TODO: cleanup dupa ce testez cu AfrGetOpt la detect cu whildcard

			$iKeyLen = strlen($sArgvKey);
			foreach ($this->getServerParam('argv', []) as $sBlockValue) {
				if ($sBlockValue === $sArgvKey) {
					$bMatched = true;
					break;
				} elseif (substr($sBlockValue, 0, $iKeyLen + 1) === $sArgvKey . '=') {
					$bMatched = true;
					$sDetectVal = trim(substr($sBlockValue, $iKeyLen + 1));
					break;
				}
			}
			if (!$bMatched) {
				$mQaOpt = $this->getopt('', [$sArgvKey . '::'])[$sArgvKey] ?? null;
				if ($mQaOpt !== null) {
					$bMatched = true;
					$sDetectVal = $mQaOpt !== false && strlen((string)$mQaOpt) ? (string)$mQaOpt : null;
				}
			}
		}
		return [$bMatched, $sDetectVal];
	}
}

