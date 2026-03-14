<?php
declare(strict_types=1);

namespace Autoframe\Core\Http\Download;

use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;
use Autoframe\Core\Env\Exception\AfrEnvException;
use Autoframe\Core\Event\Exception\AfrEventException;
use Autoframe\Core\Http\Download\Exception\AfrHttpDownloadException;
use Autoframe\Core\Http\Header\AfrHttpHeader;
use Autoframe\Core\Http\Header\Exception\AfrHttpHeaderException;
use Autoframe\Core\Http\Request\AfrRequestClass;

class AfrHttpDownload extends AfrSingletonAbstractClass
{

	/**
	 * Stream file.
	 * @param string $sFilePath
	 * @param int $iCache 0=no, 1=yes, -1= session cache default
	 * @param bool $bUseEtag
	 * @param bool $bExit
	 * @return void
	 * @throws AfrContainerException
	 * @throws AfrEnvException
	 * @throws AfrEventException
	 * @throws AfrHttpHeaderException
	 * @throws AfrHttpDownloadException
	 */
	public function streamFile(
		string $sFilePath,
		int    $iCache = -1,
		bool   $bUseEtag = true,
		bool   $bExit = true
	): void
	{
		if (!is_file($sFilePath)) {
			$this->getAfrHttpHeader()->h404();
			return;
		}

		//$iLastModifyTs = filemtime($sFilePath);
		//$iSize = filesize($sFilePath);
		list($iSize, $iLastModifyTs) = $this->httpComputeFileSizeMtime($sFilePath);

		$sGmtLastModify = $this->getAfrHttpHeader()->getHeaderGmtDateFromTs($iLastModifyTs);
		$headers = AfrRequestClass::getInstance()->getServerRequestHeaders();
		if ($iCache === 0) {
			$bUseEtag = false; // etag should be off when cache is off
		}

		$eTag = $bUseEtag ? $this->getAfrHttpHeader()->headerETag($sFilePath, $iSize, $iLastModifyTs) : '';

		if ($bUseEtag && $this->getAfrHttpHeader()->canServe304($eTag, $sGmtLastModify)) {
			$this->getAfrHttpHeader()->h304NotModified(); // use cache
		} else {
			if ($iCache > 0) {
				$this->getAfrHttpHeader()->headerDoCache($iCache);
			} elseif ($iCache === 0) {
				$this->getAfrHttpHeader()->headerNoCache();
			} // elseif ($iCache < 0) {} // set by session_cache_limiter() and session_cache_expire() if session was previously started
			$this->getAfrHttpHeader()->headerContentTypeMime($sFilePath);
			$this->getAfrHttpHeader()->headerContentLength($sFilePath, $iSize);
			$this->getAfrHttpHeader()->headerLastModified($sFilePath, $iLastModifyTs, $sGmtLastModify);

			if ($handle = @fopen($sFilePath, 'r')) {
				while (($buffer = fgets($handle, 4096)) !== false) {
					ob_start();
					echo $buffer;
					ob_end_flush();
				}
				if (!feof($handle)) {
					$this->getAfrHttpHeader()->e503ServiceTemporaryUnavailable('Error: unexpected file read fail');
				}
				fclose($handle);
			} else {
				$this->getAfrHttpHeader()->e503ServiceTemporaryUnavailable('The requested file can`t be open!');
			}
		}

		if ($bExit) {
			exit();
		}

	}

	/**
	 * Get afr http header.
	 * @return AfrHttpHeader
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 */
	public function getAfrHttpHeader(): AfrHttpHeader
	{
		return AfrHttpHeader::getInstance();
	}

	/**
	 * 0: no Content-Disposition;
	 * 1: Content-Disposition: attachment If you want to encourage the client to download it instead of following the default behaviour;
	 * 2: Content-Disposition: attachment + application/force-download;
	 * 3: Content-Disposition: inline: With inline, the browser will try to open the file within the browser;
	 * For example, if you have a PDF file and Firefox/Adobe Reader, an inline disposition will open the PDF within Firefox,
	 * whereas attachment will force it to download. If you're serving a .ZIP file, browsers won't be able
	 * to display it inline, so for inline and attachment dispositions, the file will be downloaded.
	 * @param int $iDownloadMode
	 * @param string $sFullFilePath
	 * @param string $sSaveFileName
	 * @param bool $bExit
	 * @param null $contextRes
	 * @return false|int|void
	 * @throws AfrContainerException
	 * @throws AfrEnvException
	 * @throws AfrEventException
	 * @throws AfrHttpDownloadException
	 * @throws AfrHttpHeaderException
	 */
	function httpDownloadFile(
		int    $iDownloadMode,
		string $sFullFilePath,
		string $sSaveFileName = '',
		bool   $bExit = true,
		       $contextRes = null
	)
	{
		if (!is_readable($sFullFilePath)) {
			$this->getAfrHttpHeader()->setHttpResponseCode(404);
			throw new AfrHttpDownloadException('File is not readable: ' . $sFullFilePath);
		}
		$sSaveFileName = strlen($sSaveFileName) ? trim($sSaveFileName) : basename($sFullFilePath);

		header('Content-Transfer-Encoding: binary');
		$this->getAfrHttpHeader()->headerContentTypeMime($sFullFilePath);
		$this->getAfrHttpHeader()->headerContentLength($sFullFilePath);
		$this->getAfrHttpHeader()->headerLastModified($sFullFilePath);
		$this->getAfrHttpHeader()->headerContentDisposition($iDownloadMode, $sSaveFileName);
		$r = $contextRes ? readfile($sFullFilePath, false, $contextRes) : readfile($sFullFilePath);
		return $bExit ? die() : $r;
	}


