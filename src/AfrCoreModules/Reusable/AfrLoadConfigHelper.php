<?php
namespace Autoframe\Core\AfrCoreModules\Reusable;

class AfrLoadConfigHelper{
	public static function loadConfigFile(string $sDir, string $sFile, bool $bCheckForExistence = null): ?array
	{
		$sPath = rtrim($sDir, '\/') . DIRECTORY_SEPARATOR . $sFile;
		if ($bCheckForExistence && !file_exists($sPath)) return null;
		$aConfig = include $sPath;
		return is_array($aConfig) ? $aConfig : null;
	}
}
