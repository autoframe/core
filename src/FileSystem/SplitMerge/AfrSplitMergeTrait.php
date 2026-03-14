<?php
declare(strict_types=1);

namespace Autoframe\Core\FileSystem\SplitMerge;

use Autoframe\Core\FileSystem\SplitMerge\Exception\AfrFileSystemSplitMergeException;

trait AfrSplitMergeTrait
{

    /**
     * Split.
     * @param string $sSourceFullFilePath
     * @param int $iPartSize
     * @param bool $bOverwrite
     * @param string $sOtherDestinationDirectoryPath
     * @param bool $bUnlinkSource
     * @return int
     * @throws AfrFileSystemSplitMergeException
     */
    public function split(
        string $sSourceFullFilePath,
        int    $iPartSize,
        bool   $bOverwrite,
        string $sOtherDestinationDirectoryPath = '',
        bool   $bUnlinkSource = false
    ): int
    {

        if ($iPartSize < 1) {
            throw new AfrFileSystemSplitMergeException(
                'You can not split a file to zero or negative byte sizes!'
            );
        }
        if (!$this->isFilePathSplit($sSourceFullFilePath)) {
            throw new AfrFileSystemSplitMergeException(
                'File can not be split because it is missing: ' . $sSourceFullFilePath
            );
        }
        $iFileSize = filesize($sSourceFullFilePath);
        $iTotalParts = max((int)ceil($iFileSize / $iPartSize), 1);
        $iExtLength = strlen((string)$iTotalParts); //pad with zero up to

        $sSourceFullFilePath = realpath($sSourceFullFilePath);
        if (!$sOtherDestinationDirectoryPath || realpath($sOtherDestinationDirectoryPath) === false) {
            $sOtherDestinationDirectoryPath = pathinfo($sSourceFullFilePath)['dirname'];
        }
        if (!is_dir($sOtherDestinationDirectoryPath)) {
            throw new AfrFileSystemSplitMergeException(
                'File can not be split because destination directory is missing: ' . $sOtherDestinationDirectoryPath
            );
        }

        $sDestinationFullFilePath = $sOtherDestinationDirectoryPath . DIRECTORY_SEPARATOR . basename($sSourceFullFilePath);

        if ($iTotalParts < 2) {
            if ($sDestinationFullFilePath !== $sSourceFullFilePath) {
                $bAction = $bUnlinkSource ?
                    rename($sSourceFullFilePath, $sDestinationFullFilePath) :
                    copy($sSourceFullFilePath, $sDestinationFullFilePath);
                if (!$bAction) {
                    throw new AfrFileSystemSplitMergeException(
                        'File can not be splitted because into destination path: ' . $sOtherDestinationDirectoryPath
                    );
                }
            }
            return $iTotalParts;
        }


        $sourceStream = fopen($sSourceFullFilePath, 'r');
        for ($iCurrentPart = 1; $iCurrentPart <= $iTotalParts; $iCurrentPart++) {
            $sNewFilePath = $this->getPadName($sDestinationFullFilePath, $iCurrentPart, $iExtLength);


            $writeStream = fopen($sNewFilePath, $bOverwrite ? 'w' : 'x');
            if (!$writeStream) {
                throw new AfrFileSystemSplitMergeException(
                    'File already exists and can not be overwritten:  ' . $sNewFilePath
                );
            }

            $iOffset = ($iCurrentPart - 1) * $iPartSize;
            $iOffsetEnd = min($iOffset + $iPartSize, $iFileSize);
            $iRWBuffer = min($iOffsetEnd - $iOffset, 1024 * 64);

            fseek($sourceStream, $iOffset);
            while (($sData = fread($sourceStream, $iRWBuffer))) {
                fwrite($writeStream, $sData);
                $iOffset += $iRWBuffer;
                if ($iOffset >= $iOffsetEnd) {
                    break;
                }
                $iRWBuffer = min($iOffsetEnd - $iOffset, 1024 * 64);
            }
            fclose($writeStream);
        }
        fclose($sourceStream);
        if ($bOverwrite) {
            $sFilePathTail = $this->getPadName($sDestinationFullFilePath, $iCurrentPart, $iExtLength);
            if ($this->isFilePathSplit($sFilePathTail)) {
                rename($sFilePathTail, $sFilePathTail . '.tail' . time());
            }
        }
        $iCurrentPart--;
        if ($iCurrentPart > 1 && $bUnlinkSource) {
            unlink($sSourceFullFilePath);
        }
        return $iCurrentPart;
    }

