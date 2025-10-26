<?php

namespace Autoframe\Core\Afr;

use Autoframe\Core\CliTools\AfrCliHttpDetect;
use Autoframe\Core\Container\AfrDefaultBindings;
use Autoframe\Core\Database\Connection\AfrDbConnectionManagerFacade;
use Autoframe\Core\Database\Orm\Action\CnxActionFacade;
use Autoframe\Core\Exception\AfrException;
use Autoframe\Core\Http\Request\AfrRequestClass;
use Autoframe\Core\Http\Request\AfrRequestInterface;
use Autoframe\Core\Module\AfrModuleBox;
use Autoframe\Core\Router\Contracts\AfrRouterInterface;
use Autoframe\Core\Session\AfrSessionPhp;
use Autoframe\Core\Tenant\AfrTenant;
use Autoframe\Core\Event\AfrEvent;

$_SERVER['REQUEST_TIME_FLOAT'] ??= microtime(true);

class AfrExecutionThread
{
	//TODO: BINDINGS DE INCLUS FILA CONFIG.php si verificat daca se executa la includere cu use!!!  => C:\xampp\htdocs\core\src\Afr\AfrBindings.php
	//TODO refactorizare THF CONFIGURABLE
	//TODO check core\object\* namespace
	//TODO: lista module si incarcare din lista
	// rute, functionalitati, middleware, controlere
	// selector aw - extins - fixed + bindings
	const AFR_CONTAINER_BINDINGS = 'container.bindings';
	const SET_CONSTANTS_FOR_ALL_TENANTS = 'setConstantsAllTenants';
	const THREAD_INIT = 'thread.init';
	const TENANT_LOAD = 'tenant.load';
	const TENANT_BINDINGS = 'tenant.bindings';

	const TENANT_EXTRA_CONFIG = 'tenant.extraConfig';
	const AFR_EVENT_CONFIG = 'event.config';
	const ENV_LOAD = 'env.load';
	const ENV_EXTRA = 'env.extraConfig';
	const PHP_INI_FROM_ENV = 'env.phpIni';
	const THF_CONFIGURABLE_CONFIG = 'thfc.cnf';
	const SESSION_CONFIG = 'ses.cnf';
	const FILE_SYSTEM_CONFIG = 'fs.cnf';
	const CACHE_CONFIG = 'cache.cnf';
	const DB_CONFIG = 'db.cnf';
	const MODULE_READ = 'mod.read'; //TODO from tenant read by class:: and cached by tenant
	const MODULE_CONTAINER_BINDINGS = 'mod.bindings';
	const MODULE_SETTINGS = 'mod.settings';
	const REQUEST_INIT = 'request.init';
	const ROUTER_BEFORE = 'router.before';

	const ROUTER_INIT = 'router.init';
	const ROUTE_HANDEL = 'route.handel';
	const VIEW_RENDER = 'view.render';
	const SHUTDOWN_FX = 'shutdownFx';

	const ORDER = [
		self::SET_CONSTANTS_FOR_ALL_TENANTS, // sBaseDirPath/constants.php
		self::AFR_CONTAINER_BINDINGS,// //AfrDefaultBindings::default(); ..src/Afr/AfrBindings.php
		self::THREAD_INIT, //Afr::app()->container()->bind('thread', static::class); Afr::app()->container()->registerInstance(static::class, $this);
		self::TENANT_LOAD, // AfrTenant::loadConfig();
		self::AFR_EVENT_CONFIG, // AfrTenant::loadConfig();
		self::TENANT_BINDINGS, //AfrDefaultBindings::applyDefaultTenantConfig();

		self::TENANT_EXTRA_CONFIG, //blank
		self::ENV_LOAD, // Afr::app()->env()->readEnv( 30.day,  sBaseDirPath/getTenantAlias.$_ENV['AFR_ENV'].env )
		self::ENV_EXTRA,//blank
		self::PHP_INI_FROM_ENV, //AfrPhpIni::applyPhpIniEnvConfig()
		self::MODULE_READ,
		self::MODULE_CONTAINER_BINDINGS,
		self::MODULE_SETTINGS,
		self::THF_CONFIGURABLE_CONFIG,
		self::FILE_SYSTEM_CONFIG,
		self::CACHE_CONFIG,
		self::DB_CONFIG, // AfrDbConnectionManagerFacade::getInstance
		self::SESSION_CONFIG,
		self::REQUEST_INIT, //Afr::app()->setCustomRequest(AfrDefaultRequestClass::getInstance());   //TODO as dep injection into container
		self::ROUTER_BEFORE, //TODO
		self::ROUTER_INIT, //TODO
		self::ROUTE_HANDEL,  // TODO return (static::$oRouterInstance)();
		self::VIEW_RENDER, //TODO return static::$oRouterInstance->getCollectedResultsFromRoutes();
		self::SHUTDOWN_FX,
	];

