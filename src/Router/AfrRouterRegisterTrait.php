<?php

namespace Autoframe\Core\Router;

use Autoframe\Core\Event\AfrEvent;
use Autoframe\Core\Event\Exception\AfrEventException;
use Autoframe\Core\Http\Header\AfrHttpHeader;
use Autoframe\Core\Http\Request\AfrHttpConstantsInterface;
use Autoframe\Core\Http\Request\AfrRequestInterface;
use Autoframe\Core\Router\Exception\AfrRouterException;
use Autoframe\Core\AfrCoreModules\FnContracts\AfrHttpRoutesContract;
use Closure;

trait AfrRouterRegisterTrait
{

	protected string $baseRoute = ''; //Current base route, used for (sub)route mounting

	/**
	 * Mounts a collection of callbacks onto a base route.
	 *
	 * @param string $baseRoute The route sub pattern to mount the callbacks on
	 * @param Closure|callable $fn The callback method
	 */
	public function registerMountToBaseRoute(string $baseRoute, $fn): self
	{
		$curBaseRoute = $this->baseRoute; // Track current base route

		//$this->baseRoute .= $baseRoute;
		$this->baseRoute .= rtrim($baseRoute, '/'); // Build new base route string

		$fn instanceof Closure ? ($fn->bindTo($this))($this) : $fn($this); //call_user_func($fn);

		$this->baseRoute = $curBaseRoute; // Restore original base route
		return $this;
	}

	/**
	 * Shorthand for a route accessed using any method.
	 *
	 * @param string $pattern A route pattern such as /about/system
	 * @param Closure|callable $fn The handling function to be executed
	 * @throws AfrRouterException|AfrEventException
	 */
	public function registerRouteAllMethods(string $pattern, $fn, array $routeOptions = []): self
	{
		return $this->registerCodeRoute(AfrHttpRoutesContract::AllowedHTTPRequestMethods, $pattern, $fn, $routeOptions);
	}

	/**
	 * @throws AfrRouterException|AfrEventException
	 */
	public function registerRouteAnyMethod(string $pattern, $fn, array $routeOptions = []): self
	{
		return $this->registerCodeRoute(AfrHttpRoutesContract::AllowedHTTPRequestMethods, $pattern, $fn, $routeOptions);
	}

	/**
	 * Shorthand for a route accessed using GET.
	 *
	 * @param string $pattern A route pattern such as /about/system
	 * @param object|callable $fn The handling function to be executed
	 * @throws AfrRouterException|AfrEventException
	 */
	public function registerRouteGetMethod(string $pattern, $fn, array $routeOptions = []): self
	{
		return $this->registerCodeRoute([AfrHttpRoutesContract::GET], $pattern, $fn, $routeOptions);
	}

	/**
	 * Shorthand for a route accessed using POST.
	 *
	 * @param string $pattern A route pattern such as /about/system
	 * @param object|callable $fn The handling function registerRouteMethod to be executed
	 * @throws AfrRouterException|AfrEventException
	 */
	public function registerRoutePostMethod(string $pattern, $fn, array $routeOptions = []): self
	{
		return $this->registerCodeRoute([AfrHttpRoutesContract::POST], $pattern, $fn, $routeOptions);
	}

	/**
	 * Shorthand for a route accessed using PATCH.
	 *
	 * @param string $pattern A route pattern such as /about/system
	 * @param object|callable $fn The handling function to be executed
	 * @throws AfrRouterException|AfrEventException
	 */
	public function registerRoutePatchMethod(string $pattern, $fn, array $routeOptions = []): self
	{
		return $this->registerCodeRoute([AfrHttpRoutesContract::PATCH], $pattern, $fn, $routeOptions);
	}

	/**
	 * Shorthand for a route accessed using DELETE.
	 *
	 * @param string $pattern A route pattern such as /about/system
	 * @param object|callable $fn The handling function to be executed
	 * @throws AfrRouterException|AfrEventException
	 */
	public function registerRouteDeleteMethod(string $pattern, $fn, array $routeOptions = []): self
	{
		return $this->registerCodeRoute([AfrHttpRoutesContract::DELETE], $pattern, $fn, $routeOptions);
	}

	/**
	 * Shorthand for a route accessed using PUT.
	 *
	 * @param string $pattern A route pattern such as /about/system
	 * @param object|callable $fn The handling function to be executed
	 * @throws AfrRouterException|AfrEventException
	 */
	public function registerRoutePutMethod(string $pattern, $fn, array $routeOptions = []): self
	{
		return $this->registerCodeRoute([AfrHttpRoutesContract::PUT], $pattern, $fn, $routeOptions);
	}

	/**
	 * Shorthand for a route accessed using OPTIONS.
	 *
	 * @param string $pattern A route pattern such as /about/system
	 * @param object|callable $fn The handling function to be executed
	 * @throws AfrRouterException|AfrEventException
	 */
	public function registerRouteOptionsMethod(string $pattern, $fn, array $routeOptions = []): self
	{
		return $this->registerCodeRoute([AfrHttpRoutesContract::OPTIONS], $pattern, $fn, $routeOptions);
	}


