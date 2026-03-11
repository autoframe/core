<?php

namespace Autoframe\Core\Afr;

use Autoframe\Core\CliTools\AfrCliHttpDetect;
use Autoframe\Core\Container\AfrContainerFacade;
use Autoframe\Core\Container\AfrDefaultBindings;
use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\Env\AfrEnv;
use Autoframe\Core\Env\AfrEnvFacade;
use Autoframe\Core\Env\AfrEnvInterface;
use Autoframe\Core\Event\AfrEvent;
use Autoframe\Core\Event\Exception\AfrEventException;
use Autoframe\Core\Exception\AfrException;
use Autoframe\Core\Container\AfrContainerInterface;
use Autoframe\Core\Container\AfrLiteContainer;
use Autoframe\Core\Http\Header\AfrHttpHeader;
use Autoframe\Core\Http\Header\AfrHttpStatusCode;
use Autoframe\Core\Http\Request\AfrRequestClass;
use Autoframe\Core\Http\Request\AfrRequestInterface;
use Autoframe\Core\ModuleBox\AfrModuleBoxClass;
use Autoframe\Core\ModuleBox\AfrModuleBoxFacade;
use Autoframe\Core\ModuleBox\AfrModuleBoxInterface;
use Autoframe\Core\Router\AfrRouter;
use Autoframe\Core\Router\Contracts\AfrRouterInterface;
use Autoframe\Core\Tenant\AfrTenant;

$_SERVER['REQUEST_TIME_FLOAT'] ??= microtime(true);

/**
 * TODO define individual static functions from AfrTenant
 * @mixin AfrTenant
 * @method static string|null getTenantAlias()
 * @method static string getHost()
 *
 * @see AfrTenant
 */
class Afr
{
	const V = '0.1.0a';
	protected static self $oAfr;
	protected string $sAppBaseDirectory;

	/** @var AfrContainerInterface|AfrLiteContainer */
	protected AfrContainerInterface $oAfrContainer;

	/** @var AfrModuleBoxInterface|AfrModuleBoxClass */
	protected AfrModuleBoxInterface $oAfrBox;
	/**
	 * @var AfrEnv|AfrEnvInterface
	 */
	protected AfrEnvInterface $oAfrEnv;
	protected AfrRequestInterface $oAfrRequest;

	/**
	 * In order to have a SOLID implementation, there are 4 classes that are bound first:
	 * Afr::class should be only extended. There should be at least one 100% concrete, and that is Afr::class.
	 * AfrTenant will get statically called from Afr::class, so also concrete, but swappable.
	 * Container and Env classes have interfaces and facades, but they are needed as a base.
	 * Conclusion: customize with Afr::makeApp parameters, or mix with constants
	 * Base dir is totally required, preferred __DIR__
	 */
	protected static array $aAltConfig = [
		'AFR_TENANT_FQCN' => AfrTenant::class,// static calls to AfrTenant::class
		'AFR_CONTAINER_FQCN' => AfrLiteContainer::class,
		'AFR_ENV_FQCN' => AfrEnv::class,
		'AFR_ENV_CACHE_SECONDS' => 3600 * 24 * 35,
		'bIgnoreUserAbort' => false,
	];

	/**
	 * @param string|null $sAppBaseDirectory
	 * @param array|null $aAltConfig
	 * @return Afr
	 * @throws AfrException
	 * @throws \ReflectionException
	 */
	public static function makeApp(
		string $sAppBaseDirectory = null,
		array  $aAltConfig = null
	): Afr
	{
		AfrEvent::dispatchEvent(AfrEvent::AFR_BOOTSTRAP.'.makeApp', null, 0);
		return static::app() ?? new static($sAppBaseDirectory, $aAltConfig);
	}

	protected static function getAltConfig(string $sKey): ?string
	{
		$sKey = trim(strtoupper(trim($sKey)), '\\');
		if (defined($sKey)) return (string)constant($sKey);
		if (defined('\\' . $sKey)) return (string)constant('\\' . $sKey);
		return $_ENV[$sKey] ?? null;
	}

	protected function setAltConfig(string $sAppBaseDirectory, array $aAltConfig = null): void
	{
		foreach (static::$aAltConfig as $sKey => $sValue) {
			if (($sAltV = static::getAltConfig($sKey)) !== null) {
				static::$aAltConfig[$sKey] = $sAltV;
			}
		}
		if ($aAltConfig) static::$aAltConfig += $aAltConfig;
		static::$aAltConfig['AFR_BASE_DIR'] = $sAppBaseDirectory;//used for event
	}


	public static function makeInstanceAvailable(Afr $oThis): void
	{
		static::$oAfr ??= $oThis;
	}

