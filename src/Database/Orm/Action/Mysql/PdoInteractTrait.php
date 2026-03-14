<?php

namespace Autoframe\Core\Database\Orm\Action\Mysql;

use Autoframe\Core\Database\Connection\AfrDbConnectionManagerFacade;
use Autoframe\Core\Database\Connection\Exception\AfrDatabaseConnectionException;
use Autoframe\Core\Database\Orm\Action\CnxActionInterface;
use Autoframe\Core\Database\Orm\Action\DbActionInterface;
use Autoframe\Core\Database\Orm\Action\OrmTypeDescriptor;
use Autoframe\Core\Database\Orm\Action\RowHelperTrait;
use Autoframe\Core\Database\Orm\Action\TblActionInterface;
use Autoframe\Core\Database\Cache\AfrDbStructureCacheFacade;
use PDO;

trait PdoInteractTrait
{
	use RowHelperTrait;
	use EscapeTrait;


	//todo cache, performance logger, etc

	/**
	 * @throws AfrDatabaseConnectionException
	 */
	protected function getPdo(): PDO
	{
		return AfrDbConnectionManagerFacade::getInstance()->getConnectionByAlias(
			$this->getNameConnAlias()
		);
	}

	////////////////////////////////////////

	/**
	 * alias for manyQuery
	 *
	 * @param string $sQuery
	 * @return array
	 * @throws AfrDatabaseConnectionException
	 */
	public function getRow(string $sQuery): array
	{
		if (
			strpos(strtoupper(substr($sQuery, -20, 20)), ' LIMIT') === false
		) {
			$sQuery .= ' LIMIT 1';
		}

		$mRow = $this->getPdo()->query($sQuery)->fetch(PDO::FETCH_ASSOC);
		return is_object($mRow) ? get_object_vars($mRow) : (array)$mRow;
	}


	/**
	 * alias for getRow
	 * @param string $sQuery
	 * @return array
	 * @throws AfrDatabaseConnectionException
	 */
	public function manyQuery(string $sQuery): array
	{
		return $this->getRow($sQuery);

	}

	////////////////////////////////////////


	/**
	 * alis for multipleQuery
	 *
	 * @param string $sQuery
	 * @param string $sIndexColumn
	 * @return array
	 * @throws AfrDatabaseConnectionException
	 */
	public function getAllRows(string $sQuery, string $sIndexColumn = 'id'): array
	{
		$aList = [];
		foreach ($this->getPdo()->query($sQuery)->fetchAll(PDO::FETCH_ASSOC) as $mRow) {
			$mRow = is_object($mRow) ? get_object_vars($mRow) : (array)$mRow;
			if (isset($mRow[$sIndexColumn])) {
				$aList[$mRow[$sIndexColumn]] = $mRow;
			} else {
				$aList[] = $mRow;
			}
		}
		return $aList;
	}


	/**
	 * alias for getAllRows
	 * @param string $sQuery
	 * @param string $sIndexColumn
	 * @return array
	 * @throws AfrDatabaseConnectionException
	 */
	public function multipleQuery(string $sQuery, string $sIndexColumn = 'id'): array
	{
		return $this->getAllRows($sQuery, $sIndexColumn);
	}

	////////////////////////////////////////

	/**
	 * alias for oneQuery
	 * @param string $sQuery
	 * @return string|int|float|null|mixed
	 * @throws AfrDatabaseConnectionException
	 */
	public function getCell(string $sQuery)
	{
		$aRow = $this->getRow($sQuery);
		return count($aRow) > 0 ? array_pop($aRow) : null;
	}


	/**
	 * alias for getCell
	 * @param string $sQuery
	 * @return string|int|float|null|mixed
	 * @throws AfrDatabaseConnectionException
	 */
	public function oneQuery(string $sQuery)
	{
		return $this->getCell($sQuery);

	}

	////////////////////////////////////////

