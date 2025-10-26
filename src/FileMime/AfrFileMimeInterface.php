<?php
declare(strict_types=1);

namespace Autoframe\Core\FileMime;

interface AfrFileMimeInterface
{
	/**
	 * @return array
	 */
	public function getFileMimeTypes(): array;


	/**
	 * @return array
	 */
	public function getFileMimeExtensions(): array;


	/**
	 * @return string 'application/octet-stream'
	 */
	public function getFileMimeFallback(): string;


	/**
	 * Input: '/dir/test.wmz'
	 * Output: ['application/x-ms-wmz','application/x-msmetafile']
	 * wmz extension has multiple mimes
	 * @param string $sFileNameOrPath
	 * @return array
	 */
	public function getAllMimesFromFileName(string $sFileNameOrPath): array;

	/**
	 * Input: '/dir/test.jpg'
	 * Output: 'image/jpeg'
	 * @param string $sFileNameOrPath
	 * @return string
	 */
	public function getMimeFromFileName(string $sFileNameOrPath): string;


	/**
	 * Input: 'image/jpeg'
	 * Output: ['jpeg','jpg','jpe']
	 * @param string $sMime
	 * @return array
	 */
	public function getExtensionsForMime(string $sMime): array;

	/**
	 * Input: '/dir/test.jpg'
	 * Output: 'jpg'
	 * @param string $sFileNameOrPath
	 * @return string
	 */
	public function getExtensionFromPath(string $sFileNameOrPath): string;

}