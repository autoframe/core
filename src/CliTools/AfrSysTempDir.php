<?php

namespace Autoframe\Core\CliTools;

use Autoframe\Core\Afr\Afr;
use Autoframe\Core\Arr\Merge\AfrArrMergeProfileClass;
use Autoframe\Core\FileSystem\DirPath\AfrDirPathClass;

/**
 * Temp dir concepts:
 * - all code running from phpUnit tests / root user / other user / Windows Service / win user should use the same temp sys dir
 * - all code depending on the current composer installation, should have the same temp sys dir
 * - multiple composer versions located in separated paths (php7/php8) will have a different system temp dir!
 * - when running composer update, it is likely that the generated temp file will vanish, so we regenerate it!
 * - the temp sub-dir contains a md5 hash of the current class path
 * - the local file descriptor will be copied / enriched within any temp path, so that a multi-user / version info can be retrieved
 */
class AfrSysTempDir
{
	const AFR_TMP_DIR_NAME = 'AUTOFRAME_TEMP_DIR';
	const LOCAL_FILE_DESCRIPTOR = 'AfrSysTempDir.EnvDescriptor.php';
	const DEFAULT_DIR = 'defaultTmpPath';
	const NO_COMMON_TEMP = 'NO_COMMON_TEMP';
	const CONTEXT = 'context';

	protected static string $sysGetTempDir = '';
	protected static array $aTempDirs = [];
	protected static array $aVars = [];

	public static function sysGetTempDir(): string
	{
		//use in project Afr::getTempDir(); returns  => AfrSysTempDir::sysGetTempDir() . DIRECTORY_SEPARATOR . self::getTenantAlias();

		//can be updated at runtime
		if (defined($c = '\AFR_SYS_TEMP_DIR')) {
			return constant($c);
		}
		if (!empty($_ENV[$c])) {
			return $_ENV[$c];
		}
		if (empty(static::$sysGetTempDir)) {
			static::$sysGetTempDir = static::sysGetTempDirCheck();
		}
		return static::$sysGetTempDir;
	}

	/**
	 * @param string|object $soAliasSubDir
	 * @return string
	 */
	public static function sysGetTempDirAliasSubDir($soAliasSubDir): string
	{
		$soAliasSubDir = is_object($soAliasSubDir) ? get_class($soAliasSubDir) : (string)$soAliasSubDir;
		$soAliasSubDir = trim(trim($soAliasSubDir),'\\');
		if(strpos($soAliasSubDir, '\\') !== false) { //handle class names
			$soAliasSubDir = array_slice(explode('\\', $soAliasSubDir), -1, 1)[0];
		}
		if(empty($soAliasSubDir)) return static::sysGetTempDir();

		$soAliasSubDir =
			static::sysGetTempDir() . DIRECTORY_SEPARATOR .
			preg_replace('/[^A-Za-z0-9_-]/', '_', $soAliasSubDir);
		return static::existAndWritable($soAliasSubDir) ? $soAliasSubDir : static::sysGetTempDir();
	}

	protected static function getCurrentHash(): string
	{
		if (empty(self::$aVars[__FUNCTION__])) {
			self::$aVars[__FUNCTION__] = md5(__FILE__);
		}
		return self::$aVars[__FUNCTION__];
	}

	protected static function get_sys_get_temp_dir(): string
	{
		if (empty(self::$aVars[__FUNCTION__])) {
			$sPath = rtrim(sys_get_temp_dir(), '\\/');
			if (empty($sPath)) {
				static::getAlternativeTempDir();
			}
			self::$aVars[__FUNCTION__] = $sPath;
		}
		return self::$aVars[__FUNCTION__];
	}

	protected static function getAlternativeTempDir(): string
	{
		$sPath = trim((string)getenv('TMP'));
		$sPath = empty($sPath) ? trim((string)getenv('TEMP')) : $sPath;
		if (empty($sPath)) {
			$sPath = DIRECTORY_SEPARATOR == '/' ? '/tmp' : 'C:\\Windows\\TEMP';
		}
		return $sPath;
	}

	protected static function getSelfSysTempDir(): string
	{
		if (empty(self::$aVars[__FUNCTION__])) {
			self::$aVars[__FUNCTION__] =
				rtrim(static::get_sys_get_temp_dir(), '\\/') . DIRECTORY_SEPARATOR .
				self::AFR_TMP_DIR_NAME . DIRECTORY_SEPARATOR .
				self::getCurrentHash();
		}
		return self::$aVars[__FUNCTION__];
	}


