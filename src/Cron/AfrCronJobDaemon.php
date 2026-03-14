<?php

namespace Autoframe\Core\Cron;

use Autoframe\Core\Afr\Afr;
use Autoframe\Core\CliTools\AfrCheckExec;
use Autoframe\Core\CliTools\AfrCliHttpDetect;
use Autoframe\Core\CliTools\AfrSysTempDir;
use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\Cron\Log\Channel\AfrCronLogChannelDoNotLog;
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
use Autoframe\Core\Cron\Log\AfrCronLoggerClass;
use Autoframe\Core\Http\CurlGetBodyWithTimeout\AfrGetHttpBodyWithTimeout;
use Autoframe\Core\Error\AfrError;
use Autoframe\Core\Http\Request\AfrCliConstantsInterface;

class AfrCronJobDaemon
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

	protected bool $bIsWorker;
	protected bool $bLoggedOnceEmptyQueue = false;
	protected AfrCronLoggerInterface $oCronLogger;
	protected float $fStartTime;
	private string $sLockName;
	private string $sAliasName;
	private static array $aHashCache = [];
	protected ?AfrCronJob $oWorkerJob = null;
	protected ?AfrLockFileClass $oLockWorker = null;

	private array $spinnerShapes = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
	private int $spinnerIndex = 0;
	public static float $fiDaemonSleepSeconds = 2;

	/**
	 * Make.
	 * @param AfrCronLoggerInterface|null $oAltLogger
	 * @param string|null|false|array $saDSJW CLI worker data AfrCliConstantsInterface::CRON_WORKER_ARGV_KEY
	 * @return AfrCronJobDaemon
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 * @throws AfrException
	 */
	public static function make(AfrCronLoggerInterface $oAltLogger = null, $saDSJW = null): self
	{
		$oInstance = new static();
		$oInstance->fStartTime = floatval($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
		$oInstance
			->setLogger($oAltLogger)
			->prepareDaemonWorkerLogger($saDSJW);
		return $oInstance;
	}

	/**
	 * Run.
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
		register_shutdown_function(function () { $this->logRunTime(); });

		$this->bIsWorker ? $this->runWorker() : $this->runDaemon();
		if ($snErr = AfrError::getLastErrorReadable([E_DEPRECATED, E_USER_DEPRECATED])) {
			$this->log($snErr, true);
		}
		$this->unlock();

	}

	/**
	 * Log run time.
	 */
	public function logRunTime(bool $bInlineString = false): string
	{
		$sOut = '';
		if (!empty($this->fStartTime)) {
			$fTimeSeconds = microtime(true) - $this->fStartTime;
			$sTxt = $fTimeSeconds < 1 ?
				(round($fTimeSeconds * 1000, 3) . 'ms') :
				(round($fTimeSeconds, 3) . 'sec');
			$sOut = ($this->bIsWorker ? 'Job execution' : 'Daemon up') . ' time: ' . $sTxt;
			$this->fStartTime = 0.0;
		}
		if (!$bInlineString && $sOut) {
			if (!empty($this->oWorkerJob) && $this->oWorkerJob->isTurnOffLog()) {
				return $sOut;
			}
			$this->log($sOut);
		}
		return $sOut;

	}

	protected function lock(bool $bForce = false): bool
	{
		$sLockName = $this->getLockName();
		if ($bForce || !($this->bIsWorker && $this->oWorkerJob->isAllowParallelRun())) {
			if (empty($this->oLockWorker)) {
				$this->oLockWorker = new AfrLockFileClass($sLockName);
			}
			if ($this->oLockWorker->isLocked()) {
				$sCmd = $this->oWorkerJob ? $this->oWorkerJob->getCommand() : AfrCliHttpDetect::getEntryPoint();
				$this->log("Already running! Lock PID(" . $this->oLockWorker->getLockPid() . ") $sLockName; CMD: " . $sCmd);
				return false;
			} elseif (!$this->oLockWorker->obtainLock()) {
				$this->log("Fail to obtain lock $sLockName", true);
				$this->oLockWorker->releaseLock();
				return false;
			} else {
				$this->log($sLockName . '» Locked at PID(' . $this->oLockWorker->getLockPid() . ")");
			}
		} else {
			$this->log("Multi-threads allowed on [$sLockName] " . $this->oWorkerJob->getCommand());
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
	 * @param string|null|false|array $saDSJW CLI worker data AfrCliConstantsInterface::CRON_WORKER_ARGV_KEY
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
		AfrEvent::dispatchEvent(AfrEvent::CRON_DAEMON, [__FUNCTION__]);
		//TODO: !!! JOBS venite din module / CORE JOBS ca si LOADER static

		$this->setAllDaemonJobsAndCache(false);
		$bWhile = $bDaemonStartup = true;
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
						$this->log("[$sGroupAlias] Corrupted JOB: " . print_r($oJob, true), true);
						continue;
					}
					if ($oJob->isSkipped()) {
						continue;
					}
					if (!$oJob->isValidConfig()) {
						$this->log("[$sGroupAlias] Invalid registered Job: " . $oJob, true);
						continue;
					}

					if (!$oJob->canTrigger($aNow) && !($bDaemonStartup && $oJob->isRunOnStartup())) {
						continue;
					}

					if ($oJob->isAlwaysRunService()) {
						$oJobLock = new AfrLockFileClass($sJobLockName = $this->getLockNameForJob($oJob));
						if ($oJobLock->isLocked()) {
							if (empty($aShowOnceEveryHour[$h = date('H')])) {
								$aShowOnceEveryHour = [$h => []];//reset each hour
							}
							if (empty($aShowOnceEveryHour[$h][$sJobLockName])) {
								//log message once each hour
								$this->log(
									"Service is already running on lock $sJobLockName PID(" .
									$oJobLock->getLockPid() . ") " . $oJob->getCommand()
								);
								$aShowOnceEveryHour[$h][$sJobLockName] = true;
							}
							continue;
						}
					}

					$sCmd = strtoupper($oJob->getCommand());
					if (in_array($sCmd, [static::KILL_ALL_PHP_INSTANCES, static::EXIT_DAEMON, static::RESPAWN_DAEMON])) {
						$this->log("[$sGroupAlias] Ending soon because of command: " . $oJob->getCronTime() . ' ' . $oJob->getCommand());
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
			$this->loadAllDaemonJobsFromLatestCacheVersion(); //read the latest cache version
			$bDaemonStartup = false;
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
		$sCommand = $this->oWorkerJob->getCommand();
		if (empty($sCommand)) {
			$this->log('Invalid job on worker: ' . $this->oWorkerJob->exportLine(), true);
		} elseif ($this->processCommandRefreshJobsEmbeddedWorker()) {
			return; //Do nothing
		} elseif (filter_var($sCommand, FILTER_VALIDATE_URL)) {
			$this->runWorkerOpenUrl($sCommand);
		} else {
			$sCommand = (substr($sCommand, 0, 4) === 'CLI:') ?
				trim(substr($sCommand, 4)) :
				AfrBackgroundWorkerClass::getPhpBin(false) . ' ' . trim($sCommand);

			AfrCheckExec::isProcOpenCloseAvailable() ?
				$this->runWorkerProcOpen($sCommand) :
				$this->runWorkerExecFallback($sCommand . (DIRECTORY_SEPARATOR !== '\\' ? ' > /dev/null &' : ''));
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
			AfrEvent::dispatchEvent(AfrEvent::CRON_JOB, [__FUNCTION__, $oJob]);
			$this->spawnCliWorker($oJob);
		}
	}

	/**
	 * @param AfrCronJob $oJob
	 * @param bool $bRespawnNew
	 * @return void
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 * @throws AfrException
	 */
	protected function spawnCliWorker(AfrCronJob $oJob, bool $bRespawnNew = false): void
	{
		$sEntryPoint = $this->getJobTenantEntryPoint();
		$sCronWorkerArgvKey = ' --' . AfrCliConstantsInterface::CRON_WORKER_ARGV_KEY . '=';
		$sJobData = rtrim(strtr(base64_encode((string)$oJob), '+/', '@_'), '=');
		if (strpos($sEntryPoint, $sCronWorkerArgvKey) !== false) {
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
		$this->log((
			$bRespawnNew ? 'RESPAWN' : 'Spawn') .
			($oJob->isAlwaysRunService() ? ' service' : '') .
			' worker [' . static::computeHash($sCmd) . '](' . ($oJob->getCronTime()) . ') ' . $sCmd
		);
		AfrBackgroundWorkerClass::execWithArgs($sEntryPoint, true); //background detached pid
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
		//	$d= AfrCliConstantsInterface::CRON_DAEMON_ARGV_KEY;	$w= AfrCliConstantsInterface::CRON_WORKER_ARGV_KEY;
		if (is_null($saDSJW)) {
			if (AfrCliHttpDetect::isCli() && ($aArgsLst = AfrGetOpt::getInstance()->getoptDetectAllArgs($_SERVER['argv'], false))) {
				$saDSJW = $aArgsLst[AfrCliConstantsInterface::CRON_WORKER_ARGV_KEY] ?? null;
			}/* elseif (!AfrCliHttpDetect::isCli() && !empty($_REQUEST[AfrCliConstantsInterface::CRON_WORKER_ARGV_KEY])) {
			// THIS IS UNSECURE, so the implementation is temporarily stopped
			$saDSJW = $_REQUEST[AfrCliConstantsInterface::CRON_WORKER_ARGV_KEY];
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
			$this->sLockName = $this->bIsWorker ?
				$this->getLockNameForJob($this->oWorkerJob) :
				'CronDaemon_' . $this->getTenantName() . '_' . $this->getHash();
		}
		return $this->sLockName;
	}

	protected function getLockNameForJob(AfrCronJob $oJob): string //ok
	{
		return 'CronWorker_' .
			($oJob->isTenantInsensitiveJob() ? '' : $this->getTenantName()) .
			'_' . $oJob->getHash();
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

	/**
	 * Compute hash.
	 */
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

	/**
	 * Kill all phpprocesses.
	 */
	public static function killAllPHPProcesses(): string //ok
	{
		// Windows OR Unix/Linux/macOS: Kill all php processes
		exec(DIRECTORY_SEPARATOR === '\\' ? 'taskkill /F /IM php.exe' : "pkill -f php", $output, $status);
		return $status === 0 ?
			"All PHP processes terminated successfully.\n" . print_r($output, true) :
			"Failed to terminate PHP processes or no processes found.\n" . print_r($output, true);
	}

	/**
	 * Get job tenant entry point.
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
	 * Get logger.
	 * @return AfrCronLoggerClass|AfrCronLoggerInterface
	 */
	public function getLogger(): AfrCronLoggerInterface
	{
		return $this->oCronLogger;
	}

	/**
	 * Log.
	 */
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
		$this->aJobs = AfrConJobSources::getInstance()->getAllJobs($bRefreshInstanceCache);

		$bRefreshJobsWorkerFound = false;

		$i = 0;
		foreach ($this->aJobs as $aJobs) {
			foreach ($aJobs as $oJob) {
				if ($oJob->getCommand() === self::REFRESH_JOBS && $oJob->isValidConfig()) {
					$bRefreshJobsWorkerFound = true;
				}
				$i++;
			}
		}
		if (!$bRefreshJobsWorkerFound) {
			$oRefreshJob = new AfrCronJob();
			$oRefreshJob->setCommand(self::REFRESH_JOBS)->setCronTime('* * * * *');
			$this->aJobs['AfrCronJobDaemon'][self::REFRESH_JOBS] = $oRefreshJob;
		}
		file_put_contents($this->getJobCacheLocation(), serialize($this->aJobs));
		$this->log(
			'Loaded #' . $i . ' JOBS from ' .
			'SOURCES(' . implode(', ', array_keys($this->aJobs)) . '); ' .
			$this->logRunTime(true)
		);

		return $i;
	}


	/**
	 * @throws AfrEventException
	 * @throws AfrContainerException
	 * @throws AfrEnvException
	 */
	protected function loadAllDaemonJobsFromLatestCacheVersion(): void
	{
		$aFromCache = null;
		$sCacheFile = $this->getJobCacheLocation();
		if (is_file($sCacheFile) && is_readable($sCacheFile) && filemtime($sCacheFile) > time() - 3600) {
			$aFromCache = unserialize(file_get_contents($sCacheFile));
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
			$this->oWorkerJob->isTurnOffLog() or $this->log("Worker thread PID($iPid): " . $sCommand);
			$sTargetLoopSecondForAliveChecks = rand(0, 59);

			$bJobUpdated = $bTimeLimitReached = false;
			usleep(20_000);
			$fnLimitSeconds = $this->oWorkerJob->isTimeLimitedSeconds() ? $this->oWorkerJob->getTimeLimitedSeconds() + 0.02 : null;
			$fnMaxRunTime = empty($fnLimitSeconds) ? null : $fnLimitSeconds + $this->fStartTime;
			while ($aStatus['running'] && !$bJobUpdated && !$bTimeLimitReached) {
				$aStatus = proc_get_status($rProcess);

				if (!empty($aStatus['running']) && !is_null($fnMaxRunTime) && microtime(true) >= $fnMaxRunTime) {
					$bTimeLimitReached = true;
					usleep(50_000);
				} elseif (!empty($aStatus['running']) && intval(date('s')) === $sTargetLoopSecondForAliveChecks) {
					$sTargetLoopSecondForAliveChecks = ($sTargetLoopSecondForAliveChecks + 59) % 60; //subtract one sec
					$this->WorkerProcOpenReadJobChangesEveryMinute($bJobUpdated, $onNewJob);
					$bJobUpdated && usleep(50_000);
				} else {
					if ($this->oWorkerJob->canTriggerAlwaysRunServiceRestartTime()) {
						$this->oWorkerJob->isTurnOffLog() or $this->log(
							'Service will restart now because of the flag A(' .
							$this->oWorkerJob->getAlwaysRunServiceUnixCronTimeValue() . ')'
						);
						//restart the service in one second
						$bJobUpdated = true;
					} else {
						usleep(100_000);
					}

				}
			}
			$bStopJob = $bJobUpdated || $bTimeLimitReached;
			if ($bStopJob && !$this->oWorkerJob->isTurnOffLog()) {
				if ($bJobUpdated) $this->log('Job will STOP because of new service settings: ' . $onNewJob->exportLine());
				if ($bTimeLimitReached) $this->log('Job reached time limit seconds: ' . $this->oWorkerJob->getTimeLimitedSeconds());
			}

			is_resource($fh1) && fclose($fh1);
			is_resource($fh2) && fclose($fh2);
			if ($bStopJob) usleep(50_000);

			if ($bStopJob && $aStatus['running']) {
				if ($bJobUpdated) $this->log('JOB force close because of new job settings @ ' . $sCommand);
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
				if (strpos((string)$exitCode, '-TRUE') !== false) $exitCode = 0;
				elseif (strpos((string)$exitCode, '-FALSE') !== false) $exitCode = 1;
				else $exitCode = intval($exitCode);
				$this->log('JOB exited: ' . $sCommand . " (#$exitCode)", (bool)$exitCode);

			} else {
				$exitCodeInStatus = intval($aStatus['exitcode']);
				$exitCode = proc_close($rProcess);
				$exitCode = $exitCodeInStatus > -1 ? $exitCodeInStatus : $exitCode;
				$this->log('JOB completed: ' . $sCommand . " (#$exitCode)", (bool)$exitCode);
			}

			foreach ([$sPipeFileOutput => (bool)$exitCode, $sPipeFileError => true] as $f => $e) {
				if (is_file($f) && is_readable($f)) {
					$sPipe = file_get_contents($f);
					if ($sPipe && strlen($sPipe)) $this->log($sPipe, $e, intval($exitCode));
					unset($sPipe);
				}

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
			$this->loadAllDaemonJobsFromLatestCacheVersion();
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
			//TODO: on restart condition && on framework update?
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

	protected function processCommandRefreshJobsEmbeddedWorker(): bool
	{
		if ($this->bIsWorker && $this->oWorkerJob->getCommand() === static::REFRESH_JOBS) {
			$oRefreshJobsLock = new AfrLockFileClass(static::REFRESH_JOBS . '_' . $this->getTenantName());
			if ($oRefreshJobsLock->isLocked()) {
				return true;
			}
			$oRefreshJobsLock->obtainLock();
			set_time_limit(300);//fallback 5 minutes max to prevent unmanaged freeze
			try {
				$this->setAllDaemonJobsAndCache(true);
				AfrEvent::dispatchEvent(AfrEvent::CRON_WORKER, [__FUNCTION__]);

			} catch (\Throwable $e) {
				$this->log('Exception when ' . static::REFRESH_JOBS . ': ' . $e->getMessage(), true);
				die(99);
			}
			$oRefreshJobsLock->releaseLock();
			return true;

		}
		return false;

	}

	/**
	 * Run worker exec fallback.
	 * @param string $sCommand
	 * @return void
	 * @throws AfrException
	 */
	public function runWorkerExecFallback(string $sCommand): void
	{
		$this->log("Worker thread started via fallback exec($sCommand)");
		if ($this->oWorkerJob->isTimeLimitedSeconds()) {
			$this->log('proc_open() function is missing! Impossible to enforce time limit in seconds: ' . $this->oWorkerJob->getTimeLimitedSeconds());
		}

		$output = $result_code = null;
		$sFData = exec($sCommand, $output, $result_code);
		$result_code = intval($result_code);
		if ($sFData === false) {
			$this->log('Fail to execute: ' . $sCommand . " (#$result_code)➔ " . trim(error_get_last()['message'] ?? ''), true, $result_code);
		} else {
			$output = !empty($output) && is_array($output) ? implode("\n", $output) : (string)$sFData;
			if (!($this->oWorkerJob->isTurnOffLog() && $result_code === 0)) {
				$this->log("JOB exec completed: $sCommand (#$result_code)", $result_code !== 0);
				strlen($output) < 1 || $this->log($output, $result_code !== 0, $result_code);
			}
		}
		$onNewJob = null;
		if ($this->oWorkerJob->isAlwaysRunService()) {
			$onNewJob = $this->oWorkerJob;
			$bExpiredJob = false;
			$this->WorkerProcOpenReadJobChangesEveryMinute($bExpiredJob, $onNewJob);
		}
		$this->unlock();
		if ($onNewJob) { //open the new execution thread if needed
			$this->spawnCliWorker($onNewJob, true);
		}

	}

	/**
	 * @param string|null $sCommand
	 * @return void
	 * @throws AfrEnvException
	 */
	protected function runWorkerOpenUrl(?string $sCommand): void
	{
		$this->oWorkerJob->isTurnOffLog() or $this->log('Worker opening url: ' . $sCommand);
		$iTimeoutMs = 50000;
		$iConTimeoutMs = 50000;
		if (!empty($fnLimitSeconds = $this->oWorkerJob->getTimeLimitedSeconds())) {
			$iTimeoutMs = $iConTimeoutMs = intval($fnLimitSeconds * 1000);
		} elseif (Afr::app()) {
			$iTimeoutMs = Afr::app()->env()->getEnv('AFR_CRON_DAEMON_CURL_TIMEOUT_MS', $iTimeoutMs);
			$iConTimeoutMs = Afr::app()->env()->getEnv('AFR_CRON_DAEMON_CURL_TIMEOUT_MS', max($iConTimeoutMs, $iTimeoutMs));
		}
		$sData = AfrGetHttpBodyWithTimeout::get($sCommand, max($iTimeoutMs, $iConTimeoutMs), false);
		$inHttpStatus = AfrGetHttpBodyWithTimeout::getLastHttpStatus();

		if ($sData === false) {
			$sErr = trim(error_get_last()['message'] ?? '');
			$this->log(
				"Fail to open url $sCommand Status(" . ($inHttpStatus ?? 'NULL') . ")" . ($sErr ? ' ➔ ' . $sErr : ''),
				true
			);
		} else {
			$bError = $inHttpStatus !== 200;
			if ($bError || !$this->oWorkerJob->isTurnOffLog()) {
				$this->log(
					"URL status($inHttpStatus) from " . $sCommand . " ➔\n$sData",
					$bError
				);
			}
		}
	}


}
