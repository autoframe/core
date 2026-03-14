<?php

namespace Autoframe\Core\Database\Cache;

use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;
use Closure;

class AfrDbStructureCacheRam extends AfrSingletonAbstractClass implements AfrDbStructureCacheInterface
{

	public array $aCache = [];

	/**
	 * Cache structure get.
	 * @inheritDoc
	 */
	public function cacheStructureGet(AfrDbCacheKey $oKey, ?Closure $closureSet = null)
	{
		$a = $oKey->getCnxAlias() ?? '*';
		$d = $oKey->getDbName() ?? '*';
		$t = $oKey->getTableName() ?? '*';
		$f = $oKey->getFunction() ?? '*';
		$k = (string)$oKey;
		if (!isset($this->aCache[$a][$d][$t][$f][$k]) && $closureSet) {
			return $this->cacheStructureSet($oKey, $closureSet->call($this, $oKey));
		}
		return $this->aCache[$a][$d][$t][$f][$k] ?? null;
	}

	/**
	 * Cache structure set.
	 * @inheritDoc
	 */
	public function cacheStructureSet(AfrDbCacheKey $oKey, $mValue)
	{
		$a = $oKey->getCnxAlias() ?? '*';
		$d = $oKey->getDbName() ?? '*';
		$t = $oKey->getTableName() ?? '*';
		$f = $oKey->getFunction() ?? '*';
		$k = (string)$oKey;
		return $this->aCache[$a][$d][$t][$f][$k] = $mValue;
	}

	/**
	 * Cache key.
	 * @inheritDoc
	 */
	public function cacheKey(
		string $sFunction,
		array  $aParams,
		string $sCnxAlias = null,
		string $sDbName = null,
		string $sTableName = null
	): AfrDbCacheKey
	{
		return new (AfrDbStructureCacheFacade::getAfrDbCacheKey_FQCN())(
			$sFunction,
			$aParams,
			$sCnxAlias,
			$sDbName,
			$sTableName
		);
	}

	/**
	 * Cache flush all.
	 * @inheritDoc
	 */
	public function cacheFlushAll(): void
	{
		$this->aCache = [];
	}

	/**
	 * Cache flush alias.
	 * @inheritDoc
	 */
	public function cacheFlushAlias(string $sAlias): void
	{
		$this->aCache[$sAlias] = null;
	}

	/**
	 * Cache flush alias db.
	 */
	public function cacheFlushAliasDb(string $sAlias, string $sDatabaseName): void
	{
		$this->aCache[$sAlias][$sDatabaseName] = null;
	}

	/**
	 * Cache flush alias db table.
	 */
	public function cacheFlushAliasDbTable(string $sAlias, string $sDatabaseName, string $sTableName): void
	{
		$this->aCache[$sAlias][$sDatabaseName][$sTableName] = null;
	}


}
