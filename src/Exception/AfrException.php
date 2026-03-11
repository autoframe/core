<?php
declare(strict_types=1);

namespace Autoframe\Core\Exception;

use Autoframe\Core\Event\AfrEvent;
use Exception;
use Throwable;

/**
 * If you don't like the default exception class, you can use your own:
 * define('Autoframe\Core\Exception\SWAP_AFR_EXCEPTION','..dir../CustomAfrException.php');
 */
if (defined(__NAMESPACE__ . '\SWAP_AFR_EXCEPTION')) {
	$phpInclude = constant(__NAMESPACE__ . '\SWAP_AFR_EXCEPTION');
	if (file_exists($phpInclude)) {
		include_once($phpInclude);
	}
}

if (!class_exists(__NAMESPACE__ . '\AfrException', false)) {

	class AfrException extends Exception implements Throwable
	{
		protected static array $iLoopLevel = [];
		/**
		 * Construct the exception. Note: The message is NOT binary safe.
		 * Redefine the exception so message isn't optional
		 * @link https://php.net/manual/en/exception.construct.php
		 * @param string $message [optional] The Exception message to throw.
		 * @param int $code [optional] The Exception code.
		 * @param null|Throwable $previous [optional] The previous throwable used for the exception chaining.
		 */
		public function __construct($message, $code = 0, Throwable $previous = null)
		{

			if(isset(self::$iLoopLevel[get_class($this)])){
				debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
				die('Circular Exceptions detected!'."\n".$message);
			}
			self::$iLoopLevel[get_class($this)]=true;

			parent::__construct($message, $code, $previous);
			//debug_print_backtrace();die("\n\n\t$message\n\n");
			if (!$this instanceof \Autoframe\Core\Event\Exception\AfrEventException) {
				//prevent infinite looping
				try {
					\Autoframe\Core\Event\AfrEvent::dispatchEvent(
						AfrEvent::AFR_EXCEPTION,
						[$message, $code, get_class($this)]
					);
				} catch (\Autoframe\Core\Event\Exception\AfrEventException $e) {
				}
			}
			unset(self::$iLoopLevel[get_class($this)]);
		}

		/**
		 * String representation of the exception
		 * @link https://php.net/manual/en/exception.tostring.php
		 * @return string the string representation of the exception.
		 */
		public function __toString(): string
		{
			$sCode = $this->code ? " {code.{$this->code}}" : '';
			$aClass = get_class($this);
		//	$aClass = explode('\\', get_class($this)); $aClass = end($aClass);
			$sMsg = $aClass . $sCode . "\n" . $this->message;
			if (($bIsCli = http_response_code() === false)) {
				return
					(\Autoframe\Core\CliTools\AfrCliTextColors::getInstance()->colorRed('')->textGet()) .
					$sMsg .
					(\Autoframe\Core\CliTools\AfrCliTextColors::getInstance()->colorDefaultAllBgStyle()->textGet()) .
					"\n" . $this->getTraceAsString();
			}
			return $sMsg;
		}
	}
}