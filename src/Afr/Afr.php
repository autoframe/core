<?php

namespace Autoframe\Core\Afr;

use Autoframe\Core\CliTools\AfrCliHttpDetect;
use Autoframe\Core\Container\AfrContainerFacade;
use Autoframe\Core\Container\AfrDefaultBindings;
use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\Env\AfrEnv;
use Autoframe\Core\Env\AfrEnvInterface;
use Autoframe\Core\Event\AfrEvent;
use Autoframe\Core\Event\Exception\AfrEventException;
use Autoframe\Core\Exception\AfrException;
use Autoframe\Core\Container\AfrContainerInterface;
use Autoframe\Core\Container\AfrLiteContainer;
use Autoframe\Core\Http\Header\AfrHttpHeader;
use Autoframe\Core\Http\Request\AfrRequestClass;
use Autoframe\Core\Http\Request\AfrRequestInterface;
use Autoframe\Core\Router\AfrRouter;
use Autoframe\Core\Router\Contracts\AfrRouterInterface;
use Autoframe\Core\Tenant\AfrTenant;

$_SERVER['REQUEST_TIME_FLOAT'] ??= microtime(true);

/**
 * TODO define individual static functions from AfrTenant
 * @mixin AfrTenant
 * @method static string|null getTenantAlias()
 * @method static bool isCli()
 * @method static string getHost()
 *
 * @see AfrTenant
 */
class Afr
{
	public static bool $bIgnoreUserAbort = false;
	protected static self $oAfr;
	protected string $sAppBaseDirectory;

	/**
	 * @var AfrContainerInterface|AfrLiteContainer
	 */
	protected AfrContainerInterface $oAfrContainer;
	/**
	 * @var AfrEnv|AfrEnvInterface
	 */
	protected AfrEnvInterface $oAfrEnv;
	protected AfrRequestInterface $oAfrRequest;


	/**
	 * @param string|null $sAppBaseDirectory
	 * @param string|null $sContainerClass
	 * @return Afr
	 * @throws AfrException
	 */
	public static function makeApp(string $sAppBaseDirectory = null, string $sContainerClass = null): Afr
	{
		return static::app() ?? new static($sAppBaseDirectory, $sContainerClass);
	}
	/**
	 * @throws AfrException
	 */
	protected function __construct(
		string $sAppBaseDirectory = null,
		string $sContainerClass = null
	)
	{
		$this->checkUserAbort();

		if (!empty(static::$oAfr)) {
			throw new AfrException('Afr already initialized!');
		}

		$sAppBaseDirectory ??= defined($c = '\AFR_BASE_DIR') ? constant($c) :
			dirname(AfrCliHttpDetect::getEntryPoint(null, false,false));

		if (empty($this->sAppBaseDirectory = $sAppBaseDirectory)) {
			throw new AfrException('App directory not set!');
		}
		AfrTenant::setBaseDirPath($this->sAppBaseDirectory);
		AfrTenant::includeCommonConstantsAllTenants(); //this is the only way to set some constants for custom containers
		AfrEvent::dispatchEvent(AfrEvent::AFR_BOOTSTRAP, [$sAppBaseDirectory, $sContainerClass], 0);


		if ($sContainerClass) {
			AfrContainerFacade::xetContainerClass($sContainerClass);
		} elseif (defined($c = '\AFR_CONTAINER')) {
			AfrContainerFacade::xetContainerClass(constant($c));
		}
		$this->oAfrContainer = AfrContainerFacade::getContainer();
		static::$oAfr = $this; // make Afr::app() available

		AfrDefaultBindings::setAutoframeDefaultContainerBindings();
		AfrDefaultBindings::applyDefaultTenantConfig();

		$this->oAfrEnv = $this->oAfrContainer->get(AfrEnvInterface::class);
		$this->oAfrEnv->setBaseDir($this->sAppBaseDirectory);
		if (AfrCliHttpDetect::isUntrustedHttpRequest()) {
			AfrEvent::dispatchEvent();
			AfrHttpHeader::getInstance()->e500Html(
				'Untrusted http request detected!'
			);
		}

		//($this->oAfrEnv = AfrEnv::getInstance())->setBaseDir($this->sAppBaseDirectory);

	}

	public function getAppBaseDirectory(): string
	{
		return $this->sAppBaseDirectory;
	}

	public static function app(): ?self
	{
		return static::$oAfr ?? null;
	}

	/**
	 * @return AfrExecutionThread
	 */
	public function thread(): AfrExecutionThread
	{
		return AfrExecutionThread::getInstance();
	}

	/**
	 * @return AfrLiteContainer|AfrContainerInterface
	 */
	public function container(): AfrContainerInterface //AfrLiteContainer
	{
		return $this->oAfrContainer;
	}

	/**
	 * @return AfrEnv|AfrEnvInterface
	 */
	public function env(): AfrEnvInterface
	{
		return $this->oAfrEnv;
	}

	/**
	 * @param AfrRequestInterface $oAfrRequest
	 * @return $this
	 */
	public function setRequest(AfrRequestInterface $oAfrRequest): self
	{
		$this->oAfrRequest = $oAfrRequest;
		return $this;
	}

	/**
	 * @throws AfrContainerException
	 */
	public function request(): AfrRequestInterface
	{

		if (empty($this->oAfrRequest)) {
			//$this->setRequest(AfrDefaultRequestClass::getInstance());
			$this->setRequest(
				$this->container()->get(AfrRequestInterface::class)
			);
		}
		return $this->oAfrRequest;
	}

	/**
	 * @return AfrRouter|AfrRouterInterface
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 */
	public function router(): AfrRouter
	{
		return AfrRouter::getInstance();//todo: container
	}

	/**
	 * TODO.... steps
	 */
	public function run(...$mArgs): array
	{
		AfrEvent::dispatchEvent(AfrEvent::AFR_RUN, $mArgs);
		return $this->thread()->run(...$mArgs);
	}

	/**
	 * @param $name
	 * @param $arguments
	 * @return mixed
	 */
	public static function __callStatic($name, $arguments)
	{
		return AfrTenant::$name(...$arguments);
	}

	protected function checkUserAbort(): void
	{
		$sArgs = implode(' ', $_SERVER['argv'] ?? []);
		if (
			static::$bIgnoreUserAbort ||
			strpos($sArgs, '--AFR_IGNORE_USER_ABORT') !== false ||
			strpos($sArgs, '--CRON_DAEMON') !== false
		) {
			ignore_user_abort(true);
		}
	}


}