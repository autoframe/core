<?php

namespace Autoframe\Core\Cron;

use Autoframe\Core\Afr\Afr;
use Autoframe\Core\CliTools\AfrCheckExec;
use Autoframe\Core\CliTools\AfrCliHttpDetect;
use Autoframe\Core\CliTools\AfrCliTextColors;
use Autoframe\Core\CliTools\AfrSysTempDir;
use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\Cron\Log\Channel\AfrCronLogChannelDoNotLog;
use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;
use Autoframe\Core\Env\Exception\AfrEnvException;
use Autoframe\Core\Event\AfrEvent;
use Autoframe\Core\Event\Exception\AfrEventException;
use Autoframe\Core\Exception\AfrException;
use Autoframe\Core\Http\Buffer\AfrHttpImplicitFlush;
use Autoframe\Core\ProcessControl\Lock\AfrLockFileClass;
use Autoframe\Core\ProcessControl\Worker\Background\AfrBackgroundWorkerClass;
use Autoframe\Core\Router\Contracts\AfrRouterConstantsInterface;
use Autoframe\Core\Tenant\AfrTenant;
use Autoframe\Core\CliTools\AfrGetOpt;
use Autoframe\Core\Cron\Log\AfrCronLoggerInterface;
use Autoframe\Core\String\AfrStr;
use Autoframe\Core\Cron\Log\AfrCronLoggerClass;
use Autoframe\Core\Http\CurlGetBodyWithTimeout\AfrGetHttpBodyWithTimeout;
use Autoframe\Core\Error\AfrError;

class AfrCronJobDaemon // extends AfrSingletonAbstractClass
{

	const cronTime = 'cTime';
	const command = 'cmd';
	const skipped = 'skip';
	const flags = 'flags';
	const alias = 'alias';
	const KILL_ALL_PHP_INSTANCES = 'KILL_ALL_PHP_INSTANCES';
	const EXIT_DAEMON = 'EXIT_DAEMON';
	const RESPAWN_DAEMON = 'RESPAWN_DAEMON';
	const REFRESH_JOBS = 'REFRESH_JOBS';

	/** @var AfrCronJob[][] */
	protected array $aJobs = [];

	protected int $iLastExecutedMinute = -1;

	protected bool $bRunCommandAsyncUsingDaemonInsteadOfWorkers = false;
	protected bool $bIsWorker;
	protected bool $bLoggedOnceEmptyQueue = false;
	protected AfrCronLoggerInterface $oCronLogger;
	private string $sLockName;
	private string $sAliasName;
	private static array $aHashCache = [];
	protected ?AfrCronJob $oWorkerJob = null;
	protected ?AfrLockFileClass $oLockWorker = null;

	private array $spinnerShapes = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
	private int $spinnerIndex = 0;
	public static float $fiDaemonSleepSeconds = 2;

	/**
	 * @param AfrCronLoggerInterface|null $oAltLogger
	 * @param string|null|false|array $saDSJW CLI worker data AfrRouterConstantsInterface::CRON_WORKER_ARGV_KEY
	 * @return AfrCronJobDaemon
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 * @throws AfrException
	 */
	public static function make(AfrCronLoggerInterface $oAltLogger = null, $saDSJW = null): self
	{
		//die('TODO -- tenant in worker and daemon!!!');
		// php .\index.php --CRON_DAEMON --CRON_WORKER=eyJmbGFncyI6bnVsbCwiY1RpbWUiOiIqICogKiAqICoiLCJjbWQiOiJodHRwOlwvXC9sb2NhbGhvc3Q6ODA4XC9jb3JlXC9zcmNcL211bHRpZXhlYy5waHAiLCJhbGlhcyI6bnVsbCwic2tpcCI6ZmFsc2V9
		$oInstance = new static();
		$oInstance
			->setLogger($oAltLogger)
			->prepareDaemonWorkerLogger($saDSJW);
		return $oInstance;
	}

	/**
	 * @return void
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 * @throws AfrException
	 */
	public function run(): void
	{
		AfrHttpImplicitFlush::getInstance()->setHttpImplicitFlush();
		set_time_limit(0);
		ignore_user_abort(true);
		if (!$this->lock()) {
			return;
		}
		error_clear_last();
		$this->bIsWorker ? $this->runWorker() : $this->runDaemon();
		if ($snErr = AfrError::getLastErrorReadable([E_DEPRECATED, E_USER_DEPRECATED])) {
			$this->log($snErr, true);
		}

	}

	protected function lock(bool $bForce = false): bool
	{
		$sLockName = $this->getLockName();
		if ($bForce || !($this->bIsWorker && $this->oWorkerJob->isAllowParallelRun())) {
			if (empty($this->oLockWorker)) {
				$this->oLockWorker = new AfrLockFileClass($sLockName);
			}
			if ($this->oLockWorker->isLocked()) {
				$this->log("Already running! Lock PID(" . $this->oLockWorker->getLockPid() . ") $sLockName");
				return false;
			} elseif (!$this->oLockWorker->obtainLock()) {
				$this->log("Fail to obtain lock $sLockName", true);
				$this->oLockWorker->releaseLock();
				return false;
			} else {
				$this->log($sLockName . '» Locked at PID(' . $this->oLockWorker->getLockPid() . ")");
			}
		} else {
			$this->log("Parallel / multithreading allowed on " . $sLockName);
		}
		return true;
	}

