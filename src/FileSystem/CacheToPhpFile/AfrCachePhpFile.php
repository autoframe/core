<?php

namespace Autoframe\Core\FileSystem\CacheToPhpFile;

use Autoframe\Core\Afr\Afr;
use Autoframe\Core\Arr\Export\AfrArrExportArrayAsStringClass;
use Autoframe\Core\Arr\Export\AfrArrExportArrayAsStringInterface;
use Autoframe\Core\CliTools\AfrSysTempDir;
use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;
use Autoframe\Core\Env\Exception\AfrEnvException;
use Autoframe\Core\FileSystem\OverWrite\AfrOverWriteClass;
use Autoframe\Core\FileSystem\OverWrite\AfrOverWriteInterface;
use Autoframe\Core\Tenant\AfrTenant;

class AfrCachePhpFile extends AfrSingletonAbstractClass{
	/**
	 * @var AfrOverWriteClass|AfrOverWriteInterface|false
	 */
	protected AfrOverWriteInterface $oOverWrite;
	/**
	 * @var AfrArrExportArrayAsStringClass|AfrArrExportArrayAsStringInterface|false
	 */
	protected AfrArrExportArrayAsStringInterface $oExportArray;

	/**
	 * @param AfrOverWriteInterface|null $oOverWrite
	 * @return AfrOverWriteInterface
	 */
	public function xetOverWrite(AfrOverWriteInterface $oOverWrite = null): AfrOverWriteInterface
	{
		if ($oOverWrite) {
			$this->oOverWrite = $oOverWrite;
		} elseif (empty($this->oOverWrite)) {
			$this->oOverWrite = AfrOverWriteClass::getInstance();
		}
		return $this->oOverWrite;
	}


	/**
	 * @param AfrArrExportArrayAsStringInterface|null $oExportArray
	 * @return AfrArrExportArrayAsStringInterface
	 */
	public function xetExportArray(AfrArrExportArrayAsStringInterface $oExportArray = null): AfrArrExportArrayAsStringInterface
	{
		if ($oExportArray) {
			$this->oExportArray = $oExportArray;
		} elseif (empty($this->oExportArray)) {
			$this->oExportArray = AfrArrExportArrayAsStringClass::getInstance();
		}
		return $this->oExportArray;
	}


	/**
	 * @param string $sFilePath
	 * @return self
	 * @throws AfrEnvException
	 */
	public function readEnvPhpFile(string $sFilePath): self
	{
			$sTempDir = AfrSysTempDir::sysGetTempDirAliasSubDir($this);
			$sTempDir = Afr::getTempDir(). DIRECTORY_SEPARATOR.'AfrModuleBox-RT.php';
			$sTempDir = AfrTenant::getTempDir(). DIRECTORY_SEPARATOR.'AfrModuleBox-RT.php';
		//TODO: closures serialize via OPIS

		if (!is_file($sFilePath)) {
			if (
				!empty($this->sBaseDir) &&
				is_file($this->sBaseDir . DIRECTORY_SEPARATOR . $sFilePath)
			) {
				$sFilePath = $this->sBaseDir . DIRECTORY_SEPARATOR . $sFilePath;
			} else {
				throw new AfrEnvException('Unable to find the php array env file: ' . $sFilePath);
			}
		}
		$aData = include $sFilePath;
		if (!is_array($aData)) {
			throw new AfrEnvException('Unable to load an empty php array env file: ' . $sFilePath);
		}
		$this->aEnvData = array_merge($this->aEnvData, $aData);
		return $this;
	}




	/**
	 * @return void
	 */
	protected function setCache(): void
	{
		if (empty($this->aEnvData)) {
			//	$this->aEnvData = ['AFR_DEBUG' => 1];
			return;
		}
		$sHeader = '<?php /* ' . gmdate('D, d M Y H:i:s') . ' GMT ->loadCache: ' .
			str_replace('*/', '* /', print_r($this->aEnvDirsFiles, true)) .
			"*/ \n return ";
		$this->xetOverWrite()->overWriteFile(
			$this->getCacheFileName(),
			$sHeader . $this->xetExportArray()->exportPhpArrayAsString($this->aEnvData),
		);
	}

	/**
	 * @return string
	 */
	public function getCacheFileName(): string
	{
		if (empty($this->sCacheFile)) {
			$this->sCacheFile = $this->sBaseDir .
				DIRECTORY_SEPARATOR .
				(AfrTenant::getTenantAlias() ?? '_') . '.' . $_ENV['AFR_ENV'] .
				//'_' . substr(md5(serialize($this->aEnvDirsFiles)), 10, 8) .
				'.env.php';
		}
		return $this->sCacheFile;
	}

}