	/**
	 * Store a middleware route and a handling function to be executed when accessed using one of the specified methods.
	 *
	 * @param array $methods ['GET','POST',...]
	 * @param string $pattern REGEX pattern such as /about/system
	 * @param object|callable $fn The handling function to be executed
	 * @param array $routeOptions
	 * @return AfrRouter
	 * @throws AfrRouterException|AfrEventException
	 */
	public function registerMiddlewareRoute(array $methods, string $pattern, $fn, array $routeOptions = []): self
	{
		return $this->registerRouteTypeMethods(static::MIDDLEWARE_ROUTE, $methods, $pattern, $fn, $routeOptions);
	}


	/**
	 * @param array $methods ['GET','POST',...]
	 * @param string $pattern REGEX pattern such as /about/system
	 * @param $fn
	 * @param array $routeOptions
	 * @return AfrRouterRegisterTrait|AfrRouter
	 * @throws AfrEventException
	 * @throws AfrRouterException
	 */
	public function registerCodeRoute(array $methods, string $pattern, $fn, array $routeOptions = []): self
	{
		return $this->registerRouteTypeMethods(static::CODE_ROUTE, $methods, $pattern, $fn, $routeOptions);
	}

	/**
	 * @param array $methods ['GET','POST',...]
	 * @param string $pattern REGEX pattern such as /about/system
	 * @param $fn
	 * @param array $routeOptions
	 * @return AfrRouterRegisterTrait|AfrRouter
	 * @throws AfrEventException
	 * @throws AfrRouterException
	 */
	public function registerAfterRoute(array $methods, string $pattern, $fn, array $routeOptions = []): self
	{
		return $this->registerRouteTypeMethods(static::AFTER_ROUTE, $methods, $pattern, $fn, $routeOptions);
	}


	/**
	 * @param string $sType
	 * @param array $aHTTP_methods
	 * @param string $pattern
	 * @param $fn
	 * @param array $routeOptions
	 * @return self
	 * @throws AfrRouterException|AfrEventException
	 */
	protected function registerRouteTypeMethods(string $sType, array $aHTTP_methods, string $pattern, $fn, array $routeOptions = []): self
	{
		AfrEvent::dispatchEvent();

		if (!in_array($sType, static::HTTP_ROUTE_TYPES)) {
			throw new AfrRouterException('Invalid route type: ' . $sType);
		}

		//fix short wildcard
		if ($pattern === '*') $pattern = '/.*';

		//used in mount method
		$pattern = $this->baseRoute . '/' . trim($pattern, '/');
		$pattern = $this->baseRoute ? rtrim($pattern, '/') : $pattern;

		// WTF? //TODO cleanup
		//	$routeOptions = array_filter($routeOptions, function ($v) { return (bool)$v; }); //optimise for print_r
		//	$routeOptions = array_map(function ($v) { return is_array($v) ? implode("; ", $v) : $v; }, $routeOptions); //optimise for print_r

		$fnStack_I = is_object($fn) ? 'o'.spl_object_id($fn) : 'k'.count($this->aFnStack);
		//$fnStack_I = count($this->aFnStack);
		$this->aFnStack[$fnStack_I] = [$fn, $routeOptions];

		$bAnyRoute = empty($aHTTP_methods) || $aHTTP_methods === AfrHttpConstantsInterface::AllowedHTTPRequestMethods;
		if ($bAnyRoute) {
			$aHTTP_methods = AfrHttpConstantsInterface::AllowedHTTPRequestMethods;
		}
		foreach ($aHTTP_methods as $method) {
			if (!$bAnyRoute && !in_array($method, AfrHttpConstantsInterface::AllowedHTTPRequestMethods)) {
				throw new AfrRouterException('Invalid route request method: ' . $method);
			}
			$route = [
				'pattern' => $pattern,
				'fnStack_I' => $fnStack_I,
			];

			if ($sType === static::MIDDLEWARE_ROUTE) {
				$this->aMiddlewareRoutes[$method][] = $route;
			} elseif ($sType === static::AFTER_ROUTE) {
				$this->aAfterRoutes[$method][] = $route;
			} else {
				$this->aCodeRoutes[$method][] = $route;
			}
		}
		return $this;
	}


	/**
	 * @throws AfrRouterException|AfrEventException
	 */
	public function registerRedirectMiddleware(string $fromPattern, string $toFixed, int $code = 302, bool $strip_params = false, array $build_query = []): self
	{
		if ($fromPattern == $toFixed) {
			return $this;
		}
		return $this->registerMiddlewareRoute(AfrHttpRoutesContract::AllowedHTTPRequestMethods, $fromPattern, function () use ($toFixed, $code, $strip_params, $build_query) {
			AfrHttpHeader::getInstance()->headerRedirect3xx($code, $toFixed, $strip_params, $build_query);
		});
	}

	/**
	 * @throws AfrRouterException|AfrEventException
	 */
	public function registerRedirectPermanentMiddleware(string $fromPattern, string $toFixed): self
	{
		if ($fromPattern == $toFixed) {
			return $this;
		}
		return $this->registerRedirectMiddleware($fromPattern, $toFixed, 301);
	}


}