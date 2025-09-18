<?php

namespace Autoframe\Core\Cron;

use Autoframe\Core\Afr\Afr;
use Autoframe\Core\CliTools\AfrCliHttpDetect;
use Autoframe\Core\CliTools\AfrCliTextColors;
use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\Cron\Log\Channel\AfrCronLogChannelDoNotLog;
use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;
use Autoframe\Core\Env\Exception\AfrEnvException;
use Autoframe\Core\Event\AfrEvent;
use Autoframe\Core\Event\Exception\AfrEventException;
use Autoframe\Core\Exception\AfrException;
use Autoframe\Core\ProcessControl\Lock\AfrLockFileClass;
use Autoframe\Core\ProcessControl\Worker\Background\AfrBackgroundWorkerClass;
use Autoframe\Core\Router\Contracts\AfrRouterConstantsInterface;
use Autoframe\Core\Tenant\AfrTenant;
use Autoframe\Core\CliTools\AfrGetOpt;
use Autoframe\Core\Cron\Log\AfrCronLoggerInterface;
use Autoframe\Core\String\AfrStr;
use Autoframe\Core\Cron\Log\AfrCronLoggerClass;
use Autoframe\Core\Http\CurlGetBodyWithTimeout\GetHttpBodyWithTimeout;

class AfrCronJobDaemon // extends AfrSingletonAbstractClass
{

	const cronTime = 'cTime';
	const command = 'cmd';
	const skipped = 'skip';
	const flags = 'flags';
	const alias = 'alias';
	const KILL_ALL_PHP_INSTANCES = 'KILL_ALL_PHP_INSTANCES';
	const EXIT_DAEMON = 'EXIT_DAEMON';
	const RESPAWN = 'RESPAWN';

	/** @var AfrCronJob[] */
	protected array $aJobs = [];

//	protected string $sCronJobsDataInputSource;
//	protected bool $bHttpInputSource = false;
	protected int $iLastExecutedMinute = -1;
	protected int $iCronFileNotFoundSafeguard = 0;
	protected int $iCronLoadTime = -1;

