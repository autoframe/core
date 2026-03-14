<?php
declare(strict_types=1);

namespace Autoframe\Core\FileSystem\Traversing;

use Autoframe\Core\FileSystem\DirPath\Exception\AfrFileSystemDirPathException;

use function ltrim;
use function strtolower;
use function readdir;
use function filetype;
use function strpos;
use function substr;
use function strlen;
use function closedir;

trait AfrDirTraversingFileListTrait
{
    use AfrDirTraversingDependency;

    /**
     * Get dir file list.
     * @param string $sDirPath absolute or relative path
     * @param array $aFilterExtensions ['jpg','php',''] filter images, scripts and file without extension
     * @return array|false
     * @throws AfrFileSystemDirPathException
     */
    public function getDirFileList(
        string               $sDirPath,
        array                $aFilterExtensions = []
    )
    {
        $this->checkAfrDirPathInstance();
        if (!self::$AfrDirPathInstance->isDir($sDirPath) || !$rDir = self::$AfrDirPathInstance->openDir($sDirPath)) {
            return false;
        }

        $aFiles = [];
        $sDirPath = self::$AfrDirPathInstance->addFinalSlash($sDirPath);
        foreach ($aFilterExtensions as &$sFilter) {
            $sFilter = '.' . ltrim(strtolower($sFilter), '.');
        }

        while ($sEntryName = readdir($rDir)) {
            $sFilePath = $sDirPath . $sEntryName;
            if (filetype($sFilePath) === 'file') {
                if (!empty($aFilterExtensions)) {
                    $this->getDirFileListFilterExtensions($aFilterExtensions, $sEntryName, $aFiles);
                } else {
                    $aFiles[] = $sEntryName;
                }
            }
        }
        closedir($rDir);
        sort($aFiles, SORT_NATURAL);

        return $aFiles;
    }

    /**
     * @param array $aFilterExtensions
     * @param string $sEntryName
     * @param array $aFiles
     */
    protected function getDirFileListFilterExtensions(array $aFilterExtensions, string $sEntryName, array &$aFiles)
    {
        $sEntryNameLower = strtolower($sEntryName);
        foreach ($aFilterExtensions as $sFilterExtension) {
            if ($sFilterExtension === '.') { //files without any extension
                if (substr($sEntryName, -1, 1) === '.' || strpos($sEntryName, '.') === false) {
                    $aFiles[] = $sEntryName; //file without extension
                    break;
                }
                continue; //file with extension
            }
            if (substr($sEntryNameLower, -strlen($sFilterExtension)) === $sFilterExtension) {
                $aFiles[] = $sEntryName;
                break;
            }
        }
    }


}
