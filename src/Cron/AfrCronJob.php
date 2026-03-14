<?php

namespace Autoframe\Core\Cron;

use Autoframe\Core\Afr\Afr;
use Autoframe\Core\CliTools\AfrCliHttpDetect;
use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\Event\Exception\AfrEventException;
use Autoframe\Core\Exception\AfrException;
use Autoframe\Core\InterfaceToConcrete\AfrVendorPath;

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
final class AfrCronJob
{
	protected array $aJob = [
		AfrCronJobDaemon::flags => null, // S O P T(0.5) A I
		AfrCronJobDaemon::cronTime => null, //unix * * * * *
		AfrCronJobDaemon::command => null, //executed with proc_open() / exec()
		AfrCronJobDaemon::alias => null, // some name for logging
		AfrCronJobDaemon::skipped => false, //line starts with #
	];

	/**
	 * Parse line.
	 */
	public static function parseLine(string $sLine): array
	{
		$sLine = trim($sLine);
		if (empty($sLine) || strlen($sLine) < 11) {
			return [];
		}
		$sFirstChar = substr($sLine, 0, 1);
		if ($sFirstChar === '{' || $sFirstChar === '[') {
			return (array)json_decode($sLine, true);
		}
		$aJob = [];
		if ($sFirstChar === '#') {
			$aJob[AfrCronJobDaemon::skipped] = true;
			$sLine = substr($sLine, 1);
			$sFirstChar = substr($sLine, 0, 1);
		}
		if ($sFirstChar === '<' && strpos($sLine, '>') !== false) {
			$sFlags = explode('>', substr($sLine, 1))[0];
			$aJob[AfrCronJobDaemon::flags] = $sFlags;
			$sLine = ltrim(substr($sLine, strlen($sFlags) + 2));
		}

		$parts = explode(' ', $sLine);
		if (count($parts) >= 6) {
			$aJob += [
				AfrCronJobDaemon::cronTime => trim(implode(' ', array_slice($parts, 0, 5))),
				AfrCronJobDaemon::command => trim(implode(' ', array_slice($parts, 5))),
			];
		}

		return $aJob;
	}

	/**
	 * Parse lines.
	 * @param string $sLines
	 * @return AfrCronJob[]
	 */
	public static function parseLines(string $sLines): array
	{
		$aJobs = [];
		$aLines = array_filter(
			explode("\n", str_replace(["\r\n", "\r"], "\n", $sLines)),
			fn($line) => trim($line) !== ''
		);
		foreach ($aLines as $sLine) {
			$aJobs[] = new self($sLine);
		}
		return $aJobs;
	}

	/**
	 * Export lines.
	 */
	public static function exportLines(array $aJobs): string
	{
		$aOut = [];
		foreach ($aJobs as $oJob) {
			if ($oJob instanceof AfrCronJob) {
				$aOut[] = $oJob->exportLine();
			} elseif (is_string($oJob) && count(self::parseLine($oJob)) > 1) {
				$aOut[] = $oJob; //json or simple line
			} elseif (!empty($oJob[AfrCronJobDaemon::cronTime]) && !empty($oJob[AfrCronJobDaemon::command])) {
				$aOut[] = (new self($oJob))->exportLine();
			}
		}
		return implode("\n", $aOut);
	}

	/**
	 * Create a new instance.
	 */
	public function __construct($sLine_OR_aJob = null)
	{
		if (!empty($sLine_OR_aJob)) {
			if (is_string($sLine_OR_aJob)) {
				$this->aJob = array_merge($this->aJob, self::parseLine($sLine_OR_aJob));
			} elseif (is_array($sLine_OR_aJob)) {
				$this->aJob = array_merge($this->aJob, $sLine_OR_aJob);
			}
		}

	}


	/**
	 * Export line.
	 */
	public function exportLine(): string
	{
		return json_encode($this->aJob);
	}


	/**
	 * Has flag.
	 */
	public function hasFlag(string $sFlag): bool
	{
		if (empty($this->aJob[AfrCronJobDaemon::flags])) {
			return false;
		}
		return strpos($this->aJob[AfrCronJobDaemon::flags], $sFlag) !== false;
	}

	/**
	 * Get flag val.
	 */
	public function getFlagVal(string $sFlag): ?string
	{
		if (empty($this->aJob[AfrCronJobDaemon::flags])) {
			return null;
		}
		$sFlag .= '(';
		if (($iFlagPos = strpos($sF = $this->aJob[AfrCronJobDaemon::flags], $sFlag)) !== false) {
			if ($iEnd = strpos($sF, ')', $iFlagPos + 1)) {
				return substr($sF, $iFlagPos + 2, $iEnd - $iFlagPos - 2);
			}
		}
		return null;
	}

