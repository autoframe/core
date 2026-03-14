<?php

namespace Autoframe\Core\Http\Request;

use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\Env\Exception\AfrEnvException;
use Autoframe\Core\Event\Exception\AfrEventException;
use Autoframe\Core\Exception\AfrException;
use Autoframe\Core\Http\Request\Exception\AfrHttpRequestException;

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
interface AfrRequestInterface
{

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
	): AfrRequestInterface;


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
	): AfrRequestInterface;

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
	): AfrRequestInterface;

	/**
	 * Set this request as app request instance.
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function setThisRequestAsAppRequestInstance(): AfrRequestInterface;

	/**
	 * Restore original app request instance.
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function restoreOriginalAppRequestInstance(): AfrRequestInterface;


	/**
	 * Convert to https.
	 * @param bool $bHttps
	 * @return AfrRequestClass|AfrRequestInterface
	 * @throws AfrHttpRequestException
	 */
	public function convertToHTTPS(bool $bHttps);

	/**
	 * Is cli.
	 */
	public function isCli(): bool;

	/**
	 * Is http post request.
	 */
	public function isHttpPostRequest(): bool;

	/**
	 * Is http get request.
	 */
	public function isHttpGetRequest(): bool;

	/**
	 * Is http head request.
	 */
	public function isHttpHeadRequest(): bool;

	/**
	 * Is http put request.
	 */
	public function isHttpPutRequest(): bool;

	/**
	 * Is http options request.
	 */
	public function isHttpOptionsRequest(): bool;

	/**
	 * Is http patch request.
	 */
	public function isHttpPatchRequest(): bool;

	/**
	 * Is http delete request.
	 */
	public function isHttpDeleteRequest(): bool;

	/**
	 * Get http request method.
	 */
	public function getHttpRequestMethod(): ?string;

	/**
	 * Get http request method original.
	 */
	public function getHttpRequestMethodOriginal(): ?string;

	/**
	 * Get http request uri.
	 */
	public function getHttpRequestUri(): ?string;

	/**
	 * Get cli args.
	 */
	public function getCliArgs(): ?array;

	/**
	 * Get argv param.
	 */
	public function getArgvParam(): array;

	/**
	 * Set argv param.
	 */
	public function setArgvParam(array $argv): AfrRequestInterface;

	/**
	 * Get query param.
	 */
	public function getQueryParam(string $key, $default = null);

	/**
	 * Set query param.
	 * @param string $key
	 * @param $value
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function setQueryParam(string $key, $value): AfrRequestInterface;

	/**
	 * Unset query param.
	 * @param string $key
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function unsetQueryParam(string $key): AfrRequestInterface;

	/**
	 * Has query param.
	 */
	public function hasQueryParam(string $key): bool;

	/**
	 * Get post param.
	 */
	public function getPostParam(string $key, $default = null);

	/**
	 * Set post param.
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function setPostParam(string $key, $value): AfrRequestInterface;

	/**
	 * Unset post param.
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function unsetPostParam(string $key): AfrRequestInterface;

	/**
	 * Has post param.
	 */
	public function hasPostParam(string $key): bool;

	/**
	 * Get file param.
	 */
	public function getFileParam(string $key, $default = null);

	/**
	 * Set file param.
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function setFileParam(string $key, $value): AfrRequestInterface;

	/**
	 * Unset file param.
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function unsetFileParam(string $key): AfrRequestInterface;

	/**
	 * Has file param.
	 */
	public function hasFileParam(string $key): bool;

	/**
	 * Get server param.
	 */
	public function getServerParam(string $key, $default = null);

	/**
	 * Set server param.
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function setServerParam(string $key, $value): AfrRequestInterface;

	/**
	 * Unset server param.
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function unsetServerParam(string $key): AfrRequestInterface;

	/**
	 * Has server param.
	 */
	public function hasServerParam(string $key): bool;

	/**
	 * Get cookie param.
	 */
	public function getCookieParam(string $key, $default = null);

	/**
	 * Set cookie param.
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function setCookieParam(string $key, $value): AfrRequestInterface;

	/**
	 * Unset cookie param.
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function unsetCookieParam(string $key): AfrRequestInterface;

	/**
	 * Has cookie param.
	 */
	public function hasCookieParam(string $key): bool;

	/**
	 * Get all get params.
	 */
	public function getAllGetParams(): array;

	/**
	 * Get all post params.
	 */
	public function getAllPostParams(): array;

	/**
	 * Get all file params.
	 */
	public function getAllFileParams(): array;

	/**
	 * Get all server params.
	 */
	public function getAllServerParams(): array;

	/**
	 * Get request param.
	 */
	public function getRequestParam(string $key, $default = null);

	/**
	 * Set request param.
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function setRequestParam(string $key, $value): AfrRequestInterface;

	/**
	 * Unset request param.
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function unsetRequestParam(string $key): AfrRequestInterface;

	/**
	 * Has request param.
	 */
	public function hasRequestParam(string $key): bool;

	/**
	 * Get all request params.
	 */
	public function getAllRequestParams(): array;

	/**
	 * Get all cookie params.
	 */
	public function getAllCookieParams(): array;

	/**
	 * Set php input mock.
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function setPhpInputMock(?string $php_input_mock): AfrRequestInterface;

	/**
	 * Get php input.
	 */
	public function getPhpInput(): ?string;

	/**
	 * Set php stdin mock.
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function setPhpStdinMock(?string $php_stdin_mock = null): AfrRequestInterface;

	/**
	 * Get php stdin.
	 */
	public function getPhpStdin(): ?string;

	/**
	 * Get php sapi.
	 */
	public function getPhpSapi(): string;

	/**
	 * Set php sapi.
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function setPhpSapi(string $php_sapi): AfrRequestInterface;

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
	public function getopt(string $short_options, array $long_options = [], int &$rest_index = null);

	/**
	 * Getopt detect all args.
	 * @return array|false
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 * @throws AfrException
	 */
	public function getoptDetectAllArgs();

	/**
	 * Detect argv key presence.
	 * @param string $sArgvKey
	 * @return array [true|false, null|$sValue];
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 * @throws AfrException
	 */
	public function detectArgvKeyPresence(string $sArgvKey): array;
}
