<?php

namespace Autoframe\Core\CliTools;

use Autoframe\Core\InterfaceToConcrete\AfrVendorPath;

class AfrVendorDir
{

	/**
	 * Path is inside vendor dir.
	 */
	public static function pathIsInsideVendorDir(string $sPath): bool
	{
		return AfrVendorPath::pathIsInsideVendorDir($sPath);
	}

	/**
	 * Get vendor path.
	 */
	public static function getVendorPath(): string
	{
		return AfrVendorPath::getVendorPath();
	}

	/**
	 * Get base dir path.
	 */
	public static function getBaseDirPath(): string
	{
		return AfrVendorPath::getBaseDirPath();
	}

	/**
	 * Get composer json.
	 */
	public static function getComposerJson(): array
	{
		return AfrVendorPath::getComposerJson();
	}

	/**
	 * Get composer ts.
	 */
	public static function getComposerTs(): int
	{
		return AfrVendorPath::getComposerTs();
	}


}
