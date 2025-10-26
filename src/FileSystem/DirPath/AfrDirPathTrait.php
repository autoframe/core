<?php
declare(strict_types=1);

namespace Autoframe\Core\FileSystem\DirPath;

use Autoframe\Core\Afr\Afr;
use Autoframe\Core\Env\Exception\AfrEnvException;
use Autoframe\Core\FileSystem\DirPath\Exception\AfrFileSystemDirPathException;
use function filetype;
use function opendir;
use function substr;
use function substr_count;
use function rtrim;
use function array_diff;
use function count;
use function strpos;
use function array_fill;
use function str_replace;
use function array_filter;
use function explode;
use function array_pop;
use function implode;

trait AfrDirPathTrait
{
	/**
	 * the call filetype()=="dir" is clearly faster than the is_dir() call
	 * @param string $sDirPath
	 * @return bool
	 */
	public function isDir(string $sDirPath): bool
	{
		//Possible values are fifo, char, dir, block, link, file, socket and unknown.
		return @filetype($sDirPath) === 'dir';
	}

	/**
	 * @param string $sDirPath
	 * @param $context
	 * @return false|resource
	 * @throws AfrFileSystemDirPathException
	 */
	public function openDir(string $sDirPath, $context = null)
	{
		try {
			if ($context) {
				$resource = opendir($sDirPath, $context);
			} else {
				$resource = opendir($sDirPath);
			}
		} catch (\Throwable $ex) {
			throw new AfrFileSystemDirPathException('Unable to open directory: ' . $sDirPath);
		}
		return $resource;
	}

	/**
	 * Detect path slash style: /
	 * @param string $sDirPath
	 * @return string
	 */
	public function detectDirectorySeparatorFromPath(string $sDirPath): string
	{
		if (
			substr($sDirPath, 0, 2) === '\\\\' || # Detect Windows network path \\192.168.0.1\share
			substr($sDirPath, 1, 2) == ':\\'  #Detect Windows drive path C:\Dir
		) {
			return '\\';
		}

		$iWinDs = substr_count($sDirPath, '\\');
		$iUnixDs = substr_count($sDirPath, '/');
		if ($iWinDs + $iUnixDs < 1) {
			return DIRECTORY_SEPARATOR;
		} elseif ($iWinDs > $iUnixDs) {
			return '\\';
		}
		return '/';
	}

	/**
	 * Remove final slash from a directory path
	 * @param string $sDirPath
	 * @return string
	 */
	public function removeFinalSlash(string $sDirPath): string
	{
		return rtrim($sDirPath, '\/');
	}

	/**
	 * Add a final slash to a directory path
	 * @param string $sDirPath
	 * @return string
	 */
	public function addFinalSlash(string $sDirPath): string
	{
		return rtrim($sDirPath, '\/') . $this->detectDirectorySeparatorFromPath($sDirPath);
	}

	/**
	 * @param string $sDirPath
	 * @param string $sSlashFormat
	 * @return string
	 */
	protected function correctSlashStyleMethod(string $sDirPath, string $sSlashFormat): string
	{
		$aSearch = array_diff(['/', '\\'], [$sSlashFormat]);
		$iTypes = count($aSearch);
		if ($iTypes) {
			foreach ($aSearch as $sDs) {
				if (strpos($sDirPath, $sDs) !== false) {
					$aReplace = array_fill(0, $iTypes, $sSlashFormat);
					return str_replace($aSearch, $aReplace, $sDirPath);
				}
			}
		}
		return $sDirPath;
	}

	/**
	 * Make the path for FILE and DIR to a uniform path for cross system like windows to unix and keep existing slash format
	 * @param string $sDirPath
	 * @return string
	 */
	public function makeUniformSlashStyle(string $sDirPath): string
	{
		return $this->correctSlashStyleMethod(
			$sDirPath,
			$this->detectDirectorySeparatorFromPath($sDirPath)
		);
	}

	/**
	 * Make the dir path to a uniform path for cross system like windows to unix
	 * Full fix for a full directory path
	 * @param string $sDirPath
	 * @param bool $bWithFinalSlash
	 * @param bool $bCorrectSlashStyle
	 * @return string
	 */
	public function correctDirPathFormat(
		string $sDirPath,
		bool   $bWithFinalSlash = false,
		bool   $bCorrectSlashStyle = true
	): string
	{
		$sSlashStyle = $this->detectDirectorySeparatorFromPath($sDirPath);
		$sDirPath = $this->removeFinalSlash($sDirPath) . ($bWithFinalSlash ? $sSlashStyle : '');
		if ($bCorrectSlashStyle) {
			$sDirPath = $this->correctSlashStyleMethod($sDirPath, $sSlashStyle);
		}
		return $sDirPath;
	}

	/**
	 * IN: 'this/is/../a/./test/.///is'
	 * OUT: 'this/a/test/is'
	 * This method does not check for the correctness of the path, just does some cleanup
	 * @param string $sPath
	 * @return string
	 */
	public function simplifyAbsolutePath(string $sPath): string
	{
		$sDs = $this->detectDirectorySeparatorFromPath($sPath);
		$sPath = str_replace(['/', '\\'], $sDs, $sPath);
		$aAbsolutes = [];
		foreach (array_filter(explode($sDs, $sPath), 'strlen') as $sSegment) {
			if ('.' === $sSegment) {
				continue;
			}
			if ('..' === $sSegment && count($aAbsolutes) > 0) {
				array_pop($aAbsolutes);
			} else {
				$aAbsolutes[] = $sSegment;
			}
		}
		return implode($sDs, $aAbsolutes);
	}

