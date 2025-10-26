<?php
declare(strict_types=1);

namespace Autoframe\Core\FileMime;

use Autoframe\Core\FileMime\Exception\AfrFileSystemMimeException;
use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;

use function file_get_contents;
use function file_put_contents;
use function filemtime;
use function is_file;
use function is_array;
use function ksort;
use function trim;
use function str_replace;
use function substr;
use function explode;
use function strtolower;
use function count;
use function basename;
use function implode;

/**
 * Utility reads the file 'mime.types' and updates the traits AfrFileMimeExtensions and AfrFileMimeTypes
 */
class AfrFileMimeGeneratorClass extends AfrSingletonAbstractClass
{
	protected string $sGeneratorMimeTypesPath = __DIR__ . DIRECTORY_SEPARATOR . 'mime.types';


	/**
	 * Keeps track of mime.types and regenerates AfrFileMimeExtensions and AfrFileMimeTypes traits if needed
	 * @param int $iMaxAgeSeconds
	 * @param int $iRegenerateTraitsIfxSecondsOlderThanMimes
	 * @param string $sUrl
	 * @return bool
	 * @throws AfrFileSystemMimeException
	 */
	public function synchronizeMimeTypesFromApache(
		int    $iMaxAgeSeconds = 3600 * 24 * 365 * 2,
		int    $iRegenerateTraitsIfxSecondsOlderThanMimes = 10,
		string $sUrl = 'https://svn.apache.org/repos/asf/httpd/httpd/trunk/docs/conf/mime.types'
	): bool
	{
		$bFileExistsMimeTypes = is_file($this->sGeneratorMimeTypesPath);
		$iMimeTypesTs = $bFileExistsMimeTypes ? filemtime($this->sGeneratorMimeTypesPath) : 0;

		if ($bFileExistsMimeTypes && $iMimeTypesTs + $iMaxAgeSeconds > time()) {
			if (!$this->traitsAreUpToDate($iRegenerateTraitsIfxSecondsOlderThanMimes)) {
				//traits are older + delta seconds
				$this->generateTraitsFromMimeTypes(-1); //regenerate traits
			}
			return true;
		}

		//update mime.types file
		$sRawRemoteData = file_get_contents($sUrl);
		list($aFileMimeExtensions, $aFileMimeTypes) = $this->AfrFileMimeGeneratorParseMimeTypes($sRawRemoteData);
		if (count($aFileMimeExtensions) < 500 || count($aFileMimeTypes) < 500) {
			//soft fail. perhaps the apache site in unreachable...
			trigger_error('Unable to parse: ' . $sUrl);
			return false; //unable to verify / parse new data
		}

		$bNewSaved = (bool)file_put_contents(
			$this->sGeneratorMimeTypesPath . '.new',
			'# ' . $sUrl . ' ' . gmdate("D, d M Y H:i:s \G\M\T") . "\n" .
			$sRawRemoteData
		);
		if (!$bNewSaved) {
			throw new AfrFileSystemMimeException('Unable to save: ' . $this->sGeneratorMimeTypesPath . '.new');
		}

		if ($bFileExistsMimeTypes) {
			if (!rename($this->sGeneratorMimeTypesPath, $this->sGeneratorMimeTypesPath . '.' . time() . '.bk')) {
				throw new AfrFileSystemMimeException('Unable to rename: ' . $this->sGeneratorMimeTypesPath);
			}
		}

		$bNewIsInPlace = rename($this->sGeneratorMimeTypesPath . '.new', $this->sGeneratorMimeTypesPath);
		if (!$bNewIsInPlace) {
			throw new AfrFileSystemMimeException('Unable to rename: ' . $this->sGeneratorMimeTypesPath . '.new');
		}

		$this->generateTraitsFromMimeTypes(-1); //regenerate traits
		return true;
	}