	/**
	 * @throws AfrException|\ReflectionException
	 */
	protected function __construct(
		string $sAppBaseDirectory = null,
		array  $aAltConfig = null
	)
	{
		if (!empty(static::$oAfr)) throw new AfrException('Afr already initialized!');

		$sAppBaseDirectory ??=
			$aAltConfig['AFR_BASE_DIR'] ??
			static::getAltConfig('AFR_BASE_DIR') ??
			dirname(AfrCliHttpDetect::getEntryPoint(null, false, false));

		AfrEvent::dispatchEvent(AfrEvent::AFR_BOOTSTRAP.'.constants.php', null, 0);
		if (is_file($sConstantsPath = $sAppBaseDirectory . DIRECTORY_SEPARATOR . 'constants.php')) {
			define(AfrExecutionThread::BOOTSTRAP_BASEDIR_CONSTANTS_FOR_ALL_TENANTS, true);
			include_once $sConstantsPath;
		}

		$this->setAltConfig($sAppBaseDirectory, $aAltConfig);

		//1.APP BASE DIR PATH
		if (empty($this->sAppBaseDirectory = $sAppBaseDirectory))
			throw new AfrException('App DIR is empty!');

		AfrEvent::dispatchEvent(AfrEvent::AFR_BOOTSTRAP, static::$aAltConfig, 0);
		AfrContainerFacade::xetContainerClass(static::$aAltConfig['AFR_CONTAINER_FQCN']);
		AfrEnvFacade::xetEnvClass(static::$aAltConfig['AFR_ENV_FQCN']);
		$_ENV['AFR_ENV_CACHE_SECONDS'] ??= intval( //zero: parsing every time, slowley....
			defined($c = 'AFR_ENV_CACHE_SECONDS') ? constant($c) : static::$aAltConfig['AFR_ENV_CACHE_SECONDS']
		);
		$this->thread()->runBootstrap($this);

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
		//TODO: public static::aStep1Bootstrap; public static::aStep2...
		return AfrExecutionThread::getInstance();
	}

	/**
	 * @return AfrLiteContainer|AfrContainerInterface
	 */
	public function container(): AfrContainerInterface //AfrLiteContainer
	{
		return $this->oAfrContainer ??= AfrContainerFacade::getContainer();
	}

	/**
	 * @return AfrModuleBoxClass|AfrModuleBoxInterface
	 * @throws AfrException
	 */
	public function box(): AfrModuleBoxInterface
	{
		return $this->oAfrBox ??= AfrModuleBoxFacade::getBox();
	}

	/**
	 * @return AfrEnv|AfrEnvInterface
	 * @throws AfrContainerException
	 * @throws \Autoframe\Core\Env\Exception\AfrEnvException
	 */
	public function env(): AfrEnvInterface
	{
		return $this->oAfrEnv ??= AfrEnvFacade::getEnvInstance()->setBaseDir($this->sAppBaseDirectory);
		if (empty($this->oAfrEnv)) { //lazy init
			$this->oAfrEnv = $this->container()->get(AfrEnvInterface::class);
			$this->oAfrEnv->setBaseDir($this->sAppBaseDirectory);
		}
		return $this->oAfrEnv;
	}

	/**
	 * @param AfrRequestClass|AfrRequestInterface $oAfrRequest
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
	public function router(): AfrRouterInterface
	{
		return AfrRouter::getInstance();//todo: container|facade
	}

	/**
	 * TODO.... steps
	 */
	public function run(...$mArgs): array
	{
		//	AfrEvent::dispatchEvent(AfrEvent::AFR_RUN, $mArgs);
		$this->thread()->runContext($this);
		$this->checkUserAbort();
		$this->thread()->runRequestRouteRender($this);
		return $this->thread()->getReport();
		//return $this->thread()->run(...$mArgs);
	}

	/**
	 * @param $name
	 * @param $arguments
	 * @return mixed
	 */
	public static function __callStatic($name, $arguments)
	{
		/** @var AfrTenant $sTenantFQCN */
		$sTenantFQCN = static::$aAltConfig['AFR_TENANT_FQCN'];
		return $sTenantFQCN::$name(...$arguments);
	}

	//TODO: MIXIN static+object+properties

	protected function checkUserAbort(): void
	{

		$sArgs = implode(' ', $_SERVER['argv'] ?? []);
		if (
			static::$aAltConfig['bIgnoreUserAbort'] ||
			strpos($sArgs, '--AFR_IGNORE_USER_ABORT') !== false ||
			strpos($sArgs, '--CRON_DAEMON') !== false
		) {
			ignore_user_abort(true);
		}
	}


}