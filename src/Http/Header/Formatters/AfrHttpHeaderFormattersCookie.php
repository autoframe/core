<?php
declare(strict_types=1);

namespace Autoframe\Core\Http\Header\Formatters;

use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;
use Autoframe\Core\Http\Header\Formatters\Exception\AfrHttpHeaderFormattersException;

class AfrHttpHeaderFormattersCookie extends AfrSingletonAbstractClass
{

	protected bool $bFormatEnforcePhpCookieOverRFC6265 = true;
	protected bool $bCookieLineEncodeSizeOptimize = true;

	/**
	 * @param null|bool $bSet
	 * @return bool
	 */
	public function xetFormatCookieHeaderEnforcePhpOverRFC6265(bool $bSet = null): bool
	{
		if ($bSet !== null) {
			$this->bFormatEnforcePhpCookieOverRFC6265 = $bSet;
		}
		return $this->bFormatEnforcePhpCookieOverRFC6265;
	}


	/**
	 * @param null|bool $bSet
	 * @return bool
	 */
	public function xetFormatCookieLineEncodeSizeOptimize(bool $bSet = null): bool
	{
		if ($bSet !== null) {
			$this->bCookieLineEncodeSizeOptimize = $bSet;
		}
		return $this->bCookieLineEncodeSizeOptimize;
	}


	/**
	 * @param $saData
	 * @param bool $bCookiePrefix
	 * @param bool $bSetCookie
	 * @return string
	 * @throws AfrHttpHeaderFormattersException
	 */
	public function formatIntoCookieHeaderLine(
		$saData,
		bool $bCookiePrefix,
		bool $bSetCookie = false
	): string
	{
		if (is_null($saData)) {
			return '';
		}
		$sDataType = gettype($saData);
		if (!in_array($sDataType, ['string', 'array'])) {
			throw new AfrHttpHeaderFormattersException('Invalid cookie data supplied for $saData: ' . $sDataType);
		}

		$sOut = '';
		if (is_string($saData)) {
			if (strlen($saData) < 3) {
				return '';
			}
			if (strpos($saData, '=') === false) {
				throw new AfrHttpHeaderFormattersException(
					'Invalid cookie data structure for $saData: ' . $saData
				);
			}
			$saData = $this->formatCookieLineIntoAssociativeArray($saData, true);
		}
		if (is_array($saData)) {
			$sCookieGlue = '; ';
			foreach ($saData as $mKey => $mVal) {
				if (!is_string($mVal)) {
					throw new AfrHttpHeaderFormattersException(
						'Invalid cookie array data supplied for $saData ' . print_r($saData, true)
					);
				}
				$mVal = rtrim($mVal, $sCookieGlue);

				if (is_string($mKey)) {
					$sFinalKey = $mKey;
					$sFinalVal = $mVal;
				} else {
					if (strpos($mVal, '=') === false) {
						throw new AfrHttpHeaderFormattersException(
							'Invalid cookie array data structure for $saData ' . print_r($saData, true)
						);
					}
					$mVal = explode('=', $mVal);
					$sFinalKey = urldecode($mVal[0]);
					$sFinalVal = urldecode($mVal[1]);
				}
				$sOut .=
					$this->formatCookieNameForHeader($sFinalKey, true) .
					'=' .
					$this->formatCookieValueForHeader($sFinalVal) . $sCookieGlue;
			}
			if ($sOut) {
				$sOut = substr($sOut, 0, -strlen($sCookieGlue));
			}

		}
		$sPrefix = (!empty($sOut) && $bCookiePrefix ? ($bSetCookie ? 'Set-Cookie: ' : 'Cookie: ') : '');
		return $sPrefix . $sOut;
	}

	/**
	 * @param string $sCookies
	 * @param bool $bStrictError
	 * @return array
	 * @throws AfrHttpHeaderFormattersException
	 */
	public function formatCookieLineIntoAssociativeArray(
		string $sCookies,
		bool   $bStrictError = false
	): array
	{
		$aOut = [];
		foreach ($this->parseHeaderCookieLine($sCookies, $bStrictError) as $aData) {
			$aOut[$aData[0]] = $aData[1];
		}
		return $aOut;
	}

