<?php

namespace Autoframe\Core\Cron;

final class AfrCronJobGenericEntryPoints
{
	protected static array $aMatrix = [];

	public static function pushIntoReplaceMatrix(string $sKeyToReplaceInPath, string $sValueToBeReplaced): void
	{
		self::$aMatrix[$sKeyToReplaceInPath] = $sValueToBeReplaced;
	}

	public static function getReplaceMatrix(): array
	{
		return self::$aMatrix;
	}

}