<?php

namespace Autoframe\Core\Router;

use Autoframe\Core\Afr\Afr;
use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\Env\Exception\AfrEnvException;
use Autoframe\Core\Event\Exception\AfrEventException;
use Autoframe\Core\Http\Header\AfrHttpHeader;
use Autoframe\Core\Http\Header\Exception\AfrHttpHeaderException;
use Autoframe\Core\Http\Request\AfrRequestInterface;
use Autoframe\Core\Router\Exception\AfrRouterException;
use Closure;

trait AfrRouterHandleTrait
{
	/**
	 * The first n routes will be executed, the remaining routes will be skipped
	 * @var int[]
	 */
	protected array $aMaxHandledRoutesOfType = [
		self::MIDDLEWARE_ROUTE => 50,
		self::CODE_ROUTE => 1,
		self::AFTER_ROUTE => 20,
	];

	/**
	 * @param AfrRequestInterface $oRequest
	 * @param Closure|null $oClosureAfterRoute
	 * @return int
	 * @throws AfrContainerException
	 * @throws AfrEnvException
	 * @throws AfrEventException
	 * @throws AfrHttpHeaderException
	 * @throws AfrRouterException
	 */
	public function __invoke(AfrRequestInterface $oRequest, Closure $oClosureAfterRoute = null): int
	{
		return $this->run($oRequest, $oClosureAfterRoute);
	}

	/**
	 * @param AfrRequestInterface $oRequest
	 * @param Closure|null $oClosureAfterRoute
	 * @return int
	 * @throws AfrContainerException
	 * @throws AfrEnvException
	 * @throws AfrEventException
	 * @throws AfrHttpHeaderException
	 * @throws AfrRouterException
	 */
	protected function run(AfrRequestInterface $oRequest, Closure $oClosureAfterRoute = null): int
	{
		//todo: to improve the CLI console script call because the request method can be set twice from the InitRequest() and by parameter. This muste be tested!!!
		//	echo '<pre>'.print_r(thfRouter::getRequestConfig(),true).'</pre>';

		if ($oRequest->isCli()) {
			//return AfrCliRouterHelper::run();
			$bIsQa = false;
			$sQaIndexStack = null;
			$iQaKeyLen = strlen(self::QA_ARGV_KEY);
			foreach ($oRequest->getServerParam('argv', []) as $sValue) {
				if ($sValue === self::QA_ARGV_KEY) {
					$bIsQa = true;
					break;
				} elseif (substr($sValue, 0, $iQaKeyLen + 1) === self::QA_ARGV_KEY . '=') {
					$bIsQa = true;
					$sQaIndexStack = substr($sValue, $iQaKeyLen + 1);
					break;
				}
			}
			if (!$bIsQa) {
				$mQaOpt = $oRequest->getopt('', ['QA::'])['QA'] ?? null;
				if ($mQaOpt !== null) {
					$bIsQa = true;
					$sQaIndexStack = $mQaOpt !== false && strlen((string)$mQaOpt) ? (string)$mQaOpt : null;
				}
			}
			if($bIsQa){
				(new CliCache())->getActions(); //todo remove dupa ce mut in module de QA si fac bootstrap
				return AfrCliRouterHelper::run($sQaIndexStack);
			}


			return 0;
			if (rand(1, 5) > 8) {


				die('CLI TODO implemenare ' . __FILE__ . PHP_EOL); //TODO
			}
			return 0;

		} else {
			return $this->dispatchHttpRoute($oRequest, $oClosureAfterRoute);
		}
	}


