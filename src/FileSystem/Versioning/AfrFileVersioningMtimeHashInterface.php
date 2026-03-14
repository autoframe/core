<?php
declare(strict_types=1);

namespace Autoframe\Core\FileSystem\Versioning;

use Autoframe\Core\FileSystem\Versioning\Exception\AfrFileSystemVersioningException;

interface AfrFileVersioningMtimeHashInterface
{
    /**
     * File versioning mtime hash.
     * @param string $sDirPath
     * @param bool $bCanThrowException
     * @return string
     * @throws AfrFileSystemVersioningException
     */
    public function fileVersioningMtimeHash(string $sDirPath, bool $bCanThrowException): string;
}