	protected function unlock(): void
	{
		if ($this->oLockWorker && $this->oLockWorker->isLocked()) {
			$sId = $this->oLockWorker->getLockPid();
			$bRelease = $this->oLockWorker->releaseLock();
			if (!$bRelease) {
				$this->log($this->getLockName() . "» Unlocked ERROR  at PID($sId)", true);
			}
			usleep(20_000);
		}
	}

	/**
	 * @param string|null|false|array $saDSJW CLI worker data AfrRouterConstantsInterface::CRON_WORKER_ARGV_KEY
	 * @return void
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 * @throws AfrException
	 */
	protected function prepareDaemonWorkerLogger($saDSJW = null)
	{
		$amWorkerDataJsonArr = $saDSJW ? static::decodeWorkerInitDataFromCliArgvOrRequest($saDSJW) : null;
		if (!empty($amWorkerDataJsonArr[static::command])) {
			$amWorkerDataJsonArr[static::cronTime] ??= '* * * * *';//worker force valid if command exists
			$this->oWorkerJob = new AfrCronJob($amWorkerDataJsonArr);
		}

		if ($this->oWorkerJob && $this->oWorkerJob->isValidConfig()) {
			$this->bIsWorker = true;
			$sFullCommand = $this->oWorkerJob->getCommand();
			$sFlags = $this->oWorkerJob->getFlags() ?? '';
		} else {
			$this->bIsWorker = false;
			$sFullCommand = AfrCliHttpDetect::isCli() ? self::getJobTenantEntryPoint() : $_SERVER['REQUEST_URI'];
			$sFlags = '';
		}

		$this->getLogger()->setCommandAliasTenantWorker(
			$sFullCommand,
			$sFlags,
			$this->getAliasName(),
			$this->getTenantName(),
			$this->bIsWorker,
			$this->getHash()
		);
		if ($this->bIsWorker && $this->oWorkerJob->isTurnOffLog()) {
			$this->getLogger()->resetChannels()->pushChannel(AfrCronLogChannelDoNotLog::getInstance());
		}
		AfrConJobSources::getInstance()->setAfrCronLogger($this->getLogger());


	}


	/**
	 * @return void
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 * @throws AfrException
	 */
	protected function runDaemon(): void
	{
		usleep(1000 * 250);
		AfrEvent::dispatchEvent(AfrEvent::CRON_DAEMON, [__FUNCTION__]);
		//TODO: RUN CLI: some cli command
		//TODO: RUN CLI: some cli command
		//TODO: RUN CLI: some cli command
		//TODO: RUN CLI: some cli command
		// The default setting is -1, which means that max_execution_time is used instead.
		// Set to 0 to allow unlimited time.
		//TODO: log folder for DEAMON / WORKERS tenant sensitive???
		//TODO: RESPAWN_DAEMON if older than TS! atat pentru deamon, cat si pentru worker --implementat altcumva
		//TODO: !!! JOBS venite din module / CORE JOBS ca si LOADER static
		//TODO: worker incarca url https din backup md5 facut de deamon
		//TODO: CRON ENTRY POINT PHP file : trebuie sa fie replaceble in comanda: |AFR.DEAMOM.ENTRY.FILE.PHP|
		//TODO: conexia la db se va inchide dupa o perioada, asa ca va trebui redeschisa in servicii!
		//TODO: log exit status 0 ok, altceva eroare!
		// TODO: Flags Startup S; Log: L; NewInstanceEveryTime: N; TenantService: T(10Seconds); AnyTenantService: A(20Seconds); Cron Alias [AliasX]

		//TODO: respawn as worker!!
		//TODO: logs for workers

		//todo: wget http - test
		//todo run mised crons ?
		//todo run startup crons + locks
		$this->setAllDaemonJobsAndCache(false);
		// print_r($this->aJobs);		die;

		//TODO: fac cache initial jobs,
		// apoi ii fac update periodic prin rularea unui thread diferit,
		// care va putea sa fie citit de catre workeri si verificat allive status la jobs,
		// apoi schimbarile noi trebuie sa se reflecte si in daemon preferabil din cache, sau din surse incomplete


		$bWhile = true;
		$sEndCmd = '';

		while ($bWhile) {
			$aNow = getdate();
			$iMinute = (int)$aNow['minutes'];
			if ($iMinute === $this->iLastExecutedMinute) {
				$this->callDisplaySpinnerAndSleep();
				continue;
			}
			$this->iLastExecutedMinute = $iMinute;
			$aToDispatch = [];
			foreach ($this->aJobs as $sGroupAlias => $aGroup) {
				foreach ($aGroup as $oJob) {
					if (!$oJob instanceof AfrCronJob) {
						$this->log('Corrupted JOB: ' . print_r($oJob, true), true);
						continue;
					}
					if ($oJob->isSkipped()) {
						continue;
					}
					if (!$oJob->isValidConfig()) {
						$this->log('Invalid registered Job: ' . $oJob, true);
						continue;
					}
					if (!$oJob->canTrigger($aNow)) {
						continue;
					}
					if ($oJob->isAlwaysRunService()) {
						$sJobLockName = $this->getLockNameForJob($oJob);
						$oJobLock = new AfrLockFileClass($sJobLockName);
						if ($oJobLock->isLocked()) {
							$this->log(
								"Service is already running on lock $sJobLockName PID(" .
								$oJobLock->getLockPid() . ") " . $oJob->getCommand()
							);
							continue;
						}
					}

					$sCmd = strtoupper($oJob->getCommand());
					if (in_array($sCmd, [static::KILL_ALL_PHP_INSTANCES, static::EXIT_DAEMON, static::RESPAWN_DAEMON])) {
						$this->log('Ending soon because of command: ' . $oJob->getCronTime() . ' ' . $oJob->getCommand());
						$bWhile = false;
						$sEndCmd = $sCmd;
						continue;
					}
					$aToDispatch[] = $oJob;
				}
			}

			if (empty($this->aJobs) && !$this->bLoggedOnceEmptyQueue) {
				$this->bLoggedOnceEmptyQueue = true;
				$this->log('Empty Job Queue', true);
			}
			//dispatch jobs
			if ($bWhile || $sEndCmd === static::RESPAWN_DAEMON) {
				$this->daemonDispatchJobs($aToDispatch);
			}

			if (
				$bWhile &&
				Afr::app() &&
				is_file($sEnvCache = Afr::app()->env()->getCacheFileName()) &&
				filemtime($sEnvCache) > $_SERVER['REQUEST_TIME']
			) {
				$this->log('Respawning soon because of framework update / clear cache in file ' . $sEnvCache);
				$bWhile = false;
				$sEndCmd = static::RESPAWN_DAEMON;
			}
			$this->callDisplaySpinnerAndSleep();
			$this->setAllDaemonJobsFromLatestCacheVersion(); //read the latest cache version
		}

		$this->log("Daemon jobs loop ended by [$sEndCmd]");
		if ($sEndCmd) {
			$this->endCmdDaemon($sEndCmd);
		}
		$this->unlock();
	}