    protected function getPadName(string $sFullFilePath, int $iCurrentPart, int $iExtLength): string
    {
        if (strlen((string)$iCurrentPart) < $iExtLength) {
            return $sFullFilePath . '.' . str_pad(
                    (string)$iCurrentPart,
                    $iExtLength,
                    '0',
                    STR_PAD_LEFT
                );
        }
        return $sFullFilePath . '.' . $iCurrentPart;
    }

    /**
     * @param $sFullFilePath
     * @return bool
     */
    protected function isFilePathSplit($sFullFilePath): bool
    {
        return @filetype($sFullFilePath) === 'file';
    }

    /**
     * Merge.
     * @param string $sFirstPartPath
     * @param string $sOtherDestinationDirectoryPath
     * @param bool $bOverWriteDestination
     * @param bool $bValidatePartList
     * @param bool $bUnlinkSourcePartsOnSuccess
     * @return int
     * @throws AfrFileSystemSplitMergeException
     */
    public function merge(
        string $sFirstPartPath,
        string $sOtherDestinationDirectoryPath = '',
        bool   $bOverWriteDestination = true,
        bool   $bValidatePartList = true,
        bool   $bUnlinkSourcePartsOnSuccess = false
    ): int
    {
        if (!$this->isFilePathSplit($sFirstPartPath)) {
            throw new AfrFileSystemSplitMergeException(
                'Merge failed because first segment is missing: ' . $sFirstPartPath
            );
        }
        $aPathInfo = pathinfo($sFirstPartPath);
        foreach (['dirname', 'basename', 'extension', 'filename'] as $sKey) {
            $aPathInfo[$sKey] = $aPathInfo[$sKey] ?? '';
        }
        $bIsFileFirstOfMergeShards = $this->isFileFirstOfMergeShards($sFirstPartPath);

        if (strlen($aPathInfo['filename']) < 1 && $bIsFileFirstOfMergeShards) {
            throw new AfrFileSystemSplitMergeException(
                'Merge failed because first segment has invalid filename: ' . $sFirstPartPath
            );
        }

        if ($sOtherDestinationDirectoryPath && is_dir($sOtherDestinationDirectoryPath)) {
            $sDestinationFullFilePath = $sOtherDestinationDirectoryPath . DIRECTORY_SEPARATOR . $aPathInfo['filename'];
        } else {
            $sDestinationFullFilePath = substr($sFirstPartPath, 0, -1 - strlen($aPathInfo['extension']));
        }

        if (!$bOverWriteDestination && $this->isFilePathSplit($sDestinationFullFilePath)) {
            throw new AfrFileSystemSplitMergeException(
                'File can not be merge because file will be overwritten: ' . $sDestinationFullFilePath . "\nFrom: $sFirstPartPath"
            );
        }

        /* $aPathInfo = pathinfo($sFirstPartPath);
         $aPathInfo['extension'] = $aPathInfo['extension'] ?? '';
         $iExtensionLength = strlen($aPathInfo['extension']);
         if (
             $bValidatePartList &&
             !$this->validatePartNumberBeforeMerge($aParts,$iExtensionLength)
         ) {
             $aParts = [];
         }*/

        $aParts = $this->getDirPartList($sFirstPartPath, $bValidatePartList);

        if (!$bIsFileFirstOfMergeShards) {
//            return 1;
//            throw new AfrFileSystemSplitMergeException(
//                'Merge failed because first segment has invalid extension: ' . $sFirstPartPath . ' [' . $aPathInfo['extension'] . ']'
//            );

            $sDestinationFullFilePath = substr(
                    $sDestinationFullFilePath,
                    0,
                    -strlen($aPathInfo['filename'])
                ) . $aPathInfo['basename'];
//            $aParts['$sFirstPartPath'] = $sFirstPartPath;
//            $aParts['$sDestinationFullFilePath'] = $sDestinationFullFilePath;
//            $aParts['$bUnlinkSourcePartsOnSuccess'] = $bUnlinkSourcePartsOnSuccess;
//            file_put_contents($sDestinationFullFilePath . '_INFO.txt', print_r($aParts, true));
            if (count($aParts) < 2 && $sFirstPartPath !== $sDestinationFullFilePath) {
                $bAction = $bUnlinkSourcePartsOnSuccess ?
                    rename($sFirstPartPath, $sDestinationFullFilePath) :
                    copy($sFirstPartPath, $sDestinationFullFilePath);
                if (!$bAction) {
                    throw new AfrFileSystemSplitMergeException(
                        'File can not be splitted because into destination path: ' . $sOtherDestinationDirectoryPath
                    );
                }
            }
            return 1;
        }

        $bBlind = $this->blindMerge($sDestinationFullFilePath, $aParts);
        /* file_put_contents(__CLASS__.__FUNCTION__.time().rand(100,999),print_r([
             '$sFirstPartPath'=> $sFirstPartPath ,
             '$bValidatePartList'=> $bValidatePartList ,
             '$sDestinationFullFilePath'=> $sDestinationFullFilePath ,
             '$aParts'=> $aParts ,
             '$bBlind'=> $bBlind ,
         ],true));*/
        if ($bBlind && $bUnlinkSourcePartsOnSuccess && $bValidatePartList && count($aParts) > 1) {
            foreach ($aParts as $sPathToUnlink) {
                unlink($sPathToUnlink);
            }
        }

        return $bBlind ? count($aParts) : 0;
    }

