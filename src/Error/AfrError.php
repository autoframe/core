<?php
declare(strict_types=1);

namespace Autoframe\Core\Error;

//TODO:  set_error_handler
use Autoframe\Core\Http\Request\AfrRequestClass;
use Autoframe\Core\String\AfrStr;
use Autoframe\Core\Tenant\AfrTenant;

class AfrError
{

	public function initErrorHandler()
	{
		// This storage is freed on error (case of allowed memory exhausted)
		$this->memory = str_repeat('*', 1024 * 2024);

		register_shutdown_function(function()
		{
			$this->memory = null;
			if ((!is_null($err = error_get_last())) && (!in_array($err['type'], array (E_NOTICE, E_WARNING))))
			{
				// $this->emergencyMethod($err);
			}
		});
		return $this;
	}
	const message_type_PHP_system_logger = 0;
	const message_type_email_to_destination = 1;
	const message_type_no_log = 2;
	const message_type_appended_to_destination_file = 3;
	const message_type_SAPI_logging_handler = 4;

	public static function errorHandler(int $errno, string $errstr, string $errfile, int $errline): bool
	{
		//TODO
	}

	public static function error_log(string  $message,
	                                 int     $message_type = 0,
	                                 ?string $destination = null,
	                                 ?string $additional_headers = null
	): bool
	{
		$aArgs = func_get_args();
		$aArgs[0] = $message . ' @' . static::getContext();
		return error_log(...$aArgs);
	}


	public static function getContext(): string
	{
		$sOut = '[' . (AfrTenant::getTenantAlias() ?? 'Any.Tenant') . '] ';
		if (!empty($_SERVER['REQUEST_URI'])) {
			$sOut .= ($_SERVER["SERVER_PROTOCOL"] ?? 'WEB') . ' ' . $_SERVER['REQUEST_URI'];
		} else {
			$sPhpSelf = $_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME'] ?? '';
			$sPathStart = substr($sPhpSelf, 0, 1);
			if (!in_array($sPathStart, ['\\', '/'])) {
				$sPhpSelf = $sPathStart === '.' ? substr($sPhpSelf, 1) : $sPhpSelf;
				$sPhpSelf = rtrim(getcwd(), '/\\') . DIRECTORY_SEPARATOR . ltrim($sPhpSelf, '/\\');
			}
			if (!empty($_SERVER['argv'])) {
				$_SERVER['argv'][0] = $sPhpSelf;
			}
			$sOut .= 'CLI ' . implode(
					' ',
					!empty($_SERVER['argv']) ? $_SERVER['argv'] : [$sPhpSelf]
				);
		}
		return $sOut;
	}

	/**
	 * @param int $iRemoveLastNLevels
	 * @param int $options 0 exlude ["object"] | 1  show all ["object"] AND ["args"] | 2 - exlude ["object"] AND ["args"]
	 * @param int $limit
	 * @return array
	 */
	public static function getMinifiedBacktrace(
		int $iRemoveLastNLevels = 1,
		int $options = DEBUG_BACKTRACE_PROVIDE_OBJECT,
		int $limit = 0
	): array
	{

		$aHuge = debug_backtrace($options, $limit);
		$aHuge = array_slice($aHuge, $iRemoveLastNLevels);
		$aStack = [];
		foreach ($aHuge as $iKey => & $aItem) {
			if (isset($aItem['object'])) {
				unset($aHuge[$iKey]['object']);
			}
			if (isset($aItem['args'])) {
				unset($aHuge[$iKey]['args']);
			}

			$aStack[] =
				($aItem['file'] ?? '---') . ':' .
				($aItem['line'] ?? '---') . ' > ' .
				($aItem['class'] ?? '---') . '::' .
				($aItem['function'] ?? '---') . ' - ' .
				($aItem['line'] ?? '---');

		}
		unset($aItem);
		return $aStack;
	}


