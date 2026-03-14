<?php
declare(strict_types=1);

namespace Autoframe\Core\FileSystem\SplitMergeCopyDir;

use Autoframe\Core\FileSystem\Recursive\AfrRecursiveRmDir;
use Autoframe\Core\FileSystem\SplitMerge\AfrSplitMergeInterface;
use Autoframe\Core\FileSystem\SplitMerge\AfrSplitMergeClass;
use Autoframe\Core\FileSystem\SplitMerge\Exception\AfrFileSystemSplitMergeException;
use Autoframe\Core\FileSystem\SplitMergeCopyDir\Exception\AfrFileSystemSplitMergeCopyDirException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

trait AfrSplitMergeCopyDirTrait
{

    protected static AfrSplitMergeInterface $AfrSplitMergeInterface;

    /**
     * Xet afr split merge interface.
     * @param AfrSplitMergeInterface|null $AfrSplitMergeInterface
     * @return AfrSplitMergeInterface
     */
    public function xetAfrSplitMergeInterface(
        AfrSplitMergeInterface $AfrSplitMergeInterface = null
    ): AfrSplitMergeInterface
    {
        if ($AfrSplitMergeInterface) {
            self::$AfrSplitMergeInterface = $AfrSplitMergeInterface;
        } elseif (empty(self::$AfrSplitMergeInterface)) {
            self::$AfrSplitMergeInterface = AfrSplitMergeClass::getInstance();
        }
        return self::$AfrSplitMergeInterface;
    }

    /**
     * Split copy dir.
     * @param string $sSourceDir
     * @param string $sDestinationDir
     * @param int $iPartSize
     * @param bool $bOverwriteFiles
     * @param bool $bUnlinkSourcePartsOnSuccess
     * @return int
     * @throws AfrFileSystemSplitMergeCopyDirException
     * @throws AfrFileSystemSplitMergeException
     */
    public function splitCopyDir(
        string $sSourceDir,
        string $sDestinationDir,
        int    $iPartSize,
        bool   $bOverwriteFiles,
        bool   $bUnlinkSourcePartsOnSuccess = false
    ): int
    {
        return $this->prepareCopyDir(
            __FUNCTION__,
            $sSourceDir,
            $sDestinationDir,
            $iPartSize,
            $bOverwriteFiles,
            $bUnlinkSourcePartsOnSuccess
        );
    }

    /**
     * @param string $sFx
     * @param string $sSourceDir
     * @param string $sDestinationDir
     * @param int $iPartSize
     * @param bool $bOverwrite
     * @param bool $bUnlinkSourcePartsOnSuccess
     * @return int
     * @throws AfrFileSystemSplitMergeCopyDirException
     * @throws AfrFileSystemSplitMergeException
     */
    protected function prepareCopyDir(
        string $sFx,
        string $sSourceDir,
        string $sDestinationDir,
        int    $iPartSize,
        bool   $bOverwrite,
        bool   $bUnlinkSourcePartsOnSuccess = false
    ): int
    {
        $sRealPathSourceDir = realpath($sSourceDir);
        if ($sRealPathSourceDir === false) {
            throw new AfrFileSystemSplitMergeCopyDirException(
                $sFx . ' Invalid source path: ' . $sSourceDir
            );
        }

        $sRealPathDestinationDir = $sDestinationDir === '' ? $sRealPathSourceDir : realpath($sDestinationDir);
        if ($sRealPathDestinationDir === false) {
            throw new AfrFileSystemSplitMergeCopyDirException(
                $sFx . ' Invalid destination path: ' . $sDestinationDir
            );
        }

        if ($sRealPathSourceDir === $sRealPathDestinationDir && $sDestinationDir === '') {
            $bUnlinkSourcePartsOnSuccess = true;
//            throw new AfrFileSystemSplitMergeCopyDirException(
//                $sFx . ' The source and destination can\'t be identical: ' . $sRealPathSourceDir
//            );
        }

        $aSources = $this->collectPathInfo($sRealPathSourceDir, $sFx);
        if (count($aSources['files']) < 1) {
            return 0;
        }

        foreach ($aSources['dirs'] as $aDirInfo) {
            $sDestDPath = $sRealPathDestinationDir . $aDirInfo['sRelativePath'];
            if (!is_dir($sDestDPath) && !mkdir($sDestDPath, $aDirInfo['iPerms'], true)) {
                throw new AfrFileSystemSplitMergeCopyDirException(
                    $sFx . ' Unable to create destination path: ' . $sDestDPath
                );
            }
        }

        $iTotalProcessed = 0;
        $oSplitCopy = $this->xetAfrSplitMergeInterface();
        foreach ($aSources['files'] as $sSourceFilePath => $aFileInfo) {
            if (@filetype($sSourceFilePath) !== 'file') {
                continue;
            }
            $sDestFilePath = $sRealPathDestinationDir . $aFileInfo['sRelativePath'];
            $sDestDirPath = substr($sDestFilePath, 0, -strlen($aFileInfo['sName']) - 1);

            if ($sFx === 'splitCopyDir') {
                $iShards = $oSplitCopy->split(
                    $sSourceFilePath,
                    $iPartSize,
                    $bOverwrite,
                    $sDestDirPath,
                    $bUnlinkSourcePartsOnSuccess
                );
                $iTotalProcessed += $iShards ? 1 : 0;
            } elseif ($sFx === 'mergeCopyDir') {
//                if (!$oSplitCopy->isFileFirstOfMergeShards($sSourceFilePath)) {
//                    continue;
//                }
                $iProcessed = $oSplitCopy->merge(
                    $sSourceFilePath,
                    $sSourceDir === $sDestDirPath ? '' : $sDestDirPath,
                    $bOverwrite,
                    true,
                    $bUnlinkSourcePartsOnSuccess
                );
                $iTotalProcessed += $iProcessed ? 1 : 0;
            } else {
                throw new AfrFileSystemSplitMergeCopyDirException($sFx . ' Unimplemented method!');
            }
        }
        if ($sFx === 'mergeCopyDir' && $bUnlinkSourcePartsOnSuccess && $sDestinationDir !== '') {
            foreach ($aSources['dirs'] as $sSourceDirPath => $aDirInfo) {
                @rmdir($sSourceDirPath);
            }
        }

        return $iTotalProcessed;
    }

