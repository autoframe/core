<?php
declare(strict_types=1);

namespace Autoframe\Core\Http\Header;

use Autoframe\Core\CliTools\AfrCliHttpDetect;
use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;
use Autoframe\Core\Env\AfrEnv;
use Autoframe\Core\Env\Exception\AfrEnvException;
use Autoframe\Core\Error\AfrError;
use Autoframe\Core\Event\AfrEvent;
use Autoframe\Core\Event\Exception\AfrEventException;
use Autoframe\Core\Http\Download\AfrHttpDownload;
use Autoframe\Core\Http\Download\Exception\AfrHttpDownloadException;
use Autoframe\Core\Http\Header\Exception\AfrHttpHeaderException;
use Autoframe\Core\Afr\Afr;
use Autoframe\Core\Http\Request\AfrRequestClass;
use Autoframe\Core\String\AfrStr;
use Autoframe\Core\FileMime\AfrFileMimeClass;


class AfrHttpHeader extends AfrSingletonAbstractClass
{


	/** just the mime type and the length */
	const NoContentDisposition = 0;

	/** attachment: If you want to encourage the client to download it instead of following the default behaviour */
	const ContentDispositionFileTransfer = 1;

	/** attachment with application/force-download */
	const ContentDispositionFileTransferForceDownload = 2;

	/** inline: With inline, the browser will try to open the file within the browser */
	const ContentDispositionInline = 3;


	/**
	 * @param $mData
	 * @param bool $bExit
	 * @return void
	 */
	public function toJson($mData, bool $bExit)
	{
		header('Content-Type: application/json');
		$mData = json_encode($mData);
		$this->headerContentLength('', strlen($mData));
		echo $mData;
		if ($bExit) {
			exit;
		}
	}


	/**
	 *  100: Continue;
	 *  101: Switching Protocols;
	 *  200: OK;
	 *  201: Created;
	 *  202: Accepted;
	 *  203: Non-Authoritative Information;
	 *  204: No Content;
	 *  205: Reset Content;
	 *  206: Partial Content;
	 *  300: Multiple Choices;
	 *  301: Moved Permanently;
	 *  302: Moved Temporarily;
	 *  303: See Other;
	 *  304: Not Modified;
	 *  305: Use Proxy;
	 *  400: Bad Request;
	 *  401: Unauthorized;
	 *  402: Payment Required;
	 *  403: Forbidden;
	 *  404: Not Found;
	 *  405: Method Not Allowed;
	 *  406: Not Acceptable;
	 *  407: Proxy Authentication Required;
	 *  408: Request Date-out;
	 *  409: Conflict;
	 *  410: Gone;
	 *  411: Length Required;
	 *  412: Precondition Failed;
	 *  413: Request Entity Too Large;
	 *  414: Request-URI Too Large;
	 *  415: Unsupported Media Type;
	 *  500: Internal Server Error;
	 *  501: Not Implemented;
	 *  502: Bad Gateway;
	 *  503: Service Unavailable;
	 *  504: Gateway Date-out;
	 *  505: HTTP Version not supported
	 * @param int $iCode
	 * @return bool
	 * @throws AfrEnvException|AfrHttpHeaderException|AfrEventException
	 */
	public function setHttpResponseCode(int $iCode): bool
	{
		AfrEvent::dispatchEvent();
		$filename = $line = null;
		if (headers_sent($filename, $line)) {
			if (Afr::app()->env()->getEnv('HTTP_HEADER_RESPONSE_CODE_ERROR_IS_CRITICAL', true)) {
				throw new AfrHttpHeaderException("Header block has already been sent in $filename line $line");
			}
			return false;
		} else {
			return (bool)http_response_code($iCode);
		}
	}

	/**
	 * @return bool|int
	 */
	public function getHttpResponseCode()
	{
		return http_response_code();
	}

	public function getHeaders(AfrRequestClass $rq = null): array
	{
		return AfrCliHttpDetect::getServerRequestHeaders($rq);
	}

