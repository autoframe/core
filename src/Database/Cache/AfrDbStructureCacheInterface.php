<?php

namespace Autoframe\Core\Database\Cache;

use Autoframe\Core\Database\Orm\Action\CnxActionInterface;
use Autoframe\Core\Database\Orm\Action\DbActionInterface;
use Autoframe\Core\Database\Orm\Action\TblActionInterface;
use Autoframe\Core\Database\Connection\Exception\AfrDatabaseConnectionException;
use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonInterface;
use Closure;

interface AfrDbStructureCacheInterface // extends AfrSingletonInterface
{

	//TODO EVENT TRIGGERING!!!

	/**
	 * Creates a cache key in the connection index.
	 * This may be used for checking database or table existence or table description on runtime
	 * @param AfrDbCacheKey $oKey
	 * @param Closure|null $closureSet
	 * @return mixed
	 * @throws AfrDatabaseConnectionException
	 */
	public function cacheStructureGet(AfrDbCacheKey $oKey, ?Closure $closureSet = null);

	/**
	 * Cache structure set.
	 * @param AfrDbCacheKey $oKey
	 * @param $mValue
	 * @throws AfrDatabaseConnectionException
	 */
	public function cacheStructureSet(AfrDbCacheKey $oKey, $mValue);


	/**
	 * Cache key.
	 * @param string $sFunction
	 * @param array $aParams
	 * @param string|null $sCnxAlias
	 * @param string|null $sDbName
	 * @param string|null $sTableName
	 * @return AfrDbCacheKey
	 * @throws AfrDatabaseConnectionException
	 */
	public function cacheKey(
		string $sFunction,
		array  $aParams,
		string $sCnxAlias = null,
		string $sDbName = null,
		string $sTableName = null
	): AfrDbCacheKey;


	/**
	 * Flushes the cached keys form all connections
	 */
	public function cacheFlushAll(): void;

	/**
	 * Flushes the cached key form the connexion alias
	 * @param string $sAlias
	 * @return void
	 */
	public function cacheFlushAlias(string $sAlias): void;

	/**
	 * Cache flush alias db.
	 */
	public function cacheFlushAliasDb(string $sAlias, string $sDatabaseName): void;

	/**
	 * Cache flush alias db table.
	 */
	public function cacheFlushAliasDbTable(string $sAlias, string $sDatabaseName, string $sTableName): void;


	//public static function getInstance():self;

}
