<?php
declare(strict_types=1);

namespace Autoframe\Core\Http\CurlSimple;

use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;
use Autoframe\Core\Event\Exception\AfrEventException;
use Autoframe\Core\Http\CurlSimple\Exception\AfrHttpCurlSimpleException;
use Autoframe\Core\Http\Header\Formatters\Exception\AfrHttpHeaderFormattersException;
use Autoframe\Core\Http\Header\Formatters\AfrHttpHeaderFormatters;
use Autoframe\Core\Http\Header\Formatters\AfrHttpHeaderFormattersCookie;
use Autoframe\Core\Http\Url\AfrUrlUtils;

//TODO: refactor to solid
class AfrHttpCurlSimple  extends AfrSingletonAbstractClass
{
    use AfrHttpCurlSimpleMethods;

	/**
     * Do http stream request.
	 * @param string $sUrl
	 * @param string $sMethod
	 * @param array $aPostData
	 * @param string|array|null $mOptionalHeaders
	 * @param float $fTimeoutSeconds
	 * @param bool $bIgnoreErrors
	 * @param int $iMaxRedirects
	 * @param bool $bDebugList
	 * @return array
	 * @throws AfrHttpCurlSimpleException
	 * @throws AfrHttpHeaderFormattersException
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 */
    public function doHttpStreamRequest(
        string $sUrl,
        string $sMethod = 'POST',
        array  $aPostData = [],
               $mOptionalHeaders = NULL,
        float  $fTimeoutSeconds = 60,
        bool   $bIgnoreErrors = true,
        int    $iMaxRedirects = 0,
        bool   $bDebugList = false
    ): array
    {

        $aOptions = [
            'http' => [
                'method' => $sMethod,
                'ignore_errors' => $bIgnoreErrors,
                'follow_location' => $iMaxRedirects ? 1 : 0,
                'max_redirects' => $iMaxRedirects,
                'timeout' => $fTimeoutSeconds,
            ]];

        $aOptionalHeaders = AfrHttpHeaderFormatters::getInstance()->formatMixedHeadersInputToKeyArray($mOptionalHeaders);
        if (count($aOptionalHeaders)) {
            $aOptions['http']['header'] = AfrHttpHeaderFormatters::getInstance()->formatFlattenHeaderArray($aOptionalHeaders);
        }

        if (count($aPostData)) {
            $aOptions['http']['content'] = http_build_query($aPostData);
        }

        $ctx = stream_context_create($aOptions);
        if ($bDebugList) {
            $this->oldPrea($aOptions);
            $this->oldPrea($ctx);
        }
        $fp = @fopen($sUrl, 'rb', false, $ctx);
        if (!$fp) {
            throw new AfrHttpCurlSimpleException('Unable to open stream to: ' . $sUrl);
        }
        $response = stream_get_contents($fp);
        if ($response === false) {
            throw new AfrHttpCurlSimpleException('Unable to get response stream from: ' . $sUrl);
        }
        return ['header' => $http_response_header, 'content' => $response];
    }


	/**
	 * @param string $sUrl full url https://...
	 * @param string $sMethod GET, POST, HEAD...
	 * @param array $aPostFields [ k=>v ]
	 * @param array $aHeaders [ 0=>'Afr: curl' | k=>v : 'Accept-Language' => 'en'   ]
	 * @param array $aSetOpt [ CURLOPT_TIMEOUT => 5 , ]
	 * @param array|string $saCookies [ k=>v, ] | lang=en; ts=1669731924
	 * @param string $sRef full url https://...
	 * @param int $iMaxRedirects zero, +int or -1 = infinite
	 * @param array $aUserPwd [0=> user, 1=> pwd]
	 * @return array used with: $aResult = $this->makeSimpleCurlRequest($aOrganizedCurlOptions)
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 * @throws AfrHttpCurlSimpleException
	 * @throws AfrHttpHeaderFormattersException
	 */
    protected function prepareSimpleCurlHandle(
        string $sUrl,
        string $sMethod = 'GET', // 'GET','POST','HEAD'...
        array  $aPostFields = [],
        array  $aHeaders = [],
        array  $aSetOpt = [],
               $saCookies = '', // header string || array
        string $sRef = '',
        int    $iMaxRedirects = 0,
        array  $aUserPwd = []
    ): array
    {
        if (count($aPostFields) && $sMethod != 'POST') {
            $sMethod = 'POST';
        }

        $aOrganizedHeaders = $this->prepareCurlOrganizedHeaders($aHeaders);

        if ($sRef) {
            $aOrganizedHeaders['Referer'] = $sRef;
        }

        if ($saCookies) {
            $sCookieHeaderLine = AfrHttpHeaderFormattersCookie::getInstance()->formatIntoCookieHeaderLine($saCookies, false, false);
            if (!empty($aOrganizedHeaders['Cookie'])) {
                $sCookieHeaderLine = rtrim($aOrganizedHeaders['Cookie'], '; ') . '; ' . $sCookieHeaderLine;
                $sCookieHeaderLine = AfrHttpHeaderFormattersCookie::getInstance()->formatIntoCookieHeaderLine($sCookieHeaderLine, false, false);
            }
            $aOrganizedHeaders['Cookie'] = $sCookieHeaderLine;
        }

        if (!$aOrganizedHeaders['Cookie']) {
            unset($aOrganizedHeaders['Cookie']);
        }

        $aOrganizedCurlOptions = $this->prepareCurlOrganizedOptionsAndHeaders(
            $this->prepareCurlOrganizedOptions($aSetOpt),
            $aOrganizedHeaders,
            $sMethod,
            $sUrl
        );
        $this->prepareCurlOrganizedOptionsSetMaxRedirects($aOrganizedCurlOptions, $iMaxRedirects);
        $this->prepareCurlOrganizedOptionsSetPostData($aOrganizedCurlOptions, $aPostFields, $sMethod);
        $this->prepareCurlOrganizedOptionsSetReturnConnectionHeaders($aOrganizedCurlOptions, true);
        $this->prepareCurlOrganizedOptionsSetUserPwd($aOrganizedCurlOptions, $aUserPwd);
        return $aOrganizedCurlOptions;

    }

