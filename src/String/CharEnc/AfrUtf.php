<?php

namespace Autoframe\Core\String\CharEnc;

use function array_keys;
use function array_values;
use function html_entity_decode;
use function ord;
use function preg_split;
use function str_replace;
use function substr;


class AfrUtf
{
    const ISOMAP = [
        'ÅŸ' => 'ş',
        'Å£' => 'ţ',
        'Äƒ' => 'ă',
        'Ã®' => 'î',
        'Ã¢' => 'â',
        'Åž' => 'Ş',
        'Å¢' => 'Ţ',
        'Ä‚' => 'Ă',
        'ÃŽ' => 'Î',
        'Ã‚' => 'Â',
    ];

    const ENTMAP = [
        '&Aring;&Yuml;' => 'ş',
        '&Aring;&pound;' => 'ţ',
        '&Auml;&fnof;' => 'ă',
        '&Atilde;&reg;' => 'î',
        '&Atilde;&cent;' => 'â',
        '&Aring;ž' => 'Ş',
        '&Aring;&cent;' => 'Ţ',
        '&Auml;&sbquo;' => 'Ă',
        '&Atilde;Ž' => 'Î',
        '&Atilde;&sbquo;' => 'Â',
    ];

    /**
     * Diacritice fix from isox.
     * @param string $str
     * @return string
     */
    public static function diacriticeFixFromIsox(string $str): string
    {
        return str_replace(
            array_keys(self::ISOMAP),
            array_values(self::ISOMAP),
            $str
        );
    }

    /**
     * Diacritice fix from entities.
     * @param string $str
     * @return string
     */
    public static function diacriticeFixFromEntities(string $str): string
    {
        return str_replace(
            array_keys(self::ENTMAP),
            array_values(self::ENTMAP),
            $str
        );
    }

    /**
     * Html entity decode.
     * @param string $string
     * @return string
     */
    public static function html_entity_decode(string $string): string
    {
        return html_entity_decode(
            $string,
            ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5
        );
    }

    /**
     * Utf to array.
     * @param string $utfStr
     * @return array
     */
    public static function utfToArray(string $utfStr): array
    {
        $chars = [];
        $pointer = 0;
        while (($chr = self::nextChar($utfStr, $pointer)) !== false) {
            $chars[] = $chr;
        }
        return $chars;
    }

    /**
     * Next char.
     * @param string $string
     * @param int $pointer
     * @return false|string
     */
    public static function nextChar(string $string, int &$pointer)
    {
        if (!isset($string[$pointer])) return false;
        $char = ord($string[$pointer]);
        if ($char < 128) {
            return $string[$pointer++];
        } else {
            if ($char < 224) {
                $bytes = 2;
            } elseif ($char < 240) {
                $bytes = 3;
            } elseif ($char < 248) {
                $bytes = 4;
            } elseif ($char == 252) {
                $bytes = 5;
            } else {
                $bytes = 6;
            }
            $str = substr($string, $pointer, $bytes);
            $pointer += $bytes;
            return $str;
        }
    }

    /**
     * Str split unicode.
     * @param string $str
     * @param int $l
     * @return array|false|string[]
     */
    public static function strSplitUnicode(string $str, $l = 0)
    {
        return preg_split('/(.{' . $l . '})/us', $str, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
    }

    /**
     * Utf8 to entities.
     * @param string $string
     * @return string
     */
    public static function UTF8ToEntities(string $string): string
    {
        /* note: apply htmlspecialchars if desired /before/ applying this function
        /* Only do the slow convert if there are 8-bit characters */
        /* avoid using 0xA0 (\240) in ereg ranges. RH73 does not like that */
        if (!preg_match("/[\200-\237]/", $string) and !preg_match("/[\241-\377]/", $string)) return $string;
        // reject too-short sequences
        $string = preg_replace("/[\302-\375]([\001-\177])/", "&#65533;\\1", $string);
        $string = preg_replace("/[\340-\375].([\001-\177])/", "&#65533;\\1", $string);
        $string = preg_replace("/[\360-\375]..([\001-\177])/", "&#65533;\\1", $string);
        $string = preg_replace("/[\370-\375]...([\001-\177])/", "&#65533;\\1", $string);
        $string = preg_replace("/[\374-\375]....([\001-\177])/", "&#65533;\\1", $string);

        // reject illegal bytes & sequences
        $string = preg_replace("/[\300-\301]./", "&#65533;", $string);        // 2-byte characters in ASCII range
        $string = preg_replace("/\364[\220-\277]../", "&#65533;", $string);    // 4-byte illegal codepoints (RFC 3629)
        $string = preg_replace("/[\365-\367].../", "&#65533;", $string);    // 4-byte illegal codepoints (RFC 3629)
        $string = preg_replace("/[\370-\373]..../", "&#65533;", $string);    // 5-byte illegal codepoints (RFC 3629)
        $string = preg_replace("/[\374-\375]...../", "&#65533;", $string);    // 6-byte illegal codepoints (RFC 3629)
        $string = preg_replace("/[\376-\377]/", "&#65533;", $string);        // undefined bytes
        $string = preg_replace("/[\302-\364]{2,}/", "&#65533;", $string);    // reject consecutive start-bytes

        $string = preg_replace("/([\360-\364])([\200-\277])([\200-\277])([\200-\277])/e",
            "'&#'.((ord('\\1')&7)<<18 | (ord('\\2')&63)<<12 |" . " (ord('\\3')&63)<<6 | (ord('\\4')&63)).';'", $string);// decode four byte unicode chars

        $string = preg_replace("/([\340-\357])([\200-\277])([\200-\277])/e",
            "'&#'.((ord('\\1')&15)<<12 | (ord('\\2')&63)<<6 | (ord('\\3')&63)).';'", $string);// decode three byte unicode characters

        $string = preg_replace("/([\300-\337])([\200-\277])/e",
            "'&#'.((ord('\\1')&31)<<6 | (ord('\\2')&63)).';'", $string); // decode two byte unicode characters

        $string = preg_replace("/[\200-\277]/", "&#65533;", $string); // reject leftover continuation bytes
        return $string;
    }

    /**
     * Convert to numeric entities.
     * @param string $string
     * @param string $encoding
     * @param int $iMapId
     * @return string
     */
    public static function convertToNumericEntities(string $string, string $encoding = '', int $iMapId = 0): string
    {
        $aMaps = [
            //  int start_code1, int end_code1, int offset1, int mask1,
            1 => array(0x000000, 0x10ffff, 0, 0xffffff), //UTF-8 all
            2 => array(0x80,     0x10ffff, 0, 0xffffff), //UTF-8
            3 => array(0x80,     0xff,     0, 0xff),     //ISO-8859-1 Convert Left side to HTML numeric character reference
            4 => array(0x21,     0x10ffff, 0, 0xffffff ) //UTF-8 include normal alphanumeric characters, not whitespace or line carriages
        ];
        if (strpos($string, '&#') !== false) {
            $string = html_entity_decode($string,ENT_QUOTES | ENT_HTML401);
        }
        if (empty($encoding)) {
            $encoding = mb_detect_encoding($string);
        }
        if (empty($iMapId)) {
            if (strpos($encoding, 'UTF') !== false) {
                $iMapId = 2;
            } elseif (in_array($string, ['ISO-8859-1', 'ISO-8859-2'])) {
                $iMapId = 3;
            } else {
                $iMapId = 1;
            }
        }
        return mb_encode_numericentity($string, $aMaps[$iMapId], $encoding);
    }


}