	/**
	 * if the file 'mime.types' in at least 10 seconds newer than the traits.
	 * @param int $iDeltaTs
	 * @return bool
	 * @throws AfrFileSystemMimeException
	 */
	public function traitsAreUpToDate(int $iDeltaTs = 10): bool
	{
		if (!is_file($this->sGeneratorMimeTypesPath)) {
			throw new AfrFileSystemMimeException('Config file is missing: ' . $this->sGeneratorMimeTypesPath);
		}
		$iMimeTypesTs = filemtime($this->sGeneratorMimeTypesPath);
		$bUpToDate = true;
		foreach (['AfrFileMimeExtensions', 'AfrFileMimeTypes'] as $sClassName) {
			if (
				!is_file(__DIR__ . DIRECTORY_SEPARATOR . $sClassName . '.php') ||
				$iMimeTypesTs > filemtime(__DIR__ . DIRECTORY_SEPARATOR . $sClassName . '.php') + $iDeltaTs
			) {
				$bUpToDate = false;
			}
		}
		return $bUpToDate;

	}

	/**
	 * This method reads the file 'mime.types' and updates the traits AfrFileMimeExtensions and AfrFileMimeTypes
	 * @return void
	 * @throws AfrFileSystemMimeException
	 */
	protected function generateTraitsFromMimeTypes(): void
	{

		list($aFileMimeExtensions, $aFileMimeTypes) = $this->AfrFileMimeGeneratorParseMimeTypes(
			file_get_contents($this->sGeneratorMimeTypesPath)
		);

		if (count($aFileMimeExtensions) < 500 || count($aFileMimeTypes) < 500) {
			throw new AfrFileSystemMimeException('Parse file failed: ' . $this->sGeneratorMimeTypesPath);
		}
		if (!$this->initFileMimeParseMimePhp("AfrFileMimeExtensions", $aFileMimeExtensions)) {
			throw new AfrFileSystemMimeException('Unable to write the file: ' . __DIR__ . '/AfrFileMimeExtensions.php');
		}
		if (!$this->initFileMimeParseMimePhp("AfrFileMimeTypes", $aFileMimeTypes)) {
			throw new AfrFileSystemMimeException('Unable to write the file: ' . __DIR__ . '/AfrFileMimeTypes.php');
		}

	}


	/**
	 * @return array[]
	 */
	protected function AfrFileMimeGeneratorParseMimeTypes(string $sFileContents): array
	{
		$sFileContents = str_replace("\r", "\n", $sFileContents);
		// Because someone does not deem worthy this mime types, I do:
		$sFileContents .= "\napplication/x-httpd-php 	php php3 php4 php5 php6 inc";
		$sFileContents .= "\napplication/x-httpd-php-source 	phps";

		$aFileMimeTypes = $aFileMimeExtensions = [];
		foreach (explode("\n", $sFileContents) as $sLine) {
			if (empty($sLine)) {
				continue;
			}
			$sLine = trim(str_replace("\t", ' ', $sLine));
			if (substr($sLine, 0, 1) == '#') {
				continue; // skip comments
			}
			$sMime = '';
			$aExt = [];
			foreach (explode(' ', $sLine) as $sPart) {
				if ($sPart) {
					if (!$sMime && strpos($sPart, '/') !== false) {
						$sMime = $sPart;
					} else {
						$aExt[] = strtolower($sPart);
					}
				}
			}
			if ($sMime && !empty($aExt)) {
				$aFileMimeTypes[$sMime] = $aExt;
				foreach ($aExt as $sExt) {
					$aFileMimeExtensions[$sExt] = $sMime;
				}
			}
		}
		ksort($aFileMimeTypes);
		ksort($aFileMimeExtensions);
		return array($aFileMimeExtensions, $aFileMimeTypes);
	}


	/**
	 * @param string $sClass
	 * @param array $aData
	 * @return false|int
	 */
	protected function initFileMimeParseMimePhp(string $sClass, array $aData)
	{
		$sTrait = "<?php\nnamespace " . __NAMESPACE__ . ";\n";
		$sTrait .= "//Updated in " . __NAMESPACE__ . '\\' . basename(__FILE__) . '->' . __FUNCTION__ . " based on mime.types\n";
		$sTrait .= "trait $sClass {\n";
		$sTrait .= 'public static array $a' . $sClass . " = [\n";
		foreach ($aData as $sKey => $mVal) {
			if (is_array($mVal)) {
				$sTrait .= "'$sKey' => ['" . implode("','", $mVal) . "'],\n";
			} else {
				$sTrait .= "'$sKey' => '$mVal',\n";
			}
		}
		$sTrait .= "];\n}";
		return file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . $sClass . '.php', $sTrait);
	}

}