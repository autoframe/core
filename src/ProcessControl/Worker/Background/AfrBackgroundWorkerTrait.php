<?php
declare(strict_types=1);

namespace Autoframe\Core\ProcessControl\Worker\Background;

use Autoframe\Core\CliTools\AfrCheckExec;
use Autoframe\Core\Exception\AfrException;

trait AfrBackgroundWorkerTrait
{
	protected static string $sPhpBin = '';

	/**
	 * Returns /usr/bin/php or C:\xampp\php\php.exe or php
	 * @param bool $bStartInBackgroundOnWindows
	 * @return string
	 */
	public static function getPhpBin(bool $bStartInBackgroundOnWindows = false): string
	{
		if (empty(static::$sPhpBin)) {
			static::$sPhpBin = defined('PHP_BINARY') && is_file(PHP_BINARY) ? PHP_BINARY : 'php';
			if (static::$sPhpBin === 'php') { //fallback detect
				if (DIRECTORY_SEPARATOR === '\\') { //Windows
					$ini = php_ini_loaded_file(); //assuming that php.exe is in the same folder as php.ini
					$exe = $ini ? (substr($ini, 0, -3) . 'exe') : '';
					if ($exe && is_file($exe)) {
						static::$sPhpBin = $exe;
					}
				} else { //Unix
					if (is_file($phpBin = '/usr/bin/php')) {
						static::$sPhpBin = $phpBin;
					}
				}
			}
		}
		return ($bStartInBackgroundOnWindows && DIRECTORY_SEPARATOR === '\\' ? 'start /B ' : '') . static::$sPhpBin;
	}

	/**
	 * !!! IMPORTANT !!! called script should have `ignore_user_abort(true);` for the script to run in Background!
	 * Calls: php $execFileArgs > /dev/null & or widows equivalent
	 * @param string $execFileArgs
	 * @param bool $bStartInBackground
	 * @return void
	 * @throws AfrException
	 */
	public static function execWithArgs(string $execFileArgs, bool $bStartInBackground = true): void
	{
		if (substr($execFileArgs, 0, 4) === 'php ') {
			$execFileArgs = substr($execFileArgs, 4);
		}
		$call = static::getPhpBin($bStartInBackground) . ' ' . trim($execFileArgs);
		if(DIRECTORY_SEPARATOR !=='\\'){
			$call.= ' > /dev/null &';
		}
		static::dispatchExec($call);
	}

	/**
	 * Exec cli.
	 * @throws AfrException
	 */
	public static function execCli(string $sCliCommand): void
	{
		self::dispatchExec(trim($sCliCommand));
	}

	/**
	 * @param string $call
	 * @return void
	 * @throws AfrException
	 */
	protected static function dispatchExec(string $call): void
	{
		if (DIRECTORY_SEPARATOR === '\\') { //windows
			if (!AfrCheckExec::isPOpenCloseAvailable()) {
				throw new AfrException('popen / pclose functions not available in php.ini ➔ disable_functions');
			}
			pclose(popen($call, 'r'));
		} else { //unix
			if (!AfrCheckExec::isExecAvailable()) {
				throw new AfrException('exec function not available in php.ini ➔ disable_functions');
			}
			exec($call);
		}
	}

}