    /**
     * Is file first of merge shards.
     * @param string $sFullFilePath
     * @return bool
     */
    public function isFileFirstOfMergeShards(string $sFullFilePath): bool
    {
        return
            ltrim(pathinfo($sFullFilePath, PATHINFO_EXTENSION), '0') === '1' &&
            $this->isFilePathSplit(substr($sFullFilePath, 0, -1) . '2');
    }

    /**
     * @throws AfrFileSystemSplitMergeException
     */
    protected function validatePartNumberBeforeMerge(array $aParts, int $iExtensionLength): bool
    {
        $iTotalParts = count($aParts);
        if ($iTotalParts < 1) {
            return false;
        }
        $aExpectedExtAndSize = [];
        for ($i = 1; $i <= $iTotalParts; $i++) {
            $sExpected = substr($this->getPadName('', $i, $iExtensionLength), 1);
            $aExpectedExtAndSize[$sExpected] = 0;
        }
        $sLastExtInSeries = $sExpected;
        foreach ($aParts as $sPartPath) {
            $sExpectedPartToCheck = substr($sPartPath, -$iExtensionLength, $iExtensionLength);
            if (isset($aExpectedExtAndSize[$sExpectedPartToCheck])) {
                $aExpectedExtAndSize[$sExpectedPartToCheck] = filesize($sPartPath);
            }
        }
        $iSeriesPartSize = -1;
        foreach ($aExpectedExtAndSize as $sExt => $iFileSize) {
            if ($iSeriesPartSize < 0) {
                $iSeriesPartSize = $iFileSize;
            }
            if ((string)$sExt === $sLastExtInSeries && $iFileSize > 0 && $iFileSize <= $iSeriesPartSize) {
                continue; //last segment has a smaller size, but not zero
            }
            if ($iSeriesPartSize !== $iFileSize) {
                throw new AfrFileSystemSplitMergeException(
                    'Invalid file size in merging series: ' .
                    substr($sPartPath, 0, -$iExtensionLength) . $sExt . '; Expected ' .
                    "$iSeriesPartSize bytes but found $iFileSize bytes!"
                );
            }
        }
        foreach ($aParts as $sPartPath) {
            $sExpectedPartToCheck = substr($sPartPath, -$iExtensionLength, $iExtensionLength);
            if (!isset($aExpectedExtAndSize[$sExpectedPartToCheck])) {
                throw new AfrFileSystemSplitMergeException(
                    'Invalid file in merging series: ' . $sPartPath
                );
            }
        }
        return true;
    }