	/**
	 * Set flag.
	 */
	public function setFlag(string $sFlag, bool $bOn, $mValue = null): self
	{
		$sFoundVal = '';
		if (empty($this->aJob[AfrCronJobDaemon::flags])) {
			if ($bOn) {
				$this->aJob[AfrCronJobDaemon::flags] = '';
			}
			$bHas = false;
		} else {
			if ($bHas = $this->hasFlag($sFlag)) {
				$sFoundVal = $this->getFlagVal($sFlag);
				$sFoundVal = strlen((string)$sFoundVal) ? "($sFoundVal)" : '';
			}
		}

		$mValue = $bOn && strlen((string)$mValue) ? "($mValue)" : ''; //wrap inside parenthesis

		if ($bHas && !$bOn) {
			$this->aJob[AfrCronJobDaemon::flags] = str_replace($sFlag . $sFoundVal, '', $this->aJob[AfrCronJobDaemon::flags]);
		} elseif (!$bHas && $bOn) {
			$this->aJob[AfrCronJobDaemon::flags] = $sFlag . $mValue . $this->aJob[AfrCronJobDaemon::flags];
		} elseif ($bOn && $bHas && $mValue !== $sFoundVal) {
			$this->aJob[AfrCronJobDaemon::flags] = str_replace($sFlag . $sFoundVal, $sFlag . $mValue, $this->aJob[AfrCronJobDaemon::flags]);
		}
		//elseif (!$bHas && !$bOn) {}
		return $this;
	}

	/**
	 * Set run on startup.
	 */
	public function setRunOnStartup(bool $bOn): self
	{
		return $this->setFlag('S', $bOn);
	}

	/**
	 * Is run on startup.
	 */
	public function isRunOnStartup(): bool
	{
		return $this->hasFlag('S');
	}

	/**
	 * Set time limited seconds.
	 * @throws AfrException
	 */
	public function setTimeLimitedSeconds(bool $bOn, float $fSleepAfterJobDone = null): self
	{
		if ($bOn && empty($fSleepAfterJobDone)) {
			throw new AfrException('The flag `TimeLimitedSeconds` T(x) can`t be ON with null seconds!');
		}
		return $this->setFlag('T', $bOn, $fSleepAfterJobDone);
	}

	/**
	 * Get time limited seconds.
	 */
	public function getTimeLimitedSeconds(): ?float
	{
		return (float)($this->getFlagVal('T') ?? null);
	}

	/**
	 * Is time limited seconds.
	 */
	public function isTimeLimitedSeconds(): bool
	{
		return $this->hasFlag('T');
	}

	/**
	 * Set turn off log.
	 */
	public function setTurnOffLog(bool $bOn): self
	{
		return $this->setFlag('O', $bOn);
	}

	/**
	 * Is turn off log.
	 */
	public function isTurnOffLog(): bool
	{
		return $this->hasFlag('O');
	}

	/**
	 * Set always run service.
	 */
	public function setAlwaysRunService(bool $bOn, string $sRestartAtUnixTime = null): self
	{
		//unix * * * * *
		if ($sRestartAtUnixTime !== null && count(explode(' ', $sRestartAtUnixTime)) !== 4) {
			$sRestartAtUnixTime = null;
		}
		return $this->setFlag('A', $bOn, $sRestartAtUnixTime);
	}

	/**
	 * Get always run service unix cron time value.
	 */
	public function getAlwaysRunServiceUnixCronTimeValue(): ?string
	{
		return $this->getFlagVal('A');
	}

	/**
	 * Can trigger always run service restart time.
	 */
	public function canTriggerAlwaysRunServiceRestartTime(array $aNow = null): bool
	{
		if (empty($snValue = $this->getAlwaysRunServiceUnixCronTimeValue())) {
			return false;
		}
		$startTime = intval($_SERVER['REQUEST_TIME_FLOAT'] ?? ($_SERVER['REQUEST_TIME'] ?? 0));
		return
			$this->isValidConfig() &&
			$startTime + 61 < time() && //at least 61 seconds old
			AfrUnixCronTrigger::getInstance()->triggerUnixCron($snValue, $aNow ?? getdate());
	}

	/**
	 * Is always run service.
	 */
	public function isAlwaysRunService(): bool
	{
		return $this->hasFlag('A');
	}

	/**
	 * Set allow parallel run.
	 */
	public function setAllowParallelRun(bool $bOn): self
	{
		return $this->setFlag('P', $bOn);
	}

	/**
	 * Is allow parallel run.
	 */
	public function isAllowParallelRun(): bool
	{
		return $this->hasFlag('P');
	}

	/**
	 * Set tenant insensitive job.
	 */
	public function setTenantInsensitiveJob(bool $bOn): self
	{
		return $this->setFlag('I', $bOn);
	}

	/**
	 * Is tenant insensitive job.
	 */
	public function isTenantInsensitiveJob(): bool
	{
		return $this->hasFlag('I');
	}

	/**
	 * Get alias.
	 */
	public function getAlias(): ?string
	{
		return $this->aJob[AfrCronJobDaemon::alias];
	}

	/**
	 * Set alias.
	 */
	public function setAlias(string $sAlias = null): self
	{
		$this->aJob[AfrCronJobDaemon::alias] = $sAlias;
		return $this;
	}