	/**
	 * @throws AfrEventException
	 * @throws AfrException
	 */
	protected function runWorker(): void
	{
		AfrEvent::dispatchEvent(AfrEvent::CRON_WORKER, [__FUNCTION__]);
		//TODO: effective execution time tEnd - tStart in log in microtime

		$sCommand = $this->oWorkerJob->getCommand();
		if ($sCommand === static::REFRESH_JOBS) {
			try {
				$this->setAllDaemonJobsAndCache(true);
			} catch (\Throwable $e) {
				$this->log('Exception when ' . static::REFRESH_JOBS . ': ' . $e->getMessage(), true);
				die(99);
			}
			die(0); //stop framework because the jobs are refreshed
		}

		$this->oWorkerJob->isAlwaysRunService();
		$this->oWorkerJob->isAllowParallelRun();
		$this->oWorkerJob->isRunOnStartup();
		//	$this->oWorkerJob->setAlwaysRunService(true);
		//	$this->oWorkerJob->setAllowParallelRun(true);
		//	file_put_contents(__DIR__.'/'.$this->getLogger()->getHash(),$this->oWorkerJob);
		//TODO:  SERVICE / RESPAWN / TURN OFF LOG, etc
		//TODO:  SERVICE / RESPAWN / TURN OFF LOG, etc
		//TODO:  SERVICE / RESPAWN / TURN OFF LOG, etc
		//TODO:  SERVICE / RESPAWN / TURN OFF LOG, etc
		// $this->>runCommandSync($this->oWorkerJob);
		// $this->>runCommandSync($this->oWorkerJob);
		// $this->>runCommandSync($this->oWorkerJob);
		// $this->>runCommandSync($this->oWorkerJob);
		// $this->>runCommandSync($this->oWorkerJob);
		// $this->>runCommandSync($this->oWorkerJob);
		// $this->>runCommandSync($this->oWorkerJob);

		//$this->sCommandHash = $this->getCommandHash($sCommand);

		if (filter_var($sCommand, FILTER_VALIDATE_URL)) {
			$this->log('Worker opening url: ' . $sCommand);
			$sData = @file_get_contents($sCommand);
			if ($sData === false) {
				$this->log(
					"Fail to get data from URL:  $sCommand ➔ " . trim(error_get_last()['message'] ?? ''),
					true
				);
			} else {
				$this->log(
					'Data from URL: ' . $sCommand .
					($this->oWorkerJob->isTurnOffLog() ? '✓' : " ➔ $sData")
				);
			}
			return;
		}

		$bCliCmd = substr($sCommand, 0, 4) === 'CLI:';
		$sCommand = $bCliCmd ?
			trim(substr($sCommand, 4)) :
			AfrBackgroundWorkerClass::getPhpBin(false) . ' ' . trim($sCommand);

		//TODO: numai pentru servicii la care am nevoie de process control / restart
		//TODO: numai pentru servicii la care am nevoie de process control / restart
		//TODO: cand se schimba fila de env|composer version restartez serviciile dupa setare din job DECI FLAG NOU cu S(*****)
		//TODO: cand se schimba fila de env restartez serviciile?


		if (AfrCheckExec::isProcOpenCloseAvailable()) {
			$this->runWorkerProcOpen($sCommand);
		} else {
			//TODO complete / refactor::
			//TODO complete / refactor::
			//TODO complete / refactor::
			/*			$bCliCmd ?
							AfrBackgroundWorkerClass::execCli($sCommand):
							AfrBackgroundWorkerClass::execWithArgs($sCommand);*/
			$output = $result_code = null;
			//todo: test run exe cmd
			$sFData = exec($sCommand, $output, $result_code);
			if ($sFData === false) {
				$this->log('Fail to execute: ' . $sCommand . " (#$result_code)➔ " . trim(error_get_last()['message'] ?? ''), true);
			} else {
				$output = !empty($output) && is_array($output) ? implode("\n", $output) : $sFData;
				$this->log('Data from CLI: ' . $sCommand . " (#$result_code)➔ $output");
			}

		}
		$this->unlock();
	}