	/**
	 * alias for getAllCells
	 * Retrieves multiple rows having a single value for each row
	 * Same as array values or oneMultipleQuery
	 *
	 * @param string $sQuery
	 * @return array
	 * @throws AfrDatabaseConnectionException
	 */
	public function oneRowsQuery(string $sQuery): array
	{
		$aList = [];
		foreach ($this->getPdo()->query($sQuery)->fetchAll(PDO::FETCH_NUM) as $mRow) {
			$mRow = is_object($mRow) ? get_object_vars($mRow) : (array)$mRow;
			$aList[] = array_pop($mRow);
		}
		return $aList;
	}

	/**
	 * alias for oneRowsQuery
	 * @param string $sQuery
	 * @return array
	 * @throws AfrDatabaseConnectionException
	 */
	public function getAllCells(string $sQuery): array
	{
		return $this->oneRowsQuery($sQuery);

	}

	////////////////////////////////////////


	/**
	 * Exec pdo statement.
	 * @param string $sQuery
	 * @return false|int
	 * @throws AfrDatabaseConnectionException
	 */
	public function execPdoStatement(string $sQuery)
	{
		return $this->getPdo()->exec($sQuery);
	}


	/**
	 * Set default database.
	 * @param string|null $sDatabaseName
	 * @throws AfrDatabaseConnectionException
	 */
	public function setDefaultDatabase(string $sDatabaseName = null): void
	{
		if ($this instanceof CnxActionInterface) {
			$this->mDefaultDatabase = $sDatabaseName;
			return;
		}
		$this->getConnexionInstance()->setDefaultDatabase($sDatabaseName);
	}

	/**
	 * Get default database name.
	 * @return string|null
	 */
	public function getDefaultDatabaseName(): ?string
	{
		return ($this instanceof CnxActionInterface) ?
			$this->mDefaultDatabase :
			$this->getConnexionInstance()->getDefaultDatabaseName();
	}


	////////////////////////////////////////


	/**
	 * Count query.
	 * @param string $sQuery
	 * @return int
	 * @throws AfrDatabaseConnectionException
	 */
	public function countQuery(string $sQuery): int
	{
		//SELECT COUNT(*) FROM `db`.`table` WHERE `cell`='val'
		return (int)$this->getCell($sQuery);
	}

	/**
	 * Res count rows.
	 * @param $aWhere
	 * @param string|null $sTable
	 * @param string|null $sDb
	 * @return int
	 * @throws AfrDatabaseConnectionException
	 */
	public function resCountRows($aWhere, string $sTable = null, string $sDb = null): int
	{
		$sTable = $this->contextTable($sTable, true);
		$sDb = $this->contextDb($sDb, true);

		//TODO !!!!!!!!!!!!!!!!!!!
		return (int)$this->getCell(
			'SELECT COUNT(*) FROM ' .
			self::escapeDbName($sDb) . '.' . self::escapeTableName($sTable) .
			' WHERE ' . $aWhere
		);

	}

	/**
	 * Many qa.
	 */
	public function many_qa(string $tablename, array $where, $return_query = false)
	{
		// TODO: Implement many_qa() method.
	}

	/**
	 * Insert query.
	 */
	public function insertQuery(string $sQuery, bool $bReturnTableAutoIncrement = false): ?int
	{
		// TODO: Implement insertQuery() method.
	}

	/**
	 * Insert qa.
	 */
	public function insertQa(string $tablename, $a, $keys_to_exclude = array('id'), $setify_only_keys = array(), $return_query = false): ?int
	{
		// TODO: Implement insertQa() method.
	}

	function insert_update($tablename, $a, $keys_to_exclude = array('id'), $setify_only_keys = array(), $return_query = false)
	{
		// TODO: Implement insert_update() method.
	}

	/**
	 * Update query.
	 */
	public function update_query($sQuery)
	{
		// TODO: Implement update_query() method.
	}

	/**
	 * Update qa.
	 */
	public function update_qa($tablename, $a, $where, $limit = 'LIMIT 1', $return_query = false)
	{
		// TODO: Implement update_qa() method.
	}

