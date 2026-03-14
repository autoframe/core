<?php
declare(strict_types=1);

namespace Autoframe\Core\String;

use function chr;
use function count;
use function explode;
use function html_entity_decode;
use function htmlentities;
use function in_array;
use function ini_get;
use function is_array;
use function is_string;
use function mb_strlen;
use function mb_substr;
use function number_format;
use function print_r;
use function str_replace;
use function strlen;
use function strtolower;
use function substr_count;

class AfrStr
{

    /**
     * @var int bitwise
     */
    public static int $iFlagsHtmlentities = ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401;

    /**
     * @var int bitwise
     */
    public static int $iFlagsHtmlspecialchars = ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401;

    /**
     * @var string
     */
    protected static string $sHtmlEncoding; // = 'UTF-8';

    /**
     * @param string $str
     * @param bool $bSingleQuotEncapsulation
     * @return string
     */
    static function q(string $str, bool $bSingleQuotEncapsulation = true): string
    {
        $quot = $bSingleQuotEncapsulation ? "'" : '"';
        $backSlash = chr(92);  //chr(92)=\
        return str_replace(
            [$backSlash, $quot],
            [$backSlash . $backSlash, $quot . $quot],
            $str);
    }

    /**
     * @param string $sType UTF-8|ISO-8859-15|...
     */
    static function setHtmlEncoding(string $sType = '')
    {
        if (!$sType) {
            $sType = ini_get("default_charset");
        }
        self::$sHtmlEncoding = $sType;
    }

    /**
     * @return string
     */
    static function getHtmlEncoding(): string
    {
        if (empty(self::$sHtmlEncoding)) {
            self::setHtmlEncoding();
        }
        return self::$sHtmlEncoding;
    }

    /**
     * @param string|array $saData
     * @param string $sEncoding
     * @return array|string
     */
    static function h($saData, string $sEncoding = '')
    {
        if (!$sEncoding) {
            $sEncoding = self::getHtmlEncoding();
        }
        if (is_array($saData)) {
            foreach ($saData as &$val) {
                $val = self::h($val, $sEncoding);
            }
        } elseif (is_string($saData)) {
            $saData = htmlentities($saData, self::$iFlagsHtmlentities, $sEncoding);
        }
        return $saData;
    }

	/**
	 * @param string $sValueStr
	 * @param string $sEncoding
	 * @param bool $double_encode
	 * @return string
	 */
    static function hXml(string $sValueStr, string $sEncoding = '', bool $double_encode = true): string
    {
        if (!$sEncoding) {
            $sEncoding = self::getHtmlEncoding();
        }
        return htmlspecialchars($sValueStr,self::$iFlagsHtmlspecialchars, $sEncoding, $double_encode);
    }

    /**
     * Uh.
     * @param string $sHtml
     * @param string $sEncoding
     * @return string
     */
    public static function uh(string $sHtml, string $sEncoding = ''): string
    {
        if (!$sEncoding) {
            $sEncoding = self::getHtmlEncoding();
        }
        return html_entity_decode($sHtml, self::$iFlagsHtmlentities, $sEncoding);
    }

    /**
     * Short str.
     * @param string $txt
     * @param int $len
     * @param array $aIgnore
     * @return string
     */
    public static function shortStr(string $txt, int $len, array $aIgnore = []): string
    {
        if (mb_strlen($txt) > $len && !in_array($txt, $aIgnore)) {
            $txt = mb_substr($txt, 0, $len - 3) . '...';
        }
        return $txt;
    }

    /**
     * Prea.
     * @param mixed $mixed
     * @param bool $print
     * @return string
     */
    public static function prea($mixed, bool $print = true): string
    {
        $html = '<pre>' . print_r(self::h($mixed), true) . '</pre>';
        if ($print) {
            echo $html;
        }
        return $html;
    }

    /**
     * Extract between.
     * @param string $str
     * @param string $start_char
     * @param string $end_char
     * @return array
     */
    public static function extractBetween(string $str, string $start_char, string $end_char = ''): array
    {
        if ($str && $start_char) {
            if (!$end_char) {
                $end_char = $start_char;
            }
        } else {
            return [NULL];
        }
        $arr = [];
        $str = explode($start_char, $str);
        $parts = count($str);
        if ($parts < 2) {
            return [NULL];
        }
        if ($start_char === $end_char) {
            $i = 0;
            while ($i < $parts) {
                $arr[] = $str[$i + 1]??null;
                $i += 2;
            }
        } else {
            unset($str[0]);
            foreach ($str as &$val) {
                $val = explode($end_char, $val);
                $val = $val[0];
                $arr[] = $val;
            }
        }
        return $arr;
    }


