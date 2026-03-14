<?php
declare(strict_types=1);

namespace Autoframe\Core\FileSystem\OverWrite;

use function file_exists;
use function file_put_contents;
use function ceil;
use function unlink;
use function usleep;
use function is_writable;
use function rename;

/**
 * It tries several times to overwrite a file, using a timeout and a maximum retry time
 */
trait AfrOverWriteTrait
{
    /**
     * Over write file.
     * @param string $sFilePath
     * @param string $sData
     * @param int $iMaxRetryMs
     * @param float $fDeltaSleepMs
     * @return bool
     */
    public function overWriteFile(
        string $sFilePath,
        string $sData,
        int    $iMaxRetryMs = 1500,
        float  $fDeltaSleepMs = 6.5
    ): bool
    {
        if (!file_exists($sFilePath)) {
            return file_put_contents($sFilePath, $sData) !== false;
        }
        $sFilePathAlt = $sFilePath . '.lock';
        if ($iMaxRetryMs < 1) { //at least 1
            $iMaxRetryMs = 1;
        }
        if ($fDeltaSleepMs < 0.01) { //at least 0.01
            $fDeltaSleepMs = 0.01;
        }

        $iDeltaUs = (int)ceil($fDeltaSleepMs * 1000);
        for ($i = 0; $i < $iMaxRetryMs; $i += $fDeltaSleepMs) {
            if (!file_exists($sFilePathAlt) || filemtime($sFilePathAlt) < time() - ceil($iMaxRetryMs / 1000)) {
                if (file_put_contents($sFilePathAlt, $sData) === false) {
                    @unlink($sFilePathAlt); //broken?
                }
                usleep($iDeltaUs);
            }
            if (file_exists($sFilePathAlt) && is_writable($sFilePathAlt) && is_writable($sFilePath)) {
                if (@rename($sFilePathAlt, $sFilePath)) {
                    usleep(15000);
                    if (file_get_contents($sFilePath) === $sData) {
                        return true;
                    }
                    continue;

                }
            }
            usleep($iDeltaUs);
        }
        return false;
    }
}
