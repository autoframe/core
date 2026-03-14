<?php

namespace Autoframe\Core\Database\Orm\Action\Mysql;

use Autoframe\Core\Database\Connection\Exception\AfrDatabaseConnectionException;
use Autoframe\Core\Database\Orm\Action\CnxActionInterface;
use Autoframe\Core\Database\Orm\Action\DbActionInterface;
use Autoframe\Core\Database\Orm\Action\DbActionSingletonTrait;
use Autoframe\Core\Database\Orm\Action\PdoInteractInterface;

class DbAction implements DbActionInterface, PdoInteractInterface
{
	use PdoInteractTrait;

	//   use EscapeTrait;
	use Syntax;
	use DbActionSingletonTrait;


	/**
	 * Get connexion instance.
	 */
	public function getConnexionInstance(): CnxActionInterface
	{
		return $this->oCnxAction;
	}

	/**
	 * Db get charset and collation.
	 */
	public function dbGetCharsetAndCollation(): array
	{
		return $this->oCnxAction->cnxGetDatabaseCharsetAndCollation($this->getNameDatabase());
	}

	/**
	 * Db set charset and collation.
	 */
	public function dbSetCharsetAndCollation(string $sCharset, string $sCollation = ''): bool
	{
		return $this->oCnxAction->cnxSetDatabaseCharsetAndCollation($this->getNameDatabase(), $sCharset, $sCollation);
	}

	/**
	 * Db get tbl list.
	 * @throws AfrDatabaseConnectionException
	 */
	public function dbGetTblList(string $sLike = ''): array
	{
		$query = "SELECT TABLE_NAME
              FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = " . static::encapsulateCellValue($this->getNameDatabase());
		if ($sLike) {
			$query .= " AND TABLE_NAME LIKE " . static::encapsulateCellValue($sLike);
		}

		return $this->getAllCells($query);
	}

	/**
	 * Db get tbl list with charset.
	 */
	public function dbGetTblListWithCharset(string $sLike = ''): array
	{
		$this->trimTblName($sLike, false);

		$aRows = $this->getAllRows(
			"SELECT * 
              FROM `information_schema`.`TABLES`
              WHERE `TABLE_SCHEMA` = " . static::encapsulateCellValue($this->getNameDatabase()) .
			($sLike ? " AND `TABLE_NAME` LIKE " . static::encapsulateCellValue($sLike) : ''),
			'TABLE_NAME'
		);
		foreach ($aRows as &$aRow) {
			$sCharset = explode('_', $aRow['TABLE_COLLATION'] ?? '')[0];
			$sCharset = !empty($sCharset) ? $sCharset : 'utf8';
			$aRow = array_merge(
				$aRow,
				[
					self::CON_ALIAS => $this->getNameConnAlias(),
					self::DB_NAME => $this->getNameDatabase(),
					self::TBL_NAME => $aRow['TABLE_NAME'],

					self::CHARSET => $sCharset,
					self::COLLATION => $aRow['TABLE_COLLATION'] ?? $sCharset . '_general_ci',
					self::ENGINE => $aRow['ENGINE'] ?? 'MyISAM',
					self::COMMENT => $aRow['TABLE_COMMENT'] ?? '',
					self::AUTOINCREMENT => $aRow['AUTO_INCREMENT'] ?? null,
					self::TBL_TYPE => $aRow['TABLE_TYPE'] ?? '',
					self::TBL_TYPE_TEMPORARY => ($aRow['TEMPORARY'] ?? '') === 'N',
				]
			);
		}
		return $aRows;


	}

	/**
	 * Db show create table.
	 * @throws AfrDatabaseConnectionException
	 */
	public function dbShowCreateTable(string $sTblName): string
	{
		$this->trimTblName($sTblName, true);

		//SHOW CREATE TABLE dms_new.dms_file;
		$sQuery = 'SHOW CREATE TABLE ' . static::encapsulateDbTblColName($this->getNameDatabase());
		$sQuery .= '.' . static::encapsulateDbTblColName($sTblName);
		$mRow = $this->getPdo()->query($sQuery)->fetch(\PDO::FETCH_ASSOC);
		$mRow = is_object($mRow) ? get_object_vars($mRow) : (array)$mRow;
		return end($mRow);
	}

	/**
	 * Db tbl exists.
	 * @param string $sTblName
	 * @return bool
	 * @throws AfrDatabaseConnectionException
	 */
	public function dbTblExists(string $sTblName): bool
	{
		$this->trimTblName($sTblName, false);
		if (strlen($sTblName) < 1) {
			return false;
		}
		$query = "SELECT TABLE_NAME
              FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = " . static::encapsulateCellValue($this->getNameDatabase());
		$query .= ' AND TABLE_NAME = ' . static::encapsulateCellValue($sTblName) . ' LIMIT 1';
		return (bool)$this->oneQuery($query);
	}