	/**
	 * 300 Multiple Choices;
	 * 301 Moved Permanently - Forget the old page existed. Convert to GET;
	 * 302 Found - Use the same method (GET/POST) to request the specified page;
	 * 303 See Other - Use GET to request the specified page. Use this to redirect after a POST;
	 * 304 Not Modified use cache;
	 * 305 Use Proxy;
	 * 306 Switch Proxy;
	 * 307 Temporary Redirect - use for GET/HEAD; ELSE: ask user to redirect; do not use with forms!;
	 * 308 Permanent Redirect (experimental RFC7238);
	 * @param int $iCode
	 * @param string $sLocation
	 * @param bool $bStripGetParams
	 * @param array $aBuildQuery
	 * @param bool $bExit
	 * @return void
	 * @throws AfrEnvException|AfrHttpHeaderException|AfrEventException|AfrContainerException
	 */
	public function headerRedirect3xx(
		int    $iCode = 307,
		string $sLocation = '',
		bool   $bStripGetParams = false,
		array  $aBuildQuery = [],
		bool   $bExit = true
	): void
	{
		AfrEvent::dispatchEvent();
		$sRqUri = ($_SERVER['REQUEST_URI']??'/');
		if (!$sLocation) {
			if ($iCode === 301) {
				$iCode = 307; //prevent wrong permanent redirect
			}
			$sLocation = $sRqUri;
		}

		if (
			(!empty($_POST) || ($_SERVER['REQUEST_METHOD']??'') === 'POST') &&
			(!$iCode || $iCode == 302)) {
			$iCode = 303;
		}


		if ($bStripGetParams) {
			$sLocation = explode('?', $sLocation)[0];
		}

		if (!empty($aBuildQuery)) {
			$sLoc_tmp = explode('?', $sLocation);
			$aExisting = [];
			if (!empty($sLoc_tmp[1])) {
				parse_str($sLoc_tmp[1], $aExisting);
			}
			foreach ($aBuildQuery as $key => $val) {
				$aExisting[$key] = $val;
			}
			$sLocation = $sLoc_tmp[0] . '?' . http_build_query($aExisting);
		}
		$filename = $line = null;
		if (headers_sent($filename, $line) === false) {
			if ($iCode != 304) {
				$this->headerNoCache();
				$this->headerExpires(0);
			}

			if ($iCode === 301 && $sLocation === $sRqUri) {
				throw new AfrHttpHeaderException('Error making a permanent redirect to the same loop page: ' . $sLocation);
			}
			$this->setHttpResponseCode($iCode);
			header('Location: ' . urlencode($sLocation));

		} else {// Show the HTML?
			$sErrMsg = 'The automatic redirect failed because the output was started in ' . $filename . ' at line ' . $line . ';';
			AfrError::error_log($sErrMsg . ' The redirect target is: ' . $sLocation);
			$sHLocation = htmlentities($sLocation, ENT_QUOTES, 'UTF-8');
			if (!AfrEnv::getInstance()->isDevOrDebug()) {
				$sErrMsg = '';
			}
			echo "$sErrMsg<br>\nHTTP #$iCode Location: <a href='$sHLocation'>" . $sHLocation . '</a>';
		}

		if ($bExit) {
			exit;
		}
	}

	/** Permanent redirect
	 * @param string $sLoc
	 * @param bool $bExit
	 * @return void
	 * @throws AfrHttpHeaderException|AfrEnvException|AfrEventException|AfrContainerException
	 */
	public function h301Permanent(string $sLoc, bool $bExit = true): void
	{
		$this->headerRedirect3xx(301, $sLoc, false, [], $bExit);
	}

	/**
	 * @param string $sLoc
	 * @param bool $bExit
	 * @return void
	 * @throws AfrEnvException
	 * @throws AfrHttpHeaderException|AfrEventException|AfrContainerException
	 */
	public function h302FoundSameMethod(string $sLoc, bool $bExit = true): void
	{
		$this->headerRedirect3xx(302, $sLoc, false, [], $bExit);
	}