	protected static function sysGetTempDirCheck(): string
	{
		if (!empty(static::$aTempDirs) && !empty($d = static::getFromSelfTempDirs())) { //loaded and validated on first run
			return $d;
		}

		$f = __DIR__ . DIRECTORY_SEPARATOR . self::LOCAL_FILE_DESCRIPTOR;
		$f2 = static::getSelfSysTempDir() . DIRECTORY_SEPARATOR . self::LOCAL_FILE_DESCRIPTOR; //backup

		if ($bf = file_exists($f)) {
			static::extendSettings($f);
			if (
				!empty(static::$aTempDirs[self::getCurrentHash()][self::NO_COMMON_TEMP]) &&
				!empty($d = static::get_sys_get_temp_dir()) ||
				!empty($d = static::getFromSelfTempDirs())
			) {
				if (rand(1, 100) == 8 && !file_exists($f2)) { //1% chance
					if (static::existAndWritable(dirname($f2))) {
						copy($f, $f2);//restore to temp dir after clear all temp
					}
				}
				return $d; //load from file
			}
		}
		if ($bf2 = file_exists($f2)) {
			if (!$bf) {
				$sPrevLoaded = null;
				copy($f2, $f); //restore from temp dir when composer update
			} else {
				$sPrevLoaded = json_encode(static::$aTempDirs);
			}
			static::extendSettings($f2);
			if (!empty(static::$aTempDirs[self::getCurrentHash()][self::NO_COMMON_TEMP])) {
				return static::get_sys_get_temp_dir();
			}
			if (!empty($d2 = static::getFromSelfTempDirs())) {
				if (!empty(static::$aTempDirs[self::getCurrentHash()][self::CONTEXT][static::get_sys_get_temp_dir()])) {
					if (!$bf || $sPrevLoaded === json_encode(static::$aTempDirs)) {
						return $d2; //load backup from file, dir exists because it contains $f2
					}
				}
			}
		}
		static::prepareWriteAll();
		file_put_contents($f, '<?php return ' . var_export(static::$aTempDirs, true) . ';');
		file_put_contents($f2, '<?php return ' . var_export(static::$aTempDirs, true) . ';');
		return static::getFromSelfTempDirs() ?? static::get_sys_get_temp_dir();
	}

	protected static function getFromSelfTempDirs(): ?string
	{
		$vH = self::getCurrentHash();
		if ( //the system has one or more paths accounted
			!empty(static::$aTempDirs[$vH][self::CONTEXT][static::get_sys_get_temp_dir()]) &&
			!empty(static::$aTempDirs[$vH][static::DEFAULT_DIR]) &&
			is_dir(static::$aTempDirs[$vH][static::DEFAULT_DIR]) &&
			is_writable(static::$aTempDirs[$vH][static::DEFAULT_DIR])
		) {
			return static::$aTempDirs[$vH][static::DEFAULT_DIR];
		}
		return null;
	}

	protected static function readAndValidateSettingsFile(string $f): array
	{
		$aData = (array)(include $f);
		$aReturn = [];
		foreach ($aData as $k => $v) {
			if (is_string($k) && strlen($k) == 32 && isset($v[static::DEFAULT_DIR])) {
				$aReturn[$k] = $v;
			}
		}
		return $aReturn;
	}

	protected static function extendSettings(string $f): void
	{
		if (!empty($aData = static::readAndValidateSettingsFile($f))) {
			static::$aTempDirs = AfrArrMergeProfileClass::getInstance()->arrayMergeProfile(static::$aTempDirs, $aData);
		}
	}


	protected static function prepareWriteAll(): void
	{
		$vH = self::getCurrentHash();
		$sCurrentContext = static::get_sys_get_temp_dir();
		$selfDir = static::getSelfSysTempDir();
		static::$aTempDirs[$vH][self::CONTEXT][$sCurrentContext][$selfDir] = true; //register current

		foreach (static::$aTempDirs[$vH][self::CONTEXT][$sCurrentContext] as $dir => &$bIsWrite) {
			$bIsWrite = static::existAndWritable($dir, $selfDir === $dir);//create only self dir and check write
		}

		$allDirs = $aWritableInAllContextDirs = []; //make a list with all context dirs and filter the writable ones
		foreach (static::$aTempDirs[$vH][self::CONTEXT] as $aDirs) {
			foreach ($aDirs as $dir => $w) {
				$allDirs[$dir] ??= $w;
				if (!$w || !is_dir($dir) || !is_writable($dir) || empty($allDirs[$dir])) {
					$allDirs[$dir] = false;
				}
			}
		}
		foreach ($allDirs as $dir => $w) {
			if ($w) {
				$aWritableInAllContextDirs[$dir] = $w;
			}
		}

		if (empty($aWritableInAllContextDirs)) {
			//wars case scenario!
			//multiple users are running this framework with multiple temp dirs that are not cross writable
			//so we try to add other temp dirs into the equation and wait for the other users instances to interact
			$sAlternative = static::getAlternativeTempDir();
			if (static::existAndWritable($sAlternative, true)) {
				$aWritableInAllContextDirs[$sAlternative] = static::$aTempDirs[$vH][self::CONTEXT][$sCurrentContext][$sAlternative] = true;
			}
		}
		if (empty($aWritableInAllContextDirs)) { //something is wrong with permissions
			static::$aTempDirs[$vH][self::NO_COMMON_TEMP] = true;
			static::$aTempDirs[$vH][self::DEFAULT_DIR] = static::get_sys_get_temp_dir(); //split temp is unpreventable!
			return;
		}
		//change default dir so everybody can write
		if (isset($aWritableInAllContextDirs[$selfDir]) && (static::$aTempDirs[$vH][self::DEFAULT_DIR] ?? '') !== $selfDir) {
			foreach ($aWritableInAllContextDirs as $dir => $w) {
				static::$aTempDirs[$vH][self::DEFAULT_DIR] = $dir;
				return;
			}
		}
		static::$aTempDirs[$vH][self::DEFAULT_DIR] = $selfDir;
	}

	protected static function existAndWritable(string $dir, bool $bCreate = true): bool
	{
		return AfrDirPathClass::getInstance()->dirExistAndWritable($dir, $bCreate);
	}
}