	/**
	 * Db get tbl charset and collation.
	 * @param string $sTblName
	 * @return array
	 * @throws AfrDatabaseConnectionException
	 */
	public function dbGetTblCharsetAndCollation(string $sTblName): array
	{
		$this->trimTblName($sTblName, true);

		$aResults = $this->dbGetTblListWithCharset($sTblName);
		if (empty($aResults[$sTblName])) {
			return [];
		}
		return [
			self::CHARSET => $aResults[$sTblName][self::CHARSET],
			self::COLLATION => $aResults[$sTblName][self::COLLATION],
		];

	}

	/**
	 * Db set tbl charset and collation.
	 * @param string $sTblName
	 * @param string $sCharset
	 * @param string $sCollation
	 * @return bool
	 * @throws AfrDatabaseConnectionException
	 */
	public function dbSetTblCharsetAndCollation(string $sTblName, string $sCharset, string $sCollation = ''): bool
	{
		$this->trimTblName($sTblName, true);

		$aCollationList = $this->oCnxAction->cnxGetAllCollationCharsets($sCharset, true);
		if (empty($sCollation)) {
			$sCollation = $sCharset . '_general_ci';
		}
		if (!isset($aCollationList[$sCollation])) {
			foreach (array_keys($aCollationList, $sCharset) as $sCollateX) {
				if (strpos($sCollateX, '_general_ci') !== false) {
					$sCollation = $sCollateX;
					$sCharset = $aCollationList[$sCollation];
					break;
				}
			}
		}
		if (!isset($aCollationList[$sCollation])) { //fallback
			$sCharset = 'utf8';
			$sCollation = 'utf8_general_ci';
		}
		$sCollationPart = $sCollation ? "COLLATE $sCollation" : '';
		$query = 'ALTER TABLE ' .
			static::encapsulateDbTblColName($this->getNameDatabase()) . '.' .
			static::encapsulateDbTblColName($sTblName) .
			" CHARACTER SET $sCharset $sCollationPart";
		return (bool)$this->execPdoStatement($query);
	}

	/**
	 * Db create tbl.
	 */
	public function dbCreateTbl(string $sTblName, string $sCharset = 'utf8mb4', string $sCollate = 'utf8mb4_general_ci', array $aOptions = []): bool
	{
		// TODO: Implement dbCreateTbl() method.
	}

	/**
	 * Db rename tbl.
	 * @param string $sTblFrom
	 * @param string $sTblTo
	 * @return bool
	 * @throws AfrDatabaseConnectionException
	 */
	public function dbRenameTbl(string $sTblFrom, string $sTblTo): bool
	{
		$this->trimTblName($sTblFrom, true);
		$this->trimTblName($sTblTo, true);

		if ($this->dbTblExists($sTblFrom)) {
			$sDb = static::encapsulateDbTblColName($this->getNameDatabase()) . '.';
			return (bool)$this->execPdoStatement(
				'RENAME TABLE ' .
				$sDb . static::encapsulateDbTblColName($sTblFrom) . ' TO ' .
				$sDb . static::encapsulateDbTblColName($sTblTo)
			);
		} else {
			throw new AfrDatabaseConnectionException(
				"Table '$sTblFrom' does not exist in database '" . $this->getNameDatabase() . "'"
			);
		}
	}


	/**
	 * Db copy table.
	 * @param string $sTableNameFrom
	 * @param string $sTableNameTo
	 * @param $mOtherDatabase
	 * @return bool
	 * @throws AfrDatabaseConnectionException
	 */
	public function dbCopyTable(string $sTableNameFrom, string $sTableNameTo, $mOtherDatabase = null): bool
	{
		$this->trimTblName($sTableNameFrom, true);
		if (!$this->dbTblExists($sTableNameFrom)) {
			throw new AfrDatabaseConnectionException(
				"Table '$sTableNameFrom' does not exist in database '" . $this->getNameDatabase() . "'"
			);
		}
		/* if($mOtherDatabase){
			 if(is_string($mOtherDatabase)){
				 $this->trimDbName($mOtherDatabase, true);
				 if(!$this->getConnexionInstance()->cnxDatabaseExists($mOtherDatabase)){
					 throw new AfrDatabaseConnectionException(
						 "Database '$mOtherDatabase' does not exist in connection '" . $this->getNameConnAlias() . "'"
					 );
				 }

				 return (bool)$this->execPdoStatement(
					 'RENAME TABLE ' .
					 static::encapsulateDbTblColName($this->getNameDatabase()) . '.' .
					 static::encapsulateDbTblColName($sTableName) . ' TO ' .
					 static::encapsulateDbTblColName($mOtherDatabase) . '.' .
					 static::encapsulateDbTblColName($sTableName)
				 );
			 }
			 elseif(is_object($mOtherDatabase)){
				 // todo cross tech copy to other db, and drop current table after checks
				 throw new AfrDatabaseConnectionException('TODO: implement cross tech move for dbMoveTableToOtherDatabase() method.');
			 }
			 throw new AfrDatabaseConnectionException('Invalid parameter received for the target database in '.__METHOD__);

		 }
		 else{
			 $this->dbShowCreateTable($sTableNameFrom);

			 //select all rows
		 }*/

	}

