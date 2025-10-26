<?php

namespace Autoframe\Core\Database\Orm\Blueprint;

use Autoframe\Core\Arr\Export\AfrArrExportArrayAsStringClass;

trait AfrBlueprintUtils
{
	/**
	 * Recursive blueprintMerge
	 * @param array $aOriginal
	 * @param array $aNew
	 * @return array
	 */
	public static function mergeBlueprint(array $aOriginal, array $aNew): array
	{
		$aOriginalKeys = array_keys($aOriginal);
		foreach ($aNew as $sNewKey => $mNewProfile) {
			if (!in_array($sNewKey, $aOriginalKeys) || $aOriginal[$sNewKey] === null) {
				$aOriginal[$sNewKey] = $mNewProfile;
			} elseif (is_array($aOriginal[$sNewKey]) && is_array($mNewProfile)) {
				$aOriginal[$sNewKey] = self::mergeBlueprint($aOriginal[$sNewKey], $mNewProfile);
			} elseif (is_integer($sNewKey)) {
				$aOriginal[] = $mNewProfile;
			} else {
				$aOriginal[$sNewKey] = $mNewProfile;
			}
		}
		return $aOriginal;
	}


	public static function exportArrayAsString(
		array  $aData,
		string $sQuot = "'",
		int    $iTab = 1,
		string $sEndOfLine = "\n",
		string $sPointComa = ';',
		string $sVarName = '$aBlueprint'
	): string
	{
		return AfrArrExportArrayAsStringClass::getInstance()->exportPhpArrayAsString(
			$aData,
			$sQuot,
			$sEndOfLine,
			$sPointComa,
			$sVarName,
			$iTab,
		);
	}

}