<?php

namespace Autoframe\Core\Afr;

use Autoframe\Core\AfrCoreModules\AfrCoreAll;
use Autoframe\Core\CliTools\AfrCliHttpDetect;
use Autoframe\Core\Container\AfrContainerFacade;
use Autoframe\Core\Container\AfrDefaultBindings;
use Autoframe\Core\Database\Connection\AfrDbConnectionManagerFacade;
use Autoframe\Core\Database\Orm\Action\CnxActionFacade;
use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonClassicTrait;
use Autoframe\Core\Env\AfrEnvFacade;
use Autoframe\Core\Env\AfrEnvInterface;
use Autoframe\Core\Event\Exception\AfrEventException;
use Autoframe\Core\Exception\AfrException;
use Autoframe\Core\Http\Header\AfrHttpHeader;
use Autoframe\Core\Http\Request\AfrRequestClass;
use Autoframe\Core\Http\Request\AfrRequestInterface;
use Autoframe\Core\Module\AfrModuleBox;
use Autoframe\Core\ModuleBox\AfrModuleBoxClass;
use Autoframe\Core\ModuleBox\AfrModuleBoxFacade;
use Autoframe\Core\Router\Contracts\AfrRouterInterface;
use Autoframe\Core\Session\AfrSessionPhp;
use Autoframe\Core\Tenant\AfrTenant;
use Autoframe\Core\Event\AfrEvent;
use Closure;

$_SERVER['REQUEST_TIME_FLOAT'] ??= microtime(true);

final class AfrExecutionThread
{
	use AfrSingletonClassicTrait;

	//TODO: BINDINGS DE INCLUS FILA CONFIG.php si verificat daca se executa la includere cu use!!!  => C:\xampp\htdocs\core\src\Afr\AfrBindings.php
	//TODO refactorizare THF CONFIGURABLE
	//TODO check core\object\* namespace
	//TODO: lista module si incarcare din lista
	// rute, functionalitati, middleware, controlere
	// selector aw - extins - fixed + bindings
	const BOOTSTRAP1_SET_BASE_DIR = 'bst.set.base.dir';
	const BOOTSTRAP_BASEDIR_CONSTANTS_FOR_ALL_TENANTS = 'bst.constants.all.tenants';
	const BOOTSTRAP3_AFR_DEFAULT_CONTAINER_BINDINGS = 'container.bindings';

	const BOOTSTRAP1_TENANT_ALIAS_LOAD = 'tenant.load';
	const BOOTSTRAP2_MAKE_AFR_AVAILABLE = 'make.afr.available';
	const BOOTSTRAP3_TENANT_CONTAINER_BINDINGS = 'tenant.bindings';
	const BOOTSTRAP7_SHUTDOWN_FX = 'shutdownFx';


	const BOOTSTRAP4_TENANT_EVENT_CONFIG = 'event.config';
	const BOOTSTRAOP5_ENV_LOAD = 'env.load';
//	const BOOTSTRAP_ENV_EXTRA = 'env.extraConfig';
	const BOOTSTRAP6_PHP_INI_FROM_ENV = 'env.phpIni';
	const CONTEXT_HTTP_UNTRUSTED_REQUEST = 'env.checkUntrustedHttpRequest';
	const CONTEXT_THF_CONFIGURABLE_CONFIG = 'thfc.cnf';
	const CONTEXT_SESSION_CONFIG = 'ses.cnf';
	const CONTEXT_FILE_SYSTEM_CONFIG = 'fs.cnf';
	const CONTEXT_CACHE_CONFIG = 'cache.cnf';
	const CONTEXT_DB_CONFIG = 'db.cnf';
	const CONTEXT_MODULE_READ = 'mod.read'; //TODO from tenant read by class:: and cached by tenant
	const CONTEXT_MODULE_CONTAINER_BINDINGS = 'mod.bindings';
	const CONTEXT_MODULE_SETTINGS = 'mod.settings';
	const RRR_REQUEST_INIT = 'request.init';
	const RRR_ROUTER_BEFORE = 'router.before';
	const RRR_ROUTER_INIT = 'router.init';
	const RRR_ROUTE_HANDEL = 'route.handel';
	const RRR_VIEW_RENDER = 'view.render';

