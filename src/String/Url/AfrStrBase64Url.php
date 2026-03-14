<?php
declare(strict_types=1);

namespace Autoframe\Core\String\Url;

use function rtrim;
use function strtr;
use function base64_encode;
use function base64_decode;
use function strlen;
use function str_repeat;


trait AfrStrBase64Url
{
    /**
     * Base64url encode.
     * @param string $string
     * @return string
     */
    public function base64url_encode(string $string): string
    {
        return rtrim(strtr(base64_encode($string), '+/', '-_'), '=');
    }

    /**
     * Base64url decode.
     * @param string $string
     * @return false|string
     */
    public function base64url_decode(string $string)
    {
        return base64_decode(strtr($string, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($string)) % 4));
    }

}
