<?php

namespace Autoframe\Core\FileSystem\CacheToPhpFile;

use Autoframe\Core\Afr\Afr;
use Autoframe\Core\Arr\Export\AfrArrExportArrayAsStringClass;
use Autoframe\Core\Arr\Export\AfrArrExportArrayAsStringInterface;
use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\Event\Exception\AfrEventException;
use Autoframe\Core\Exception\AfrException;
use Autoframe\Core\FileSystem\OverWrite\AfrOverWriteClass;
use Autoframe\Core\FileSystem\OverWrite\AfrOverWriteInterface;
use Autoframe\Core\String\Obj\AfrFqcn;


trait AfrCachePhpFileToArrayTrait
{
	protected static ?string $sPhpIncludeCacheFileName = 'cache.php';
	protected static int $iPhpIncludeCacheSeconds = 300;

	protected ?AfrOverWriteInterface $oAfrOverWrite = null;
	protected ?AfrArrExportArrayAsStringInterface $oAfrExportArray = null;


	/** @var AfrOverWriteInterface[] */
	protected static array $oAfrOverWriteFallback = [];

	/** @var AfrArrExportArrayAsStringInterface[] */
	protected static array $oAfrExportArrayFallback = [];

	private static float $compareIntFloat = 1770000000.0;

	/**
	 * @param string|object $soAliasSubDir
	 * @param string|null $sCacheFileName
	 * @param float|int|null $fiSec
	 * @return array|null
	 * @throws AfrException
	 * @throws \ReflectionException
	 */
	public function getFromCache($soAliasSubDir, string $sCacheFileName = null, $fiSec = null): ?array
	{
		$sFile = static::getClassCacheTempFilePath($soAliasSubDir, $sCacheFileName ?? static::$sPhpIncludeCacheFileName);
		$aRead = @include($sFile);

		if ($aRead === false || !isset($aRead['expire']) && !isset($aRead['created'])) {
			$this->setToCache(null, $soAliasSubDir, -3, $sCacheFileName);
			return null;
		}
		$mt = microtime(true);
		$fCheckExpired = null;

		if (is_int($fiSec) || is_float($fiSec)) { //Custom expire check in few delta seconds or in absolute time seconds
			if (isset($aRead['created']) && $fiSec < static::$compareIntFloat)
				$fCheckExpired = $aRead['created'] + $fiSec; //delta sec compared to creation
		}
		if ($fCheckExpired === null) {
			if (isset($aRead['expire'])) $fCheckExpired = (float)$aRead['expire'];
			elseif (isset($aRead['created'])) $fCheckExpired = floor($aRead['created']) + static::$iPhpIncludeCacheSeconds;
		}

		if ($fCheckExpired === null || $fCheckExpired < $mt) return null;

		return $aRead['data'] ?? null;
	}

	public function getFromCacheInline($soAliasSubDir, string $sCacheFileName = null, int $iSec = null): ?array
	{
		$sFile = static::getClassCacheTempFilePath($soAliasSubDir, $sCacheFileName ?? static::$sPhpIncludeCacheFileName);
		if (file_exists($sFile) && filemtime($sFile) + ($iSec ?? static::$iPhpIncludeCacheSeconds) >= time())
			return include($sFile);
		return null;
	}

	/**
	 * @param array|null $aDataToString
	 * @param string|object $soAliasSubDir
	 * @param float|int|null $fiExpireTime
	 * @param string|null $sCacheFileName
	 * @return bool
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 * @throws AfrException
	 * @throws \ReflectionException
	 */

	public function setToCache(?array $aDataToString, $soAliasSubDir, $fiExpireTime = null, string $sCacheFileName = null): bool
	{
		if ($sCacheFileName === null && is_string($fiExpireTime) && !is_numeric($fiExpireTime))
			$sCacheFileName = $fiExpireTime; //parameter order fix
		$fiExpireTime = $this->toFloatSec($fiExpireTime);

		return $this->setToCacheInline(
			['created' => $fiExpireTime > -1?microtime(true):0, 'expire' =>$fiExpireTime, 'data' => $aDataToString],
			$soAliasSubDir,
			$sCacheFileName
		);
	}

	protected function toFloatSec($fiExpireTime): ?float
	{
		$fiExpireTime = is_int($fiExpireTime) || is_float($fiExpireTime) ? (float)$fiExpireTime : null;
		if ($fiExpireTime !== null && $fiExpireTime > -1)
			$fiExpireTime = $fiExpireTime >= static::$compareIntFloat ? $fiExpireTime : microtime(true) + $fiExpireTime;
		return $fiExpireTime;
	}



