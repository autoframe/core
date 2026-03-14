<?php
declare(strict_types=1);

namespace Autoframe\Core\InterfaceToConcrete;

use Composer\ClassMapGenerator\ClassMapGenerator;

use function is_array;
use function spl_autoload_functions;
use function class_exists;
use function explode;
use function realpath;
use function count;
use function substr;
use function in_array;
use function implode;
use function is_file;
use function str_repeat;
use function filemtime;

/**
 * Copyright BSD-3-Clause / Nistor Alexadru Marius / Auroframe SRL Romania / https://github.com/autoframe
 * Requires composer package: composer/class-map-generator
 * This will detect the real vendor path:
 * /shared/app/afr/vendor
 * C:\xampp\htdocs\afr\vendor
 * Empty is returned on detection fail
 */
class AfrVendorPath
{
	protected static string $sVendorPath;
	protected static string $sBaseDirPath;
	protected static array $aComposerJsonData;

	/**
	 * This will detect the real vendor path:
	 * /shared/app/afr/vendor
	 * C:\xampp\htdocs\afr\vendor
	 * Empty string '' is returned on detection fail
	 * @return string
	 */
	public static function getVendorPath(): string
	{
		if (!isset(static::$sVendorPath)) {
			static::$sVendorPath = defined($c = 'AfrVendorPath_sVendorPath') ? constant($c) : static::detectVendorPath();
		}
		return static::$sVendorPath;
	}

	/**
	 * This will get the base path set in composer config files
	 * For multiple apps that use a common vendor/composer dir,
	 * this path may be outside the current app path and may be
	 * different from the current app path!
	 * Empty string '' is returned on detection fail
	 * @return string
	 */
	public static function getBaseDirPath(): string
	{
		//this may differ from the actual Tenant base path, but usually is the same
		if (!isset(static::$sBaseDirPath)) {
			return static::$sBaseDirPath = static::getVendorPath() ? dirname(static::getVendorPath()) : '';
		}
		return static::$sBaseDirPath;
	}

	/**
	 * Get composer json.
	 * @return array
	 */
	public static function getComposerJson(): array
	{
		if (isset(static::$aComposerJsonData)) {
			return static::$aComposerJsonData;
		}
		$sBasePath = static::getBaseDirPath();

		return static::$aComposerJsonData = ($sBasePath ? json_decode(
			file_get_contents($sBasePath . DIRECTORY_SEPARATOR . 'composer.json'),
			true
		) : []);
	}


	/**
	 * @return int
	 * Empty 0 is returned on detection fail
	 */
	public static function getComposerTs(): int
	{
		if (!static::getVendorPath()) {
			return 0;
		}
		$sFile = static::getVendorPath() . '/composer/installed.json';
		return is_file($sFile) ? (int)filemtime($sFile) : 0;
	}

	/**
	 * Create map.
	 * @param $sPath
	 * @return array
	 */
	public static function createMap($sPath): array
	{
		return ClassMapGenerator::createMap($sPath);
	}

	/**
	 * @return array
	 * Empty array is returned on detection fail
	 */
	public static function getFullClassFilesMap(): array
	{
		if (!static::getVendorPath()) {
			return [];
		}
		return array_merge(
			static::getComposerAutoloadClassmap(),
			static::getComposerAutoloadPsrX(4),
			static::getComposerAutoloadPsrX(0)
		);
	}

	/**
	 * Empty array is returned on detection fail
	 * @param bool $bScanPsrClassMap
	 * @return array
	 */
	public static function getComposerAutoloadX(bool $bScanPsrClassMap = false): array
	{
		$vendorDir = static::getVendorPath();
		$iLenVendorDir = strlen($vendorDir);
		$aOut = [
			'vendor' => [
				'classmap' => [],
				'psr4' => [],
				'psr0' => [],
			],
			'autoload' => [
				'classmap' => [],
				'psr4' => [],
				'psr0' => [],
			]
		];
		if (!$vendorDir) {
			return $aOut;
		}

		foreach (static::getIncludedPhpArr('autoload_classmap') as $sNsCl => $sPath) {
			$sVa = substr($sPath, 0, $iLenVendorDir) === $vendorDir ? 'vendor' : 'autoload';
			$aOut[$sVa]['classmap'][$sNsCl] = $sPath;
		}

		// https://getcomposer.org/doc/articles/autoloader-optimization.md#how-to-run-it-
		$aComposerJson = static::getComposerJson();
		if (!empty($aComposerJson['classmap-authoritative'])) {
			// !empty($aComposerJson['optimize-autoloader'])
			return $aOut; //CLASS MAP HAS EVERYTHING THERE! PSR4 and PSR0 will be missed
		}

		foreach (['autoload_psr4' => 'psr4', 'autoload_namespaces' => 'psr0'] as $sPhpIncl => $sPsrX) {
			foreach (static::getIncludedPhpArr($sPhpIncl) as $sNsCl => $aDirs) {
				foreach ($aDirs as $sPackageDir) {
					$sVa = substr($sPackageDir, 0, $iLenVendorDir) === $vendorDir ? 'vendor' : 'autoload';
					if ($bScanPsrClassMap) {
						$aOut[$sVa][$sPsrX] = array_merge($aOut[$sPsrX][$sVa], static::createMap($sPackageDir));
					} else {
						$aOut[$sVa][$sPsrX][$sNsCl][] = $sPackageDir;
					}
				}
			}
		}

		return $aOut;
	}

	/**
	 * @param array $aClasses
	 * @return void
	 */
	protected static function fixDs(array &$aClasses)
	{
		$sDsReplace = DIRECTORY_SEPARATOR === '/' ? '\\' : '/';
		foreach ($aClasses as &$sClPath) {
			if (is_array($sClPath)) {
				static::fixDs($sClPath);
			} else {
				$sClPath = strtr($sClPath, $sDsReplace, DIRECTORY_SEPARATOR);
			}

		}
	}

