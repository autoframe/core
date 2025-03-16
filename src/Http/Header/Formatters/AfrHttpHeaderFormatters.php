<?php
declare(strict_types=1);

namespace Autoframe\Core\Http\Header\Formatters;

use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;
use Autoframe\Core\Http\Header\Formatters\Exception\AfrHttpHeaderFormattersException;


class AfrHttpHeaderFormatters extends AfrSingletonAbstractClass
{

    /**
     * @param string $sHeaders raw headers
     * @return array
     */
    public function formatHttpRawHeadersToArr(string $sHeaders): array
    {
        return (array)explode("\n", str_replace("\r", '', $sHeaders));
    }

    /**
     * @param string|array|null $mHeaders
     * @return array
     * @throws AfrHttpHeaderFormattersException
     */
    public function formatMixedHeadersInputToKeyArray($mHeaders): array
    {
        if($mHeaders === null){
			return [];
        }
		$aHeaders = [];
        if (is_string($mHeaders) && strlen($mHeaders)) {
            $mHeaders = $this->formatHttpRawHeadersToArr($mHeaders);
        }
        if (is_array($mHeaders)) {
            foreach ($mHeaders as $mKey => $sVal) {
                if (is_string($mKey)) {
                    $aHeaders[rtrim($mKey, ': ')] = trim($sVal);
                } else {
                    $aFormat = $this->formatSplitHeaderLineInKeyVal($sVal);
                    $aHeaders[$aFormat[0]] = $aFormat[1];
                }
            }
        }

        if ($mHeaders && !$aHeaders) {
            throw new AfrHttpHeaderFormattersException(
                'Invalid headers format exception. The input could not be converted to header array: ' .
                print_r(func_get_arg(0), true)
            );
        }
        return $aHeaders;
    }

    /**
     * @param $mHeaders
     * @return array format for CURLOPT_HTTPHEADER
     * @throws AfrHttpHeaderFormattersException
     */
    public function formatForCurlOptHttpHeader($mHeaders): array
    {
        $aHeaders = [];
        if (is_string($mHeaders) && strlen($mHeaders)) {
            $mHeaders = $this->formatHttpRawHeadersToArr($mHeaders);
        }
        if (is_array($mHeaders)) {
            foreach ($mHeaders as $mKey => $sVal) {
                if (is_string($mKey)) {
                    $aHeaders[] = rtrim($mKey, ': ') . ': ' . trim($sVal);
                } else {
                    $aFormat = $this->formatSplitHeaderLineInKeyVal($sVal);
                    $aHeaders[] = $aFormat[0] . ': ' . $aFormat[1];
                }
            }
        }

        if ($mHeaders && !$aHeaders) {
            throw new AfrHttpHeaderFormattersException(
                'Invalid headers format exception. The input could not be converted to curl header array: ' .
                print_r(func_get_arg(0), true)
            );
        }
        return $aHeaders;
    }

    /**
     * @param array $aHeaders
     * @param string $sGlue
     * @return string
     * @throws AfrHttpHeaderFormattersException
     */
    public function formatFlattenHeaderArray(array $aHeaders, string $sGlue = "\r\n"): string
    {
        $aOut = [];
        foreach ($aHeaders as $mKey => $sVal) {
            if (!is_string($sVal)) {
                throw new AfrHttpHeaderFormattersException(
                    'Invalid header type: ' . gettype($sVal) . '; Expected string'
                );
            }
            if (is_string($mKey)) {
                $aOut[] = $mKey . ': ' . $sVal;
            } else {
                if (strpos($sVal, ': ') === false) {
                    throw new AfrHttpHeaderFormattersException(
                        'Missing `: ` in header structure: ' . $sVal
                    );
                }
                $aOut[] = $sVal;
            }
        }
        return implode($sGlue, $aOut);
    }

    /**
     * @param string $sHeaderLine
     * @return array
     * @throws AfrHttpHeaderFormattersException
     */
    public function formatSplitHeaderLineInKeyVal(string $sHeaderLine): array
    {
        $iSplit = strpos($sHeaderLine, ':');
        if ($iSplit === false) {
            throw new AfrHttpHeaderFormattersException('Invalid header line: ' . $sHeaderLine);
        }
        return [
            trim((string)substr($sHeaderLine, 0, $iSplit)),
            trim((string)substr($sHeaderLine, $iSplit + 2))
        ];
    }


}