	protected static array $aExecuteBeforeStep = [];
	protected static array $aOverwriteStep = [];
	protected static array $aExecuteAfterStep = [];
	protected static array $aCustomOrder = [];
	protected static ?array $aRunQueue = null;
	protected static array $aReport = [];

	protected array $aStep = []; //from populateSteps()
	protected string $sBaseDirPath;
	protected static AfrRouterInterface $oRouterInstance; //TODO getInstanceCheck
	protected static AfrExecutionThread $instance;

	/**
	 * @throws AfrException
	 */
	public function __construct()
	{

		if (!empty(self::$instance)) {
			throw new AfrException('Thread is already instantiated!');
		}
		self::$instance = $this;
		if (empty(Afr::app())) {
			if (defined('\AFR_BASE_DIR')) {
				Afr::makeApp(); //fallback init
			} else {
				throw new AfrException('Unable to configure the base directory!');
			}
		}
		$this->sBaseDirPath = Afr::app()->getAppBaseDirectory();
		if(!AfrCliHttpDetect::isCli()){
			//TODO: https://www.php.net/manual/en/features.dtrace.dtrace.php
			// event tracing
			//connection_status(); // 0 - NORMAL; 1 - ABORTED; 2 - TIMEOUT; 3 - ABORTED and TIMEOUT
			ignore_user_abort(true); //continue http requests
		}


		$this->initSteps();
	}


	protected function initSteps(): void
	{

		$this->aStep[self::SET_CONSTANTS_FOR_ALL_TENANTS] = function () {
//			AfrTenant::includeCommonConstantsAllTenants();
		};
		$this->aStep[self::AFR_CONTAINER_BINDINGS] = function () {
		//	AfrDefaultBindings::default();
		};

		$this->aStep[self::THREAD_INIT] = function () {
			Afr::app()->container()->bind('thread', static::class);
			Afr::app()->container()->registerInstance(static::class, $this);
		};


		$this->aStep[self::TENANT_LOAD] = function () {
			AfrTenant::loadConfig(); //loads: sBaseDirPath/tenant.env.php
		};

		$this->aStep[self::AFR_EVENT_CONFIG] = function () {
			AfrEvent::applyDefaultTenantConfig(); //TODO: test!!!
		};

		$this->aStep[self::TENANT_BINDINGS] = function () {
		//	AfrDefaultBindings::applyDefaultTenantConfig();
		};

		$this->aStep[self::TENANT_EXTRA_CONFIG] = function () {};


		$this->aStep[self::ENV_LOAD] = function () {
			$oEnv = Afr::app()->env();
			//	$oEnv->readEnv(0); //load env files from __DIR__ without cache
			$aRead = $oEnv->readEnv(
				3600 * 24 * 35,
				[
					$this->sBaseDirPath .
					DIRECTORY_SEPARATOR .
					AfrTenant::getTenantAlias() .
					'.' . $_ENV['AFR_ENV'] . '.env'
				],
				false
			)->getEnv(); // print_r($aRead);die('!AFR_ENV!');
			//	$oEnv->setEnv('FOO', 'BAR'); //set *[FOO]=BAR
			//	$oEnv->getEnv('AFR_ENV'); //get env key
			//	$oEnv->getEnv(); //get all env keys as array
			if(!empty($aRead)){
				$oEnv->registerEnv(
					!empty($aRead['MUTABLE_OVERWRITE_ENV']??true),
					!empty($aRead['REGISTER_PUT_ENV']??false)
				);
			}

		};


		$this->aStep[self::ENV_EXTRA] = function () {};
		$this->aStep[self::PHP_INI_FROM_ENV] = function () {
			AfrPhpIni::applyPhpIniEnvConfig();
		};
		$this->aStep[self::MODULE_READ] = function () {
			AfrModuleBox::getInstance()->applyDefaultTenantConfig();
		};
		$this->aStep[self::MODULE_CONTAINER_BINDINGS] = function () {};
		$this->aStep[self::MODULE_SETTINGS] = function () {};

		$this->aStep[self::THF_CONFIGURABLE_CONFIG] = function () {
			//TODO SOMETIME: AfrConfig | AfrConfigFactory
		};
		$this->aStep[self::FILE_SYSTEM_CONFIG] = function () {};
		$this->aStep[self::CACHE_CONFIG] = function () {};
		$this->aStep[self::DB_CONFIG] = function () {
			$sDbsConfig = Afr::app()->env()->getEnv('AFR_ENV_DBS_JSON','[]');
			foreach (json_decode($sDbsConfig, true) as $mDbConfig) {
				if(is_string($mDbConfig) && strpos($mDbConfig, '|||') !== false) {
					$mDbConfig = explode('|||', $mDbConfig);
				}
				AfrDbConnectionManagerFacade::getInstance()->defineConnectionAlias(...$mDbConfig);
			//	echo "\n".__FILE__.':'.__LINE__."\n"; print_r($mDbConfig);
			}
			//die;
			//	$oAfrCnx = CnxActionFacade::withConnAlias('test');
		};
		$this->aStep[self::SESSION_CONFIG] = function () {
			if(Afr::app()->env()->getEnv('SES_PROFILE')){
			//	AfrSessionPhp::getInstance()->sessionConfigAfr();
				AfrSessionPhp::getInstance()->session_start();
			}
		};

		$this->aStep[self::REQUEST_INIT] = function () {
			Afr::app()->setRequest(
				Afr::app()->container()->get(
					AfrRequestInterface::class
				)
			);

//			AfrDefaultRequestClass::getInstance(); //Afr::app()->setCustomRequest(AfrDefaultRequestClass::getInstance());
		};
		$this->aStep[self::ROUTER_BEFORE] = function () {
			Afr::app()->request();
		};


		$this->aStep[self::ROUTER_INIT] = function () {
			Afr::app()->request();
			static::$oRouterInstance = Afr::app()->router(); //TODO: test singleton
			if (!static::$oRouterInstance instanceof AfrRouterInterface) {
				throw new AfrException('Router class does not implement AfrRouterInterface!');
			}
		};

		$this->aStep[self::ROUTE_HANDEL] = function () {
			return (static::$oRouterInstance)(
				Afr::app()->request(),
				$this->aStep[self::VIEW_RENDER]
			);
		};

		$this->aStep[self::VIEW_RENDER] = function () {
			return false;
			return static::$oRouterInstance->getCollectedResultsFromRoutes();
		};

		$this->aStep[self::SHUTDOWN_FX] = function () {};

	}

