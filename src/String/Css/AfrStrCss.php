<?php
declare(strict_types=1);


namespace Autoframe\Core\String\Css;

use Autoframe\Core\FileMime\AfrFileMimeClass;


trait AfrStrCss
{

    /**
     * @param string $sFullImagePath
     * @return string
     * CSS: .logo {background: url("<?php echo getBase64InlineData ('img/logo.png'); ?>") no-repeat; }
     * <img src="<?php echo getBase64InlineData ('img/logo.png','image'); ?>"/>
     */
    public function base64EncodeImage(string $sFullImagePath): string
    {
        $sMime = AfrFileMimeClass::getInstance()->getMimeFromFileName($sFullImagePath);
        return 'data:' . $sMime. ';base64,' . base64_encode(file_get_contents($sFullImagePath));
    }

}