	/**
	 * Force path to single slash style
	 * @param string $sPath
	 * @return string
	 */
	public function fixDs(string $sPath): string
	{
		if (
			substr($sPath, 0, 2) === '\\\\' || # Detect Windows network path \\192.168.0.1\share
			substr($sPath, 1, 2) == ':\\'  #Detect Windows drive path C:\Dir
		) {
			$sFromDs = '/';
			$sToDs = '\\';
		} else {
			$sFromDs = DIRECTORY_SEPARATOR === '/' ? '\\' : '/';
			$sToDs = DIRECTORY_SEPARATOR;
		}
		return strtr($sPath, $sFromDs, $sToDs);
	}

	/**
	 * @param string $path
	 * @param bool $bCheckExistence
	 * @return false|string
	 */
	public function realpath(string $path, bool $bCheckExistence)
	{
		if (substr($path, 0, 2) === '\\\\') {
			return $path; //widows network share
		}
		if ($bCheckExistence) {
			return realpath($path);
		}
		$path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
		$absolutes = [];
		foreach (array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen') as $part) {
			if ('.' == $part) continue;
			if ('..' == $part) {
				array_pop($absolutes);
			} else {
				$absolutes[] = $part;
			}
		}
		return implode(DIRECTORY_SEPARATOR, $absolutes);
	}

	/**
	 * @throws AfrEnvException
	 */
	public function dirExistAndWritable(string $dir, bool $bCreate = true, ?int $expectedPermissions = null, bool $bClearStatCache = false): bool
	{
		$expectedPermissions ??= $this->getExpectedDirPermissions(); // can return 0775 or similar
		$expectedPermissionsOctal = $expectedPermissions & 0777;
		$dir = rtrim($dir, '\\/');
		if ($dir === '') return false;
		if ($bClearStatCache) clearstatcache(true, $dir);
		$testFile = $dir . DIRECTORY_SEPARATOR . 'AfrWriteTest'.
			getmypid() . '_' . microtime(true) . '_' . uniqid('', true) . '_' . mt_rand(10000, 99999)
			. '.tmp';

		if (!is_dir($dir)) {
			if (!$bCreate) return false;
			if (!@mkdir($dir, $expectedPermissions, true)) return false;
			if (!is_dir($dir)) return false;
			$this->attemptToSetPermissions($dir, $expectedPermissions);
			return $this->writeTestFile($testFile);
		}
		// Directory exists: fast path — if PHP thinks it's writable for the current user, we're done
		if (@is_writable($dir)) return true;

		$this->attemptToSetPermissions($dir, $expectedPermissions);
		return $this->writeTestFile($testFile);

	}

	protected function writeTestFile(string $testFile): bool
	{
		// After creation, actually verify write ability
		$fp = @fopen($testFile, 'xb'); // exclusive create (no clobber), binary for portability
		if ($fp === false) {
			// If file somehow exists, attempt cleanup once (defensive)
			if (is_file($testFile)) @unlink($testFile);
			return false;
		}
		$ok = (@fwrite($fp, 'x') !== false);
		@fclose($fp);
		@unlink($testFile);
		return $ok;
	}
	/**
	 * @param string $dir
	 * @param int $expectedPermissions
	 * @return void
	 */
	protected function attemptToSetPermissions(string $dir, int $expectedPermissions): void
	{
		// POSIX-style mode enforcement (umask-safe); on Windows this is mostly a no-op, but cheap
		if (DIRECTORY_SEPARATOR !== '\\') {
			$p = @fileperms($dir);
			if ($p !== false && (($p & 0777) !== ($expectedPermissions & 0777))) {
				@chmod($dir, $expectedPermissions);
			}
		}
	}


	/**
	 * @throws AfrEnvException
	 */
	public function getExpectedDirPermissions(): int
	{
		$sKey = 'AFR_EXPECTED_DIR_PERMISSIONS';
		$iFallback = 0775;
		if (Afr::App()) {
			$expectedPermissions = Afr::App()->env()->getEnv($sKey, $iFallback);
		} else {
			$expectedPermissions = $_ENV[$sKey] ?? getenv($sKey) ?:
				(defined($sKey) ? constant($sKey) : $iFallback);
		}
		return (int)$expectedPermissions;
	}


	public function getRelativePath(
		string $to,
		string $from,
		bool   $bForceForwardSlashes = false,
		?bool  $bCaseSensitive = null,
		bool   $bRealPath = false
	): string
	{
		$toReal = $this->fixDs($bRealPath ? realpath($to) : $to);
		$fromReal = $this->fixDs($bRealPath ? realpath($from) : $from);
		if (empty($toReal) || empty($fromReal)) {
			return $to;
		}

		$bCaseSensitive ??= DIRECTORY_SEPARATOR !== '\\';
		$toParts = explode(DIRECTORY_SEPARATOR, $toReal);
		$fromParts = explode(DIRECTORY_SEPARATOR, dirname($fromReal));

		// Find common path prefix
		$length = min(count($toParts), count($fromParts));
		$commonLength = 0;

		for ($i = 0; $i < $length; $i++) {
			if (
				($bCaseSensitive && $toParts[$i] !== $fromParts[$i]) ||
				(!$bCaseSensitive && strcasecmp($toParts[$i], $fromParts[$i]) !== 0)
			) {
				break;
			}
			$commonLength++;
		}

		// How many levels up?
		$up = array_fill(0, count($fromParts) - $commonLength, '..');
		$remaining = array_slice($toParts, $commonLength);

		return implode(
			$bForceForwardSlashes ? '/' : DIRECTORY_SEPARATOR,
			array_merge($up, $remaining)
		);
	}



}
