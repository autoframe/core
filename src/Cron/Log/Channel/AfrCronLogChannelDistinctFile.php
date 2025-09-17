<?php

namespace Autoframe\Core\Cron\Log\Channel;

use Autoframe\Core\Afr\Afr;
use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\Cron\Log\AfrCronLoggerClass;
use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;
use Autoframe\Core\Event\Exception\AfrEventException;
use Autoframe\Core\FileSystem\DirPath\AfrDirPathClass;
use Autoframe\Core\Cron\Log\AfrCronLoggerInterface;
use Autoframe\Core\Tenant\AfrTenant;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

class AfrCronLogChannelDistinctFile extends AfrSingletonAbstractClass implements AfrCronLogChannelInterface
{
	public static string $sDirDateFormat = 'Y-m';
	public static ?string $sCronLogsDir = null;
	public static bool $bLogWorkerPrintedTextInASeparateFile = true;

	protected array $aCheckedDirs = [];

	/**
	 * @param AfrCronLoggerClass|AfrCronLoggerInterface $oData
	 * @return void
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 */
	public function log(AfrCronLoggerInterface $oData): void
	{
		$sDir = static::getLogDir() . DIRECTORY_SEPARATOR .
		//	($oData->getTenant() ?? AfrTenant::AFR_NO_TENANT) . DIRECTORY_SEPARATOR .
			date(static::$sDirDateFormat, $oData->getLogTs());
		if (!isset($this->aCheckedDirs[$sDir])) {
			if (!($this->aCheckedDirs[$sDir] = AfrDirPathClass::getInstance()->dirExistAndWritable($sDir, true))) {
				error_log('Cron log dir not writable: ' . $sDir . ' in class AfrCronLogChannelDistinctFile');
			}
		}
		$bErr = $oData->isError() || $oData->getExitCode() !== null && $oData->getExitCode() > 0;
		$sMessage = trim($oData->getMessage());
		$bLogWorkerPrintedOutputToSeparateFile =
			static::$bLogWorkerPrintedTextInASeparateFile && $oData->isWorker() && strlen($sMessage) && $oData->getExitCode() !== null;

		$sAliasFname = trim($oData->getAlias() . '_' . ($oData->getHash() ? '[' . $oData->getHash() . ']' : ''), '_');
		$sFileName =
			($oData->isWorker() ? 'W' : 'D') .
			($sAliasFname ? '_' . $sAliasFname : '') . '_' .
			date('m-d', $oData->getLogTs());


		if ($bLogWorkerPrintedOutputToSeparateFile) {
			$sFileName .= date('_H-i-s', $oData->getLogTs()) . ($bErr ? '_ERROR' : '') . '_C' . $oData->getExitCode();
			$sMessage = "\n" . $oData->getFullCommand() . "\n\n" . $sMessage;//include full run command
		}


		$sText =
			date('Y-m-d H:i:sO', $oData->getLogTs()) . ' ' .
			($bErr ? '[ERROR]' : '[INFO]') .
			($oData->getExitCode() !== null ? '[EXIT-CODE:' . $oData->getExitCode() . '] ' : '') . ' ' .
			$sMessage . "\n";

		if ($this->aCheckedDirs[$sDir]) {
			$sFileName .= '.log';
			file_put_contents(
				$sDir . DIRECTORY_SEPARATOR . $sFileName,
				$sText,
				FILE_APPEND
			);
			if ($bLogWorkerPrintedOutputToSeparateFile) {
				$oData->log("Exit code(" . $oData->getExitCode() . ") logged into: $sFileName\n", $bErr, null);
			}
		} elseif ($bErr && !$bLogWorkerPrintedOutputToSeparateFile) {
			error_log($sFileName . ' ' . $sText);//fallback to system logger
		}

		$w = date('w');
		if ($w == 6 || $w == 0) { //cleanup only in weekends
			static::cleanupGcOlderThan_N_Months();
		}
	}

	public function cleanupGcOlderThan_N_Months(float $fMonths = null, bool $bForce = false): ?bool
	{
		if ($bForce || rand(1, static::GC_ONE_IN_N) == 2) {
			$fMonths ??= static::CLEAN_LOGS_OLDER_THAN_N_MONTHS;
			$secondsThreshold = (int)(max(0.5, $fMonths) * 30 * 24 * 3600);
			return static::deleteOldLogFiles(static::getLogDir(), $secondsThreshold);
		}
		return null;
	}

	protected function getLogDir(): string
	{
		if (!isset(static::$sCronLogsDir)) {
			if (!empty($_ENV[$sEnvDirSettingsKey = 'AFR_CRON_LOG_CHANNEL_DISTINCT_FILES_DIR'])) {
				return static::$sCronLogsDir = $_ENV[$sEnvDirSettingsKey];
			}
			if (Afr::app() && ($sDir = Afr::getCronLogsDir())) {
				return static::$sCronLogsDir = $sDir;
			}
			return static::$sCronLogsDir = sys_get_temp_dir();//fallback
		}
		return static::$sCronLogsDir;
	}

	protected function deleteOldLogFiles(string $directory, int $secondsThreshold): bool
	{
		if (!is_dir($directory)) {
			return false;
		}
		$now = time();
		$success = true;

		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
				RecursiveIteratorIterator::CHILD_FIRST
			);

			foreach ($iterator as $fileInfo) {
				if ($fileInfo->isFile() && $fileInfo->getExtension() === 'log') {
					$filePath = $fileInfo->getPathname();
					$fileModTime = $fileInfo->getMTime();

					if (($now - $fileModTime) > $secondsThreshold) {
						if (!unlink($filePath)) {
							error_log('Failed to delete log file: ' . $filePath);
							$success = false;
						}
					}
				}
			}
		} catch (Throwable $e) {
			error_log('Exception during log file cleanup: ' . $e->getMessage());
			return false;
		}
		return $success;
	}
}