    /**
     * Array remove.
     * @param array $sourceArray
     * @param array $excludeKeys
     * @return array
     */
    public static function array_remove(array $sourceArray, array $excludeKeys = []): array
    {
        if (is_array($excludeKeys) && $excludeKeys) {
            $new_arr = array();
            foreach ($sourceArray as $key => $val) {
                if (!in_array($key, $excludeKeys)) {
                    $new_arr[$key] = $val;
                }
            }
            return $new_arr;
        } else {
            return $sourceArray;
        }
    }

    /**
     * Round decimal.
     * @param float $float
     * @param int $decimals
     * @param string $decSep
     * @param string $thousandsSep
     * @return string
     */
    public static function roundDecimal(float $float, int $decimals = 2, string $decSep = '.', string $thousandsSep = ''): string
    {
        return number_format($float, $decimals, $decSep, $thousandsSep);
    }


	/**
	 * Round decimal.
	 */
	public static function round_decimal($float,$decimals=2){
		if(is_numeric($float)){
			$tmp=explode('.',$float);
			if(isset($tmp[1])){
				if(strlen($tmp[1])<$decimals){ $tmp[1].=str_repeat('0', ($decimals-strlen($tmp[1])) ); }
				elseif(strlen($tmp[1])>$decimals){ $tmp[1]=substr($tmp[1],0,$decimals); }
				elseif(strlen($tmp[1])==$decimals){ }
				else{$tmp[1]=str_repeat('0', $decimals );}
			}
			else{$tmp[1]=str_repeat('0', $decimals );}
			$float=$tmp[0].'.'.$tmp[1];
		}
		return $float;
	}

    /**
     * Substri count.
     * @param string $haystack
     * @param string $needle
     * @param int $offset
     * @param int|null $length
     * @return int
     */
    public static function substri_count(string $haystack, string $needle, int $offset = 0, ?int $length = null): int
    {
        if ($length === null) {
            return substr_count(strtolower($haystack), strtolower($needle), $offset);
        }
        return substr_count(strtolower($haystack), strtolower($needle), $offset, $length);
    }

    /**
     * Clear spaces.
     * @param string $str
     * @return string
     */
    public static function clearSpaces(string $str): string
    {
        return str_replace([" ","\t","\n","\r","\0","\x0B",], '', $str);
    }

    /**
     * Get class namespace.
     * @param string $class
     * @return string
     */
    public static function getClassNamespace(string $class): string
    {
        return join('\\', array_slice(explode('\\', $class), 0, -1));
    }

    /**
     * Get class basename.
     * @param string $class
     * @return string
     */
    public static function getClassBasename(string $class): string
    {
        return join('', array_slice(explode("\\", $class), -1, 1));
    }


    /**
     * Escape.
     */
    public static function escape(string $string, string $esc_type = 'html', string $char_set = '')
    {
        if (!$char_set) {
            $char_set = self::getHtmlEncoding();
        }
        switch ($esc_type) {
            case 'html':
                return htmlspecialchars($string, self::$iFlagsHtmlentities, $char_set);

            case 'htmlall':
                return htmlentities($string, self::$iFlagsHtmlentities, $char_set);

            case 'url':
                return rawurlencode($string);

            case 'urlpathinfo':
                return str_replace('%2F','/',rawurlencode($string));

            case 'quotes':
                // escape unescaped single quotes
                return preg_replace("%(?<!\\\\)'%", "\\'", $string);

            case 'hex':
                // escape every character into hex
                $return = '';
                for ($x=0; $x < strlen($string); $x++) {
                    $return .= '%' . bin2hex($string[$x]);
                }
                return $return;

            case 'hexentity':
                $return = '';
                for ($x=0; $x < strlen($string); $x++) {
                    $return .= '&#x' . bin2hex($string[$x]) . ';';
                }
                return $return;

            case 'decentity':
                $return = '';
                for ($x=0; $x < strlen($string); $x++) {
                    $return .= '&#' . ord($string[$x]) . ';';
                }
                return $return;

            case 'javascript':
                // escape quotes and backslashes, newlines, etc.
                return strtr($string, array('\\'=>'\\\\',"'"=>"\\'",'"'=>'\\"',"\r"=>'\\r',"\n"=>'\\n','</'=>'<\/'));

            case 'mail':
                // safe way to display e-mail address on a web page
                return str_replace(array('@', '.'),array(' [AT] ', ' [DOT] '), $string);

            case 'nonstd':
                // escape non-standard chars, such as ms document quotes
                $_res = '';
                for($_i = 0, $_len = strlen($string); $_i < $_len; $_i++) {
                    $_ord = ord(substr($string, $_i, 1));
                    // non-standard char, escape it
                    if($_ord >= 126){
                        $_res .= '&#' . $_ord . ';';
                    }
                    else {
                        $_res .= substr($string, $_i, 1);
                    }
                }
                return $_res;

            default:
                return $string;
        }
    }
}