	/** Use this with forms when making browser POST requests.
	 * @param string $sLoc
	 * @param bool $bExit
	 * @return void
	 * @throws AfrEnvException
	 * @throws AfrHttpHeaderException|AfrEventException|AfrContainerException
	 */
	public function h303FormPost(string $sLoc, bool $bExit = true): void
	{
		$this->headerRedirect3xx(303, $sLoc, false, [], $bExit);
	}

	/**
	 * @return void
	 * @throws AfrEnvException
	 * @throws AfrEventException
	 * @throws AfrHttpHeaderException
	 */
	public function h304NotModified(): void
	{
		$this->setHttpResponseCode(304);
	}

	/**
	 * @param string $sEtag
	 * @param string $sGmtLastModify
	 * @return bool
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 */
	public function canServe304(string $sEtag, string $sGmtLastModify): bool
	{

		$headers = AfrRequestClass::getInstance()->getServerRequestHeaders();
		return
			!$this->isRefreshRequest() &&
			strpos($headers['If-None-Match'] ?? '', $sEtag) !== false &&
			($headers['If-Modified-Since'] ?? $sGmtLastModify) === $sGmtLastModify;
	}

	/** Temporary redirect; No not use with forms!
	 *  Ask the user if is not a GTE or HEAD request
	 * @param string $sLoc
	 * @param bool $bExit
	 * @return void
	 * @throws AfrEnvException
	 * @throws AfrHttpHeaderException|AfrEventException|AfrContainerException
	 */
	public function h307Temporary(string $sLoc, bool $bExit = true): void
	{
		$this->headerRedirect3xx(307, $sLoc, false, [], $bExit);
	}

	/**
	 * @param string $sMsg
	 * @param bool $bExit
	 * @return void
	 * @throws AfrEnvException|AfrHttpHeaderException|AfrEventException
	 */
	public function h404(string $sMsg = '<h1>Page not found! 404</h1>', bool $bExit = true): void
	{
		AfrError::error_log('404 Not Found');
		$this->setHttpResponseCode(404);
		//header('HTTP/1.1 404 Not Found', true, 404);
		echo $sMsg ? $sMsg .PHP_EOL. $_SERVER['REQUEST_URI'] : null;
		if ($bExit) {
			exit;
		}
	}

	/**
	 * @param string $msg
	 * @param bool $bExit
	 * @return void
	 * @throws AfrEnvException
	 * @throws AfrEventException
	 * @throws AfrHttpHeaderException
	 */
	public function h405MethodNotAllowed(string $msg = '<h1>405 Method Not Allowed</h1>', bool $bExit = true)
	{
		$this->setHttpResponseCode(405);
		echo $msg;
		if ($bExit) {
			die();
		}
	}

	/**
	 * @param string $msg
	 * @param bool $bExit
	 * @return void
	 * @throws AfrEnvException
	 * @throws AfrEventException
	 * @throws AfrHttpHeaderException
	 */
	public function h410PageGone(string $msg = '<h1>Page GONE! 410</h1>', bool $bExit = true)
	{
		$this->setHttpResponseCode(410);
		//header('HTTP/1.1 410 Gone', true, 410);
		echo $msg;
		if ($bExit) {
			die();
		}
	}

	public function headerRetryAfter(int $iRetryAfter = 120): string
	{
		if ($iRetryAfter === 0) {
			return '';
		}
		$sRetryAfter = $iRetryAfter > 1738148561 ? $this->getHeaderGmtDateFromTs($iRetryAfter) : $iRetryAfter;
		header('Retry-After: ' . $sRetryAfter);
		return strpos($sRetryAfter, 'GMT') ? $sRetryAfter : $sRetryAfter . ' seconds';
	}

	public function h500(bool $bExit = true, int $iRetryAfter = 120)
	{
		@header('Status: 500 Internal Server Error');
		$this->setHttpResponseCode(500);
		$this->headerRetryAfter($iRetryAfter);
		AfrError::error_log('500 Internal Server Error');
		if ($bExit) {
			die();
		}
	}

