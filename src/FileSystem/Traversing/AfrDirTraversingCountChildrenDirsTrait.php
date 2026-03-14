<?php
declare(strict_types=1);

namespace Autoframe\Core\FileSystem\Traversing;

use Autoframe\Core\FileSystem\DirPath\Exception\AfrFileSystemDirPathException;

use function readdir;
use function closedir;
use function count;

trait AfrDirTraversingCountChildrenDirsTrait
{
    use AfrDirTraversingDependency;

    /**
     * Count all children dirs.
     * @param string $sDirPath
     * @return int
     * @throws AfrFileSystemDirPathException
     */
    public function countAllChildrenDirs(string $sDirPath): int
    {
        if(!in_array(substr($sDirPath, -1, 1),['/','\\'])) {
            $sDirPath.=DIRECTORY_SEPARATOR;
        }
        $aDirs = [];
        $this->checkAfrDirPathInstance();
        $rDir = self::$AfrDirPathInstance->openDir($sDirPath);
        while ($sEntryName = readdir($rDir)) {
            if ($sEntryName === '.' || $sEntryName === '..') {
                continue;
            }
            if (self::$AfrDirPathInstance->isDir($sDirPath . $sEntryName)) {
                $aDirs[] = $sEntryName;
            }
        }
        closedir($rDir);

        return count($aDirs);
    }

}
