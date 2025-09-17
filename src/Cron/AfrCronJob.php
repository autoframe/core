<?php

namespace Autoframe\Core\Cron;

use Autoframe\Core\Afr\Afr;
use Autoframe\Core\CliTools\AfrCliHttpDetect;
use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\Event\Exception\AfrEventException;
use Autoframe\Core\InterfaceToConcrete\AfrVendorPath;

//TODO: set_time_limit
//TODO: set connection time limit
/**
 * AfrCronJob Flags only for JSON lines:
 * Startup S; Startup S(someValue);
 * TurnOffLog: O;
 * AllowParallelRun: P;
 * AlwaysRunService: A;
 * AlwaysRunService with timeout of x seconds between recall: A(10)  10 seconds sleep after job end
 */
final class AfrCronJob
{
	protected array $aJob = [
		AfrCronJobDaemon::flags => null, //
		AfrCronJobDaemon::cronTime => null, //unix * * * * *
		AfrCronJobDaemon::command => null, //executed with exec()
		AfrCronJobDaemon::alias => null, // some name for logging
		AfrCronJobDaemon::skipped => false, //line starts with #
	];

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


	public function exportLine(): string
	{
		return json_encode($this->aJob);
	}


	public function hasFlag(string $sFlag): bool
	{
		if (empty($this->aJob[AfrCronJobDaemon::flags])) {
			return false;
		}
		return strpos($this->aJob[AfrCronJobDaemon::flags], $sFlag) !== false;
	}

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

	public function setRunOnStartup(bool $bOn, string $sOtherParam = null): self
	{
		return $this->setFlag('S', $bOn, $sOtherParam);
	}

	public function getRunOnStartupOtherParam(): ?string
	{
		return $this->getFlagVal('S');
	}

	public function isRunOnStartup(): bool
	{
		return $this->hasFlag('S');
	}

	public function setTurnOffLog(bool $bOn): self
	{
		return $this->setFlag('O', $bOn);
	}

	public function isTurnOffLog(): bool
	{
		return $this->hasFlag('O');
	}

	public function setAlwaysRunService(bool $bOn, float $fSleepAfterJobDone = null): self
	{
		return $this->setFlag('A', $bOn, $fSleepAfterJobDone);
	}

	public function getAlwaysRunServiceSleepAfterJobDoneSeconds(): float
	{
		return (float)($this->getFlagVal('A') ?? 0.0);
	}

	public function isAlwaysRunService(): bool
	{
		return $this->hasFlag('A');
	}

	public function setAllowParallelRun(bool $bOn): self
	{
		return $this->setFlag('P', $bOn);
	}

	public function isAllowParallelRun(): bool
	{
		return $this->hasFlag('P');
	}

	public function getAlias(): ?string
	{
		return $this->aJob[AfrCronJobDaemon::alias];
	}

	public function setAlias(string $sAlias = null): self
	{
		$this->aJob[AfrCronJobDaemon::alias] = $sAlias;
		return $this;
	}

	public function isValidConfig(): bool
	{
		return
			!empty($this->aJob[AfrCronJobDaemon::cronTime]) &&
			!empty($this->aJob[AfrCronJobDaemon::command]) &&
			!$this->aJob[AfrCronJobDaemon::skipped];
	}

	/**
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

	public function getCommand(): ?string
	{
		$sAppBaseDir = Afr::app() ?
			Afr::app()->getAppBaseDirectory() :
			AfrVendorPath::getBaseDirPath(); //fallback to vendor parent dir
		$sTenantName = Afr::getTenantAlias() ? Afr::getTenantAlias() : 'afrCronJobTenant';
		$sTenantNameArg = "--tenant='$sTenantName'";
		//TODO: generic entry points
		$sTenantEntryPoint = $sAppBaseDir . DIRECTORY_SEPARATOR . $sTenantName . '.php';

		$aMatrix = [
			'AFR_SELF_CLI_ENTRY_POINT_FILE' => AfrCliHttpDetect::getEntryPoint(null, false),
			'AFR_SELF_CLI_ENTRY_POINT_FILE_AND_ARGS' => AfrCliHttpDetect::getEntryPoint(null, true),
			'AFR_APP_BASE_DIR' => $sAppBaseDir,
			'AFR_APP_TENANT_ENTRY_PHP_FILE' => $sTenantEntryPoint,
			'AFR_APP_TENANT_NAME' => $sTenantName,
			'AFR_APP_TENANT_NAME_ARG' => $sTenantNameArg,
		];

		return str_replace(array_keys($aMatrix), array_values($aMatrix), $this->aJob[AfrCronJobDaemon::command]);
	}

	/**
	 * /someDir/xEnd22Status.php
	 * http://localhost:808/core/src/exec.php
	 * @param string|null $sCmd
	 * @return $this
	 */
	public function setCommand(string $sCmd = null): self
	{
		$this->aJob[AfrCronJobDaemon::command] = $sCmd;
		return $this;
	}

	public function getFlags(): ?string
	{
		return $this->aJob[AfrCronJobDaemon::flags];
	}

	public function setFlags(string $sFlags = null): self
	{
		$this->aJob[AfrCronJobDaemon::flags] = $sFlags;
		return $this;
	}

	public function setSkipped(bool $bSkipped): self
	{
		$this->aJob[AfrCronJobDaemon::skipped] = $bSkipped;
		return $this;
	}

	public function isSkipped(): bool
	{
		return $this->aJob[AfrCronJobDaemon::skipped];
	}

	public function getJob(bool $bNullOnInvalid): ?array
	{
		if ($bNullOnInvalid) {
			return $this->isValidConfig() ? $this->aJob : null;
		}
		return $this->aJob;
	}

	public function __toString(): string
	{
		return json_encode($this->aJob);
	}

	public function toArray(): array
	{
		return $this->aJob;
	}

}