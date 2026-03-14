<?php

namespace Autoframe\Core\Database\Orm\Action;

use Autoframe\Core\Database\Connection\Exception\AfrDatabaseConnectionException;

trait DbActionSingletonTrait
{
	protected static array $aDbActionInstances = [];
	protected CnxActionInterface $oCnxAction;
	protected string $sDatabaseName;

	/**
	 * Get name conn alias.
	 */
	public function getNameConnAlias(): string
	{
		return $this->oCnxAction->getNameConnAlias();
	}

	/**
	 * Get name database.
	 */
	public function getNameDatabase(): string
	{
		return $this->sDatabaseName;
	}

	/**
	 * Get name driver.
	 * @return string
	 * @throws AfrDatabaseConnectionException
	 */
	public function getNameDriver(): string
	{
		return $this->oCnxAction->getNameDriver();
	}


	/**
	 * Singleton's constructor should not be public. However, it can't be
	 * private either if we want to allow subclassing.
	 * @throws AfrDatabaseConnectionException
	 */
	final protected function __construct(CnxActionInterface $oCnxAction, string $sDatabaseName)
	{
		$this->oCnxAction = $oCnxAction;

		$sDatabaseName = trim($sDatabaseName);
		if (strlen($sDatabaseName) < 1) {
			throw new AfrDatabaseConnectionException('Database name is empty');
		}
		$this->sDatabaseName = $sDatabaseName;
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
	 * @param CnxActionInterface $oCnxActionInterface
	 * @param string $sDatabaseName
	 * @return self
	 * @throws AfrDatabaseConnectionException
	 */
	final public static function getInstanceUsingCnxiAndDatabase(
		CnxActionInterface $oCnxActionInterface,
		string             $sDatabaseName
	): self
	{
		$sConnAlias = $oCnxActionInterface->getNameConnAlias();
		if (empty(self::$aDbActionInstances[static::class][$sConnAlias][$sDatabaseName])) {
			return self::$aDbActionInstances[static::class][$sConnAlias][$sDatabaseName] =
				new static($oCnxActionInterface, $sDatabaseName);
		}
		return self::$aDbActionInstances[static::class][$sConnAlias][$sDatabaseName];
	}


	/**
	 * @param string $sConnAlias
	 * @param string $sDatabaseName
	 * @return DbActionSingletonTrait
	 * @throws AfrDatabaseConnectionException
	 */
	final public static function getInstanceWithConnAliasAndDatabase(
		string $sConnAlias,
		string $sDatabaseName
	): self
	{
		if (empty(self::$aDbActionInstances[static::class][$sConnAlias][$sDatabaseName])) {
			return self::$aDbActionInstances[static::class][$sConnAlias][$sDatabaseName] =
				new static(CnxActionFacade::withConnAlias($sConnAlias), $sDatabaseName);
		}
		return self::$aDbActionInstances[static::class][$sConnAlias][$sDatabaseName];

	}


}
