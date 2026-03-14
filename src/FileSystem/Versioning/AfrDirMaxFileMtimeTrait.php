<?php
declare(strict_types=1);

namespace Autoframe\Core\FileSystem\Versioning;

use Autoframe\Core\FileSystem\DirPath\Exception\AfrFileSystemDirPathException;
use Autoframe\Core\FileSystem\Traversing\AfrDirTraversingDependency;
use Autoframe\Core\FileSystem\Versioning\Exception\AfrFileSystemVersioningException;

use function is_array;
use function is_string;
use function strlen;
use function max;
use function filemtime;
use function filetype;
use function readdir;
use function readlink;
use function closedir;
use function gettype;

trait AfrDirMaxFileMtimeTrait
{
    use AfrDirTraversingDependency;

    /**
     * Get dir max file mtime.
     * @param string|array $pathStringOrPathsArray
     * @param int $iMaxSubDirs
     * @param bool $bFollowSymlinks
     * @param bool $bGetTsFromDirs
     * @param array $aFilterExtensions ['jpg','php',''] filter images, scripts and file without extension
     * @param array $aSkipDirs ['/var/dirToBeSkipped',] skip directory
     * @return int
     * @throws AfrFileSystemVersioningException
     * @throws AfrFileSystemDirPathException
     */
    public function getDirMaxFileMtime(
        $pathStringOrPathsArray,
        int $iMaxSubDirs = 1,
        bool $bFollowSymlinks = false,
        bool $bGetTsFromDirs = false,
        array $aFilterExtensions = [],
        array $aSkipDirs = []
    ): int
    {
        if ($iMaxSubDirs < 0) {
            return 0;
        }
        $iMaxTimestamp = 0;
        $this->checkAfrDirPathInstance();

        if (is_array($pathStringOrPathsArray)) {
            foreach ($pathStringOrPathsArray as $sDirPath) {
                $iMaxTimestamp = max(
                    $iMaxTimestamp,
                    $this->getDirMaxFileMtime($sDirPath, $iMaxSubDirs, $bGetTsFromDirs, $bFollowSymlinks, $aFilterExtensions, $aSkipDirs)
                );
            }
        } elseif (is_string($pathStringOrPathsArray)) {
            if (strlen($pathStringOrPathsArray) < 1 || $pathStringOrPathsArray === '.' || $pathStringOrPathsArray === '..') {
                throw new AfrFileSystemVersioningException(
                    'Invalid string "' . $pathStringOrPathsArray . '" provided for ' .
                    __CLASS__ . '->' . __FUNCTION__
                );
            }
            $sPath = $pathStringOrPathsArray;
            $aDirs = [];

            if (in_array($sPath, $aSkipDirs)) {
                return $iMaxTimestamp;
            }
            foreach ($aFilterExtensions as &$sFilter) {
                $sFilter = '.' . ltrim(strtolower($sFilter), '.');
            }
            $sPathType = $this->getDirMaxFileMtimeProcess(
                $sPath,
                $iMaxTimestamp,
                $aDirs,
                $bFollowSymlinks,
                $bGetTsFromDirs,
                $aFilterExtensions,
                $aSkipDirs
            );

            if ($sPathType === 'dir') {
                $this->checkAfrDirPathInstance();
                $sPath = self::$AfrDirPathInstance->correctDirPathFormat($sPath, true, true);
                $rDir = self::$AfrDirPathInstance->openDir($sPath);
                while ($sEntryName = readdir($rDir)) {
                    if ($sEntryName === '.' || $sEntryName === '..') {
                        continue;
                    }
                    $this->getDirMaxFileMtimeProcess(
                        $sPath . $sEntryName,
                        $iMaxTimestamp,
                        $aDirs,
                        $bFollowSymlinks,
                        $bGetTsFromDirs,
                        $aFilterExtensions,
                        $aSkipDirs
                    );
                }
                closedir($rDir);
                if ($bGetTsFromDirs) {
                    $iMaxTimestamp = max(
                        $iMaxTimestamp,
                        filemtime($sPath)
                    );
                }
            }
            foreach ($aDirs as $sTargetDir) {
                if (in_array($sTargetDir, $aSkipDirs)) {
                    continue;
                }
                $iMaxTimestamp = max(
                    $iMaxTimestamp,
                    $this->getDirMaxFileMtime($sTargetDir, $iMaxSubDirs - 1, $bGetTsFromDirs, $bFollowSymlinks, $aFilterExtensions, $aSkipDirs)
                );
            }

        } else {
            throw new AfrFileSystemVersioningException(
                'Expected string|array as parameter 1 but you have provided"' .
                gettype($pathStringOrPathsArray) . '" in ' .
                __CLASS__ . '->' . __FUNCTION__
            );
        }

        return $iMaxTimestamp;
    }

    /**
     * @param string $sTargetDir
     * @param int $iMaxTimestamp
     * @param array $aDirs
     * @param bool $bFollowSymlinks
     * @param bool $bGetTsFromDirs
     * @param array $aFilterExtensions
     * @param array $aSkipDirs
     * @return string
     */
    protected function getDirMaxFileMtimeProcess(
        string $sTargetDir,
        int    &$iMaxTimestamp,
        array  &$aDirs,
        bool   $bFollowSymlinks,
        bool   $bGetTsFromDirs,
        array  $aFilterExtensions,
        array  $aSkipDirs
    ): string
    {
        $sType = @filetype($sTargetDir); //check if file exists and ignore warning. This is the fastest way
        $sType = (string)$sType;
        if (!$sType) {
            return '';
        }
        if ($sType === 'file') {
            if ($this->extensionMatched($aFilterExtensions, $sTargetDir)) {
                $iMaxTimestamp = max($iMaxTimestamp, (int)filemtime($sTargetDir));
            }
        } elseif ($sType === 'dir') {
            $aDirs[] = $sTargetDir; //keep low the open dir resource count
            if ($bGetTsFromDirs) {
                $iMaxTimestamp = max(
                    $iMaxTimestamp,
                    filemtime($sTargetDir)
                );
            }
        } elseif ($bFollowSymlinks && $sType === 'link') {
            $sSymLinkTarget = readlink($sTargetDir);//TODO test symlinks
            if ($sSymLinkTarget) {
                $sType = $this->getDirMaxFileMtimeProcess(
                    $sSymLinkTarget,
                    $iMaxTimestamp,
                    $aDirs,
                    true,
                    $bGetTsFromDirs,
                    $aFilterExtensions,
                    $aSkipDirs
                );
            }
        }
        return $sType;
    }

    /**
     * @param array $aFilterExtensions
     * @param string $sEntryName
     * @return bool
     */
    protected function extensionMatched(array $aFilterExtensions, string $sEntryName): bool
    {
        if (empty($aFilterExtensions)) {
            return true;
        }
        //    foreach ($aFilterExtensions as &$sFilter) {
        //        $sFilter = '.' . ltrim(strtolower($sFilter), '.');
        //    }
        $sEntryNameLower = strtolower($sEntryName);
        foreach ($aFilterExtensions as $sFilterExtension) {
            if ($sFilterExtension === '.') { //files without any extension
                if (substr($sEntryName, -1, 1) === '.' || strpos($sEntryName, '.') === false) {
                    return true; //file without extension
                }
                continue; //file with extension
            }
            if (substr($sEntryNameLower, -strlen($sFilterExtension)) === $sFilterExtension) {
                return true; //match extension
            }
        }
        return false;
    }

}
