<?php
declare(strict_types=1);

namespace Autoframe\Core\FileSystem\Traversing;

use Autoframe\Core\FileSystem\DirPath\Exception\AfrFileSystemDirPathException;

interface AfrDirTraversingCountChildrenDirsInterface
{
    /**
     * Count all children dirs.
     * @param string $sDirPath
     * @return int
     * @throws AfrFileSystemDirPathException
     */
    public function countAllChildrenDirs(string $sDirPath): int;
}
