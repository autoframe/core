<?php

namespace Autoframe\Core\Env;

use Autoframe\Core\Env\Exception\AfrEnvException;
use Autoframe\Core\Arr\Export\AfrArrExportArrayAsStringInterface;
use Autoframe\Core\FileSystem\OverWrite\AfrOverWriteInterface;
use Autoframe\Core\FileSystem\Traversing\AfrDirTraversingFileListInterface;
use Autoframe\Core\Env\Parser\AfrEnvParserInterface;
use Autoframe\Core\Env\Validator\AfrEnvValidatorInterface;


/**
 * @method static AfrEnvInterface registerEnv(bool $bMutableOverwrite = false, bool $bRegisterPutEnv = false)
 * @method static mixed getEnv(string $sKey = '', $mFallback = null)
 * @method static AfrEnvInterface setBaseDir(string $sDir)
 * @method static AfrEnvInterface setEnv(string $sKey, $mData)
 * @method static AfrEnvInterface readEnvPhpFile(string $sFilePath)
 * @method static AfrEnvInterface readEnv(int $iCacheSeconds, array $aEnvDirsFiles = [], bool $bReadEnvFromBaseDir = true)
 * @method static AfrEnvInterface flush()
 * @method static bool isProduction()
 * @method static bool isStaging()
 * @method static bool isDev()
 * @method static int isDebug()
 * @method static bool isDevOrDebug()
 * @method static AfrEnvValidatorInterface required(array $aKeys)
 * @method static AfrEnvValidatorInterface ifPresent(array $aKeys)
 * @method static AfrEnvValidatorInterface unrequire(array $aKeys)
 * @method static AfrEnvParserInterface xetAfrEnvParser(AfrEnvParserInterface $oEnvParser = null)
 * @method static AfrEnvValidatorInterface xetAfrEnvValidator(AfrEnvValidatorInterface $oValidator = null)
 * @method static AfrDirTraversingFileListInterface xetFileList(AfrDirTraversingFileListInterface $oFileList = null)
 * @method static AfrOverWriteInterface xetOverWrite(AfrOverWriteInterface $oOverWrite = null)
 * @method static AfrArrExportArrayAsStringInterface xetExportArray(AfrArrExportArrayAsStringInterface $oExportArray = null)
 * @see AfrEnvInterface
 */
final class AfrEnvFacade
{
	/**
	 * @var AfrEnvInterface|AfrEnv|string implementing AfrEnvInterface
	 */
	protected static string $sEnvFQCN = AfrEnv::class;

	/**
	 * @param string|null $sEnvFQCN
	 * @return string FQCN implementing AfrEnvInterface
	 * @throws AfrEnvException
	 */
	public static function xetEnvClass(string $sEnvFQCN = null): string
	{
		if (!empty($sEnvFQCN)) {
			if (
				$sEnvFQCN !== AfrEnv::class &&
				!isset(class_implements($sEnvFQCN)[AfrEnvInterface::class])
			) {
				throw new AfrEnvException("The class $sEnvFQCN  does not implement AfrEnvInterface");
			}
			self::$sEnvFQCN = $sEnvFQCN;
		}
		return self::$sEnvFQCN;
	}

	/**
	 * @return AfrEnv|AfrEnvInterface
	 */
	public static function getEnvInstance(): AfrEnvInterface
	{
		return self::$sEnvFQCN::getInstance();
	}

	/**
	 * @param $method
	 * @param $args
	 * @return mixed
	 */
	public static function __callStatic($method, $args)
	{
		return self::getEnv()->$method(...$args);
	}

}