	/**
	 * @param string $str
	 * @param bool $bExit
	 * @param bool $bDevTrace
	 * @param int $iRetryAfter
	 * @return void
	 * @throws AfrContainerException
	 * @throws AfrEnvException
	 * @throws AfrEventException
	 */
	public function e500Html(string $str = '', bool $bExit = true, bool $bDevTrace = true, int $iRetryAfter = 120)
	{
		$this->h500(false, 0);
		$sRetryAfter = $this->headerRetryAfter($iRetryAfter);
		if (empty($str)) {
			echo '<title>' . ($_SERVER["SERVER_PROTOCOL"] ?? '') . " 500 Internal Server Error</title>\n<style>\n*{background:none !important;}\n</style>";
			$str = "<h1>" . ($_SERVER["SERVER_PROTOCOL"] ?? '') . " 500 Internal Server Error</h1><h3>Retry After $sRetryAfter</h3>" . $this->getHeaderGmtDateFromTs(time());
		} else {
			if (!trim($str) && substr_count($str, '<') < 1 && substr_count($str, '>') < 1) {
				$str = "<h1> $str </h1>";
			}
		}

		//echo 'THF backtrace: on Line:<strong>'.__LINE__.'</strong> in File:<strong>'.__FILE__.'</strong> and Func:<strong>'.__FUNCTION__ .'</strong><h3>Extended:</h3>';
		if ($bDevTrace && AfrEnv::getInstance()->isDevOrDebug()) {

			//TODO: proper trace from AfrError class
			ob_start();
			debug_print_backtrace();
			$trace = ob_get_contents();
			ob_end_clean();

			$repalce = AfrStr::extractBetween($trace, 'e500(', ") called at ")[0];
			$trace = str_replace($repalce, '', $trace);
			$trace = str_replace("\r", '', $trace);
			$trace = str_replace("\n", "\n\n\n", $trace);

			echo($str . '<br />T:' . date('Y-m-d H:i:s') . "<hr>\n"); //	prea(get_defined_vars());		prea(get_declared_classes());		prea(get_declared_interfaces());		prea(get_defined_functions());
			echo nl2br($trace);
		}

		if ($bExit) {
			die();
		}
	}


	/**
	 * @param string $str
	 * @param bool $bExit
	 * @param int $iRetryAfter
	 * @return void
	 * @throws AfrEnvException
	 * @throws AfrEventException
	 * @throws AfrHttpHeaderException
	 */
	public function e503ServiceTemporaryUnavailable(string $str = '', bool $bExit = true, int $iRetryAfter = 60): void
	{
		$this->setHttpResponseCode(503);
		$sRetryAfter = $this->headerRetryAfter($iRetryAfter);
		if (empty($str)) {
			echo "<h1>503 Service Temporarily Unavailable</h1><h3>Retry After $sRetryAfter</h3>" . $this->getHeaderGmtDateFromTs(time());
		} else {
			echo trim($str);
		}
		if ($bExit) {
			die;
		}

	}

	/**
	 * @param bool $bDie
	 * @return void
	 * @throws AfrContainerException
	 * @throws AfrEnvException
	 * @throws AfrEventException
	 * @throws AfrHttpHeaderException
	 * @throws AfrHttpDownloadException
	 */
	public function png404(bool $bDie): void
	{
		AfrEvent::dispatchEvent();
		$this->setHttpResponseCode(404);
		AfrHttpDownload::getInstance()->httpDownloadFile(
			self::NoContentDisposition,
			__DIR__ . '/404.png',
			'',
			$bDie
		);
		/*$this->headerNoCache();
		$this->headerContentTypeMime('404.png');
		echo file_get_contents(__DIR__ . '/404.png');
		if ($bDie) {
			die();
		}*/
	}


	/**
	 * @param string $sFullFilePath
	 * @param int $iKnown
	 * @return void
	 */
	public function headerContentLength(string $sFullFilePath, int $iKnown = -1): void
	{
		$iSize = $iKnown > -1 ? $iKnown : filesize($sFullFilePath);
		if ($iSize > 0) {
			header('Content-Length: ' . $iSize);
		}
	}