	/**
	 * @param array $aToDispatch
	 * @throws AfrEventException
	 * @throws AfrException
	 */
	protected function daemonDispatchJobs(array $aToDispatch): void
	{
		foreach ($aToDispatch as $iJd => $oJob) {
			if ($iJd) {
				usleep(1000 * 25);//25 ms delay from second parallel start... max 40 new workers each second
			}
			AfrEvent::dispatchEvent(
				$this->bRunCommandAsyncUsingDaemonInsteadOfWorkers ?
					AfrEvent::CRON_JOB : AfrEvent::CRON_WORKER,
				[__FUNCTION__]
			);
			$this->bRunCommandAsyncUsingDaemonInsteadOfWorkers ?
				$this->runCommandAsyncDirectlyFromDaemon($oJob) :
				$this->spawnCliWorker($oJob);
		}
	}

	/**
	 * @param AfrCronJob $oJob
	 * @return void
	 * @throws AfrException
	 */
	protected function spawnCliWorker(AfrCronJob $oJob, bool $bRespawnNew = false): void
	{
		$sEntryPoint = $this->getJobTenantEntryPoint();
		$sCronWorkerArgvKey = ' --' . AfrRouterConstantsInterface::CRON_WORKER_ARGV_KEY . '=';
		$sJobData = rtrim(strtr(base64_encode((string)$oJob), '+/', '@_'), '=');
		if (strpos($sEntryPoint, $sCronWorkerArgvKey) !== false) {
			//TODO TEST repalce ' --' . AfrRouterConstantsInterface::CRON_WORKER_ARGV_KEY . '='
			$aParts = explode($sCronWorkerArgvKey, $sEntryPoint);
			if (strpos($aParts[1], ' ') !== false) {// there are other parameters after CRON_WORKER_ARGV_KEY
				$aTailParts = explode(' ', $aParts[1]);
				$aTailParts[0] = $sJobData;// replace old job data
				$aParts[1] = implode(' ', $aTailParts);
			} else {
				$aParts[1] = $sJobData;
			}
			$sEntryPoint = implode($sCronWorkerArgvKey, $aParts);

		} else {
			$sEntryPoint .= $sCronWorkerArgvKey . $sJobData;
		}
		$sCmd = $oJob->getCommand();
		$this->log(($bRespawnNew ? 'RESPAWN' : 'Spawn') . ' worker [' . static::computeHash($sCmd) . '] ' . $sCmd);
		//	$this->log('C:\xampp\php\php.exe '.$sEntryPoint);
		AfrBackgroundWorkerClass::execWithArgs($sEntryPoint, true); //background deteched pid
	}


