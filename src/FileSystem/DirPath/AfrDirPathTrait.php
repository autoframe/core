<?php
declare(strict_types=1);

namespace Autoframe\Core\FileSystem\DirPath;

use Autoframe\Core\FileSystem\DirPath\Exception\AfrFileSystemDirPathException;
use function filetype;
use function opendir;
use function substr;
use function substr_count;
use function in_array;
use function rtrim;
use function array_diff;
use function count;
use function strpos;
use function array_fill;
use function str_replace;
use function array_filter;
use function explode;
use function array_pop;
use function implode;

trait AfrDirPathTrait
{
    /**
     * the call filetype()=="dir" is clearly faster than the is_dir() call
     * @param string $sDirPath
     * @return bool
     */
    public function isDir(string $sDirPath): bool
    {
        //Possible values are fifo, char, dir, block, link, file, socket and unknown.
        return @filetype($sDirPath) === 'dir';
    }

    /**
     * @param string $sDirPath
     * @param $context
     * @return false|resource
     * @throws AfrFileSystemDirPathException
     */
    public function openDir(string $sDirPath, $context = null)
    {
        try {
            if ($context) {
                $resource = opendir($sDirPath, $context);
            } else {
                $resource = opendir($sDirPath);
            }
        } catch (\Exception $ex) {
            throw new AfrFileSystemDirPathException('Unable to open directory: ' . $sDirPath);
        }
        return $resource;
    }

    /**
     * Detect path slash style: /
     * @param string $sDirPath
     * @return string
     */
    public function detectDirectorySeparatorFromPath(string $sDirPath): string
    {
        if (
            substr($sDirPath, 0, 2) === '\\\\' || # Detect Windows network path \\192.168.0.1\share
            substr($sDirPath, 1, 2) == ':\\'  #Detect Windows drive path C:\Dir
        ) {
            return '\\';
        }

        $iWinDs = substr_count($sDirPath, '\\');
        $iUnixDs = substr_count($sDirPath, '/');
        if ($iWinDs + $iUnixDs < 1) {
            return DIRECTORY_SEPARATOR;
        } elseif ($iWinDs > $iUnixDs) {
            return '\\';
        }
        return '/';
    }

    /**
     * Remove final slash from a directory path
     * @param string $sDirPath
     * @return string
     */
    public function removeFinalSlash(string $sDirPath): string
    {
        return rtrim($sDirPath, '\/');
    }

    /**
     * Add a final slash to a directory path
     * @param string $sDirPath
     * @return string
     */
    public function addFinalSlash(string $sDirPath): string
    {
        return rtrim($sDirPath, '\/') . $this->detectDirectorySeparatorFromPath($sDirPath);
    }

    /**
     * @param string $sDirPath
     * @param string $sSlashFormat
     * @return string
     */
    protected function correctSlashStyleMethod(string $sDirPath, string $sSlashFormat): string
    {
        $aSearch = array_diff(['/', '\\'], [$sSlashFormat]);
        $iTypes = count($aSearch);
        if ($iTypes) {
            foreach ($aSearch as $sDs) {
                if (strpos($sDirPath, $sDs) !== false) {
                    $aReplace = array_fill(0, $iTypes, $sSlashFormat);
                    return str_replace($aSearch, $aReplace, $sDirPath);
                }
            }
        }
        return $sDirPath;
    }

    /**
     * Make the path for FILE and DIR to a uniform path for cross system like windows to unix and keep existing slash format
     * @param string $sDirPath
     * @return string
     */
    public function makeUniformSlashStyle(string $sDirPath): string
    {
        return $this->correctSlashStyleMethod(
            $sDirPath,
            $this->detectDirectorySeparatorFromPath($sDirPath)
        );
    }

    /**
     * Make the dir path to a uniform path for cross system like windows to unix
     * Full fix for a full directory path
     * @param string $sDirPath
     * @param bool $bWithFinalSlash
     * @param bool $bCorrectSlashStyle
     * @return string
     */
    public function correctDirPathFormat(
        string $sDirPath,
        bool   $bWithFinalSlash = false,
        bool   $bCorrectSlashStyle = true
    ): string
    {
        $sSlashStyle = $this->detectDirectorySeparatorFromPath($sDirPath);
        $sDirPath = $this->removeFinalSlash($sDirPath) . ($bWithFinalSlash ? $sSlashStyle : '');
        if ($bCorrectSlashStyle) {
            $sDirPath = $this->correctSlashStyleMethod($sDirPath, $sSlashStyle);
        }
        return $sDirPath;
    }

    /**
     * IN: 'this/is/../a/./test/.///is'
     * OUT: 'this/a/test/is'
     * This method does not check for the correctness of the path, just does some cleanup
     * @param string $sPath
     * @return string
     */
    public function simplifyAbsolutePath(string $sPath): string
    {
        $sDs = $this->detectDirectorySeparatorFromPath($sPath);
        $sPath = str_replace(['/', '\\'], $sDs, $sPath);
        $aAbsolutes = [];
        foreach (array_filter(explode($sDs, $sPath), 'strlen') as $sSegment) {
            if ('.' === $sSegment) {
                continue;
            }
            if ('..' === $sSegment && count($aAbsolutes) > 0) {
                array_pop($aAbsolutes);
            } else {
                $aAbsolutes[] = $sSegment;
            }
        }
        return implode($sDs, $aAbsolutes);
    }

    /**
     * Force path to single slash style
     * @param string $sPath
     * @return string
     */
    public function fixDs(string $sPath): string
    {
        if (
            substr($sPath, 0, 2) === '\\\\' || # Detect Windows network path \\192.168.0.1\share
            substr($sPath, 1, 2) == ':\\'  #Detect Windows drive path C:\Dir
        ) {
            $sFromDs = '/';
            $sToDs = '\\';
        } else {
            $sFromDs = DIRECTORY_SEPARATOR === '/' ? '\\' : '/';
            $sToDs = DIRECTORY_SEPARATOR;
        }
        return strtr($sPath, $sFromDs, $sToDs);
    }

	/**
	 * @param string $path
	 * @param bool $bCheckExistence
	 * @return false|string
	 */
	public function realpath(string $path, bool $bCheckExistence)
	{
		if(substr($path, 0, 2) === '\\\\') {
			return $path; //widows network share
		}
		if($bCheckExistence){
			return realpath($path);
		}
		$path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
		$absolutes = [];
		foreach (array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen') as $part) {
			if ('.' == $part) continue;
			if ('..' == $part) {
				array_pop($absolutes);
			} else {
				$absolutes[] = $part;
			}
		}
		return implode(DIRECTORY_SEPARATOR, $absolutes);
	}
}