	/**
	 * Http file cache.
	 * @param string $sFileName_OR_FullFilePath
	 * @param int $iCacheExpire
	 * @param bool $bImmutable
	 * @param bool $bMustRevalidate
	 * @param bool $bExit
	 * @param null $contextRes
	 * @return bool|int|null
	 * @throws AfrContainerException
	 * @throws AfrEnvException
	 * @throws AfrEventException
	 * @throws AfrHttpDownloadException
	 * @throws AfrHttpHeaderException
	 */
	public function httpFileCache(
		string $sFileName_OR_FullFilePath,
		int    $iCacheExpire = 2678400,
		bool   $bImmutable = true,
		bool   $bMustRevalidate = true,
		bool   $bExit = true,
		       $contextRes = null
	)
	{
		return $this->httpFileCacheMixedParams(
			$sFileName_OR_FullFilePath,
			$bImmutable,
			$bMustRevalidate,
			$iCacheExpire,
			null,
			-1,
			$bExit,
			$contextRes
		);
	}

	/**
	 * Http stream data.
	 * @param string $sFileContents
	 * @param string $sFileNameAndMime
	 * @param int $iCacheExpire
	 * @param int $iContentsMtime
	 * @param bool $bImmutable
	 * @param bool $bMustRevalidate
	 * @param bool $bExit
	 * @return bool|int|null
	 * @throws AfrContainerException
	 * @throws AfrEnvException
	 * @throws AfrEventException
	 * @throws AfrHttpDownloadException
	 * @throws AfrHttpHeaderException
	 */
	public function httpStreamData(
		string $sFileContents,
		string $sFileNameAndMime,
		int    $iCacheExpire = 2678400,
		int    $iContentsMtime = -1,
		bool   $bImmutable = true,
		bool   $bMustRevalidate = true,
		bool   $bExit = true
	)
	{
		return $this->httpFileCacheMixedParams(
			$sFileNameAndMime,
			$bImmutable,
			$bMustRevalidate,
			$iCacheExpire,
			$sFileContents,
			$iContentsMtime,
			$bExit,
			null
		);

	}



	/**
	 * @param string $sFileName_OR_FullFilePath
	 * @param string|null $sFileContents
	 * @param int $iFileMtime
	 * @return array
	 * @throws AfrContainerException
	 * @throws AfrEnvException
	 * @throws AfrEventException
	 * @throws AfrHttpDownloadException
	 * @throws AfrHttpHeaderException
	 */
	protected function httpComputeFileSizeMtime(
		string $sFileName_OR_FullFilePath,
		string $sFileContents = null,
		int    $iFileMtime = -1
	): array
	{
		if (is_null($sFileContents) && !is_readable($sFileName_OR_FullFilePath)) {
			$this->getAfrHttpHeader()->setHttpResponseCode(404);
			throw new AfrHttpDownloadException('File is not readable: ' . $sFileName_OR_FullFilePath);
		}
		if (!is_null($sFileContents)) {
			$iFileSize = strlen($sFileContents);
			if ($iFileMtime === -1) {
				$iFileMtime = time() - 300;
			}
		} else {
			$iFileSize = filesize($sFileName_OR_FullFilePath);
			$iFileMtime = filemtime($sFileName_OR_FullFilePath);
		}
		return [$iFileSize, $iFileMtime];
	}

	/**
	 * @param string $sFileName_OR_FullFilePath
	 * @param bool $bImmutable
	 * @param bool $bMustRevalidate
	 * @param int $iCacheExpire
	 * @param string|null $sFileContents
	 * @param int $iContentsMtime
	 * @param bool $bExit
	 * @param null $contextRes
	 * @return bool|int|void
	 * @throws AfrEnvException
	 * @throws AfrHttpDownloadException
	 * @throws AfrHttpHeaderException
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 */
	protected function httpFileCacheMixedParams(
		string $sFileName_OR_FullFilePath,
		bool   $bImmutable = true,
		bool   $bMustRevalidate = true,
		int    $iCacheExpire = 2678400,
		string $sFileContents = null,
		int    $iContentsMtime = -1,
		bool   $bExit = true,
		       $contextRes = null
	)
	{
		list($iFileSize, $iFileMtime) = $this->httpComputeFileSizeMtime($sFileName_OR_FullFilePath, $sFileContents, $iContentsMtime);
		$this->getAfrHttpHeader()->httpHeaderCacheControlAndExpire($iCacheExpire, $bImmutable, $bMustRevalidate);
		$eTag = $iCacheExpire || $bImmutable ? $this->getAfrHttpHeader()->headerETag($sFileName_OR_FullFilePath, $iFileSize, $iFileMtime) : '';
		$sGmtLastModified = $this->getAfrHttpHeader()->getHeaderGmtDateFromTs($iFileMtime);
		$this->getAfrHttpHeader()->headerLastModified('', -1, $sGmtLastModified);

		$bIs304 = $this->getAfrHttpHeader()->canServe304($eTag, $sGmtLastModified);
		$return = true;

		if (!$bIs304) {
			//header('Age: '.(time()-$iFileMtime));
			$this->getAfrHttpHeader()->headerContentTypeMime($sFileName_OR_FullFilePath, '', true);
			$this->getAfrHttpHeader()->headerContentLength('', $iFileSize);
			if (!is_null($sFileContents)) {
				echo $sFileContents;
			} else {
				//TODO test readfile return number of bytes read from the file (Reads a file and writes it to the output buffer.)
				$return = $contextRes ? readfile($sFileName_OR_FullFilePath, false, $contextRes) : readfile($sFileName_OR_FullFilePath);
			}

		} else {
			$this->getAfrHttpHeader()->setHttpResponseCode(304);
			$this->getAfrHttpHeader()->headerContentLength('', 0);
		}
		return $bExit ? die() : $return;
	}


}