	/**
	 * Is valid config.
	 */
	public function isValidConfig(): bool
	{
		return
			!empty($this->aJob[AfrCronJobDaemon::cronTime]) &&
			!empty($this->aJob[AfrCronJobDaemon::command]) &&
			!$this->aJob[AfrCronJobDaemon::skipped];
	}

	/**
	 * Can trigger.
	 * @param array|null $aNow aNow = getdate();
	 * @return bool
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 */
	public function canTrigger(array $aNow = null): bool
	{
		return
			$this->isValidConfig() &&
			AfrUnixCronTrigger::getInstance()->triggerUnixCron($this->getCronTime(), $aNow ?? getdate());
	}

	/**
	 * Get cron time.
	 */
	public function getCronTime(): ?string
	{
		return $this->aJob[AfrCronJobDaemon::cronTime];
	}

	/**
	 * Valid a unix cron eg:  0 8-18/2,23 23-31/4 * 0-5
	 * minute[0-59] hour[1-23] day[1-31] month[1-12] weekDay[0-6]
	 * @param string|null $sTime
	 * @return $this
	 */
	public function setCronTime(string $sTime = null): self
	{
		$this->aJob[AfrCronJobDaemon::cronTime] = $sTime;
		return $this;
	}

	/**
	 * Get command.
	 */
	public function getCommand(): ?string
	{
		$sVendorBaseDir = AfrVendorPath::getBaseDirPath();
		$sAppBaseDir = Afr::app() ?
			Afr::app()->getAppBaseDirectory() :
			$sVendorBaseDir; //fallback to vendor parent dir
		$sTenantName = Afr::getTenantAlias() ? Afr::getTenantAlias() :
			(defined($sTenantDefault = 'AFR_TENANT_DEFAULT') ? constant($sTenantDefault) : $sTenantDefault);
		$sTenantNameArg = "--tenant='$sTenantName'";
		$sIndexEntryPoint = $sAppBaseDir . DIRECTORY_SEPARATOR . 'index.php';
		$sTenantEntryPoint = $sAppBaseDir . DIRECTORY_SEPARATOR . $sTenantName . '.php';

		$aMatrix = array_merge(
			[
				'AFR_SELF_CLI_ENTRY_POINT_FILE' => AfrCliHttpDetect::getEntryPoint(null, false),
				'AFR_SELF_CLI_ENTRY_POINT_FILE_AND_ARGS' => AfrCliHttpDetect::getEntryPoint(null, true),
				'AFR_APP_BASE_DIR' => $sAppBaseDir,
				'AFR_APP_VENDOR_BASE_DIR' => $sVendorBaseDir,
				'AFR_APP_TENANT_ENTRY_PHP_FILE' => $sTenantEntryPoint,
				'AFR_APP_INDEX_ENTRY_PHP_FILE' => $sIndexEntryPoint,
				'AFR_APP_TENANT_NAME' => $sTenantName,
				'AFR_APP_TENANT_NAME_ARG' => $sTenantNameArg,
			], AfrCronJobGenericEntryPoints::getReplaceMatrix()
		);
		return str_replace(array_keys($aMatrix), array_values($aMatrix), $this->aJob[AfrCronJobDaemon::command]);
	}

	/**
	 * /someDir/xEnd22Status.php OR php /someDir/xEnd22Status.php
	 * http://localhost:808/core/src/exec.php
	 * CLI:C:\Windows\System32\mspaint.exe
	 * @param string|null $sCmd
	 * @return $this
	 */
	public function setCommand(string $sCmd = null): self
	{
		$this->aJob[AfrCronJobDaemon::command] = $sCmd;
		return $this;
	}

	/**
	 * Get flags.
	 */
	public function getFlags(): ?string
	{
		return $this->aJob[AfrCronJobDaemon::flags];
	}

	/**
	 * Set flags.
	 */
	public function setFlags(string $sFlags = null): self
	{
		$this->aJob[AfrCronJobDaemon::flags] = $sFlags;
		return $this;
	}

	/**
	 * Set skipped.
	 */
	public function setSkipped(bool $bSkipped): self
	{
		$this->aJob[AfrCronJobDaemon::skipped] = $bSkipped;
		return $this;
	}

	/**
	 * Is skipped.
	 */
	public function isSkipped(): bool
	{
		return $this->aJob[AfrCronJobDaemon::skipped];
	}

	/**
	 * Get job.
	 */
	public function getJob(bool $bNullOnInvalid): ?array
	{
		if ($bNullOnInvalid) {
			return $this->isValidConfig() ? $this->aJob : null;
		}
		return $this->aJob;
	}

	/**
	 * Return the string representation of the instance.
	 */
	public function __toString(): string
	{
		return json_encode($this->aJob);
	}

	/**
	 * To array.
	 */
	public function toArray(): array
	{
		return $this->aJob;
	}

	/**
	 * Get hash.
	 */
	public function getHash(): ?string
	{
		$cmd = $this->getCommand();
		return $cmd ? AfrCronJobDaemon::computeHash($cmd) : null;
	}

}
