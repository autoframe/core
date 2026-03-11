<?php

namespace Autoframe\Core\AfrCoreModules\Concrete;


use Autoframe\Core\Afr\Afr;
use Autoframe\Core\AfrCoreModule\AfrCore;
use Autoframe\Core\Cron\AfrConJobSources;
use Autoframe\Core\Cron\AfrCronJobDaemon;
use Autoframe\Core\Module\AfrModuleCLIRoutesInterface;
use Autoframe\Core\Module\AfrModuleHTTPRoutesInterface;
use Autoframe\Core\Module\AfrModuleInterface;

/**
 * if(1){
 * AfrConJobSources::getInstance()->addUrlSource('demo','http://localhost:808/core/src/Cron/AfrCronJobDaemon.DemoCron.txt');
 * }
 * else{
 * AfrConJobSources::getInstance()->getSourcesFreshFromModules();
 * }
 */

/**
 * AfrCronJob Flag List:
 * Startup S;
 * TurnOffLog: O;
 * AllowParallelRun: P;
 * TimeLimitedSeconds: T(0.02)=20ms | T(60)=60 seconds;
 * AlwaysRunService: A;
 * AlwaysRunService with stop+start=restart trigger  : A(* * 5 * *);
 * TenantInsensitiveJob : I; The lock is tenant insensitive
 */

"#*/9 * * * * EXIT_DAEMON
32 * * * * EXIT_DAEMON
#*/7 * * * * RESPAWN_DAEMON
#* * * * * some.php args
*/2 * * * * REFRESH_JOBS
#* * * * * CLI:C:\Windows\System32\mspaint.exe
#* * * * * CLI:C:\Windows\System32\cmd.exe
<P>* * * * * http://localhost:808/core/src/multiexec.php
#<P>* * * * * http://localhost:808/core/
#<P>* * * * * http://localhost:808/
* * * * * https://ares.b-p-g.org/
<OA(*/3 * * * *)>* * * * * C:\xampp\htdocs\core\xEnd22Status.php
#* * * * * C:\xampp\htdocs\core\xSomeTxt.php
#* * * * * C:\xampp\htdocs\core\xNoFeedback.php
#* * * * * C:\xampp\htdocs\core\index.php AfrCronJobDaemon::demo
";


class AfrCronJobSourceToRefactor
{

/*
	protected array $aJob = [
		AfrCronJobDaemon::flags => null, // S O P T(0.5) A I
		AfrCronJobDaemon::cronTime => null, //unix * * * * *
		AfrCronJobDaemon::command => null, //executed with proc_open() / exec()
		AfrCronJobDaemon::alias => null, // some name for logging
		AfrCronJobDaemon::skipped => false, //line starts with #
	];
*/
	public function getSelfSources(): ?array
	{
		return [
			AfrConJobSources::URL_S => [['alias', 'url', []]], // ->addUrlSource
			AfrConJobSources::FGC => [['alias', 'url', null]], // ->addFileSource
			AfrConJobSources::CLOSURE_FN => [['alias', fn() => '#*/9 * * * * EXIT_DAEMON']], // ->addSourceFromClosure
		];
	}

	public function pushCronJobSources(): ?array
	{
		//TODO C:\xampp\htdocs\core\src\Cron\AfrConJobSources.php @ getSourcesFreshFromModules() K
		//TODO:  Module BOX FACTORY LIKE CONTAINER K Afr::app()->box() K
		// INTEFACE FOR MOD BOX K
		// ADD TO AFR::APP K
		//TODO src/Afr/AfrExecutionThread.php @ $this->aStep[self::MODULE_READ]
		// MODULE CORE REGISTER
		// MODULE CUSTOM REGISTER + LOCAL CONFIG from APP TENANT/ ENV ? CUSTOM FILE
		// TODO: AfrConJobSources::getSourcesFreshFromModules() //TODO!!!!!!
		/**
		 * $aModules = [//todo check default list?? @ AfrModuleBox OLD
		 * AfrCore::class => [
		 * AfrModuleInterface::class,
		 * AfrModuleHTTPRoutesInterface::class,
		 * AfrModuleCLIRoutesInterface::class
		 * ],
		 */

		Afr::app()->box();

		AfrConJobSources::getInstance()
			->addUrlSource('demo', 'http://localhost:808/core/src/Cron/AfrCronJobDaemon.DemoCron.txt')
			->addFileSource('xx', __FILE__)
			->addSourceFromClosure('cc', function () {});


		AfrConJobSources::getInstance()->getSourcesFreshFromModules();

	}
}