	/**
	 * @throws AfrException
	 * @throws AfrEnvException
	 */
	protected function runCommandAsyncDirectlyFromDaemon(AfrCronJob $oJob): void //TODO test GetHttpBodyWithTimeout|curl
	{
		//TODO: max execution time from job
		$sCommand = $oJob->getCommand();
		$sUnixCron = $oJob->getCronTime();
		$this->log('Executing Async' . ($sUnixCron ? "($sUnixCron)" : '') . '» ' . $sCommand);
		if (filter_var($sCommand, FILTER_VALIDATE_URL)) {
			$iTimeoutMs = 1000;
			$iConTimeoutMs = 2000;
			if (Afr::app()) {
				$iTimeoutMs = Afr::app()->env()->getEnv('AFR_CRON_DAEMON_CURL_TIMEOUT_MS', $iTimeoutMs);
				$iConTimeoutMs = Afr::app()->env()->getEnv('AFR_CRON_DAEMON_CURL_TIMEOUT_MS', max($iConTimeoutMs, $iTimeoutMs * 2));
			}
			//TODO: test GetHttpBodyWithTimeout vs curl
			AfrGetHttpBodyWithTimeout::get($sCommand, max($iTimeoutMs, $iConTimeoutMs), false);
			return;
			if (empty($ch = curl_init($sCommand))) {
				$this->log('Fail to initiate cURL: ' . $sCommand, true);
			} elseif (empty(curl_setopt_array($ch, [
				CURLOPT_RETURNTRANSFER => false,
				CURLOPT_HEADER => false,
				CURLOPT_TIMEOUT_MS => $iTimeoutMs,
				CURLOPT_CONNECTTIMEOUT_MS => $iConTimeoutMs,
				CURLOPT_FOLLOWLOCATION => false,
			]))) {
				$this->log('Fail to set options cURL: ' . $sCommand, true);
			} elseif (empty(curl_exec($ch))) {
				$this->log('Fail to exec cURL: ' . $sCommand . "\t" . curl_error($ch), true);
			}
			empty($ch) ?: curl_close($ch);
		} else {
			if (substr($sCommand, 0, 4) === 'CLI:') {
				AfrBackgroundWorkerClass::execCli(substr($sCommand, 4));
			} else {
				AfrBackgroundWorkerClass::execWithArgs($sCommand);
			}

		}
	}

	/**
	 * @param string $sEndCmd
	 * @return void
	 * @throws AfrException
	 */
	protected function endCmdDaemon(string $sEndCmd): void
	{
		if ($sEndCmd == static::EXIT_DAEMON) {
			$this->unlock();
			return;
		} elseif ($sEndCmd == static::KILL_ALL_PHP_INSTANCES) {
			$this->log('KILLING ALL PHP INSTANCES...');
			$this->log(static::killAllPHPProcesses());
			exit;
		} elseif ($sEndCmd == static::RESPAWN_DAEMON) {
			$iS = 60 - intval(date('s'));
			if (AfrCliHttpDetect::isCli()) {
				$sEntryPoint = self::getJobTenantEntryPoint();
				$this->log("Respawn in $iS seconds: $sEntryPoint");
				register_shutdown_function(function () use ($sEntryPoint) {
					AfrBackgroundWorkerClass::execWithArgs($sEntryPoint);
					//TODO: respawn as worker!!
					//TODO: logs for workers

				});
			} else {
				$this->log("Respawn in $iS seconds: " . $_SERVER['REQUEST_URI']);
			}
			sleep($iS + 1);
			if (!AfrCliHttpDetect::isCli()) {
				echo '<script>window.location.reload();</script>';
			}
		}
	}


	/**
	 * @param string|null|false|array $saDSJW
	 * @return mixed|null
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 * @throws AfrException
	 */
	protected static function decodeWorkerInitDataFromCliArgvOrRequest($saDSJW = null)//ok
	{
		if (is_array($saDSJW) && !empty($saDSJW[static::command])) {
			return $saDSJW;
		}
		//	$d= AfrRouterConstantsInterface::CRON_DAEMON_ARGV_KEY;	$w= AfrRouterConstantsInterface::CRON_WORKER_ARGV_KEY;
		if (is_null($saDSJW)) {
			if (AfrCliHttpDetect::isCli() && ($aArgsLst = AfrGetOpt::getInstance()->getoptDetectAllArgs($_SERVER['argv'], false))) {
				$saDSJW = $aArgsLst[AfrRouterConstantsInterface::CRON_WORKER_ARGV_KEY] ?? null;
			}/* elseif (!AfrCliHttpDetect::isCli() && !empty($_REQUEST[AfrRouterConstantsInterface::CRON_WORKER_ARGV_KEY])) {
			//todo security over http:)
			$saDSJW = $_REQUEST[AfrRouterConstantsInterface::CRON_WORKER_ARGV_KEY];
			}*/
		}

		if (!empty($saDSJW)) {
			$sWorkerDataB64 = trim((string)(is_array($saDSJW) ? array_shift($saDSJW) : $saDSJW));
			$sWorkerDataJsonStr = trim(base64_decode(strtr($sWorkerDataB64, '@_', '+/') .
				str_repeat('=', 3 - (3 + strlen($sWorkerDataB64)) % 4)));
			//echo "$sWorkerDataB64\n$sWorkerDataJsonStr\n$saDSJW";die();
			//rtrim(strtr(base64_encode($string), '+/', '@_'), '=');
			//base64_decode( strtr( $data, '@_', '+/') . str_repeat('=', 3 - ( 3 + strlen( $data )) % 4 ));
			if (in_array(substr($sWorkerDataJsonStr, 0, 1), ['[', '{'])) {
				return json_decode($sWorkerDataJsonStr, true);
			}
		}
		return null;
	}


	protected function getTenantName(): string //ok
	{
		return str_replace(' ', '_', Afr::getTenantAlias() ?? AfrTenant::AFR_NO_TENANT);
	}


