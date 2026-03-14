<?php
declare(strict_types=1);

namespace Autoframe\Core\FileSystem\Traversing;

use Autoframe\Core\FileSystem\Exception\AfrFileSystemException;

interface AfrDirTraversingFileListInterface
{
    /**
     * Get dir file list.
     * @param string $sDirPath
     * @param array $aFilterExtensions
     * @return array|false
     * @throws AfrFileSystemException
     */
    public function getDirFileList(string $sDirPath, array $aFilterExtensions = []);
}
