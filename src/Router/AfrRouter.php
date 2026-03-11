<?php

namespace Autoframe\Core\Router;

use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;
use Autoframe\Core\Event\AfrEvent;
use Autoframe\Core\Event\Exception\AfrEventException;
use Autoframe\Core\Http\Header\AfrHttpHeader;
use Autoframe\Core\Http\Header\AfrHttpStatusCode;
use Autoframe\Core\Http\Request\AfrRequestClass;
use Autoframe\Core\ModuleBox\Exception\AfrModuleException;
use Autoframe\Core\Router\Contracts\AfrRouterConstantsInterface;
use Autoframe\Core\Router\Contracts\AfrRouterInterface;
use Autoframe\Core\Router\Exception\AfrRouterException;
use Autoframe\Core\String\Obj\AfrClosureToStr;
use Closure;

/**
 * TODO
 * - Routerul citeste si inregistreaza metode din module
 * - Routerul cheama controlerele si metodele din ele
 * - Ruter => ctrl => metoda_get / post / any
 * - api / cli /http /qa routes
 * - middleware - code - after
 * - render / output support !!!
 *  Inspiration: https://github.com/bramus/router/tree/master
 * TODO CRSF: SimpleRouter::csrfVerifier(new \Demo\Middlewares\CsrfVerifier());
 */
class AfrRouter extends AfrSingletonAbstractClass implements AfrRouterInterface, AfrRouterConstantsInterface
{
	use AfrRouterRegisterTrait;
	use AfrRouterHandleTrait;
	use AfrRouterHandleCliTrait;

	public static bool $haltOn405MethodNotAllowed = true;
	protected static array $aStateHandlers = [];

	protected array $aFnStack = []; //variable that keeps the Closures route functions
	protected array $aMiddlewareRoutes = []; //middleware
	protected array $aCodeRoutes = []; //main routes
	protected array $aAfterRoutes = []; //run after code routes

	public function setHttpStatusHandler(int $iHttpStatus, ?Closure $oClosure): self //TODO
	{
		static::$aStateHandlers[$iHttpStatus] = $oClosure;
		return $this;
	}

	public function getHttpStatusHandler(int $iHttpStatus): ?Closure
	{
		$this->initDefaultHttpStatusHandlers();
		return static::$aStateHandlers[$iHttpStatus] ?? null;
	}

	protected function initDefaultHttpStatusHandlers()
	{
		static::$aStateHandlers[404] ??= function () {
			AfrHttpHeader::getInstance()->setHttpResponseCode(404);
			echo '404 Page not found!';
		};
		static::$aStateHandlers[405] ??= function ($oAfrRequestClass = null) {
			AfrHttpHeader::getInstance()->setHttpResponseCode(405);
			$sRequestMethod = $oAfrRequestClass instanceof AfrRequestClass ? $oAfrRequestClass->getHttpRequestMethodOriginal() : null;
			echo AfrHttpStatusCode::getInstance()->hStatusHeaderAndHtml(405, ($sRequestMethod ?? '') . ' Method Not Allowed');
		};
	}