    /**
     * @param array $aOrganizedCurlOptions [ CURLOPT_% => val ] for function curl_setopt_array()
     * @param bool $bInlineDebug
     * @return array
     * @throws AfrHttpHeaderFormattersException
     */
    protected function makeSimpleCurlRequest(array $aOrganizedCurlOptions, bool $bInlineDebug = false): array
    {
        if ($bInlineDebug) {
            $this->debugTranslateOrganizedCurlOptions($aOrganizedCurlOptions, $bInlineDebug);
        }
        $ch = curl_init();
        curl_setopt_array($ch, $aOrganizedCurlOptions);

        $response = curl_exec($ch);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        $aResult = [
            'sHeader' => substr($response, 0, $header_size),
            'sBody' => substr($response, $header_size),
            'sCurlError' => curl_error($ch),
            'mHttpCode' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
            'sLastUrl' => curl_getinfo($ch, CURLINFO_EFFECTIVE_URL),
            'aOrganizedCurlOptions' => $aOrganizedCurlOptions,
        ];
        curl_close($ch);
        $this->updateSimpleCurlHandleAfterRequest($aOrganizedCurlOptions, $aResult);
        return $aResult;
    }

	/**
	 * @param array $aOrganizedCurlOptions
	 * @param array $result
	 * @return void
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 * @throws AfrHttpHeaderFormattersException
	 */
    private function updateSimpleCurlHandleAfterRequest(array $aOrganizedCurlOptions, array &$result)
    {
        $aSetCookie = [];
        $sLocation = '';

        if (!empty($result['sHeader']) && is_string($result['sHeader'])) {
            foreach (AfrHttpHeaderFormatters::getInstance()->formatHttpRawHeadersToArr($result['sHeader']) as $sHeaderDirective) {
                if (substr($sHeaderDirective, 0, 12) === 'Set-Cookie: ') {
                    $aSetCookie[] = substr($sHeaderDirective, 12);
                    $this->updateCookiesInOrganizedCurlOptions($aOrganizedCurlOptions, $sHeaderDirective);
                } elseif (substr($sHeaderDirective, 0, 9) === 'Location:') {
                    $sLocation = trim(substr($sHeaderDirective, 9));
                    if (substr($sLocation, 0, 1) === '/') {
                        $sLocation = AfrUrlUtils::getInstance()->getUrlSchemeHostUpToPath(
							$aOrganizedCurlOptions[CURLOPT_URL]
	                        ) . $sLocation;
                    }
                }
            }
        }

        $result['aSetCookie'] = $aSetCookie;
        $result['aNewLocation'] = $aOrganizedCurlOptions[CURLOPT_URL] = $sLocation;
        $result['aNewOrganizedCurlOptions'] = $aOrganizedCurlOptions;

    }

    /**
     * @param array $aOrganizedCurlOptions
     * @param string $sHeaderDirective
     * @return void
     * @throws AfrHttpHeaderFormattersException
     */
    private function updateCookiesInOrganizedCurlOptions(array &$aOrganizedCurlOptions, string $sHeaderDirective): void
    {
        $aParseHeaderLineSetCookieInfo = AfrHttpHeaderFormattersCookie::getInstance()->parseHeaderLineSetCookieInfo($sHeaderDirective);
        if(!$aParseHeaderLineSetCookieInfo){
            return;
        }
        $aExistingCookies = empty($aOrganizedCurlOptions[CURLOPT_COOKIE]) ? [] :
	        AfrHttpHeaderFormattersCookie::getInstance()->formatCookieLineIntoAssociativeArray($aOrganizedCurlOptions[CURLOPT_COOKIE], false);

        $sCookieName = $aParseHeaderLineSetCookieInfo['sCookieName'];
        if ($aParseHeaderLineSetCookieInfo['iExpire'] < time()) {
            if (isset($aExistingCookies[$sCookieName])) {
                unset($aExistingCookies[$sCookieName]);
            }
        } else {
            $aExistingCookies[$sCookieName] = $aParseHeaderLineSetCookieInfo['sCookieValue'];
        }

        if (count($aExistingCookies)) {
            $aOrganizedCurlOptions[CURLOPT_COOKIE] = AfrHttpHeaderFormattersCookie::getInstance()->formatIntoCookieHeaderLine($aExistingCookies, false, false);
        } else {
            unset($aOrganizedCurlOptions[CURLOPT_COOKIE]);
        }

    }

    /**
     * @param string $sUrl
     * @param array $aPost
     * @return void
     * @throws AfrHttpCurlSimpleException
     * @throws AfrHttpHeaderFormattersException
     */
    protected function makeTestRequestTo(
        string $sUrl = 'https://autoframe.ro/',
        array  $aPost = []
    ) //TODO finish:
    {
        $aOrganizedCurlOptions = $this->prepareSimpleCurlHandle(
            $sUrl,
            $aPost ? 'POST' : 'GET',
            $aPost,
            [
                'Afr: curl',
                'Accept-Language:'
            ],
            [],
            'defaultP=P_cookie'
        );
        $aResult = $this->makeSimpleCurlRequest($aOrganizedCurlOptions, true);
        print_r($aResult);
        die;
    }

}