	public static array $aStep1Bootstrap = [
		self::BOOTSTRAP1_SET_BASE_DIR, //  sBaseDirPath/constants.php @ AfrTenant::includeCommonTenantConstantsAllFromBaseDir()
		self::BOOTSTRAP_BASEDIR_CONSTANTS_FOR_ALL_TENANTS, //  sBaseDirPath/constants.php @ AfrTenant::includeCommonTenantConstantsAllFromBaseDir()
		self::BOOTSTRAP1_TENANT_ALIAS_LOAD, // AfrTenant::loadConfigResolveTenantAliasProcessConfigOrInitSample();
		self::BOOTSTRAP3_AFR_DEFAULT_CONTAINER_BINDINGS,// //AfrDefaultBindings::default(); ..src/Afr/AfrBindings.php
		self::BOOTSTRAP3_TENANT_CONTAINER_BINDINGS, //AfrDefaultBindings::applyDefaultTenantConfig(); AFTER Afr::loadConfigResolveTenantAlias...
		self::BOOTSTRAP4_TENANT_EVENT_CONFIG, // AfrEvent::applyDefaultTenantConfig(); //TODO: test!!!  AFTER Afr::loadConfigResolveTenantAlias...
		self::BOOTSTRAOP5_ENV_LOAD, // Afr::app()->env()->readEnv( 35.day,  sBaseDirPath/getTenantAlias.$_ENV['AFR_ENV'].env )
		self::BOOTSTRAP2_MAKE_AFR_AVAILABLE, //  Afr::makeInstanceAvailable($oAfr);
//		self::BOOTSTRAP_ENV_EXTRA,//blank
		self::BOOTSTRAP6_PHP_INI_FROM_ENV, //AfrPhpIni::applyPhpIniEnvConfig() // //depends on env
		self::BOOTSTRAP7_SHUTDOWN_FX,
	];
	public static array $aStep2Context = [
		self::CONTEXT_HTTP_UNTRUSTED_REQUEST, //AfrCliHttpDetect::isUntrustedHttpRequest(null,$bE500IfUntrusted=true);
		self::CONTEXT_MODULE_READ,
		self::CONTEXT_MODULE_CONTAINER_BINDINGS,
		self::CONTEXT_MODULE_SETTINGS,
		self::CONTEXT_THF_CONFIGURABLE_CONFIG,
		self::CONTEXT_FILE_SYSTEM_CONFIG,
		self::CONTEXT_CACHE_CONFIG,
		self::CONTEXT_DB_CONFIG, // AfrDbConnectionManagerFacade::getInstance
		self::CONTEXT_SESSION_CONFIG,
	];
	public static array $aStep3RequestRouteRender = [
		self::RRR_REQUEST_INIT, //Afr::app()->setCustomRequest(AfrDefaultRequestClass::getInstance());   //TODO as dep injection into container
		self::RRR_ROUTER_BEFORE, //TODO
		self::RRR_ROUTER_INIT, //TODO
		self::RRR_ROUTE_HANDEL,  // TODO return (self::$oRouterInstance)();
		self::RRR_VIEW_RENDER, //TODO return self::$oRouterInstance->getCollectedResultsFromRoutes();
	];


	protected array $aStep = []; //from initSteps()

	protected array $aRulateDeja = [];
	protected array $aRulateReturn = [];

	protected array $aExecuteBeforeStep = [];
	protected array $aExecuteAfterStep = [];
	/**
	 * @var true
	 */
	protected bool $bRunning = false;


	protected function __construct()
	{
		$this->initSteps();
	}