	/**
	 * @return array
	 */
	protected static function getComposerAutoloadClassmap(): array
	{
		return static::getIncludedPhpArr('autoload_classmap');
	}


	/**
	 * @param int $iPsr
	 * @return array
	 */
	protected static function getComposerAutoloadPsrX(int $iPsr): array
	{
		$aNsDirs = [];

		if ($iPsr === 4) {
			$aNsDirs = static::getIncludedPhpArr('autoload_psr4');
		} elseif ($iPsr === 0) {
			$aNsDirs = static::getIncludedPhpArr('autoload_namespaces');
		}
		return static::createMapFromPsrX($aNsDirs);

	}

	/**
	 * Create map from psr x.
	 * @param array $aNsDirs
	 * @return array
	 */
	public static function createMapFromPsrX(array $aNsDirs): array
	{
		$aClasses = [];
		foreach ($aNsDirs as $aDirs) {
			foreach ($aDirs as $sPackageDir) {
				$aClasses = array_merge(static::createMap($sPackageDir), $aClasses);
			}
		}
		return $aClasses;
	}


	/**
	 * @param $sPhp
	 * @return array
	 */
	protected static function getIncludedPhpArr($sPhp): array
	{
		$aClasses = [];
		$sStaticMapFile = static::getVendorPath() . '/composer/' . $sPhp . '.php';
		if (file_exists($sStaticMapFile)) {
			$aClasses = (include $sStaticMapFile);
			if (!is_array($aClasses)) {
				$aClasses = [];
			}
		}
		static::fixDs($aClasses);
		return $aClasses;
	}


	/** Detects a valid vendor path in the file system
	 * @return string
	 */
	protected static function detectVendorPath(): string
	{
		//use a class pah from all composer distributions, because the file surely exists under vendor dir
		$oReflector = new \ReflectionClass(ClassMapGenerator::class);
		$sVendorPath = dirname($oReflector->getFileName(), 4);
		if (strlen($sVendorPath) > 5) {
			return $sVendorPath;
		}

		return static::detectVendorPathSlowUsingFileSystem();
	}

	/** Detects if composer is installed into a valid vendor path
	 * @param string $sPath
	 * @return string
	 */
	protected static function physicallyCheckForComposerVendorPath(string $sPath): string
	{
		$arRealPath = explode('vendor', (string)realpath($sPath));
		if (($iParts = count($arRealPath)) < 2) {
			return '';
		}
		$sChrAfterVendor = substr($arRealPath [$iParts - 1], 0, 1);
		$sChrBeforeVendor = substr($arRealPath [$iParts - 2], -1, 1);
		if (
			!in_array($sChrAfterVendor, ['', '\\', '/']) ||
			!in_array($sChrBeforeVendor, ['\\', '/'])
		) {
			return '';
		}
		$arRealPath [$iParts - 1] = ''; //clear last part
		$sRealPath = implode('vendor', $arRealPath);
		return is_file($sRealPath . '/composer/installed.json') ? (string)realpath($sRealPath) : '';
	}

	/**
	 * @return array
	 * Fallback for vendor directory that is outside the app directory
	 */
	protected static function getSplComposerClassMap(): array
	{
		$sClassLoader = 'Composer\\Autoload\\ClassLoader';
		if (!class_exists($sClassLoader)) {
			return [];
		}
		foreach ((array)spl_autoload_functions() as $aAutoloadFunction) {
			if (!is_array($aAutoloadFunction)) {
				continue;
			}
			foreach ($aAutoloadFunction as $mLoader) {
				if ($mLoader instanceof $sClassLoader) {
					return (array)$mLoader->getClassMap();
				}
			}
		}
		return [];
	}

	/**
	 * Check if the given path is located inside the vendor directory.
	 *
	 * @param string $sPath The path to check.
	 * @return bool True if the path is inside the vendor directory, false otherwise.
	 */
	public static function pathIsInsideVendorDir(string $sPath): bool
	{
		$sPath = strtr($sPath, '\\', '/');
		if (strpos($sPath . '/', '/vendor/') !== false) {
			$aSegments = [];
			foreach (explode('/', $sPath) as $i => $sPart) {
				if ($sPart === '' && $i || $sPart === '.') {
					continue;
				}
				if ($sPart === '..') {
					if (!empty($aSegments)) {
						array_pop($aSegments);
					}
				} else {
					$aSegments[] = $sPart;
				}
			}
			return strpos(implode('/', $aSegments) . '/', '/vendor/') !== false;
		}
		return false;
	}

	/**
	 * Detect vendor path slow using file system.
	 * @return string
	 */
	public static function detectVendorPathSlowUsingFileSystem(): string
	{
		//production, already installed in composer
		if ($sVendorPath = static::physicallyCheckForComposerVendorPath(__DIR__)) {
			return $sVendorPath;
		}
		//local afr dev$i
		for ($i = 2; $i <= 6; $i++) {
			$sDir = __DIR__ . DIRECTORY_SEPARATOR .
				str_repeat('..' . DIRECTORY_SEPARATOR, $i) .
				'vendor';
			if ($sVendorPath = static::physicallyCheckForComposerVendorPath($sDir)) {
				return $sVendorPath;
			}
		}


		//fallback: script is loaded outside composer vendor dir or other custom path:
		$i = 0;
		foreach (static::getSplComposerClassMap() as $sDir) {
			if ($i >= 10) {
				break; //limit to 10 entries is a reasonable value before failing
			}
			$i++;
			if ($sVendorPath = static::physicallyCheckForComposerVendorPath($sDir)) {
				return $sVendorPath;
			}
		}
		return ''; //fail!
	}

}