	protected function getLockName(): string //ok
	{
		if (empty($this->sLockName)) {
			/*$this->sLockName = 'Cron' .
				($this->bIsWorker ? 'Worker_' : 'Daemon_') .
				$this->getTenantName() . '_' .
				$this->getHash();*/
			$this->sLockName = $this->bIsWorker ?
				$this->getLockNameForJob($this->oWorkerJob) :
				'CronDaemon_' . $this->getTenantName() . '_' . $this->getHash();
		}
		return $this->sLockName;
	}

	protected function getLockNameForJob(AfrCronJob $oJob): string //ok
	{
		return 'CronWorker_' . $this->getTenantName() . '_' . $oJob->getHash();
	}


	protected function getAliasName(): string //ok
	{
		if (empty($this->sAliasName)) {
			$sTenant = $this->getTenantName();
			if ($this->bIsWorker) {
				$sAlias = $this->oWorkerJob->getAlias() ?? '';
				$this->sAliasName =
					'Worker-' . $sTenant . '-' .
					($sAlias ? $sAlias . '-' : '') .
					$this->getHash();
			} else {
				$this->sAliasName = 'Daemon-' . $sTenant;
			}
		}

		return $this->sAliasName;
	}


	protected function getHash(): string //ok
	{
		return $this->bIsWorker ?
			$this->oWorkerJob->getHash() :
			static::computeHash('Daemon@' . $this->getTenantName());
	}

	public static function computeHash(string $sCommand): string //ok
	{
		if (!empty(self::$aHashCache[$sCommand])) return self::$aHashCache[$sCommand];

		$md5 = md5($sCommand);
		$mod = 0;
		for ($i = 0; $i < 32; $i++) {
			$mod = ($mod * 16 + hexdec($md5[$i])) % 3656158440062976;
		}
		return self::$aHashCache[$sCommand] =
			str_pad(base_convert($mod, 10, 36), 10, '_', STR_PAD_LEFT);
	}

	protected function callDisplaySpinnerAndSleep(): void
	{
		$this->displaySpinner();
		if (static::$fiDaemonSleepSeconds < 1) {
			usleep((int)(1000 * 1000 * static::$fiDaemonSleepSeconds));
		} else {
			sleep((int)static::$fiDaemonSleepSeconds);
		}
		$this->displaySpinner(true);
	}

	private function displaySpinner(bool $bClear = false): void //ok
	{
		if (!AfrCliHttpDetect::isCli()) {
			return;
		}
		if ($bClear) {
			echo "\r\033[0m" . str_repeat(' ', 40) . "\r";
			return;
		}
		$sDate = '🤖' . date('Y-m-d H:i:sO');
		$iStep = static::$fiDaemonSleepSeconds >= 1 ? 6 : 3;
		$iSec = (int)(intval(substr($sDate, -2)) / $iStep);
		echo "\r\033[32m" .
			$sDate .
			" \033[34m" .
			str_repeat(' ', $iSec) .
			$this->spinnerShapes[$this->spinnerIndex] .
			//	"\033[0m".
			str_repeat(' ', 60 / $iStep - $iSec);
		$this->spinnerIndex = ($this->spinnerIndex + 1) % count($this->spinnerShapes);
	}

	public static function killAllPHPProcesses(): string //ok
	{
		if (DIRECTORY_SEPARATOR === '\\') {
			exec('taskkill /F /IM php.exe', $output, $status);
		} else {
			// Unix/Linux/macOS: Kill all php processes except the current one
			exec("pkill -f php", $output, $status);
		}
		if ($status === 0) {
			echo $r = "All PHP processes terminated successfully.\n" . print_r($output, true);
		} else {
			echo $r = "Failed to terminate PHP processes or no processes found.\n" . print_r($output, true);
		}
		return $r;
	}

	/**
	 * @return string
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 * @throws AfrException
	 */
	public function getJobTenantEntryPoint(): string
	{
		$sEntryPoint = AfrCliHttpDetect::getEntryPoint();
		$sEntryPoint .= AfrTenant::getTenantArgInCli() ? '' : ' --tenant=' . escapeshellarg(AfrTenant::getTenantAlias());
		return $sEntryPoint;
	}


	/**
	 * @throws AfrEventException
	 * @throws AfrContainerException
	 */
	protected function setLogger(AfrCronLoggerInterface $oCronLogger = null): self
	{
		if (!empty($oCronLogger)) {//set
			$this->oCronLogger = $oCronLogger;
		} elseif (empty($this->oCronLogger)) {//init
			$this->oCronLogger = Afr::app() ?
				Afr::app()->container()->get(AfrCronLoggerInterface::class) :
				AfrCronLoggerClass::getInstance();
		}
		return $this;
	}

	/**
	 * @return AfrCronLoggerClass|AfrCronLoggerInterface
	 */
	public function getLogger(): AfrCronLoggerInterface
	{
		return $this->oCronLogger;
	}