	protected function initSteps(): void
	{
		$this->aStep[self::BOOTSTRAP1_SET_BASE_DIR] = function (Afr $oAfr = null) {
			//$sTenantFQCN = !empty(Afr::$sTenantFQCN) ? Afr::$sTenantFQCN : (Afr::app() ? get_class(Afr::app()) : AfrTenant::class);
			/** @var Afr|null $oAfr */
			if ($oAfr) $oAfr::setBaseDirPath($oAfr->getAppBaseDirectory());
			else Afr::setBaseDirPath(Afr::app()->getAppBaseDirectory()); //AfrTenant::setBaseDirPath
		};

		$this->aStep[self::BOOTSTRAP_BASEDIR_CONSTANTS_FOR_ALL_TENANTS] = function (Afr $oAfr = null) {
			//$sTenantFQCN = !empty(Afr::$sTenantFQCN) ? Afr::$sTenantFQCN : (Afr::app() ? get_class(Afr::app()) : AfrTenant::class);
			/** @var Afr|null $oAfr */
			if ($oAfr) $oAfr::includeCommonTenantConstantsAllFromBaseDir();
			else Afr::includeCommonTenantConstantsAllFromBaseDir(); //AfrTenant::setBaseDirPath
		};

		$this->aStep[self::BOOTSTRAP1_TENANT_ALIAS_LOAD] = function () {
			Afr::loadConfigResolveTenantAliasProcessConfigOrInitSample(); //loads: sBaseDirPath/tenant.env.php
		};


		$this->aStep[self::BOOTSTRAP2_MAKE_AFR_AVAILABLE] = function (Afr $oAfr) {
			Afr::makeInstanceAvailable($oAfr);
		};


		$this->aStep[self::BOOTSTRAP3_AFR_DEFAULT_CONTAINER_BINDINGS] = function () {
			AfrDefaultBindings::setAutoframeDefaultContainerBindings();
			//AfrDefaultBindings::applyDefaultTenantConfig(); // AFTER Afr::loadConfigResolveTenantAliasProcessConfigOrInitSample()
		};


		$this->aStep[self::BOOTSTRAP3_TENANT_CONTAINER_BINDINGS] = function () {
			AfrDefaultBindings::applyDefaultTenantConfig(); // AFTER Afr::loadConfigResolveTenantAliasProcessConfigOrInitSample()
			// // AfrDefaultBindings::setAutoframeDefaultContainerBindings();
			//	AfrDefaultBindings::applyDefaultTenantConfig();// MOVED INTO Afr::__construct()
		};

		$this->aStep[self::BOOTSTRAP4_TENANT_EVENT_CONFIG] = function () {
			AfrEvent::applyDefaultTenantConfig();
		};


		$this->aStep[self::BOOTSTRAOP5_ENV_LOAD] = function () {
			$oEnv = Afr::app() ?
				Afr::app()->env() :
				AfrEnvFacade::getEnvInstance()->setBaseDir(Afr::getBaseDirPath());
			//	$oEnv->readEnv(0); //load env files from __DIR__ without cache
			$iCacheSeconds = intval($_ENV['AFR_ENV_CACHE_SECONDS'] ??
				(defined($c = 'AFR_ENV_CACHE_SECONDS') ? constant($c) : 3600 * 24 * 35));
			$aRead = $oEnv->readEnv($iCacheSeconds, [Afr::getTenantEnvFile()], false)->getEnv(); // print_r($aRead);die('!AFR_ENV!');
			//	$oEnv->setEnv('FOO', 'BAR'); //set *[FOO]=BAR
			//	$oEnv->getEnv('AFR_ENV'); //get env key
			//	$oEnv->getEnv(); //get all env keys as array
			if (!empty($aRead)) {
				$oEnv->registerEnv(
					!empty($aRead['MUTABLE_OVERWRITE_ENV'] ?? true),
					!empty($aRead['REGISTER_PUT_ENV'] ?? false)
				);
			}

		};

		//	$this->aStep[self::BOOTSTRAP_ENV_EXTRA] = function () {};
		$this->aStep[self::BOOTSTRAP6_PHP_INI_FROM_ENV] = function () {
			AfrPhpIni::applyPhpIniEnvConfig(); //depends on env
		};

		$this->aStep[self::CONTEXT_HTTP_UNTRUSTED_REQUEST] = function () {
			AfrCliHttpDetect::isUntrustedHttpRequest(null, true);
		};

		// Todo: -1. core modules granular register
		// Todo: -2. custom modules AIO register config VIA @@ AfrModuleBoxClass::getInstance()->applyDefaultTenantConfig()
		// Todo: 2.1 cache? but how??? trebuie sa fac core + module in one go? conectare la db??
		// Todo: 2.2 de module depind restul de functionalitati, deci critice: DA, adica sesiuni, db, etc

		$this->aStep[self::CONTEXT_MODULE_READ] = function () {
//			AfrModuleBox::getInstance()->applyDefaultTenantConfig(); //TODO deprecat -> deprecated
			Afr::app()->box()->applyDefaultTenantConfig();
			//	AfrModuleBoxFacade::getBox()->applyDefaultTenantConfig();
		};
		$this->aStep[self::CONTEXT_MODULE_CONTAINER_BINDINGS] = function () {};  //TODO: deprecated
		$this->aStep[self::CONTEXT_MODULE_SETTINGS] = function () {};   //TODO: deprecated

		$this->aStep[self::CONTEXT_THF_CONFIGURABLE_CONFIG] = function () {
			//TODO SOMETIME: AfrConfig | AfrConfigFactory
		};
		$this->aStep[self::CONTEXT_FILE_SYSTEM_CONFIG] = function () {};
		$this->aStep[self::CONTEXT_CACHE_CONFIG] = function () {};
		$this->aStep[self::CONTEXT_DB_CONFIG] = function () {
			$sDbsConfig = Afr::app()->env()->getEnv('AFR_ENV_DBS_JSON', '[]');
			foreach (json_decode($sDbsConfig, true) as $mDbConfig) {
				if (is_string($mDbConfig) && strpos($mDbConfig, '|||') !== false) {
					$mDbConfig = explode('|||', $mDbConfig);
				}
				AfrDbConnectionManagerFacade::getInstance()->defineConnectionAlias(...$mDbConfig);
				//	echo "\n".__FILE__.':'.__LINE__."\n"; print_r($mDbConfig);
			}
			//die;
			//	$oAfrCnx = CnxActionFacade::withConnAlias('test');
		};
		$this->aStep[self::CONTEXT_SESSION_CONFIG] = function () {
			if (Afr::app()->env()->getEnv('SES_PROFILE')) {
				//	AfrSessionPhp::getInstance()->sessionConfigAfr();
				AfrSessionPhp::getInstance()->session_start();
			}
		};

		$this->aStep[self::RRR_REQUEST_INIT] = function () {
			Afr::app()->setRequest(
				Afr::app()->container()->get(
					AfrRequestInterface::class
				)
			);

//			AfrDefaultRequestClass::getInstance(); //Afr::app()->setCustomRequest(AfrDefaultRequestClass::getInstance());
		};
		$this->aStep[self::RRR_ROUTER_BEFORE] = function () {
			Afr::app()->request();
		};


		$this->aStep[self::RRR_ROUTER_INIT] = function () {
			Afr::app()->request();
			/*		self::$oRouterInstance = Afr::app()->router(); //TODO: test singleton
					if (!self::$oRouterInstance instanceof AfrRouterInterface) {
						throw new AfrException('Router class does not implement AfrRouterInterface!');
					}*/
			Afr::app()->router();
		};

		$this->aStep[self::RRR_ROUTE_HANDEL] = function () {
			return (Afr::app()->router())( //invoke
				Afr::app()->request(),
				Afr::app()->thread()->getStep(
					Afr::app()->thread()::RRR_VIEW_RENDER
				),
			);

			/*return (self::$oRouterInstance)(
				Afr::app()->request(),
				$this->aStep[self::VIEW_RENDER]
			);*/
		};

		$this->aStep[self::RRR_VIEW_RENDER] = function () {
			return false;
			return self::$oRouterInstance->getCollectedResultsFromRoutes();
		};

		$this->aStep[self::BOOTSTRAP7_SHUTDOWN_FX] = function () {};

	}


