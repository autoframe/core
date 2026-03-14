<?php
declare(strict_types=1);

namespace Autoframe\Core\String\Html;

use Autoframe\Core\Exception\AfrException;
use Autoframe\Core\String\AfrStr;

use function filter_var;
use function strip_tags;
use function trim;
use function rtrim;
use function str_replace;
use function strlen;
use function substr;
use function is_numeric;
use function is_array;
use function array_unique;
use function is_string;
use function implode;
use function array_diff;
use function array_values;
use function strtolower;
use function explode;

class AfrStrHtml
{
    /**
     * Test.
     */
    public static function test()
    {
        AfrStr::prea( self::addClass('class="deja"','noua'));
        AfrStr::prea( self::addClass(['deja'],'noua'));
        AfrStr::prea( self::removeClass('class="deja veche left"','veche'));
        AfrStr::prea( self::removeClass(['left','veche','deja'],'veche'));
        AfrStr::prea( self::removeClass(['left','veche','deja'],'veche'));
    }

    public static string $sAutoQuot = '"';

    /**
     * Add class.
     * @param $HtmlClass 1 str: 'class="deja"' arr: ['deja']
     * @param string $sNewClassName
     * @param string $sQuot
     * @return array|string
     * @throws AfrException
     */
    public static function addClass($HtmlClass, string $sNewClassName, string $sQuot = '')
    {
        $sNewClassName = self::validateClassName($sNewClassName);
        if (!$sNewClassName) {
            throw new AfrException('Blank $sNewClassName structure supplied!');
        }

        if (is_array($HtmlClass)) {
            $HtmlClass[] = $sNewClassName;
            $HtmlClass = array_unique($HtmlClass, SORT_STRING);
        } elseif (is_string($HtmlClass)) {
            $classStructure = self::normalizeHtmlClassStructure($HtmlClass, $sQuot);
            $classStructure['aClasses'][] = AfrStr::h($sNewClassName);

            $HtmlClass = implode(' ', $classStructure['aClasses']);
            if ($classStructure['class'] && $HtmlClass) {
                $HtmlClass =
                    $classStructure['class'] .
                    $classStructure['quot'] .
                    $HtmlClass .
                    $classStructure['quot'];
            }
        } else {
            throw new AfrException('Invalid $HtmlClass structure supplied!');
        }
        return $HtmlClass;
    }

    /**
     * Remove class.
     * @param $HtmlClass  1 str: 'class="deja"' arr: ['deja']
     * @param string $sNewClassName
     * @param string $sQuot
     * @return array|string
     * @throws AfrException
     */
    public static function removeClass($HtmlClass, string $sRemoveClassName, string $sQuot = '')
    {
        $sRemoveClassName = self::validateClassName($sRemoveClassName);

        if (is_array($HtmlClass)) {
            $HtmlClass = array_diff($HtmlClass, [$sRemoveClassName]);
            $HtmlClass = array_values($HtmlClass);
        } elseif (is_string($HtmlClass)) {
            $classStructure = self::normalizeHtmlClassStructure($HtmlClass, $sQuot);
            $classStructure['aClasses'] = array_diff($classStructure['aClasses'], [$sRemoveClassName]);

            $HtmlClass = implode(' ', $classStructure['aClasses']);
            if ($classStructure['class'] && $HtmlClass) {
                $HtmlClass =
                    $classStructure['class'] .
                    $classStructure['quot'] .
                    $HtmlClass .
                    $classStructure['quot'];
            }
        } else {
            throw new AfrException('Invalid $HtmlClass structure supplied!');
        }
        return $HtmlClass;
    }

    /**
     * @param string $HtmlClass
     * @param string $sQuot
     * @return array
     */
    private static function normalizeHtmlClassStructure(string $HtmlClass, string $sQuot = ''): array
    {
        $HtmlClass = trim($HtmlClass, ' \'"');
        $structure['class'] = '';
        $structure['quot'] = '';
        $structure['aClasses'] = [];
        $sClassConstant = 'class=';
        if (strlen($HtmlClass) > 7 && strtolower(substr($HtmlClass, 0, 6)) === $sClassConstant) {
            $structure['class'] = $sClassConstant;
            $sDetectQuot = substr($HtmlClass, 6, 1);
            if (in_array($sDetectQuot, ['"', "'"])) {
                $HtmlClass = rtrim($HtmlClass, $sDetectQuot);
                $structure['quot'] = $sDetectQuot;
                $HtmlClass = substr($HtmlClass, 7);
            } else {
                $HtmlClass = substr($HtmlClass, 6);
            }
            if (in_array($sQuot, ['"', "'"])) {
                $structure['quot'] = $sQuot;
            }
            if (!$structure['quot']) {
                $structure['quot'] = self::$sAutoQuot;
            }
        }
        foreach (explode(' ', trim($HtmlClass)) as $sClassName) {
            if ($sClassName) {
                $structure['aClasses'][] = $sClassName;
            }
        }
        return $structure;
    }

    /**
     * Validate class name.
     * @param string $sNewClassName
     * @return string
     * @throws AfrException
     */
    public static function validateClassName(string $sNewClassName): string
    {
        $sNewClassName = trim($sNewClassName);
        if (
            strlen($sNewClassName) < 1 ||
            is_numeric(substr($sNewClassName, 0, 1))
        ) {
            throw new AfrException('Blank or invalid $sNewClassName supplied!');
        }
        return $sNewClassName;
    }

    /**
     * @param string $html
     * @return array
     * Nested tables are not supported
     */
    public static function tableToArray(string $html): array
    {
        $out = array();
        $tables = AfrStr::extractBetween($html, '<table', '</table>');
        foreach ($tables as $ti => $table) {
            $rows = AfrStr::extractBetween($table, '<tr', '</tr>');
            foreach ($rows as $ri => $row) {
                $row = str_replace(array('<th', '</th>', "\r", "\n", '&nbsp;'), array('<td', '</td>', NULL, ' ', ' '), $row);//merge header with rows
                $cels = AfrStr::extractBetween($row, '<td', '</td>');
                foreach ($cels as $ci => $cell) {
                    $s = trim(AfrStr::uh(strip_tags('<p' . $cell . '</p>'))); //fix invalid strip start point
                    $out[$ti][$ri][$ci] = filter_var($s, FILTER_SANITIZE_STRING);
                }
            }
        }
        return $out;
    }

}