	/**
	 * @param string $dir
	 * @param string $sExtension
	 * @param bool $bSerialize
	 * @return array
	 */
	public function logHttpRequestedToFile(string $dir = '.', string $sExtension = 'txt', bool $bSerialize = false): array
	{
		if (!$dir) {
			$dir = __DIR__;
		}
		if (!$dir) {
			$dir = '.'.DIRECTORY_SEPARATOR;
		}

		$aOut = AfrRequestClass::getInstance()->getRequestedFullData(true,true,true,true,true,true);

		$sFilename = date('Y-m-d_H-i-s_') .
			microtime() . '_' .
			$_SERVER['REQUEST_METHOD'] . '_' .
			$_SERVER['REQUEST_URI'] . '.' . $sExtension;
		$sFilename = str_replace(str_split('<>\/|:*?" ', 1), '_', $sFilename);
		$sFilename = rawurldecode($sFilename);
		$sPath = rtrim($dir, ' /\\') . DIRECTORY_SEPARATOR . $sFilename;

		file_put_contents($sPath, $bSerialize ? serialize($aOut) : print_r($aOut, true), FILE_APPEND);

		return $aOut;
	}

}

//trigger_error('nive err', E_USER_NOTICE);

function process_error_backtrace_general($errno, $errstr, $errfile, $errline, $errcontext, $args = false)
{
	if (!(error_reporting() & $errno)) return;//$debug thorr
	switch ($errno) {
		case E_WARNING      :
		case E_USER_WARNING :
		case E_STRICT       :
		case E_NOTICE       :
		case E_USER_NOTICE  :
			$type = 'warning';
			$fatal = false;
			break;
		default             :
			$type = 'fatal error';
			$fatal = true;
			break;
	}
	$trace = array_reverse(debug_backtrace());
	array_pop($trace);
	if (php_sapi_name() == 'cli') {
		echo 'Backtrace from ' . $type . ' \'' . $errstr . '\' at ' . $errfile . ' ' . $errline . ':' . "\n";
		foreach ($trace as $item) {
			echo '  ' . (isset($item['file']) ? $item['file'] : '<unknown file>') . ' ' . (isset($item['line']) ? $item['line'] : '<unknown line>') . ' calling ' . $item['function'] . '()' . "\n";
		}
	} else {
		if ($args) {
			$a = 'Args listfrom ' . $type . ' \'' . $errstr . '\' at ' . $errfile . ' ' . $errline . ':' . "\n";
		}
		echo '<p class="error_backtrace">' . "\n";
		echo '  Backtrace from ' . $type . ' \'' . $errstr . '\' at ' . $errfile . ' ' . $errline . ':' . "\n";
		echo '  <ol>' . "\n";
		foreach ($trace as $item) {
			echo '    <li>' . (isset($item['file']) ? $item['file'] : '<unknown file>') . ' ' . (isset($item['line']) ? $item['line'] : '<unknown line>') . ' calling ' . $item['function'] . '()</li>' . "\n";
			if ($args) {
				ob_start();
				echo $item['function'] . '  <ol>(';
				AfrStr::prea($item['args']);
				echo ')</ol>' . "\r\n";
				$a .= ob_get_contents();
				ob_end_clean();
			}
		}
		echo '  </ol>' . "\n";
		if ($args) {
			echo $a;
		}
		echo '</p>' . "\n";
	}
	if (ini_get('log_errors')) {
		$items = array();
		foreach ($trace as $item)
			$items[] = (isset($item['file']) ? $item['file'] : '<unknown file>') . ' ' . (isset($item['line']) ? $item['line'] : '<unknown line>') . ' calling ' . $item['function'] . '()';
		$message = 'Backtrace from ' . $type . ' \'' . $errstr . '\' at ' . $errfile . ' ' . $errline . ': ' . join(' | ', $items);
		error_log($message);
	}
	if ($fatal) exit(1);
}

function process_error_backtrace_ext($errno, $errstr, $errfile, $errline, $errcontext)
{
	process_error_backtrace_general($errno, $errstr, $errfile, $errline, $errcontext, true);
}


if (isset($debug_thorr) && $debug_thorr && isset($debug_thorr_ext) && $debug_thorr_ext) {
	set_error_handler('process_error_backtrace_ext');
} elseif (isset($debug_thorr) && $debug_thorr) {
	set_error_handler('process_error_backtrace_general');
}