	protected bool $bRunCommandAsyncUsingDaemonInsteadOfWorkers = false;
	protected bool $bIsWorker;
	protected bool $bLoggedOnceEmptyQueue = false;
	protected AfrCronLoggerInterface $oCronLogger;
	private string $sLockName;
	private string $sAliasName;
	private array $aHashCache = [];
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
	public function run()
	{
		set_time_limit(0);
		ignore_user_abort(true);
		if (!($this->bIsWorker && $this->oWorkerJob->isAllowParallelRun())) {
			$this->oLockWorker = new AfrLockFileClass($sLockName = $this->getLockName());

			if ($this->oLockWorker->isLocked()) {
				$this->log("Already running! Lock pid(" . $this->oLockWorker->getLockPid() . ")\t$sLockName");
				//echo $this->getLatestLogBytes(1024 * 5); //TODO
				return;
			} elseif (!$this->oLockWorker->obtainLock()) {
				$this->log("Fail to obtain lock $sLockName", true);
				$this->oLockWorker->releaseLock();
				return;
			} else {
				$this->log('Thread: pid(' . $this->oLockWorker->getLockPid() . ") lock single thread execution » $sLockName");
			}
		}
		$this->bIsWorker ? $this->rundWorker() : $this->runDaemon();
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
		$amWorkerDataJsonArr = $saDSJW ? static::decodeWorkerInitDataFromCliArgvOrRequest($saDSJW):null;
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
		//TODO: RESPAWN if older than TS! atat pentru deamon, cat si pentru worker
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
		$oJobsSources = AfrConJobSources::getInstance();
		$this->aJobs = $oJobsSources->getAllJobs($this->oCronLogger); //print_r($this->aJobs); die;

		$this->log(
			'Starting Cron Daemon watcher: ' .
			'SOURCES(' . implode(', ', array_keys($oJobsSources->getAllSources())) . ')'
		);


		$bWhile = true;
		$sEndCmd = '';

		while ($bWhile) {
			$aNow = getdate();
			$iMinute = (int)$aNow['minutes'];
			if ($iMinute === $this->iLastExecutedMinute) {
				$this->callDisplaySpinnerAndSleep();
				continue;
			}
			$this->aJobs = $oJobsSources->getAllJobs($this->oCronLogger);
			$this->iLastExecutedMinute = $iMinute;
			//TODO reload new jobs
			/*	if ($this->sCronJobsDataInputSource) {
					$this->bHttpInputSource ?
						$this->loadCronJobsFromHttp() :
						$this->loadCronJobsFromFile();
				}*/
			// $this->aJobs = $oJobsSources->getAllJobs($this->oCronLogger);
			$aToDispatch = [];
			foreach ($this->aJobs as $sGroupAlias => $aGroup) {
				foreach ($aGroup as $oJob) {
					if(!$oJob instanceof AfrCronJob) {
						$this->log('Corrupted JOB: ' . print_r($oJob,true), true);
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
					//TODO: check
					$sCmd = strtoupper($oJob->getCommand());
					if (in_array($sCmd, [static::KILL_ALL_PHP_INSTANCES, static::EXIT_DAEMON, static::RESPAWN])) {
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
			if ($bWhile || $sEndCmd === static::RESPAWN) {
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
				$sEndCmd = static::RESPAWN;
			}
			$this->callDisplaySpinnerAndSleep();

		}

		$this->log("Daemon jobs loop ended by [$sEndCmd]");
		if ($sEndCmd) {
			$this->endCmdDaemon($sEndCmd);
		}
		$this->log("Lock release: " . ($this->oLockWorker->releaseLock() ? 'true' : 'false') . ' ' . $this->getLockName());
	}

	/**
	 * @throws AfrEventException
	 */
	protected function rundWorker(): void
	{
		AfrEvent::dispatchEvent(AfrEvent::CRON_WORKER, [__FUNCTION__]);
//		usleep(1000 * 10 * rand(0, 250));
		$sCommand = $this->oWorkerJob->getCommand();
		//$this->sCommandHash = $this->getCommandHash($sCommand);
		$this->log('Worker running: ' . $sCommand);
		if (filter_var($sCommand, FILTER_VALIDATE_URL)) {
			$sData = @file_get_contents($sCommand);
			if ($sData === false) {
				$this->log('Fail to get data from URL: ' . $sCommand . ' ➔ ' . trim(error_get_last()['message'] ?? ''), true);
			} else {
				$this->log(
					'Data from URL: ' . $sCommand .
					($this->oWorkerJob->isTurnOffLog() ? '✓' : " ➔ $sData")
				);
			}
		} else {
			$output = $result_code = null;
			//error_clear_last();
			$sFData = exec(AfrBackgroundWorkerClass::getPhpBin() . ' ' . trim($sCommand), $output, $result_code);
			if ($sFData === false) {
				$this->log('Fail to execute: ' . $sCommand . " (#$result_code)➔ " . trim(error_get_last()['message'] ?? ''), true);
			} else {
				$this->log('Data from CLI: ' . $sCommand . " (#$result_code)➔ $sFData");
			}
		}
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
				$this->runCommandAsync($oJob) :
				$this->spawnCliWorker($oJob);
		}
	}

	/**
	 * @param AfrCronJob $oJob
	 * @return void
	 * @throws AfrException
	 */
	protected function spawnCliWorker(AfrCronJob $oJob): void
	{
		$sEntryPoint = $this->getJobTenantEntryPoint();
		$sEntryPoint .= ' --' . AfrRouterConstantsInterface::CRON_WORKER_ARGV_KEY . '=' .
			rtrim(strtr(base64_encode((string)$oJob), '+/', '@_'), '=');
		$sCmd = $oJob->getCommand();
		$this->log('Spawn worker [' . $this->getHash($sCmd) . '] ' . $sCmd);
		AfrBackgroundWorkerClass::execWithArgs($sEntryPoint);
	}



	/**
	 * @throws AfrException
	 * @throws AfrEnvException
	 */
	protected function runCommandAsync(AfrCronJob $oJob): void //TODO test GetHttpBodyWithTimeout|curl
	{
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
			GetHttpBodyWithTimeout::get($sCommand, max($iTimeoutMs, $iConTimeoutMs), false);
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
			$this->log("Lock release: " . ($this->oLockWorker->releaseLock() ? '1' : '0') . ' ' . $this->getLockName());
			exit;
		} elseif ($sEndCmd == static::KILL_ALL_PHP_INSTANCES) {
			$this->log('KILLING ALL PHP INSTANCES...');
			$this->log(static::killAllPHPProcesses());
			exit;
		} elseif ($sEndCmd == static::RESPAWN) {
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
			if (AfrCliHttpDetect::isCli() && ($aArgsLst = AfrGetOpt::getInstance()->getoptDetectAllArgs($_SERVER['argv']))) {
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
			$this->sLockName = 'Cron' .
				($this->bIsWorker ? 'Worker_' : 'Daemon_') .
				$this->getTenantName() . '_' .
				$this->getHash();
		}
		return $this->sLockName;
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


	protected function getHash(string $sCommand = '', string $sGetTenantName = null): string //ok
	{
		if (empty($sCommand)) {
			$sCommand = $this->bIsWorker ?
				$this->oWorkerJob->getCommand() :
				'Daemon@' . ($sGetTenantName ?? $this->getTenantName());
		}
		if (empty($this->aHashCache[$sCommand])) {
			$md5 = md5($sCommand);
			$mod = 0;
			for ($i = 0; $i < 32; $i++) {
				$mod = ($mod * 16 + hexdec($md5[$i])) % 3656158440062976;
			}
			$this->aHashCache[$sCommand] = str_pad(base_convert($mod, 10, 36), 10, '_', STR_PAD_LEFT);
		}
		return $this->aHashCache[$sCommand];
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


}
