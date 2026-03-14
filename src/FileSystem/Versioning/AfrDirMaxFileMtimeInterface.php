<?php
declare(strict_types=1);

namespace Autoframe\Core\FileSystem\Versioning;

use Autoframe\Core\FileSystem\DirPath\Exception\AfrFileSystemDirPathException;
use Autoframe\Core\FileSystem\Versioning\Exception\AfrFileSystemVersioningException;

interface AfrDirMaxFileMtimeInterface
{
    /**
     * Get dir max file mtime.
     * @param string|array $pathStringOrPathsArray
     * @param int $iMaxSubDirs
     * @param bool $bFollowSymlinks
     * @param bool $bGetTsFromDirs
     * @param array $aFilterExtensions
     * @param array $aSkipDirs
     * @throws AfrFileSystemVersioningException
     * @throws AfrFileSystemDirPathException
     * @return int
     */
    public function getDirMaxFileMtime(
        $pathStringOrPathsArray,
        int $iMaxSubDirs = 1,
        bool $bFollowSymlinks = false,
        bool $bGetTsFromDirs = false,
        array $aFilterExtensions = [],
        array $aSkipDirs = []
    ): int;
}