	/**
	 *  Execute the router: Loop all defined middlewares, code routes, and after routes and execute the handling function if a match was found.
	 * @param AfrRequestInterface $oRequest
	 * @param Closure|null $oClosureAfterRoute
	 * @return int
	 * @throws AfrContainerException
	 * @throws AfrEnvException
	 * @throws AfrEventException
	 * @throws AfrHttpHeaderException
	 * @throws AfrRouterException
	 */
	public function dispatchHttpRoute(AfrRequestInterface $oRequest, Closure $oClosureAfterRoute = null): int
	{
		$sRequestMethodOriginal = $oRequest->getHttpRequestMethod();
		if ($oRequest->isCli()) {
			throw new AfrRouterException('There is no http route to dispatch from a CLI request!');
		} elseif (!in_array($sRequestMethodOriginal, self::ALLOWED_METHODS_HTTP)) {
			$this->methodNotAllowed($oRequest, self::$haltOn405MethodNotAllowed);
		}

		//correction for HEAD to GET
		$this->correctHeadRequestMethod($oRequest, true);
		//correction for POST to X-HTTP-Method-Override into ['PUT', 'DELETE', 'PATCH']
		$this->correctXHttpMethodOverwriteRequestMethod($oRequest);

		$sRequestMethod = $oRequest->getHttpRequestMethod();
		$sRequestRouteURI = $oRequest->getHttpRequestUri();


		// Handle all middleware middlewares
		if (!empty($this->aMiddlewareRoutes[$sRequestMethod])) {
			$this->handle(
				$this->aMiddlewareRoutes[$sRequestMethod],
				static::MIDDLEWARE_ROUTE,
				$sRequestRouteURI
			);
		}

		if (empty($this->aCodeRoutes[$sRequestMethod]) /*&& $sRequestMethod !== 'GET'*/) {
			$this->methodNotAllowed($oRequest, self::$haltOn405MethodNotAllowed);
		}

		// Handle all routes
		$iCodeRoutesHandled = $this->handle(
			$this->aCodeRoutes[$sRequestMethod] ?? [],
			static::CODE_ROUTE,
			$sRequestRouteURI
		);

		// If no route was handled, trigger the 404 (if any)
		if ($iCodeRoutesHandled === 0) {
			AfrHttpHeader::getInstance()->h404('', false);
			if ($oCl404 = $this->getHttpStatusHandler(404)) {
				$this->invokeRoute($oCl404, [$oRequest]);
			} else {
				echo '<h1>Page not found! 404</h1> ' . $sRequestRouteURI;
			}
		} // If a route was handled, perform the finish callback (if any)
		else {
			// Handle all after route comands
			if (!empty($this->aAfterRoutes[$sRequestMethod])) {
				$this->handle($this->aAfterRoutes[$sRequestMethod], static::AFTER_ROUTE, $sRequestRouteURI);
			}
		}
		self::correctHeadRequestMethod($oRequest); //second run ob_end_clean() for head

		if ($oClosureAfterRoute) {
			$oClosureAfterRoute->bindTo($this)();
		}
		return $iCodeRoutesHandled; // Return true if a route was handled, false otherwise
	}


	/**
	 * Handle a set of routes and return the number handeled
	 * @param array $routes
	 * @param string $sRoute_MCA
	 * @param string $sRequestUri
	 * @return int
	 * @throws AfrContainerException
	 */
	protected function handle(array $routes, string $sRoute_MCA, string $sRequestUri): int
	{
		$numHandled = 0;
		$imaxRoutesOfType = $this->aMaxHandledRoutesOfType[$sRoute_MCA];
		// Loop all routes
		foreach ($routes as $route) {
			$matchResult = $this->patternMarch($route['pattern'], $sRequestUri);
			if ($matchResult['mPositiveMatch']) {
				if (self::invokeRoute($route, $matchResult['aParams'])) {
					++$numHandled;
				}
				if (($imaxRoutesOfType--) < 1) { // $maxRoutes of type has been reached
					break;
				}
			}
		}

		return $numHandled;
	}


	public function patternMarch(string $pattern, string $sRequestUri): array
	{
		$matches = [];
		$params = null;

		// Replace all curly braces matches {} into word patterns (like Laravel)
		$pattern = '#^' . preg_replace('/\/{(.*?)}/', '/(.*?)', $pattern) . '$#';

		$mPositiveMatch = preg_match_all($pattern, $sRequestUri, $matches, PREG_OFFSET_CAPTURE);

		if ($mPositiveMatch) {
			// Rework matches to only contain the matches, not the orig string
			$matches = array_slice($matches, 1);

			// Extract the matched URL parameters (and only the parameters)
			$params = array_map(function ($match, $index) use ($matches) {

				// We have a following parameter: take the substring from the current param position until the next one's position (thank you PREG_OFFSET_CAPTURE)
				if (isset($matches[$index + 1][0]) && is_array($matches[$index + 1][0])) {
					return trim(substr($match[0][0], 0, $matches[$index + 1][0][1] - $match[0][1]), '/');
				} // We have no following parameters: return the whole lot

				return isset($match[0][0]) ? trim($match[0][0], '/') : null;
			}, $matches, array_keys($matches));
		}

		return [
			'sConvertedPattern' => $pattern,
			'mPositiveMatch' => $mPositiveMatch,
			'aParams' => $params,
			'aMatches' => $matches,
		];

	}


	protected function correctHeadRequestMethod(AfrRequestInterface $oRequest, bool $ob_start = true): void
	{
		// If it's a HEAD request override it to being GET and prevent any output, as per HTTP Specification
		// @url http://www.w3.org/Protocols/rfc2616/rfc2616-sec9.html#sec9.4
		if ($oRequest->isHttpHeadRequest()) {
			//if($ob_start){ ob_start();}
			$oRequest
				->setServerParam('REQUEST_METHOD_ORIGINAL_BUFFERED', $ob_start && ob_start())
				->setServerParam('REQUEST_METHOD', 'GET');
		} elseif (
			$oRequest->getHttpRequestMethodOriginal() === 'HEAD' &&
			$oRequest->getHttpRequestMethod() === 'GET'
		) {
			// If it originally was a HEAD request, clean up after ourselves by emptying the output buffer
			if ($oRequest->getServerParam('REQUEST_METHOD_ORIGINAL_BUFFERED')) {
				ob_end_clean();
			}
		}
	}