	public function getReport(): array
	{
		return $this->aRulateReturn;
	}

	/**
	 * @throws AfrEventException
	 * @throws \ReflectionException
	 */
	protected function runSingleStep($onClosure, string $sStepSubName, ?object $oAfr)
	{
		if (empty($aRulateDeja[$sStepSubName])) {
			$this->aRulateDeja[$sStepSubName] = true;
			if ($onClosure instanceof \Closure) {
				AfrEvent::dispatchEvent('AfrExecutionThread.' . $sStepSubName);
				if ($oAfr) $onClosure = $onClosure->bindTo($oAfr, $oAfr);
				return $this->aRulateReturn[$sStepSubName] = ($oAfr ? $onClosure($oAfr) : $onClosure());
			} elseif (!empty($onClosure)) {
				AfrEvent::dispatchEvent('AfrExecutionThread.' . $sStepSubName, [$sStepSubName => 'NOT A CLOSURE!']);
			}
		}
		return null;
	}

	/**
	 * @throws \ReflectionException
	 * @throws AfrEventException
	 */
	protected function runStepGroup(array $aSteps, ?object $oAfr = null): ?array
	{
		if ($this->bRunning) return null;
		$this->bRunning = true;
		$aReturn = [];
		foreach ($aSteps as $sStepName) {
			foreach ($this->aExecuteBeforeStep[$sStepName] ?? [] as $i => $onClosure)
				$aReturn[$sStepName . '@b' . $i] = $this->runSingleStep($onClosure, $sStepName . '@b' . $i, $oAfr);

			if (!empty($this->aStep[$sStepName]))
				$aReturn[$sStepName] = $this->runSingleStep($this->aStep[$sStepName], $sStepName, $oAfr);

			foreach ($this->aExecuteAfterStep[$sStepName] ?? [] as $i => $onClosure)
				$aReturn[$sStepName . '@a' . $i] = $this->runSingleStep($onClosure, $sStepName . '@a' . $i, $oAfr);
		}
		$this->bRunning = false;
		return $aReturn;
	}

