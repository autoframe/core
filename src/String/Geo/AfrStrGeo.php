<?php


namespace Autoframe\Core\String\Geo;

use function atan2;
use function cos;
use function file_get_contents;
use function json_decode;
use function sin;
use function sqrt;
use function urlencode;

class AfrStrGeo
{
    private static $cacheGetCoordonatesByAddress = [];

    /**
     * Get coordonates by address.
     * @param string $address
     * @return array
     */
    public static function getCoordonatesByAddress(string $address): array
    {
        if (!isset($cacheGetCoordonatesByAddress[$address])) {
            $cacheGetCoordonatesByAddress[$address] = json_decode(
                file_get_contents('https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($address)),
                true
            );
            if (!is_array($cacheGetCoordonatesByAddress[$address]) || !$cacheGetCoordonatesByAddress[$address]) {
                $cacheGetCoordonatesByAddress[$address] = [];
            }
        }

        return $cacheGetCoordonatesByAddress[$address];
    }

    /**
     * Get lang long by address.
     * @param string $address
     * @return array
     */
    public static function getLangLongByAddress(string $address): array
    {
        $geo = ['lat' => 0, 'lng' => 0];
        $a = self::getCoordonatesByAddress($address);
        if (isset($a['results'][0]['geometry']['location']['lat'])) {
            $geo['lat'] = $a['results'][0]['geometry']['location']['lat'];
            $geo['lng'] = $a['results'][0]['geometry']['location']['lng'];
        }
        return $geo;
    }

    /**
     * Embed google map by address.
     * @param string $address
     * @param string $api_key
     * @param string $fullscreen
     * @return string
     */
    public static function embedGoogleMapByAddress(
        string $address,
        string $api_key = 'AIzaSyCE_JVq1AiNFNFv_Dx8pdv_c4lq6dG9cTs',
        string $fullscreen = 'allowfullscreen'
    ): string
    {
        return '<iframe class="embedGoogleMapByAddress" src="https://www.google.com/maps/embed/v1/search?key=' . $api_key . '&q=' . urlencode($address) . '" ' . $fullscreen . '></iframe>';
    }


    /**
     * Embed street view by address.
     * @param string $address
     * @param string $api_key
     * @param string $fullscreen
     * @param int $heading
     * @param int $pinch
     * @param int $fov
     * @return string
     */
    public static function embedStreetViewByAddress(
        string $address,
        string $api_key = 'AIzaSyCE_JVq1AiNFNFv_Dx8pdv_c4lq6dG9cTs',
        string $fullscreen = 'allowfullscreen',
        int $heading = 210,
        int $pinch = 10,
        int $fov = 35
    ):string
    {
        $a = self::getCoordonatesByAddress($address);
        return '<iframe class="embedStreetViewByAddress" src="https://www.google.com/maps/embed/v1/streetview?key=' .
            $api_key . '&location=' . $a['lat'] . ',' . $a['lng'] . '&heading=' . $heading .
            '&pitch=' . $pinch . '&fov=' . $fov . '" ' .
            $fullscreen . '></iframe>';
    }

    /**
     * Distance.
     * @param float $lat1
     * @param float $lng1
     * @param float $lat2
     * @param float $lng2
     * @param bool $miles
     * @return float
     */
    public static function distance(float $lat1, float $lng1, float $lat2, float $lng2, bool $miles = false): float
    {//distanta dintre 2 coordonate in km
        $pi80 = M_PI / 180;
        $lat1 *= $pi80;
        $lng1 *= $pi80;
        $lat2 *= $pi80;
        $lng2 *= $pi80;
        $r = 6372.797; // mean radius of Earth in km
        $dlat = $lat2 - $lat1;
        $dlng = $lng2 - $lng1;
        $a = sin($dlat / 2) * sin($dlat / 2) + cos($lat1) * cos($lat2) * sin($dlng / 2) * sin($dlng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $km = $r * $c;
        return ($miles ? ($km * 0.621371192) : $km);
    }


}
