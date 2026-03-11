<?php
declare(strict_types=1);

namespace Autoframe\Core\DesignPatterns\Singleton;

use Autoframe\Core\Exception\AfrException;

trait AfrSingletonClassicTrait
{
	protected static array $instances = [];

	final protected function __construct() {}
//	protected function __construct() {}

	/** @throws AfrException */
	final public function __clone()
	{
		throw new AfrException('Cannot clone a singleton: ' . static::class);
	}

	/** @throws AfrException */
	final public function __wakeup()
	{
		throw new AfrException('Cannot unserialize singleton: ' . static::class);
	}


	public static function getInstance():self
	{
		return self::$instances[static::class] ??= new static();
	}

	final public static function hasInstance(): bool
	{
		return !empty(self::$instances[static::class]);
	}

}