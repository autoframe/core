<?php
declare(strict_types=1);

namespace Autoframe\Core\FileSystem\DirPath;

use Autoframe\Core\FileSystem\DirPath\Exception\AfrFileSystemDirPathException;

interface AfrDirPathInterface
{
    /**
     * the call filetype()=="dir" is clearly faster than the is_dir() call
     * @param string $sDirPath
     * @return bool
     */
    public function isDir(string $sDirPath): bool;

    /**
     * @param string $sDirPath
     * @param $context
     * @return false|resource
     * @throws AfrFileSystemDirPathException
     */
    public function openDir(string $sDirPath, $context = null);

    /**
     * Detect path slash style: /
     * @param string $sDirPath
     * @return string
     */
    public function detectDirectorySeparatorFromPath(string $sDirPath): string;

    /**
     * Remove final slash from a directory path
     * @param string $sDirPath
     * @return string
     */
    public function removeFinalSlash(string $sDirPath): string;

    /**
     * Add a final slash to a directory path
     * @param string $sDirPath
     * @return string
     */
    public function addFinalSlash(string $sDirPath): string;

    /**
     * Make the dir path to a uniform path for cross system like windows to unix and keep existing slash format
     * @param string $sDirPath
     * @return string
     */
    public function makeUniformSlashStyle(string $sDirPath): string;

    /**
     * Make the dir path to a uniform path for cross system like windows to unix
     * Full fix for a full directory path
     * @param string $sDirPath
     * @param bool $bWithFinalSlash
     * @param bool $bCorrectSlashStyle
     * @return string
     */
    public function correctDirPathFormat(string $sDirPath, bool $bWithFinalSlash = false, bool $bCorrectSlashStyle = true): string;

    /**
     * IN: 'this/is/../a/./test/.///is'
     * OUT: 'this/a/test/is'
     * This method does not check for the correctness of the path, just does some cleanup
     * @param string $sPath
     * @return string
     */
    public function simplifyAbsolutePath(string $sPath): string;

    /**
     * Force path to single slash style
     * @param string $sPath
     * @return string
     */
    public function fixDs(string $sPath): string;

	/**
	 * @param string $path
	 * @param bool $bCheckExistence
	 * @return false|string
	 */
	public function realpath(string $path, bool $bCheckExistence);
}