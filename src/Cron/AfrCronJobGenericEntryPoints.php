<?php

namespace Autoframe\Core\Cron;

final class AfrCronJobGenericEntryPoints
{
	protected static array $aMatrix = [];

	/**
	 * Push into replace matrix.
	 */
	public static function pushIntoReplaceMatrix(string $sKeyToReplaceInPath, string $sValueToBeReplaced): void
	{
		self::$aMatrix[$sKeyToReplaceInPath] = $sValueToBeReplaced;
	}

	/**
	 * Get replace matrix.
	 */
	public static function getReplaceMatrix(): array
	{
		return self::$aMatrix;
	}

}