	/**
	 * @param array $aRoutes
	 * @param string $baseRoute
	 * @return int
	 * @throws AfrEventException
	 * @throws AfrModuleException
	 * @throws AfrRouterException|\ReflectionException
	 */
	public function registerHTTPRoutesFromModule(array $aRoutes, string $baseRoute = ''): int
	{
		AfrEvent::dispatchEvent();
		$curBaseRoute = $this->baseRoute; // Track current base route
		$this->baseRoute = rtrim($baseRoute, '/');
		if (strlen($this->baseRoute) > 0 && substr($this->baseRoute, 0, 1) !== '/') {
			$this->baseRoute = '/' . trim($this->baseRoute, '/');
		}

		$iRegistered = 0;
		foreach ($aRoutes as $sType => $aRouteClusterInfo) {
			if (!in_array($sType, static::HTTP_ROUTE_TYPES)) {
				throw new AfrModuleException('Invalid routes type group: ' . $sType);
			} elseif (!is_array($aRouteClusterInfo)) {
				throw new AfrModuleException("Routes group `$sType` should be an array");
			}

			foreach ($aRouteClusterInfo as $sKeyCluster => $aRoute) {
				if (!is_string($sKeyCluster)) {
					throw new AfrModuleException(
						'The HTTP routes must be have a string key in order to respect ' .
						'SOLID open/close principle when extending modules'
					);
				}
				if (!is_array($aRoute) ||
					count($aRoute) < 2 ||
					!is_array($aRoute[0]) ||
					!is_string($aRoute[1]) ||
					!$aRoute[2] instanceof \Closure ||
					isset($aRoute[3]) && !is_array($aRoute[3])
				) {
					throw new AfrModuleException(
						'Invalid route definition! Use this format: ' .
						"'testMiddleware' => [['GET', 'POST'], '/.*', function () { return;}, ['Option1']]"
					);
				}
				$this->registerRouteTypeMethods($sType, $aRoute[0], $aRoute[1], $aRoute[2], $aRoute[3] ?? []);
				$iRegistered++;
			}
		}
		$this->baseRoute = $curBaseRoute; // Restore original base route
		return $iRegistered;
	}

	/*
		public function registerCLIRoutesFromModule(
			array $aRoutes,
			bool $bMergeQA = true,
			bool $bMergeInline = true,
			bool $bMergeCrons = true
		): int
		{
			$iRegistered = 0;
			foreach ($aRoutes as $sType => $aRouteClusterInfo) {
				if (!in_array($sType, [static::CLI_CRON_JOB_REQUEST, static::CLI_INLINE, static::CLI_QA_REQUEST])) {
					throw new AfrModuleException('Invalid routes type group: ' . $sType);
				} elseif (!is_array($aRouteClusterInfo)) {
					throw new AfrModuleException("Routes group `$sType` should be an array");
				}
				foreach ($aRouteClusterInfo as $sKeyCluster => $mStack) {
					if (!is_string($sKeyCluster)) {
						throw new AfrModuleException(
							'The CLI routes must be have a string key in order to respect ' .
							'SOLID open/close principle when extending modules'
						);
					}
				}
				// php index.php --QA=initTenantFileSystem OR php index.php QA
				if ($sType === static::CLI_QA_REQUEST) {
					foreach ($aRouteClusterInfo as $sKeyCluster => $mStack) {
						// $mStack should be an array of closures OR Closure that returns array of closures
						$iRegistered += AfrCliQaRouter::addActionGroup($sKeyCluster, $mStack, $bMergeQA);
					}
				}

			}
			return $iRegistered;
		}
	*/

	public function debugRoutes(bool $closureDump = false): array
	{
		//TODO: reactoru using AfrArrExportArrayAsStringClass::getInstance()->exportPhpArrayAsString()
		$out = [
			static::MIDDLEWARE_ROUTE => $this->aMiddlewareRoutes,
			static::CODE_ROUTE => $this->aCodeRoutes,
			static::AFTER_ROUTE => $this->aAfterRoutes,
			'aMaxHandledRoutesOfType' => $this->aMaxHandledRoutesOfType,
			'$aStateHandlers' => static::$aStateHandlers,
		];
		if (!$closureDump) {
			$out['aFnStack'] = $this->aFnStack;
		} else {
			$fnStackCode = [];
			foreach ($this->aFnStack as $i => $afn) {
				$fnStackCode[$i] = [
					self::closureDump($afn[0]) . "<hr>\r\n",
					$afn[1]
				];
			}
			$out['aFnStack'] = $fnStackCode;
		}
		return $out;
	}


	protected function closureDump($c): ?string
	{
		if ($c instanceof Closure) {
			return AfrClosureToStr::dump($c);
		}
		return var_export($c, TRUE);
	}

}