	public function log(string $message, bool $bError = false, int $exitCode = null): self
	{
		$this->getLogger()->log($message, $bError, $exitCode);
		return $this;
	}


	protected function getJobCacheLocation(): string
	{
		return AfrSysTempDir::sysGetTempDirAliasSubDir(__CLASS__) .
			DIRECTORY_SEPARATOR . preg_replace(
				'/[^A-Za-z0-9_-]/', '_',
				(Afr::getTenantAlias() ?? AfrTenant::AFR_NO_TENANT)
			) . '.cache';
	}

	/**
	 * @throws AfrEventException
	 * @throws AfrEnvException
	 * @throws AfrContainerException
	 */
	protected function setAllDaemonJobsAndCache(bool $bRefreshInstanceCache = false): int
	{
		$oJobsSources = AfrConJobSources::getInstance();
		$this->aJobs = $oJobsSources->getAllJobs($bRefreshInstanceCache);

		$i = 0;
		foreach ($this->aJobs as $aJobs) {
			foreach ($aJobs as $oJob) {
				$i++;
			}
		}
		file_put_contents($this->getJobCacheLocation(), serialize($this->aJobs));
		$this->log(
			'Loaded #' . $i . ' JOBS from ' .
			'SOURCES(' . implode(', ', array_keys($oJobsSources->getAllSources())) . ')'
		);

		return $i;
	}


	/**
	 * @throws AfrEventException
	 * @throws AfrContainerException
	 * @throws AfrEnvException
	 */
	protected function setAllDaemonJobsFromLatestCacheVersion(): void
	{
		$aFromCache = null;
		$sCacheFile = $this->getJobCacheLocation();
		if (is_file($sCacheFile) && is_readable($sCacheFile) && filemtime($sCacheFile) > time() - 3600) {
			$aFromCache = unserialize(file_get_contents($this->getJobCacheLocation()));
		}
		if (is_array($aFromCache)) {
			$this->aJobs = $aFromCache;
		} else {
			$this->setAllDaemonJobsAndCache(true);
		}
	}


	/**
	 * @param string $sCommand
	 * @return array|void
	 * @throws AfrException
	 */
	protected function runWorkerProcOpen(string $sCommand): void
	{
		// services will always restart automatically if a crash occurs
		$onNewJob = $this->oWorkerJob->isAlwaysRunService() ? $this->oWorkerJob : null;
		$this->log('$this->oWorkerJob->isAlwaysRunService()?' . (
			$this->oWorkerJob->isAlwaysRunService() ? 1 : 0
			) . '@ ' . $this->oWorkerJob->exportLine());
		$sPipeFilePrefix = AfrSysTempDir::sysGetTempDirAliasSubDir(__CLASS__) .
			DIRECTORY_SEPARATOR . $this->getLockName() . '_P' . ((int)getmypid()) . '.pipe';
		$sPipeFileOutput = $sPipeFilePrefix . '.out';
		$sPipeFileError = $sPipeFilePrefix . '.err';
		touch($sPipeFileOutput);
		touch($sPipeFileError);
		$fh1 = fopen($sPipeFileOutput, 'w');
		$fh2 = fopen($sPipeFileError, 'w');

		$cleanup = function () use (&$sPipeFileOutput, &$sPipeFileError, &$fh1, &$fh2) {
			is_resource($fh1) && fclose($fh1);
			is_resource($fh2) && fclose($fh2);
			is_file($sPipeFileOutput) && unlink($sPipeFileOutput);
			is_file($sPipeFileError) && unlink($sPipeFileError);
		};

		$spec = [1 => $fh1, 2 => $fh2];

		if (DIRECTORY_SEPARATOR === '\\') { //windows
			$rProcess = proc_open($sCommand, $spec, $pipes, null, null, [
				'bypass_shell' => true,
				'create_new_console' => false,
				//	'blocking_pipes' => false,
				//	'create_process_group' => true,
				//	'suppress_errors' => true,
			]);
		} else {
			$rProcess = proc_open($sCommand, $spec, $pipes);
		}
		if ($rProcess) {
			$aStatus = proc_get_status($rProcess);
			if (empty($aStatus['running'])) {
				$exitCode = proc_close($rProcess);
				$this->log("Worker failed to register running status! Exit code:`$exitCode` for " . $sCommand, true);
				$cleanup();
				return;
			}
			$iPid = intval($aStatus['pid'] ?? 0);
			$this->log("Worker thread PID($iPid): " . $sCommand);
			$sTargetLoopSecondForAliveChecks = rand(0, 59);
			$bExpiredJob = false;
			usleep(20_000);

			while ($aStatus['running'] && !$bExpiredJob) {
				$aStatus = proc_get_status($rProcess);
				if (!empty($aStatus['running']) && intval(date('s')) === $sTargetLoopSecondForAliveChecks) {
					//$this->log('MakrK: ' . $sTargetLoopSecondForAliveChecks); //TODO: remove!!!!!!
					//substract one sec
					$sTargetLoopSecondForAliveChecks = ($sTargetLoopSecondForAliveChecks + 59) % 60;
					$this->WorkerProcOpenReadJobChangesEveryMinute($bExpiredJob, $onNewJob);
					$bExpiredJob && usleep(50_000);
				} else {
					usleep(100_000);
				}
			}
			$this->log('$bExpiredJob-outsideWhile: ' . intval($bExpiredJob), true);

			is_resource($fh1) && fclose($fh1);
			is_resource($fh2) && fclose($fh2);
			!$bExpiredJob && usleep(50_000);

			if ($bExpiredJob && $aStatus['running']) { //terminated by process execution TODO XXX
				$this->log('JOB force close for ' . $sCommand);
				$exitCode = 'KILL:';
				$aStatusTerm = is_resource($rProcess) ? proc_get_status($rProcess) : null;
				if (is_resource($rProcess)) {
					if (!empty($aStatusTerm['running'])) {
						$exitCode .= proc_terminate($rProcess, 15) ? 'proc_terminate-TRUE' : 'proc_terminate-FALSE'; // SIGTERM hard kill on Windows
					} else {
						$exitCode = proc_close($rProcess);
					}
				}
				usleep(75_000);
				$aStatusTerm = is_resource($rProcess) ? proc_get_status($rProcess) : null;
				if (!empty($aStatusTerm['running'])) {
					is_resource($rProcess) && proc_terminate($rProcess, 9); // SIGKILL
				}
				if (!empty($aStatusTerm['running']) && $iPid && function_exists('posix_kill')) {
					$exitCode .= posix_kill($iPid, 2) ? ' posix_kill-TRUE' : ' posix_kill-FALSE'; //SIGINT

				}
				if (strpos($exitCode, '-TRUE') !== false) $exitCodeInStatus = 0;
				elseif (strpos($exitCode, '-FALSE') !== false) $exitCodeInStatus = 1;
				else $exitCodeInStatus = intval($exitCode);
				$this->log('JOB exited: ' . $sCommand . " (#$exitCode)", (bool)$exitCodeInStatus, $exitCodeInStatus);

			} else {
				$exitCodeInStatus = intval($aStatus['exitcode']);
				$exitCode = proc_close($rProcess);
				$exitCode = $exitCodeInStatus > -1 ? $exitCodeInStatus : $exitCode;
				$this->log('JOB completed: ' . $sCommand . " (#$exitCode)", (bool)$exitCode, intval($exitCode));
			}

			foreach ([$sPipeFileOutput => (bool)$exitCode, $sPipeFileError => true] as $f => $e) {
				if (is_file($f)) {
					$sPipe = file_get_contents($f, false, null, 0, 1024 * 1024);
					if ($sPipe && strlen($sPipe)) $this->log($sPipe, $e);
				}
				unset($sPipe);
			}
		} else {
			sleep(120); //something went wrong here... wait 2 minutes and retry
		}
		$cleanup();
		$this->unlock();

		if ($onNewJob) { //open the new execution thread if needed
			$this->spawnCliWorker($onNewJob, true);
		}
	}

