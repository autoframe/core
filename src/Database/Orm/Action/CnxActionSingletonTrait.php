<?php

namespace Autoframe\Core\Database\Orm\Action;

use Autoframe\Core\Database\Connection\AfrDbConnectionManagerFacade;
use \Autoframe\Core\Database\Connection\Exception\AfrDatabaseConnectionException;

trait CnxActionSingletonTrait
{
	protected static array $instances = [];
	protected string $sConnAlias;

	/**
	 * Get name conn alias.
	 */
	public function getNameConnAlias(): string
	{
		return $this->sConnAlias;
	}

	/**
	 * Get name driver.
	 * @return string
	 * @throws AfrDatabaseConnectionException
	 */
	public function getNameDriver(): string
	{
		return AfrDbConnectionManagerFacade::getInstance()->getDriverType($this->sConnAlias);
	}

	/**
	 * Singleton's constructor should not be public. However, it can't be
	 * private either if we want to allow subclassing.
	 */
	final protected function __construct(string $sConnAlias)
	{
		$this->sConnAlias = $sConnAlias;

	}

	/**
	 * Cloning and un-serialization are not permitted for singletons.
	 * @throws AfrDatabaseConnectionException
	 */
	final public function __clone()
	{
		throw new AfrDatabaseConnectionException('Cannot clone a singleton: ' . static::class);
	}

	/**
	 * @throws AfrDatabaseConnectionException
	 */
	final public function __wakeup()
	{
		throw new AfrDatabaseConnectionException('Cannot unserialize singleton: ' . static::class);
	}


	/**
	 * The method you use to get the Singleton's instance.
	 * @return self
	 * @throws AfrDatabaseConnectionException
	 */
	final public static function getInstanceWithConnAlias(string $sConnAlias): self
	{
		if (empty($sConnAlias)) {
			throw new AfrDatabaseConnectionException('Alias can\'t be empty');
		}
		if (empty(self::$instances[static::class][$sConnAlias])) {
			return self::$instances[static::class][$sConnAlias] = new static($sConnAlias);
		}
		return self::$instances[static::class][$sConnAlias];
	}


}