    /**
     * @param string $sSourceDir
     * @param string $sParentFn
     * @return array
     * @throws AfrFileSystemSplitMergeCopyDirException
     */
    protected function collectPathInfo(string $sSourceDir, string $sParentFn): array
    {
        $aFound = ['dirs' => [], 'files' => []];
        $sRealPathSourceDir = realpath($sSourceDir);
        if ($sRealPathSourceDir === false) {
            throw new AfrFileSystemSplitMergeCopyDirException(
                $sParentFn ?: __FUNCTION__ . ' Invalid path: ' . $sSourceDir
            );
        }
        $aFiles = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sRealPathSourceDir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        /** @var SplFileInfo $oSplFileInfo */
        foreach ($aFiles as $oSplFileInfo) {
            $sFullFilePath = $oSplFileInfo->getRealPath();
            $sRelativePath = substr($sFullFilePath, strlen($sRealPathSourceDir));
            if (!$sRelativePath || strlen(trim($sRelativePath, '\/')) < 1) {
                continue;
            }
            $bIsDir = $oSplFileInfo->isDir();
            $type = $bIsDir ? 'dirs' : 'files';
            if (isset($aFound[$type][$sFullFilePath])) {
                continue;
            }
            $aFound[$type][$sFullFilePath] = [
                'sName' => $bIsDir ? basename($sFullFilePath) : $oSplFileInfo->getFilename(),
                'iSize' => $bIsDir ? 0 : $oSplFileInfo->getSize(),
                'bIdDir' => $bIsDir,
                'sRelativePath' => $sRelativePath,
                'sFullFilePath' => $sFullFilePath,
                'iPerms' => octdec(substr(decoct((int)$oSplFileInfo->getPerms()), -4)),
            ];
        }
        ksort($aFound['dirs'], SORT_NATURAL);
        ksort($aFound['files'], SORT_NATURAL);
        return $aFound;
    }

    /**
     * Merge copy dir.
     * @param string $sSourceDir
     * @param string $sDestinationDir
     * @param bool $bOverwriteFiles
     * @param bool $bUnlinkSourcePartsOnSuccess
     * @return int
     * @throws AfrFileSystemSplitMergeCopyDirException
     * @throws AfrFileSystemSplitMergeException
     */
    public function mergeCopyDir(
        string $sSourceDir,
        string $sDestinationDir = '',
        bool   $bOverwriteFiles = false,
        bool   $bUnlinkSourcePartsOnSuccess = false
    ): int
    {
        return $this->prepareCopyDir(
            __FUNCTION__,
            $sSourceDir,
            $sDestinationDir,
            0,
            $bOverwriteFiles,
            $bUnlinkSourcePartsOnSuccess
        );

    }

}
