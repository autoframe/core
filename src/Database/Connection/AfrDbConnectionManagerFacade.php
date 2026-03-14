<?php

namespace Autoframe\Core\Database\Connection;

use Closure;

/**
 * https://www.php.net/manual/en/features.persistent-connections.php
 */
class AfrDbConnectionManagerFacade
{
	protected static ?AfrDbConnectionManagerInterface $oConnectionManager = null;

	/**
	 * @var string|Closure|Callable|null FQCN class name that implements AfrDbConnectionManagerInterface or Closure
	 */
	protected static $mImplementation = null;

	/**
	 * Get instance.
	 */
	public static function getInstance(): AfrDbConnectionManagerInterface
	{
		if (!empty(self::$oConnectionManager)) {
			return self::$oConnectionManager;
		}

		if (!empty(self::$mImplementation)) {
			if (
				self::$mImplementation instanceof Closure ||
				is_callable(self::$mImplementation)
			) {
				return self::$oConnectionManager = (self::$mImplementation)();
			}
			if (is_string(self::$mImplementation)) {
				/** @var AfrDbConnectionManagerInterface $sFQCN */
				$sFQCN = self::$mImplementation;
				self::$mImplementation = null;
				return self::$oConnectionManager = $sFQCN::getInstance();
			}

		} elseif (!empty($_ENV['AfrDbConnectionManagerFacade'])) {
			/** @var AfrDbConnectionManagerInterface $sFQCN */
			$sFQCN = $_ENV['AfrDbConnectionManagerFacade'];
			$_ENV['AfrDbConnectionManagerFacade'] = null;
			return self::$oConnectionManager = $sFQCN::getInstance();
		}

		return self::$oConnectionManager = AfrDbConnectionManagerClass::getInstance(); //default framework
	}

	/**
	 * InstanceOf|Closure|Callable|FQCN_string
	 * @param AfrDbConnectionManagerInterface|Closure|Callable|string $sFQCN_Closure_AfrDbConnectionManagerInterface
	 * @return void
	 */
	public static function setInstance($sFQCN_Closure_AfrDbConnectionManagerInterface): void
	{
		if ($sFQCN_Closure_AfrDbConnectionManagerInterface instanceof AfrDbConnectionManagerInterface) {
			self::$oConnectionManager = $sFQCN_Closure_AfrDbConnectionManagerInterface;
			self::$mImplementation = null;
		} else {
			self::$mImplementation = $sFQCN_Closure_AfrDbConnectionManagerInterface;
			self::$oConnectionManager = null;
		}
	}

}