	public static function getInstance(): AfrExecutionThread
	{
		return self::$instance ?? (new self());
	}


	public function run(): array
	{
		AfrEvent::dispatchEvent('AfrExceptionThread.Run');
		static::$aRunQueue ??= static::customOrder();
		foreach (static::$aRunQueue as $k => $sStepName) {
			static::runStep($k);
		}
		return static::$aReport;
	}

	public function runStepByStep(): ?array
	{
		static::$aRunQueue ??= static::customOrder();
		foreach (static::$aRunQueue as $k => $sStepName) {
			return static::runStep($k);
		}
		return null;
	}

	protected function runStep($k): array
	{
		$sStepName = static::$aRunQueue[$k];
		unset($this->aStep[$k]);
		if (!empty(static::$aExecuteBeforeStep[$sStepName])) {
			foreach (static::$aExecuteBeforeStep[$sStepName] as $i => $closure) {
				$aPartial[$sStepName . '@b' . $i] = static::$aReport[$sStepName . '@b' . $i] = $closure();
			}
		}
		if (!empty(static::$aOverwriteStep[$sStepName])) {
			$aPartial[$sStepName . '@o'] = static::$aReport[$sStepName . '@o'] = static::$aOverwriteStep[$sStepName]();
		} elseif (!empty($this->aStep[$sStepName])) {
			$aPartial[$sStepName . '@s'] = static::$aReport[$sStepName . '@s'] = $this->aStep[$sStepName]();
		}
		if (!empty(static::$aExecuteAfterStep[$sStepName])) {
			foreach (static::$aExecuteAfterStep[$sStepName] as $i => $closure) {
				$aPartial[$sStepName . '@a' . $i] = static::$aReport[$sStepName . '@a' . $i] = $closure();
			}
		}
		return $aPartial ?? [];
	}


	public static function customOrder(array $aCustomOrder = []): array
	{
		if (!empty($aCustomOrder)) {
			static::$aCustomOrder = $aCustomOrder;
		} elseif (empty(static::$aCustomOrder)) {
			static::$aCustomOrder = static::ORDER;
		}
		return static::$aCustomOrder;
	}

	public static function executeBeforeStep(string $sStep, \Closure $closure)
	{
		static::$aExecuteBeforeStep[$sStep][] = $closure;
	}

	public static function overwriteStep(string $sStep, \Closure $closure)
	{
		static::$aOverwriteStep[$sStep] = $closure;
	}


	public static function executeAfterStep(string $sStep, \Closure $closure)
	{
		static::$aExecuteAfterStep[$sStep][] = $closure;
	}

	public static function getReport(): array
	{
		return [
			'before' => static::$aExecuteBeforeStep,
			'step' => static::$aOverwriteStep,
			'after' => static::$aExecuteAfterStep,
			'order' => static::customOrder(),
			'queue' => static::$aRunQueue,
			'report' => static::$aReport,
		];
	}

}