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
	 * @return string
	 */
	public static function getPhpBin(): string
	{
		if (static::$sPhpBin) {
			return static::$sPhpBin;
		}
		$php = 'php';
		if (DIRECTORY_SEPARATOR === '\\') { //Windows
			$ini = php_ini_loaded_file(); //assuming that php.exe is in the same folder as php.ini
			$exe = $ini ? (substr($ini, 0, -3) . 'exe') : '';
			if ($exe && is_file($exe)) {
				$php = $exe;
			}
			return static::$sPhpBin = 'start /B ' . $php;
		} else { //Unix
			if (is_file($phpBin = '/usr/bin/php')) {
				return static::$sPhpBin = $phpBin;
			}
		}
		return static::$sPhpBin = $php;
	}

	/**
	 * !!! IMPORTANT !!! called script should have `ignore_user_abort(true);` for the script to run in Background!
	 * Calls: php $execFileArgs > /dev/null & or widows equivalent
	 * @param string $execFileArgs
	 * @return void
	 * @throws AfrException
	 */
	public static function execWithArgs(string $execFileArgs): void
	{
		if (substr($execFileArgs, 0, 4) === 'php ') {
			$execFileArgs = substr($execFileArgs, 4);
		}
		$call = static::getPhpBin() . ' ' . trim($execFileArgs);
		static::dispatchExec($call);
	}

	/**
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
			exec($call . ' > /dev/null &');
		}
	}

}