    /**
     * @param string $sFirstPartPath
     * @param bool $bValidatePartList
     * @return array
     * @throws AfrFileSystemSplitMergeException
     */
    protected function getDirPartList(string $sFirstPartPath, bool $bValidatePartList = true): array
    {
        $aParts = [];

        $aPathInfo = pathinfo($sFirstPartPath);
        foreach (['dirname', 'basename', 'filename', 'extension'] as $sKey) {
            $aPathInfo[$sKey] = $aPathInfo[$sKey] ?? '';
        }
        $iFileNameLength = strlen($aPathInfo['filename']);
        $iDirNameLength = strlen($aPathInfo['dirname']);
        $iExtensionLength = strlen($aPathInfo['extension']);

        $rDir = opendir($aPathInfo['dirname']);
        if (empty($rDir)) {
            throw new AfrFileSystemSplitMergeException(
                'Unable to merge files from dir: ' . $aPathInfo['dirname']
            );
        }
        while ($sDirFile = readdir($rDir)) {
            $sFilePath = ($iDirNameLength ?
                    substr($sFirstPartPath, 0, $iDirNameLength + 1) :
                    ''
                ) . $sDirFile;
            if (
                strlen($sDirFile) === strlen($aPathInfo['basename']) &&
                substr($sDirFile, 0, $iFileNameLength) === $aPathInfo['filename'] &&
                substr($sDirFile, $iFileNameLength, 1) === '.' &&
                $this->isFilePathSplit($sFilePath)
            ) {
                $aParts[] = $sFilePath;
            }
        }
        closedir($rDir);
        sort($aParts, SORT_NATURAL);

        if ($bValidatePartList && count($aParts) !== 1 && !$this->validatePartNumberBeforeMerge($aParts, $iExtensionLength)) {
            return [];
        }

        return $aParts;
    }

    /**
     * Blind merge.
     * @param string $sDestinationFilePath
     * @param array $aParts
     * @return void
     * @throws AfrFileSystemSplitMergeException
     */
    public function blindMerge(string $sDestinationFilePath, array $aParts): bool
    {
        if (count($aParts) < 1) {
            return false;
        }
        if (in_array($sDestinationFilePath, $aParts)) {
            throw new AfrFileSystemSplitMergeException(
                'Unable to merge into: ' . $sDestinationFilePath . ' because this path is found $aParts'
            );
        }
        if (count($aParts) === 1) {
            if (!rename($aParts[0], $sDestinationFilePath)) {
                throw new AfrFileSystemSplitMergeException(
                    'Unable to merge/rename single merge destination: ' . $sDestinationFilePath
                );
            }
            return true;
        }

        $destinationStream = fopen($sDestinationFilePath, 'w');
        if (!$destinationStream) {
            throw new AfrFileSystemSplitMergeException(
                'Unable to open for write merge destination: ' . $sDestinationFilePath
            );
        }

        foreach ($aParts as $sPartToMergePath) {
            $readStream = fopen($sPartToMergePath, 'r');
            if (!$readStream) {
                throw new AfrFileSystemSplitMergeException(
                    'Unable to read file for merging: ' . $sPartToMergePath
                );
            }
            while (($sData = fread($readStream, 2048))) {
                if (fwrite($destinationStream, $sData) === false) {
                    throw new AfrFileSystemSplitMergeException(
                        'Unable to write data to merge destination: ' . $sDestinationFilePath
                    );
                }
            }
            fclose($readStream);
        }

        if (fclose($destinationStream) === false) {
            throw new AfrFileSystemSplitMergeException(
                'Unable to write/close merge destination: ' . $sDestinationFilePath
            );
        }
        return true;
    }

}