	public function getStep(string $sStepName): ?Closure
	{
		return $this->aStep[$sStepName] ?? null;
	}

	/**
	 * @throws \ReflectionException
	 * @throws AfrEventException
	 */
	public function runBootstrap(?object $oAfr = null): ?array
	{
		AfrEvent::dispatchEvent('AfrExecutionThread.' . __FUNCTION__);
		return $this->runStepGroup(self::$aStep1Bootstrap, $oAfr);
	}

	/**
	 * @throws \ReflectionException
	 * @throws AfrEventException
	 */
	public function runContext(?object $oAfr = null): ?array
	{
		AfrEvent::dispatchEvent('AfrExecutionThread.' . __FUNCTION__);
		return $this->runStepGroup(self::$aStep2Context, $oAfr);
	}

	/**
	 * @throws \ReflectionException
	 * @throws AfrEventException
	 */
	public function runRequestRouteRender(?object $oAfr = null): ?array
	{
		AfrEvent::dispatchEvent('AfrExecutionThread.' . __FUNCTION__);
		return $this->runStepGroup(self::$aStep3RequestRouteRender, $oAfr);
	}

	public function overwriteStep(string $sStep, ?Closure $closure)
	{
		self::getInstance()->aStep[$sStep] = $closure;
	}

	public function executeBeforeStep(string $sStep, Closure $closure)
	{
		self::getInstance()->aExecuteBeforeStep[$sStep][] = $closure;
	}


	public function executeAfterStep(string $sStep, Closure $closure)
	{
		self::getInstance()->aExecuteAfterStep[$sStep][] = $closure;
	}

}