<?php

namespace Autoframe\Core\String\Json;

class AfrJson
{
    /**
     * Encode.
     * @param $value
     * @param int $options
     * @param int $depth
     * @return false|string
     */
    public static function encode($value, int $options = 0, int $depth = 512)
    {
        $json = json_encode($value, $options, $depth);
        if ($json === false) {
            $json = (string)static::json_encode_custom($value);
        }
        return $json;
    }


    /**
     * Json encode custom.
     * @param $data
     * @return float|int|string
     */
    public static function json_encode_custom($data)
    {
        if (is_object($data)) {
            $data = get_object_vars($data);
        }
        if (is_array($data)) {
            $isAssociative = false;
            foreach (array_keys($data) as $key) {
                if (!is_integer($key)) {
                    $isAssociative = true;
                    break;
                }
            }

            $json = $isAssociative ? '{' : '[';
            $items = [];

            foreach ($data as $key => $value) {
                if ($isAssociative) {
                    $items[] = '"' . addslashes($key) . '":' . static::json_encode_custom($value);
                } else {
                    $items[] = static::json_encode_custom($value);
                }
            }

            $json .= implode(',', $items);
            $json .= $isAssociative ? '}' : ']';

            return $json;
        } elseif (is_string($data)) {
            return '"' . addslashes($data) . '"';
        } elseif (is_int($data) || is_float($data)) {
            return $data;
        } elseif (is_null($data)) {
            return 'null';
        } elseif (is_bool($data)) {
            return $data ? 'true' : 'false';
        } else {
            return '"' . addslashes(serialize($data)) . '"'; // handle other types (e.g., resources)
        }
    }


    /**
     * Decode.
     * @param string $json
     * @param bool|null $assoc
     * @param int $depth
     * @param int $options
     * @return mixed
     */
    public static function decode(string $json, ?bool $assoc = null, int $depth = 512, int $options = 0)
    {
        return json_decode($json, $assoc, $depth, $options);
    }


}