	/**
	 * @param int $iTs
	 * @return string
	 */
	public function getHeaderGmtDateFromTs(int $iTs): string
	{
		return gmdate('D, d M Y H:i:s', $iTs) . ' GMT';
	}

	/**
	 * @param string $sFullFilePath
	 * @param int $iKnown
	 * @param string $sGmtDate
	 * @return void
	 */
	public function headerLastModified(string $sFullFilePath, int $iKnown = -1, string $sGmtDate = ''): void
	{
		if (!$sGmtDate) {
			$this->getHeaderGmtDateFromTs($iKnown !== -1 ? $iKnown : (int)filemtime($sFullFilePath));
		}
		header('Last-Modified: ' . $sGmtDate);
	}

	/**
	 * @param int $iTimestamp
	 * @return void
	 */
	public function headerExpires(int $iTimestamp): void
	{
		$iTimestamp > 0 ?
			header('Expires: ' . $this->getHeaderGmtDateFromTs($iTimestamp)) :
			header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
	}

	/**
	 * 0: No Content-Disposition
	 * 1: Content-Disposition: attachment; If you want to encourage the client to download it instead of following the default behaviour
	 * 2: Content-Disposition: attachment + application/force-download
	 * 3: Content-Disposition: inline: With inline, the browser will try to open the file within the browser
	 * @param int $iDownloadMode
	 * @param string $sSaveFileName
	 * @return void
	 */
	public function headerContentDisposition(int $iDownloadMode, string $sSaveFileName = ''): void
	{
		if ($iDownloadMode === self::NoContentDisposition) {
			return;
		}
		if (strlen($sSaveFileName)) {
			$sSaveFileName = basename($sSaveFileName);//get original filename
		}
		$sFilename = strlen($sSaveFileName) ? '; filename=' . urlencode($sSaveFileName) : '';

		if ($iDownloadMode === self::ContentDispositionInline) {
			header('Content-Disposition: inline' . $sFilename);
		}

		if ($iDownloadMode === self::ContentDispositionFileTransfer || $iDownloadMode === self::ContentDispositionFileTransferForceDownload) {
			header('Content-Description: File Transfer');
			header('Content-Disposition: attachment' . $sFilename);
		}
		if ($iDownloadMode === self::ContentDispositionFileTransferForceDownload) {
			header('Content-Type: application/force-download');
			header('Content-Disposition: attachment' . $sFilename); //TODO: test filename
		}
	}

	/**
	 * @param string $sFileNameOrPath
	 * @param string $sCharset
	 * @param bool $bAutodetectEncoding
	 * @param array $aCharsetExtensions
	 * @return void
	 * @throws AfrEventException
	 * @throws AfrContainerException
	 */
	public function headerContentTypeMime(
		string $sFileNameOrPath,
		string $sCharset = '',
		bool   $bAutodetectEncoding = false,
		array  $aCharsetExtensions = ['html', 'js', 'css', 'csv', 'txt', 'php']
	): void
	{

		$sContentType = AfrFileMimeClass::getInstance()->getMimeFromFileName($sFileNameOrPath);
		$aInfo = pathinfo($sFileNameOrPath);
		if (!empty($aInfo['extension']) && in_array(strtolower($aInfo['extension']), $aCharsetExtensions)) {
			if ($sCharset) {
				$sContentType .= '; charset=' . $sCharset;
			} elseif ($bAutodetectEncoding) {
				$handle = fopen($sFileNameOrPath, 'r');
				if ($handle) {
					$sBuffer = fgets($handle, 1024 * 8);
					fclose($handle);
					$sContentType .= '; charset=' . mb_detect_encoding($sBuffer, mb_list_encodings());
				}
			} else {
				$sContentType .= '; charset=utf-8'; //TODO in loc de charset sa pun binary sau sa verific charset pe server?
			}
		}
		header('Content-Type: ' . $sContentType);
	}


