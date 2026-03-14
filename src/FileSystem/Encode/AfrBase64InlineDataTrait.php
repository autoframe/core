<?php
declare(strict_types=1);

namespace Autoframe\Core\FileSystem\Encode;

use Autoframe\Core\FileMime\AfrFileMimeClass;
use function base64_encode;
use function file_get_contents;


trait AfrBase64InlineDataTrait
{
    /**
     * @param string $sFullImagePath
     * @return string
     * CSS: .logo {background: url("<?php echo getBase64InlineData ('img/logo.png'); ?>") no-repeat; }
     * <img src="<?php echo getBase64InlineData ('img/logo.png','image'); ?>"/>
     */
    public function getBase64InlineData(string $sFullImagePath): string
    {
        $sMime = AfrFileMimeClass::getInstance()->getMimeFromFileName($sFullImagePath);
        return 'data:' . $sMime. ';base64,' . base64_encode(file_get_contents($sFullImagePath));
    }

    /**
     * Get base64 inline one px.
     * @return string
     */
    public function getBase64InlineOnePx(): string
    {
        return 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';
    }

}