	protected function correctXHttpMethodOverwriteRequestMethod(AfrRequestInterface $oRequest): ?string
	{
		// If it's a POST request, check for a method override header
		if ($oRequest->getHttpRequestMethod() === 'POST') {
			$sXOverwrite = strtoupper($oRequest->getServerRequestHeaders()['X-HTTP-Method-Override'] ?? '');
			if (in_array($sXOverwrite, ['PUT', 'DELETE', 'PATCH'])) {
				$oRequest->setServerParam('X-HTTP-Method-Override', $sXOverwrite);
				return $sXOverwrite;
			}
		}
		return $oRequest->getHttpRequestMethod();
	}

	/**
	 * @param string $sRequestMethod
	 * @param bool $bExit
	 * @return void
	 * @throws AfrRouterException
	 * @throws AfrContainerException
	 * @throws AfrEnvException
	 * @throws AfrEventException
	 * @throws AfrHttpHeaderException
	 */
	protected function methodNotAllowed(AfrRequestInterface $oRequest, bool $bExit = true): void
	{
		AfrHttpHeader::getInstance()->setHttpResponseCode(405);
		$sRequestMethod = $oRequest->getHttpRequestMethod();

		if ($oCl405 = $this->getHttpStatusHandler(405)) {
			$this->invokeRoute($oCl405, [$oRequest]);
		} else {
			echo '405 Method Not Allowed: ' . $sRequestMethod;
		}
		if ($bExit) {
			throw new AfrRouterException("HTTP request method `$sRequestMethod` not allowed!");
		}
	}


	/**
	 * @param array|string|Closure|callable $fn 'ns\class@ method'|Closure|function
	 * @param array $params
	 * @return bool|mixed
	 * @throws AfrContainerException
	 */
	protected function invokeRoute($fn, array $params = [])
	{
		$aRouteInfo = null; //aRouteInfo array with parametrization options
		if (!empty($fn['fnStack_I'])) {
			$aRouteInfo = $fn;
			$params['aRegisterRouteOptions'] = $this->aFnStack[$fn['fnStack_I']][1];
			$fn = $this->aFnStack[$fn['fnStack_I']][0];
		}
		if (!empty($aRouteInfo['aRegisterRouteOptions']['redirect'])) {
			die('redirect neimplementat in router');
		}

		return self::invokeRouteMethod($fn, $params, true);

	}


	/**
	 * @param Closure|string|callable $fn 'ns\class@method'|Closure|function
	 * @param array $params
	 * @param string $namespace
	 * @param bool $bForceBoolReturn
	 * @return bool|mixed
	 * @throws AfrContainerException
	 */

	protected function invokeRouteMethod(
		$fn,
		array $params = [],
		bool $bForceBoolReturn = true
	)
	{
		if ($fn instanceof Closure || is_callable($fn)) {
			// Returns the return value of the callback, or FALSE on error.
			//$r = $fn instanceof Closure ? $fn(...$params) : call_user_func_array($fn, $params) ;
			$r = $fn(...$params);
			if ($bForceBoolReturn && !$r && $r !== false) {
				$r = true; //fix void|null|''|0|'0' values for functions to avoid 404 in router
			}
			return $r;
		} // If not, check the existence of special parameters
		elseif (is_string($fn) && stripos($fn, '@') !== false) {
			// Explode segments of given route
			list($controller, $method) = explode('@', $fn);
			// Adjust controller class if namespace has been set

			try {
				$instance = Afr::app()->container()->get($controller);
			} catch (AfrContainerException $e) {
				if ($e->getCode() == 22) {
					$instance = false;
				} else {
					throw $e;
				}
			}
			//TODO verifica daca exista metoda si este statica
			//TODO return asa cum vine de la ruta, cu flag daca s-a rulat

			// Check if class exists, if not just ignore and check if the class exists on the default namespace
			if (!empty($instance) || class_exists($controller)) {
				// First check if is a static method, directly trying to invoke it.
				// If isn't a valid static method, we will try as a normal method invocation.
				$call_user_func_array = !empty($instance) ? call_user_func_array([$instance, $method], $params) : false;
				if ($call_user_func_array === false) {
					// Try to call the method as an non-static method. (the if does nothing, only avoids the notice)
					$forward_static_call_array = forward_static_call_array([$controller, $method], $params);
					if ($forward_static_call_array === false) {
						return false; //class exists but method not found
					} else {
						return $bForceBoolReturn ? true : $forward_static_call_array;
					}//static method was called
				} else {
					return $bForceBoolReturn ? true : $call_user_func_array;
				}//method was called
			} else {
				return false;
			}//class does not exist
		}
		return false;//general exception
	}

	/**
	 * @param string $sRouteType
	 * @param int $iMaxLoops
	 * @return $this
	 * @throws AfrRouterException
	 */
	public function setMaxHandledRoutesOfType(string $sRouteType, int $iMaxLoops): self
	{
		if (!isset($this->aMaxHandledRoutesOfType[$sRouteType]) || $iMaxLoops < 0) {
			throw new AfrRouterException(
				"Invalid max loop limit for maxRoutes[`$sRouteType`] = `$iMaxLoops`"
			);
		}
		$this->aMaxHandledRoutesOfType[$sRouteType] = $iMaxLoops;
		return $this;
	}

}