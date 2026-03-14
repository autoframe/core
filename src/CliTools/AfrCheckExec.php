<?php

namespace Autoframe\Core\CliTools;

final class AfrCheckExec
{
	private static ?bool $bExec = null;
	private static ?bool $bShellExec = null;
	private static ?bool $bPOpenClose = null;
	private static ?bool $bEchoTestExec = null;
	private static ?array $aDisableFunctions = null;

	/**
	 * Echo test exec taking average15 ms.
	 */
	public static function echoTestExecTakingAverage15Ms(): bool
	{
		if (self::$bEchoTestExec === null) {
			self::$bEchoTestExec = self::isExecAvailable() && trim(@exec('echo EXEC')) === 'EXEC';
		}
		return self::$bEchoTestExec;
	}
	/**
	 * Echo test shell exec taking average15 ms.
	 */
	public static function echoTestShellExecTakingAverage15Ms(): bool
	{
		if (self::$bEchoTestExec === null) {
			self::$bEchoTestExec = self::isShellExecAvailable() && trim(@shell_exec('echo EXEC')) === 'EXEC';
		}
		return self::$bEchoTestExec;
	}

	/**
	 * Is exec available.
	 */
	public static function isExecAvailable(): bool
	{
		if (self::$bExec === null) {
			self::$bExec = self::isFunctionEnabled('exec');
		}
		return self::$bExec;
	}
	/**
	 * Is popen close available.
	 */
	public static function isPOpenCloseAvailable(): bool
	{
		if (self::$bPOpenClose === null) {
			self::$bPOpenClose = self::isFunctionEnabled('pclose') && self::isFunctionEnabled('popen');
		}
		return self::$bPOpenClose;
	}
	/**
	 * Is proc open close available.
	 */
	public static function isProcOpenCloseAvailable(): bool
	{
		if (self::$bPOpenClose === null) {
			self::$bPOpenClose = self::isFunctionEnabled('proc_open') && self::isFunctionEnabled('proc_close');
		}
		return self::$bPOpenClose;
	}


	/**
	 * Is shell exec available.
	 */
	public static function isShellExecAvailable(): bool
	{
		if (self::$bShellExec === null) {
			self::$bShellExec = self::isFunctionEnabled('shell_exec');
		}
		return self::$bShellExec;
	}

	/**
	 * Is function enabled.
	 */
	public static function isFunctionEnabled(string $f): bool
	{
		if (self::$aDisableFunctions === null) {
			self::$aDisableFunctions = array_flip(array_map(
				'trim',
				explode(
					',',
					strtolower((string)(@ini_get('disable_functions')))
				)
			));
		}
		return !isset(self::$aDisableFunctions[$f]) && is_callable($f);
	}
}
