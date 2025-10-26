<?php
declare(strict_types=1);

namespace Autoframe\Core\String\Url;

use Autoframe\Core\Exception\AfrException;
use Autoframe\Core\FileMime\AfrFileMimeClass;

use function parse_url;
use function parse_str;
use function rtrim;
use function strtr;
use function base64_encode;
use function base64_decode;
use function str_pad;
use function strlen;
use function pathinfo;
use function file_get_contents;

class AfrStrUrl
{
    /**
     * @param string $url
     * @return array
     * reverse:  http_build_query($array);
     */
    public static function parseUrlGetParams(string $url): array
    {
        $output = [];
        $url = parse_url($url);
        if ($url['query']) {
            parse_str($url['query'], $output);
        }
        return $output;
    }

    /**
     * @param $data
     * @return string
     */
    public static function base64url_encode($data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * @param string $data
     * @return false|string
     */
    public static function base64url_decode(string $data)
    {
        return base64_decode( strtr( $data, '-_', '+/') . str_repeat('=', 3 - ( 3 + strlen( $data )) % 4 ));
    }

    /**
     * @param string $sFullImagePath
     * @param string $fileType
     * @return string
     * @throws AfrException
     * CSS: .logo {background: url("<?php echo base64_encode_image ('img/logo.png','png'); ?>") no-repeat; }
     * <img src="<?php echo base64EncodeFile ('img/logo.png','image'); ?>"/>
     */
    public static function base64EncodeFile(string $sFullImagePath, string $fileType = 'image'): string
    {
        $sMime = AfrFileMimeClass::getInstance()->getMimeFromFileName($sFullImagePath);
        return 'data:' . $sMime. ';base64,' . base64_encode(file_get_contents($sFullImagePath));
    }



}