	/**
	 * @param array|null $aDataToString
	 * @param $soAliasSubDir
	 * @param string|null $sCacheFileName
	 * @return bool
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 * @throws AfrException
	 * @throws \ReflectionException
	 */
	public function setToCacheInline(?array $aDataToString, $soAliasSubDir, string $sCacheFileName = null): bool
	{
		$sFile = static::getClassCacheTempFilePath($soAliasSubDir, $sCacheFileName ?? static::$sPhpIncludeCacheFileName);
		$aDataToString = $this->convertArrPhpStr($aDataToString);
		return $this->xetOverWrite()->overWriteFile(
			$sFile,
			$aDataToString
		);
	}


	/**
	 * @return AfrOverWriteClass|AfrOverWriteInterface
	 * @throws AfrEventException|AfrContainerException
	 */
	public static function xetOverWriteFallback(AfrOverWriteInterface $oAfrOverWrite = null): AfrOverWriteInterface
	{
		return $oAfrOverWrite ? static::$oAfrOverWriteFallback[static::class] = $oAfrOverWrite :
			static::$oAfrOverWriteFallback[static::class] ?? AfrOverWriteClass::getInstanceNoContainerBindings();
	}


	/**
	 * @return AfrOverWriteClass|AfrOverWriteInterface
	 * @throws AfrEventException|AfrContainerException
	 */
	public function xetOverWrite(AfrOverWriteInterface $oAfrOverWrite = null): AfrOverWriteInterface
	{
		return $oAfrOverWrite ? $this->oAfrOverWrite = $oAfrOverWrite :
			$this->oAfrOverWrite ?? static::xetOverWriteFallback();
	}


	/**
	 * @return AfrArrExportArrayAsStringClass|AfrArrExportArrayAsStringInterface
	 * @throws AfrEventException|AfrContainerException
	 */
	public function xetExportArray(AfrArrExportArrayAsStringInterface $oAfrExportArray = null): AfrArrExportArrayAsStringInterface
	{
		return $oAfrExportArray ? $this->oAfrExportArray = $oAfrExportArray :
			$this->oAfrExportArray ?? static::xetExportArrayFallback();
	}


	/**
	 * @return AfrArrExportArrayAsStringClass|AfrArrExportArrayAsStringInterface
	 * @throws AfrEventException|AfrContainerException
	 */
	public static function xetExportArrayFallback(AfrArrExportArrayAsStringInterface $oAfrOverWrite = null): AfrArrExportArrayAsStringInterface
	{
		return $oAfrOverWrite ? static::$oAfrExportArrayFallback[static::class] = $oAfrOverWrite :
			static::$oAfrExportArrayFallback[static::class] ??= AfrArrExportArrayAsStringClass::getInstanceNoContainerBindings();
	}

	/**
	 * @param string|object|null $soAliasSubDir
	 * @return string
	 * @throws AfrException
	 */
	public static function getTenantDefaultTempDir($soAliasSubDir): string
	{
		if (!is_object($soAliasSubDir) && !is_string($soAliasSubDir)) {
			$soAliasSubDir = null;
		}
		return Afr::getTempDir($soAliasSubDir);
	}

	/**
	 * @param string|object $soAliasSubDir
	 * @param string $sCacheFileName
	 * @return string
	 * @throws AfrException
	 */
	public static function getClassCacheTempFilePath($soAliasSubDir, string $sCacheFileName): string
	{
		$sSysTenantTmpDir = Afr::getTempDir($soAliasSubDir);
		if (!empty($sCacheFileName)) $sCacheFileName = AfrFqcn::getClassBaseNameFromFQCN($sCacheFileName);
//		$sCacheFileName = empty($sCacheFileName) ?	AfrFqcn::getClassBaseNameFromObjectOrFQCN($soAliasSubDir) :	AfrFqcn::getClassBaseNameFromFQCN($sCacheFileName);

		if (empty($sCacheFileName)) $sCacheFileName = static::$sPhpIncludeCacheFileName ?? 'afr_inc_cache'; //defaulting to system temp dir
		if ('.php' !== strtolower(substr($sCacheFileName, -4, 4)))
			$sCacheFileName .= '.php';
		return $sSysTenantTmpDir . DIRECTORY_SEPARATOR . $sCacheFileName;
	}

	/**
	 * @param array|null $aDataToString
	 * @return string
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 * @throws \ReflectionException
	 */
	public function convertArrPhpStr(?array $aDataToString): string
	{
		return '<?php // ' . gmdate('D, d M Y H:i:s') . " GMT\n return " . (
			$aDataToString === null ? 'null;' : $this->xetExportArray()->exportPhpArrayAsString($aDataToString)
			);
	}


}