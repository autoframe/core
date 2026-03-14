<?php


namespace Autoframe\Core\String\Seo;

use Autoframe\Core\String\AfrStr;

class AfrSeo
{

    /**
     * Title.
     * @param string $str
     * @param int $max
     * @return string
     */
    public static function title(string $str, int $max = 80): string
    {
        return AfrStr::shortStr($str, $max);
    }


    //$breadcrumb[]=array('/','OnBreak.ro','alt title'); // link nume link descriere

    /**
     * Breadcrumb.
     */
    public static function breadcrumb($array, $last_element_is_link = 1)
    {
        echo 'https://developers.google.com/search/docs/advanced/structured-data/breadcrumb#json-ld_1';die;

        if (!is_array($array)) {
            return 0;
        }
        echo '<div xmlns:v="http://rdf.data-vocabulary.org/#" class="afr_breadcrumb_container">' . PHP_EOL;
        $nivele = count($array);
        for ($i = 0; $i < $nivele; $i++) {
            if (($array[$i][2] == '')) {
                $array[$i][2] = $array[$i][1];
            }
            if ($i + 1 < $nivele + $last_element_is_link) {
                echo str_repeat('	', $i) . '<span typeof="v:Breadcrumb">' . PHP_EOL;
                echo str_repeat('	', $i) . '<a href="' . h($array[$i][0]) . '" rel="v:url" property="v:title" title="' . h($array[$i][2]) . '" class="afr_breadcrumb">';
                echo h($array[$i][1]);
                echo '</a> ' . PHP_EOL;
            } else {
                echo str_repeat('	', $i) . '<strong>' . h($array[$i][1]) . '</strong>';
            }//ultimul bredcrumb care nu este link
            if ($i + 1 < $nivele) {
                echo str_repeat('	', $i) . '&raquo; ';
            }
            if ($i + 2 - $last_element_is_link < $nivele) {
                echo '<span rel="v:child">' . PHP_EOL;
            }
        }
        echo str_repeat('</span>', ($i - 1) * 2 + ($last_element_is_link == 0 ? -1 : 1));
        echo '</div>' . PHP_EOL;
        return 1;
    }

}