	function update_qaf($tablename, $a, $where, $limit = 'LIMIT 1', $keys_to_exclude = array('id'), $setify_only_keys = array(), $return_query = false)
	{
		// TODO: Implement update_qaf() method.
	}

	function delete_query(string $sQuery)
	{
		// TODO: Implement delete_query() method.
	}

	function setify_query(array $a, $set = ' SET ')
	{
		// TODO: Implement setify_query() method.
	}


	function mysql_insert_id($x): ?int
	{
		// TODO: Implement mysql_insert_id() method.
	}

	function mysql_affected_rows($x): ?int
	{
		// TODO: Implement mysql_affected_rows() method.
	}


	/**
	 * Context table.
	 * @param string|null $sTable
	 * @param bool $bThrowErrorOnEmpty
	 * @return string
	 * @throws AfrDatabaseConnectionException
	 */
	public function contextTable(?string $sTable = null, bool $bThrowErrorOnEmpty = true): string
	{
		$sTable = trim((string)$sTable);
		if (strlen($sTable) < 1) {
			if ($this instanceof TblActionInterface) {
				$sTable = $this->getNameTable();
			}
			if ($bThrowErrorOnEmpty && strlen($sTable) < 1) {
				throw new AfrDatabaseConnectionException(
					'The table name cannot be empty.',
				);
			}
		}

		return $sTable;
	}


	/**
	 * Context db.
	 * @param string|null $sDb
	 * @param bool $bThrowErrorOnEmpty
	 * @return string|null
	 * @throws AfrDatabaseConnectionException
	 */
	public function contextDb(?string $sDb = null, bool $bThrowErrorOnEmpty = true): ?string
	{
		$sDb = trim((string)$sDb);
		if (strlen($sDb) < 1) {
			if ($this instanceof TblActionInterface || $this instanceof DbActionInterface) {
				$sDb = $this->getNameDatabase();
			} elseif ($this instanceof CnxActionInterface) {
				$sDb = $this->getDefaultDatabaseName(); //if previously set by setDefaultDatabase() or cnxUseDatabase()
				if ($bThrowErrorOnEmpty && strlen($sDb) < 1) {
					$sDb = $this->cnxUsedDatabase();//if previously set by cnxUseDatabase() over the connection or exec(USE...)
					if ($sDb) {
						$this->setDefaultDatabase($sDb);
					}
				}
			}
		}

		if ($bThrowErrorOnEmpty && strlen($sDb) < 1) {
			throw new AfrDatabaseConnectionException(
				'The database name cannot be empty!',
			);
		}
		return $sDb;
	}

	/**
	 * Context alias.
	 * @param string|null $sAlias
	 * @param bool $bThrowErrorOnEmpty
	 * @return string|null
	 * @throws AfrDatabaseConnectionException
	 */
	public function contextAlias(?string $sAlias = null, bool $bThrowErrorOnEmpty = true): ?string
	{
		$sAlias = trim((string)$sAlias);
		if (strlen($sAlias) < 1) {
			if ($this instanceof TblActionInterface || $this instanceof DbActionInterface) {
				$sAlias = $this->getConnexionInstance()->getNameConnAlias();
			} elseif ($this instanceof CnxActionInterface) {
				$sAlias = $this->getNameConnAlias();
			}
		}

		if ($bThrowErrorOnEmpty && strlen($sAlias) < 1) {
			throw new AfrDatabaseConnectionException(
				'The connexion alias cannot be empty!',
			);
		}
		return $sAlias;
	}


	//////////////////////


	/**
	 * Cnx flush orm cache.
	 */
	public function cnxFlushOrmCache(): bool
	{
		return $this->getOrmTypeDescriptor()->flushAlias(
			$this->getNameConnAlias(),
			false
		);
	}

	/**
	 * Get orm type descriptor.
	 */
	public function getOrmTypeDescriptor(): OrmTypeDescriptor
	{
		return OrmTypeDescriptor::getInstance();
	}

}
