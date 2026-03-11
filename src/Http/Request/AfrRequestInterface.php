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
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function setThisRequestAsAppRequestInstance(): AfrRequestInterface;

	/**
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function restoreOriginalAppRequestInstance(): AfrRequestInterface;


	/**
	 * @param bool $bHttps
	 * @return AfrRequestClass|AfrRequestInterface
	 * @throws AfrHttpRequestException
	 */
	public function convertToHTTPS(bool $bHttps);

	public function isCli(): bool;

	public function isHttpPostRequest(): bool;

	public function isHttpGetRequest(): bool;

	public function isHttpHeadRequest(): bool;

	public function isHttpPutRequest(): bool;

	public function isHttpOptionsRequest(): bool;

	public function isHttpPatchRequest(): bool;

	public function isHttpDeleteRequest(): bool;

	public function getHttpRequestMethod(): ?string;

	public function getHttpRequestMethodOriginal(): ?string;

	public function getHttpRequestUri(): ?string;

	public function getCliArgs(): ?array;

	public function getArgvParam(): array;

	public function setArgvParam(array $argv): AfrRequestInterface;

	public function getQueryParam(string $key, $default = null);

	/**
	 * @param string $key
	 * @param $value
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function setQueryParam(string $key, $value): AfrRequestInterface;

	/**
	 * @param string $key
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function unsetQueryParam(string $key): AfrRequestInterface;

	public function hasQueryParam(string $key): bool;

	public function getPostParam(string $key, $default = null);

	/**
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function setPostParam(string $key, $value): AfrRequestInterface;

	/**
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function unsetPostParam(string $key): AfrRequestInterface;

	public function hasPostParam(string $key): bool;

	public function getFileParam(string $key, $default = null);

	/**
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function setFileParam(string $key, $value): AfrRequestInterface;

	/**
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function unsetFileParam(string $key): AfrRequestInterface;

	public function hasFileParam(string $key): bool;

	public function getServerParam(string $key, $default = null);

	/**
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function setServerParam(string $key, $value): AfrRequestInterface;

	/**
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function unsetServerParam(string $key): AfrRequestInterface;

	public function hasServerParam(string $key): bool;

	public function getCookieParam(string $key, $default = null);

	/**
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function setCookieParam(string $key, $value): AfrRequestInterface;

	/**
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function unsetCookieParam(string $key): AfrRequestInterface;

	public function hasCookieParam(string $key): bool;

	public function getAllGetParams(): array;

	public function getAllPostParams(): array;

	public function getAllFileParams(): array;

	public function getAllServerParams(): array;

	public function getRequestParam(string $key, $default = null);

	/**
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function setRequestParam(string $key, $value): AfrRequestInterface;

	/**
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function unsetRequestParam(string $key): AfrRequestInterface;

	public function hasRequestParam(string $key): bool;

	public function getAllRequestParams(): array;

	public function getAllCookieParams(): array;

	/**
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function setPhpInputMock(?string $php_input_mock): AfrRequestInterface;

	public function getPhpInput(): ?string;

	/**
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function setPhpStdinMock(?string $php_stdin_mock = null): AfrRequestInterface;

	public function getPhpStdin(): ?string;

	public function getPhpSapi(): string;

	/**
	 * @return AfrRequestClass|AfrRequestInterface
	 */
	public function setPhpSapi(string $php_sapi): AfrRequestInterface;

	/**
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
	 * @return array|false
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 * @throws AfrException
	 */
	public function getoptDetectAllArgs();

	/**
	 * @param string $sArgvKey
	 * @return array [true|false, null|$sValue];
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 * @throws AfrException
	 */
	public function detectArgvKeyPresence(string $sArgvKey): array;
}