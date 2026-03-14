<?php

namespace Autoframe\Core\Cron\Log\Channel;

use Autoframe\Core\CliTools\AfrSysTempDir;
use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\Cron\Log\AfrCronLoggerInterface;
use Autoframe\Core\Event\Exception\AfrEventException;
use Autoframe\Core\FileSystem\DirPath\AfrDirPathClass;

/**
 * SharedLogBuffer
 *
 * Cross-process/thread temporary log buffer using a directory of tiny files
 * (one line per file) + flock-based lock file for concurrency.
 *
 * - OS: Linux/Unix + Windows
 * - PHP: 7.4+
 * - Methods: writeLog(string $line): bool
 *            readLog(): array  // flushes after read
 * - Retention: at most self::$maxLines (default 50; customizable)
 * - Locking: Each method retries lock acquisition exactly 3 times,
 *            total wait capped by static timeout values.
 */
final class AfrCronLogSharedLogBuffer
{
	/** Max number of log lines kept. */
	public static int $maxLines = 200;

	/** Max time (ms) writers wait to acquire the lock, split across 4 retries. */
	public static int $writeTimeoutMs = 9;

	private const LOCK_RETRIES = 4;

	private string $dir;
	private string $lockFile;
	private string $linePattern;

	/**
	 * Create a new instance.
	 * @throws AfrEventException
	 * @throws AfrContainerException
	 */
	public function __construct(string $key = 'CronLog')
	{
		$this->dir = rtrim(AfrSysTempDir::sysGetTempDir(), '\/') .
			DIRECTORY_SEPARATOR . 'shared_log_buffer_' .
			preg_replace('~[^a-zA-Z0-9._-]+~', '_', $key);
		if(!AfrDirPathClass::getInstance()->dirExistAndWritable($this->dir)){
			$this->lockFile = $this->linePattern = $this->dir = '';
			return; //do not throw error in here, because we don't want to compromise the cron job environment
		}
		$this->lockFile = $this->dir . DIRECTORY_SEPARATOR . '.lock';
		if(!file_exists($this->lockFile)) {
			touch($this->lockFile);
		}
		$this->linePattern = $this->dir . DIRECTORY_SEPARATOR . '*.log';
	}

	/**
	 * Append one log line into the shared buffer.
	 * Uses static $writeTimeoutMs for lock budget.
	 */
	public function writeLog(string $sLine, bool $bError = false): bool
	{

		if(empty($this->lockFile)) return false;
		if (empty($sLine = $this->normalizeLine($sLine))) return true;

		if (!($fp = @fopen($this->lockFile, 'c+'))) return false;

		if (!$this->tryLockWithRetries($fp)) {
			fclose($fp);
			return false;
		}
		$sLine = ($bError?'0 ':'1 ') . $sLine;

		try {
			$ts = sprintf('%.6f', microtime(true));
			$pid = (int)(function_exists('getmypid') ? getmypid() : 0);
			$id = bin2hex(random_bytes(4));
			$path = $this->dir . DIRECTORY_SEPARATOR . str_replace('.', '', $ts) . "-$pid-$id.log";
			if (@file_put_contents($path, $sLine, FILE_APPEND | LOCK_EX) === false) {
				usleep(2000);
				if (@file_put_contents($path, $sLine, FILE_APPEND | LOCK_EX) === false) {
					flock($fp, LOCK_UN);
					fclose($fp);
					return false;
				}
			}

			$this->pruneLocked();

			flock($fp, LOCK_UN);
			fclose($fp);
			return true;
		} catch (\Throwable $e) {
			@flock($fp, LOCK_UN);
			@fclose($fp);
			return false;
		}
	}

	protected array $aReaded = [];
	/**
	 * Read and optionally flush the buffer atomically.
	 * @return string[] Array of log lines.
	 */
	public function readLog(bool $bFLushLogsAfterRead = true): array
	{
		if(empty($this->lockFile)) return [];

		if (!($fp = @fopen($this->lockFile, 'c+'))) {
			return [];
		}

		if (!$this->tryLockWithRetries($fp)) {
			fclose($fp);
			return [];
		}

		$lines = [];
		try {
			$aFiles = @glob($this->linePattern, GLOB_NOSORT) ?: [];
			if ($aFiles) {
				sort($aFiles, SORT_STRING);
				$count = count($aFiles);
				$start = max(0, $count - max(0, self::$maxLines));
				for ($i = $start; $i < $count; $i++) {
					if(!empty($this->aReaded[$aFiles[$i]])){
						continue;
					}
					$c = @file_get_contents($aFiles[$i],false,null,0,8192);
					if ($c !== false) {
						$lines[] = rtrim($c, "\r\n");
					}
				}

				foreach ($aFiles as $f) {
					$this->aReaded[$f] = 1;
					if ($bFLushLogsAfterRead) {
						@unlink($f);
					}

				}
			}



			flock($fp, LOCK_UN);
			fclose($fp);
			return $lines;
		} catch (\Throwable $e) {
			@flock($fp, LOCK_UN);
			@fclose($fp);
			return $lines;
		}
	}

	/* ----------------------------- Internals ------------------------------ */

	private function normalizeLine(string $s): string
	{
		$s = str_replace(["\r\n", "\r"], "\n", $s);
		return preg_replace('~\n+~', ' ⏎ ', trim($s));
	}

	private function tryLock($fp, int $timeoutMs): bool
	{
		$timeoutMs = max(0, $timeoutMs);
		$deadline = microtime(true) + ($timeoutMs / 1000.0);
		do {
			if (flock($fp, LOCK_EX | LOCK_NB)) {
				return true;
			}
			usleep(500);
		} while (microtime(true) < $deadline);
		return false;
	}

	private function tryLockWithRetries($fp): bool
	{
		$iRetries = max(1, self::LOCK_RETRIES);
		$iBudget = max(0, self::$writeTimeoutMs);

		for ($i = 0; $i < $iRetries; $i++) {
			$iRemainingAttempts = $iRetries - $i;
			$perAttempt = (int)max(1, ceil($iBudget / $iRemainingAttempts));
			if ($this->tryLock($fp, $perAttempt)) {
				return true;
			}
			$iBudget -= $perAttempt;
			if ($iBudget <= 0) {
				break;
			}
		}
		return false;
	}

	private function pruneLocked(): void
	{
		$max = max(0, self::$maxLines);
		$files = @glob($this->linePattern, GLOB_NOSORT) ?: [];
		$count = count($files);
		if ($count <= $max) {
			return;
		}
		sort($files, SORT_STRING);
		$toDelete = $count - $max;
		for ($i = 0; $i < $toDelete; $i++) {
			@unlink($files[$i]);
			unset($this->aReaded[$i]);
		}
	}
}
