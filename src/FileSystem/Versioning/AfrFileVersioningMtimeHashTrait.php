<?php
declare(strict_types=1);

namespace Autoframe\Core\FileSystem\Versioning;

use Autoframe\Core\FileSystem\Versioning\Exception\AfrFileSystemVersioningException;

use function rtrim;
use function is_file;
use function filemtime;
use function strtoupper;
use function dechex;

trait AfrFileVersioningMtimeHashTrait
{
    /**
     * File versioning mtime hash.
     * @param string $sDirPath
     * @param bool $bCanThrowException
     * @return string
     * @throws AfrFileSystemVersioningException
     */
    public function fileVersioningMtimeHash(
        string $sDirPath,
        bool   $bCanThrowException
    ): string
    {
        $sDirPath = rtrim($sDirPath, '\/');
        $bFileExists = is_file($sDirPath);
        if (!$bFileExists && $bCanThrowException) {
            throw new AfrFileSystemVersioningException(
                'filemtime ' . __CLASS__ . '->' . __FUNCTION__ . 'failed in for: ' . $sDirPath
            );
        }
        $iTimestamp = (int)($bFileExists ? filemtime($sDirPath) : 0);
        return strtoupper(dechex($iTimestamp));
    }
}