	/**
	 * The browser requested a clean page by pressing F5 or CTRL+R
	 * @return bool
	 */
	public function isRefreshRequest(): bool
	{
		return (
			isset($_SERVER['HTTP_PRAGMA']) && $_SERVER['HTTP_PRAGMA'] == 'no-cache' ||
			isset($_SERVER['HTTP_CACHE_CONTROL']) && $_SERVER['HTTP_CACHE_CONTROL'] == 'no-cache' ||
			isset($_SERVER['HTTP_CACHE_CONTROL']) && $_SERVER['HTTP_CACHE_CONTROL'] == 'max-age=0'
		);
	}

	/**
	 * @param string $sFullFilePath
	 * @param int $iFileSize
	 * @param int $iFileMtime
	 * @param string $sA
	 * @param string $sB
	 * @param bool $bSendHeader
	 * @return string
	 */
	public function headerETag(
		string $sFullFilePath,
		int    $iFileSize = -1,
		int    $iFileMtime = -1,
		string $sA = '-th-',
		string $sB = '-or-',
		bool   $bSendHeader = true
	): string
	{
		$iFileSize = $iFileSize === -1 ? filesize($sFullFilePath) : $iFileSize;
		$iFileMtime = $iFileMtime === -1 ? filemtime($sFullFilePath) : $iFileMtime;
		$eTag =
			dechex(crc32((string)$iFileSize)) . $sA .
			dechex(crc32($sFullFilePath)) . $sB .
			dechex(crc32((string)$iFileMtime));
		if ($bSendHeader) {
			header('ETag: "' . $eTag . '"');
		}
		return $eTag;
	}

	/**
	 * @param int $iExpected
	 * @return bool
	 */
	public function isHttpResponseCode(int $iExpected = 200): bool
	{
		return http_response_code() === $iExpected;
	}

	/**
	 * @return void
	 */
	public function headerNoCache(): void
	{
		header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0'); // HTTP/1.1
		header('Pragma: no-cache');
		$this->headerExpires(0);
	}

	/**
	 * @param int $iSecondsToCache
	 * @return void
	 */
	public function headerDoCache(int $iSecondsToCache = 2592000): void
	{
		header('Pragma: cache');
		header('Cache-Control: max-age=' . $iSecondsToCache);
		$this->getHeaderGmtDateFromTs(time() + $iSecondsToCache);
	}

	/**
	 * Sets the Cache-Control header for immutable caching.
	 *
	 * @param int $iSecondsToCache Default is 10 years
	 * @return void
	 */
	public function headerCacheImmutable(int $iSecondsToCache = 315360000): void
	{
		header('Cache-Control: public, max-age=' . $iSecondsToCache . ', immutable');
	}


	public function httpHeaderCacheControlAndExpire(
		int  &$iCacheExpire = 2678400,
		bool $bImmutable = true,
		bool $bMustRevalidate = true
	): void
	{
		//https://www.keycdn.com/blog/cache-control-immutable
		if ($iCacheExpire < 1) {
			$iCacheExpire = 0;
			$sCacheControl = 'private';
		} else {
			$sCacheControl = 'public';
			$iCacheExpire = max($iCacheExpire, 1);//at least 1 min
		}

		if (!$bImmutable && $this->isRefreshRequest()) { //the browser requested a clean page
			$sCacheControl = 'private';
			$iCacheExpire = 0;
		}

		header(
			'Cache-Control: ' .
			$sCacheControl .
			', max-age=' . $iCacheExpire .
			($bMustRevalidate ? ', must-revalidate' : '') .
			($bImmutable ? ', immutable' : '')
		);

		$fProtocol = AfrCliHttpDetect::getHttpProtocolVersion();
		if ($fProtocol > 0 && $fProtocol < 2) {
			header('Pragma: ' . ($sCacheControl == 'private' ? 'no-cache' : 'cache'));
		}
		$this->headerExpires(time() + $iCacheExpire);
	}

}