	/**
	 * Db move table to other database.
	 * @param string $sTableName
	 * @param $mOtherDatabase
	 * @return bool
	 * @throws AfrDatabaseConnectionException
	 */
	public function dbMoveTableToOtherDatabase(string $sTableName, $mOtherDatabase): bool
	{
		//cross tech database :D
		// TODO: Implement dbMoveTableToOtherDatabase() method.
		$this->trimTblName($sTableName, true);
		if (!$this->dbTblExists($sTableName)) {
			throw new AfrDatabaseConnectionException(
				"Table '$sTableName' does not exist in database '" . $this->getNameDatabase() . "'"
			);
		}
		if (is_string($mOtherDatabase)) {
			$this->trimDbName($mOtherDatabase, true);
			if (!$this->getConnexionInstance()->cnxDatabaseExists($mOtherDatabase)) {
				throw new AfrDatabaseConnectionException(
					"Database '$mOtherDatabase' does not exist in connection '" . $this->getNameConnAlias() . "'"
				);
			}

			return (bool)$this->execPdoStatement(
				'RENAME TABLE ' .
				static::encapsulateDbTblColName($this->getNameDatabase()) . '.' .
				static::encapsulateDbTblColName($sTableName) . ' TO ' .
				static::encapsulateDbTblColName($mOtherDatabase) . '.' .
				static::encapsulateDbTblColName($sTableName)
			);
		} elseif (is_object($mOtherDatabase)) {
			// todo cross tech copy to other db, and drop current table after checks
			throw new AfrDatabaseConnectionException('TODO: implement cross tech move for dbMoveTableToOtherDatabase() method.');
		}
		throw new AfrDatabaseConnectionException('Invalid parameter received for the target database in ' . __METHOD__);

	}

	/**
	 * Db empty table.
	 * @param string $sTableName
	 * @return bool
	 * @throws AfrDatabaseConnectionException
	 */
	public function dbEmptyTable(string $sTableName): bool
	{
		$this->trimTblName($sTableName, true);
		return (bool)$this->execPdoStatement(
			'TRUNCATE TABLE ' .
			static::encapsulateDbTblColName($this->getNameDatabase()) . '.' .
			static::encapsulateDbTblColName($sTableName)
		);
	}

	/**
	 * Drops a table from the database.
	 *
	 * @param string $sTableName The name of the table to be dropped.
	 * @return bool Returns true if the table was successfully dropped, false otherwise.
	 *
	 * @throws AfrDatabaseConnectionException Thrown if there is an issue with the database connection.
	 */
	public function dbDropTable(string $sTableName): bool
	{
		$this->trimTblName($sTableName, true);
		return (bool)$this->execPdoStatement(
			'DROP TABLE IF EXISTS ' .
			static::encapsulateDbTblColName($this->getNameDatabase()) . '.' .
			static::encapsulateDbTblColName($sTableName)
		);
	}

	/**
	 * Pdo interact.
	 */
	public function pdoInteract(): PdoInteractInterface
	{
		return $this;
	}

	/**
	 * Convert tables to storage engine.
	 * @param string $from
	 * @param string $to
	 * @param bool $bExecute
	 * @return array
	 * @throws AfrDatabaseConnectionException
	 */
	public function convertTablesToStorageEngine(
		string $from = 'InnoDB',
		string $to = 'MyISAM',
		bool   $bExecute = false
	): array
	{
		$aTables = $this->getAllCells(
			'SELECT  table_name
                    FROM    information_schema.tables
                    WHERE   table_schema = ' . static::encapsulateCellValue($this->getNameDatabase()) . '
                    AND     `ENGINE` = ' . static::encapsulateCellValue($from) . "
                    AND     `TABLE_TYPE` = 'BASE TABLE'"
		);
		$aAlters = [];
		foreach ($aTables as $sTableName) {
			$aAlters[] = "ALTER TABLE " .
				static::encapsulateDbTblColName($this->getNameDatabase()) . '.' .
				static::encapsulateDbTblColName($sTableName) .
				"ENGINE=$to ;";
		}
		$aAlters = array_flip($aAlters);
		foreach ($aAlters as $sAlter => &$mStatus) {
			$mStatus = $bExecute ? $this->execPdoStatement($sAlter) : 'Not executed!';
		}

		return $aAlters;

	}

	/**
	 * Db describe table.
	 * @param string|null $sTableName
	 * @return array
	 * @throws AfrDatabaseConnectionException
	 */
	public function dbDescribeTable(string $sTableName): array
	{
		return $this->getAllRows(
			'DESCRIBE ' . self::escapeDbName($this->getNameDatabase()) . '.' . self::escapeTableName($sTableName),
			'Field'
		);
	}

	/**
	 * Db show index from table.
	 * @param string $sTableName
	 * @return array
	 * @throws AfrDatabaseConnectionException
	 */
	public function dbShowIndexFromTable(string $sTableName): array
	{
		return $this->getAllRows(
			'SHOW INDEX FROM ' . self::escapeDbName($this->getNameDatabase()) . '.' . self::escapeTableName($sTableName),
		);
	}


}