	protected function WorkerProcOpenReadJobChangesEveryMinute(bool &$bExpiredJob, &$onNewJob): void
	{
		$this->aJobs = [];
		/** @var AfrCronJob[] $aNewJobs */
		$aNewJobs = [];
		try {
			$this->setAllDaemonJobsFromLatestCacheVersion();
		} catch (\Throwable $e) {
			$this->log('Exception @setAllDaemonJobsFromLatestCacheVersion: ' . $e->getMessage());
		}
		foreach ($this->aJobs as $aAlias => $aJobs) {
			foreach ($aJobs as $oJob) {
				if ($this->oWorkerJob->getHash() === $oJob->getHash() && !$oJob->isSkipped()) {
					$aNewJobs[] = $oJob; //push the newly job to a hash compare queue
				}
			}
		}
		//$this->log('$aNewJobs: '.print_r($aNewJobs,true));

		if (count($aNewJobs) > 1) { //Why???
			$oChosen = null;
			foreach ($aNewJobs as $oNewJob) {
				if ($this->oWorkerJob->getCronTime() === $oNewJob->getCronTime()) {
					$oChosen = $oNewJob;
				}
			}
			$aNewJobs = [$oChosen ?: array_slice($aNewJobs, 0, 1)]; //selected ot get first
		} elseif (count($aNewJobs) === 1) {
			$oJobToCheck = array_pop($aNewJobs);
			if ($oJobToCheck->isAlwaysRunService()) {
				$onNewJob = $oJobToCheck; //take new service settings for the next respawn
				if (serialize($onNewJob) !== serialize($this->oWorkerJob)) {
					$bExpiredJob = true;
				}
			}
			//TODO: on restart condition?
			//TODO: on framework update?
			return; //nothing to change, because the same command is executed
		}

		//job was totally removed, so we stop the current service
		//any other job that is not a service can continue to run
		if (empty($aNewJobs)) {
			if ($this->oWorkerJob->isAlwaysRunService()) {
				$bExpiredJob = true;
				return;
			}
		}


	}


}
