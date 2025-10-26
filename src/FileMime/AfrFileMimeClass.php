<?php
declare(strict_types=1);

namespace Autoframe\Core\FileMime;

use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;

class AfrFileMimeClass  extends AfrSingletonAbstractClass implements AfrFileMimeInterface
{
	use AfrFileMimeExtensions;
	use AfrFileMimeTypes;

	/**
	 * @return array
	 */
	public function getFileMimeTypes(): array
	{
		return self::$aAfrFileMimeTypes;
	}

	/**
	 * @return array
	 */
	public function getFileMimeExtensions(): array
	{
		return self::$aAfrFileMimeExtensions;
	}

	/**
	 * @return string 'application/octet-stream'
	 */
	public function getFileMimeFallback(): string
	{
		return 'application/octet-stream';
	}

	/**
	 * Input: '/dir/test.wmz'
	 * Output: ['application/x-ms-wmz','application/x-msmetafile']
	 * wmz extension has multiple mimes
	 * @param string $sFileNameOrPath
	 * @return array
	 */
	public function getAllMimesFromFileName(string $sFileNameOrPath): array
	{
		$aReturn = [];
		$sExt = strtolower($this->getExtensionFromPath($sFileNameOrPath));
		if (!empty($sExt)) {
			foreach (self::$aAfrFileMimeTypes as $sMine => $aExtensions) {
				if (in_array($sExt, $aExtensions)) {
					$aReturn[] = $sMine;
				}
			}
		}
		if (empty($aReturn)) {
			$aReturn[] = $this->getFileMimeFallback();
		}
		return $aReturn;
	}

	/**
	 * Input: '/dir/test.jpg'
	 * Output: 'image/jpeg'
	 * @param string $sFileNameOrPath
	 * @return string
	 */
	public function getMimeFromFileName(string $sFileNameOrPath): string
	{
		$sExt = strtolower($this->getExtensionFromPath($sFileNameOrPath));
		if (empty($sExt)) {
			return $this->getFileMimeFallback();
		} elseif (isset(self::$aAfrFileMimeExtensions[$sExt])) {
			return self::$aAfrFileMimeExtensions[$sExt];
		}
		return $this->getFileMimeFallback();
	}

	/**
	 * Input: 'image/jpeg'
	 * Output: ['jpeg','jpg','jpe']
	 * @param string $sMime
	 * @return array
	 */
	public function getExtensionsForMime(string $sMime): array
	{
		if (isset(self::$aAfrFileMimeTypes[$sMime])) {
			return self::$aAfrFileMimeTypes[$sMime];
		}
		return [];
	}

	/**
	 * Input: '/dir/test.jpg'
	 * Output: 'jpg'
	 * @param string $sFileNameOrPath
	 * @return string
	 */
	public function getExtensionFromPath(string $sFileNameOrPath): string
	{
		$aPath = pathinfo($sFileNameOrPath);
		return isset($aPath['extension']) && strlen($aPath['extension']) > 0 ? $aPath['extension'] : '';
	}
}