	/**
	 * @param string $sCookieLine
	 * @param bool $bStrictError
	 * @return array
	 * @throws AfrHttpHeaderFormattersException
	 */
	public function parseHeaderCookieLine(string $sCookieLine, bool $bStrictError = false): array
	{
		$aOut = [];
		foreach (['Cookie: ', 'Set-Cookie: '] as $sHeaderPrefix) {
			$iHeaderPrefix = strlen($sHeaderPrefix);
			if (substr($sCookieLine, 0, $iHeaderPrefix) === $sHeaderPrefix) {
				$sCookieLine = substr($sCookieLine, $iHeaderPrefix);
			}
		}

		$sCookieLine = trim($sCookieLine, '; ');
		if ($bStrictError) {
			if (strpos($sCookieLine, '=') === false || strlen($sCookieLine) > 0 && strlen($sCookieLine) < 3) {
				throw new AfrHttpHeaderFormattersException(
					'Invalid cookie data structure for $sCookies: ' . func_get_arg(0)
				);
			} elseif (strpos($sCookieLine, "\r") !== false || strpos($sCookieLine, "\n") !== false) {
				throw new AfrHttpHeaderFormattersException(
					'Invalid cookie data structure because new line control characters was found!'
				);
			}

		}

		foreach (explode(';', $sCookieLine) as $sCookieKeyAndVal) {
			$sCookieKeyAndVal = trim($sCookieKeyAndVal);
			if (!$sCookieKeyAndVal) {
				continue;
			}
			$aCookiePair = explode('=', $sCookieKeyAndVal);
			if (count($aCookiePair) < 2 && $bStrictError) {
				throw new AfrHttpHeaderFormattersException(
					'Invalid cookie data pair structure for $sCookies: ' . func_get_arg(0)
				);
			}
			$sKey = urldecode(trim(substr($sCookieKeyAndVal, 0, strlen($aCookiePair[0]))));
			$sKey = $this->formatCookieNameForHeader($sKey, false);
			$sVal = urldecode(trim(substr($sCookieKeyAndVal, strlen($aCookiePair[0]) + 1)));
			$aOut[] = [$sKey, $sVal];
		}
		return $aOut;
	}

	/**
	 * @param string $sHeaderDirective
	 * @return array
	 * @throws AfrHttpHeaderFormattersException
	 */
	public function parseHeaderLineSetCookieInfo(string $sHeaderDirective): array
	{
		if (substr($sHeaderDirective, 0, 12) !== 'Set-Cookie: ') {
			return [];
		}
		$aParsed = $this->parseHeaderCookieLine($sHeaderDirective, false);
		if (!isset($aParsed[0][0]) || !isset($aParsed[0][1])) {
			return [];
		}
		$aOut = [
			'sCookieName' => $aParsed[0][0],
			'sCookieValue' => $aParsed[0][1],
			'iExpire' => strlen($aParsed[0][1]) < 1 ? 0 : (time() + 3600 * 24), //session Cookie
		];

		foreach ($aParsed as $aParsedData) {
			if (!isset($aParsedData[0])) {
				continue;
			}
			if ($aParsedData[0] === 'expires') {
				$aOut['iExpire'] = strtotime($aParsedData[1]);
			} elseif ($aParsedData[0] === 'path' && !empty($aParsedData[1])) {
				$aOut['sPath'] = $aParsedData[1];
			} elseif ($aParsedData[0] === 'domain' && !empty($aParsedData[1])) {
				$aOut['sDomain'] = $aParsedData[1];
			} elseif ($aParsedData[0] === 'SameSite' && !empty($aParsedData[1])) {
				$aOut['sSameSite'] = $aParsedData[1];
			} elseif ($aParsedData[0] === 'secure') {
				$aOut['bSecure'] = true;
			} elseif ($aParsedData[0] === 'HttpOnly') {
				$aOut['bHttpOnly'] = true;
			}
		}

		return $aOut;
	}

	/**
	 * @param string $sKey
	 * @param bool $bUrlEncode
	 * @return string
	 */
	public function formatCookieNameForHeader(
		string $sKey,
		bool   $bUrlEncode = true
	): string
	{
		$aReplaceInName = [' '];
		$aReplaceInto = ['_'];
		if ($this->bFormatEnforcePhpCookieOverRFC6265) {
			$aReplaceInName[] = '.';
			$aReplaceInto[] = '_';
		}
		$sKey = str_replace($aReplaceInName, $aReplaceInto, $sKey);
		return $bUrlEncode ? urlencode($sKey) : $sKey;
	}

	/**
	 * @param string $sVal
	 * @return string
	 */
	public function formatCookieValueForHeader(string $sVal): string
	{
		$sVal = urlencode($sVal);
		if ($this->bCookieLineEncodeSizeOptimize) {
			$aReplace = $aSearch = ['=', ':', '{', '}', '"'];
			foreach ($aSearch as &$sEnc) {
				$sEnc = urlencode($sEnc);
			}
			$sVal = str_replace($aSearch, $aReplace, $sVal);
		}
		return $sVal;
	}


}