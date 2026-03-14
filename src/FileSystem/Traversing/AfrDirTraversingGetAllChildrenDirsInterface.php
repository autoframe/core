<?php
declare(strict_types=1);

namespace Autoframe\Core\FileSystem\Traversing;

use Autoframe\Core\FileSystem\DirPath\Exception\AfrFileSystemDirPathException;
use Autoframe\Core\FileSystem\Traversing\Exception\AfrFileSystemTraversingException;

interface AfrDirTraversingGetAllChildrenDirsInterface
{
    /**
     * Get all children dirs.
     * @param string $sDirPath
     * @param int $iMaxLevels
     * @param bool $bFollowSymlinks
     * @param int $iCurrentLevel
     * @return array|false
     * @throws AfrFileSystemTraversingException
     * @throws AfrFileSystemDirPathException
     */
    public function getAllChildrenDirs(
        string $sDirPath,
        int    $iMaxLevels = 1,
        bool   $bFollowSymlinks = false,
        int    $iCurrentLevel = 0
    );
}
