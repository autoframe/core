<?php

namespace Autoframe\Core\Http\CurlSimple;

use Autoframe\Core\Http\CurlSimple\Exception\AfrHttpCurlSimpleException;
use Autoframe\Core\Http\Header\Formatters\Exception\AfrHttpHeaderFormattersException;

use Autoframe\Core\Http\Header\Formatters\AfrHttpHeaderFormatters;

trait AfrHttpCurlSimpleMethods
{
    //use AfrHttpHeaderFormatters;

    /**
     * @var array|string[]
     */
    private array $aCurlSimpleDefaultHeaders = [ //TODO: refactor to solid
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.5',
        'Cache-Control: no-cache',
        'Pragma: no-cache',
        'Cookie: default=D_cookie',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:106.0) Gecko/20100101 Firefox/106.0',
//        'Connection: keep-alive',
    ];


    /**
     * https://www.php.net/manual/en/function.curl-setopt.php
     * @var array
     */
    private array $aCurlSimpleDefaultOptions = [
        CURLOPT_RETURNTRANSFER => 1,         // return web page
        CURLOPT_HEADER => 1,        //  return headers

        CURLOPT_FOLLOWLOCATION => 0,         // follow redirects; -1 infinite; 0 or int
        CURLOPT_MAXREDIRS => 10,           // stop after 10 redirects
        CURLOPT_AUTOREFERER => true,         // set referer on redirect

        //    CURLOPT_ENCODING       => "",           // handle all encodings
        //     CURLOPT_USERAGENT      => "spider",     // who am i
        CURLOPT_CONNECTTIMEOUT => 5,          // The number of seconds to wait while trying to connect. Use 0 to wait indefinitely.
        CURLOPT_CONNECTTIMEOUT_MS => 5 * 1000,    // The number of milliseconds to wait while trying to connect. Use 0 to wait indefinitely. If libcurl is built to use the standard system name resolver, that portion of the connect will still use full-second resolution for timeouts with a minimum timeout allowed of one second.

        CURLOPT_TIMEOUT => 120,        // timeout on response
        CURLOPT_TIMEOUT_MS => 120 * 1000,        // timeout on response

        //    CURLOPT_POST            => 1,            // i am sending post data
        //    CURLOPT_POSTFIELDS     => '',    // this are my post vars

        CURLOPT_SSL_VERIFYHOST => 0,        // don't verify ssl
        CURLOPT_SSL_VERIFYPEER => 0,        //
        CURLOPT_VERBOSE => 1,         //Writes output to STDERR, or the file specified using CURLOPT_STDERR.
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:106.0) Gecko/20100101 Firefox/106.0',
    ];


    /**
     * @param $mixed
     * @return void
     */
    private function oldPrea($mixed): void
    {
        echo '<pre>' . print_r($this->oldH($mixed), true) . '</pre>';
    }

    /**
     * @param $str
     * @param string $enc
     * @return mixed|string
     */
    private function oldH($str, string $enc = 'UTF-8')
    {
        if (is_array($str)) {
            foreach ($str as $key => &$val) {
                $val = $this->oldH($val);
            }
        } elseif (is_string($str)) {
            $str = htmlentities($str, ENT_QUOTES, $enc);
        }
        return $str;
    }



    /**
     * @param array $aSetNew
     * @return array
     */
    private function getCurlSimpleDefaultHeaders(array $aSetNew = []): array
    {
        if ($aSetNew) {
            $this->aCurlSimpleDefaultHeaders = $aSetNew;
        }
        return $this->aCurlSimpleDefaultHeaders;
    }

    /**
     * @param array $aHeaders
     * @return array
     * @throws AfrHttpHeaderFormattersException
     */
    private function prepareCurlOrganizedHeaders(array $aHeaders): array
    {
		$oHeaderF = AfrHttpHeaderFormatters::getInstance();
        $aOrganizedHeaders = $oHeaderF->formatMixedHeadersInputToKeyArray($this->getCurlSimpleDefaultHeaders());
        foreach ($oHeaderF->formatMixedHeadersInputToKeyArray($aHeaders) as $sHeaderKey => $sHeaderVal) {
            if (strlen($sHeaderVal)) {
                $aOrganizedHeaders[$sHeaderKey] = $sHeaderVal;
            } elseif (isset($aOrganizedHeaders[$sHeaderKey])) {
                unset($aOrganizedHeaders[$sHeaderKey]);
            }
        }
        return $aOrganizedHeaders;
    }



    /**
     * @param array $aSetNew with [ CURLOPT_% => val ]
     * @return array having [ CURLOPT_% => val ] framework values
     */
    private function getSetCurlSimpleDefaultOptions(array $aSetNew = []): array
    {
        if ($aSetNew) {
            $this->aCurlSimpleDefaultOptions = $aSetNew;
        }
        return $this->aCurlSimpleDefaultOptions;
    }


    /**
     * @param array $aCurlSetOpt with [ CURLOPT_% => val ]
     * @return array having [ CURLOPT_% => val ] custom + framework values
     */
    private function prepareCurlOrganizedOptions(array $aCurlSetOpt): array
    {
        $aOrganizedOpt = $this->getSetCurlSimpleDefaultOptions();
        foreach ($aCurlSetOpt as $iHeaderKey => $mHeaderVal) {
            if (isset($aOrganizedOpt[$iHeaderKey]) && (is_null($mHeaderVal) || $mHeaderVal === '')) {
                unset($aOrganizedOpt[$iHeaderKey]);
                continue;
            }
            if (is_int($iHeaderKey)) {
                $aOrganizedOpt[$iHeaderKey] = $mHeaderVal;
            }
        }
        return $aOrganizedOpt;
    }

    /**
     * @param array $aCurlSetOpt
     * @param array $aOrganizedHeaders
     * @param string $sMethod
     * @return array
     * @throws AfrHttpHeaderFormattersException
     */
    private function prepareCurlOrganizedOptionsAndHeaders(
        array  $aCurlSetOpt,
        array  $aOrganizedHeaders,
        string $sMethod,
        string $sUrl
    ): array
    {
        $aCurlSetOpt[CURLOPT_URL] = $sUrl;
        if ($sMethod === 'HEAD') {

            $aCurlSetOpt[CURLOPT_NOBODY] = 1;
        }

        if (!empty($aOrganizedHeaders['Referer'])) {
            $aCurlSetOpt[CURLOPT_REFERER] = $aOrganizedHeaders['Referer'];
            unset($aOrganizedHeaders['Referer']);
        }

        if (!empty($aOrganizedHeaders['User-Agent'])) {
            $aCurlSetOpt[CURLOPT_USERAGENT] = $aOrganizedHeaders['User-Agent'];
            unset($aOrganizedHeaders['User-Agent']);
        }

        if (!empty($aOrganizedHeaders['Cookie'])) {
            $aCurlSetOpt[CURLOPT_COOKIE] = $aOrganizedHeaders['Cookie'];
            unset($aOrganizedHeaders['Cookie']);
        }

        if (count($aOrganizedHeaders)) {
            $aCurlSetOpt[CURLOPT_HTTPHEADER] = AfrHttpHeaderFormatters::getInstance()->formatForCurlOptHttpHeader(
				$aOrganizedHeaders
            );
        }

        return $aCurlSetOpt;
    }

    /**
     * Setting to -1 allows inifinite redirects, and 0 refuses all redirects.
     * @param array $aCurlSetOpt
     * @param int $iMaxRedirects
     * @return void
     */
    private function prepareCurlOrganizedOptionsSetMaxRedirects(array &$aCurlSetOpt, int $iMaxRedirects): void
    {
        $aCurlSetOpt[CURLOPT_FOLLOWLOCATION] = $iMaxRedirects ? 1 : 0;
        $aCurlSetOpt[CURLOPT_AUTOREFERER] = $iMaxRedirects ? 1 : 0;
        $aCurlSetOpt[CURLOPT_MAXREDIRS] = max(-1, $iMaxRedirects); // -1; 0; int
    }

    /** Set body data for POST request
     * @param array $aCurlSetOpt [ CURLOPT_% => val ] for function curl_setopt_array()
     * @param array $aPostFields [ key=> val ]
     * @param string $sMethod mandatory POST
     * @return void
     */
    private function prepareCurlOrganizedOptionsSetPostData(array &$aCurlSetOpt, array $aPostFields, string $sMethod): void
    {
        if ($sMethod == 'POST') {
            $aCurlSetOpt[CURLOPT_POST] = 1;
            $aCurlSetOpt[CURLOPT_POSTFIELDS] = http_build_query($aPostFields);
        }
    }

    /**
     * @param array $aCurlSetOpt [ CURLOPT_% => val ] for function curl_setopt_array()
     * @param bool $bReturnConnectionHeaders set [ CURLOPT_HEADER = 1]
     * @return void
     */
    private function prepareCurlOrganizedOptionsSetReturnConnectionHeaders(
        array &$aCurlSetOpt,
        bool  $bReturnConnectionHeaders
    )
    {
        $aCurlSetOpt[CURLOPT_HEADER] = $bReturnConnectionHeaders ? 1 : 0;
    }

    /**
     * @param array $aCurlSetOpt [ CURLOPT_% => val ] for function curl_setopt_array()
     * @param array $aUserPwd [ 0=> username, 1=> pwd ]
     * @return void
     * @throws AfrHttpCurlSimpleException
     */
    private function prepareCurlOrganizedOptionsSetUserPwd(
        array &$aCurlSetOpt,
        array $aUserPwd
    )
    {
        if (count($aUserPwd) === 2) {
            $sUP = '';
            foreach ($aUserPwd as $sData) {
                if (!is_string($sData)) {
                    throw new AfrHttpCurlSimpleException('Corrupted user/pwd array provided for curl');
                }
                $sUP .= ($sUP ? ':' : '') . urlencode($sData);
            }

            $aCurlSetOpt[CURLOPT_USERPWD] = $sUP;
        } elseif (!empty($aUserPwd)) {
            throw new AfrHttpCurlSimpleException('Invalid user/pwd array provided for curl');
        }

    }



    /**
     * @param array $aOrganizedCurlOptions [ CURLOPT_% => val ] for function curl_setopt_array()
     * @param bool $bVarDump inline var_dump
     * @return array having replaced afferent indexes with string CURLOPT_% codes
     */
    private function debugTranslateOrganizedCurlOptions(array $aOrganizedCurlOptions, bool $bVarDump = false): array
    {
        $aAlias = $this->debugGetCurlConstantAliasCodes();
        $aDebugOptions = [];
        foreach ($aOrganizedCurlOptions as $sK => $mV) {
            $aDebugOptions[$aAlias[$sK] ?? $sK] = $mV;
        }
        if ($bVarDump) {
            var_dump($aDebugOptions);
        }
        return $aDebugOptions;
    }


    /**
     * @return array having constants with CURLOPT_% codes
     */
    private function debugGetCurlConstantAliasCodes(): array
    {
        $aAlias = [];
        $sPrefix = 'CURLOPT_';
        $iPrefix = strlen($sPrefix);
        foreach (get_defined_constants() as $sConstant => $mVal) {
            if (substr($sConstant, 0, $iPrefix) === $sPrefix) {
                if(is_int($mVal) || is_string($mVal)){
                    $aAlias[$mVal] = $sConstant;
                }
            }
        }
        return $aAlias;
    }


}