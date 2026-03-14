<?php
declare(strict_types=1);

namespace Autoframe\Core\FileSystem\Encode;


interface AfrBase64InlineDataInterface
{
    /**
     * @param string $sFullImagePath
     * @return string
     * CSS: .logo {background: url("<?php echo getBase64InlineData ('img/logo.png'); ?>") no-repeat; }
     * <img src="<?php echo getBase64InlineData ('img/logo.png'); ?>"/>
     */
    public function getBase64InlineData(string $sFullImagePath): string;

    /**
     * Get base64 inline one px.
     * @return string
     */
    public function getBase64InlineOnePx(): string;

}
