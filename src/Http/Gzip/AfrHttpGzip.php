<?php

namespace Autoframe\Core\Http\Gzip;

use Autoframe\Core\CliTools\AfrCliHttpDetect;
use Autoframe\Core\Http\Gzip\Exception\AfrHttpGzipException;

class AfrHttpGzip
{
	/**
	 * Output print as gzip.
	 * @param string $sData
	 * @param int $iLevel
	 * @param int $iEncoding
	 * @param bool $bExit
	 * @return void
	 * @throws AfrHttpGzipException
	 */
	public static function outputPrintAsGzip(
		string $sData,
		int    $iLevel = -1,
		int    $iEncoding = ZLIB_ENCODING_GZIP,
		bool   $bExit = true
	): void
	{
		if (substr_count($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip')) {
			if (!is_callable('gzencode')) {
				throw new AfrHttpGzipException(
					'gzencode() not callable! \'ext-zlib\' is missing from environment'
				);
			}
			$filename = $line = '';
			if (headers_sent()) {
				throw new AfrHttpGzipException(
					"Gzip failed because headers sent in $filename at $line"
				);
			}
			$sGz = gzencode($sData, $iLevel, $iEncoding);
			if ($sGz === false) {
				throw new AfrHttpGzipException('Gzip encode failed!');
			} else {
				header('Content-Encoding: gzip');
				echo $sGz;
			}
		} else {
			echo $sData;
		}

		if ($bExit) {
			exit;
		}
	}

	/**
	 * Run early on, and not combine with 304 cache requests!
	 * Check for session start...
	 * @return bool
	 * @throws AfrHttpGzipException
	 */
	public static function ob_gzhandler(): bool
	{

		if (
			!AfrCliHttpDetect::isCli() &&
			headers_sent() === false &&
			substr_count($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip')
		) {
			if (!is_callable('ob_gzhandler')) {
				throw new AfrHttpGzipException(
					'ob_gzhandler() not callable!'
				);
			}
			header('Content-Encoding: gzip');
			return ob_start('ob_gzhandler');         // TODO: test!
		}
		return false;
	}

}
