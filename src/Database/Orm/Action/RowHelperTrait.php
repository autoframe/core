<?php

namespace Autoframe\Core\Database\Orm\Action;

use Autoframe\Core\Database\Connection\Exception\AfrDatabaseConnectionException;

trait RowHelperTrait
{

	/**
	 * Row to array.
	 */
	public static function rowToArray($objOrArr): array
	{
		return is_object($objOrArr) ? get_object_vars($objOrArr) : (array)$objOrArr;
	}

	/**
	 * Rows to array.
	 */
	public static function rowsToArray($traversable): array
	{
		$aOut = [];
		if (!empty($traversable)) {
			foreach ($traversable as $k => $mData) {
				$aOut[$k] = static::rowToArray($mData);
			}
		}

		return $aOut;
	}

	/**
	 * Row to std class.
	 */
	public static function rowToStdClass($objOrArr): object
	{
		return is_object($objOrArr) ? $objOrArr : (object)$objOrArr;
	}

	/**
	 * Rows to std class.
	 */
	public static function rowsToStdClass($traversable, bool $bReference = true)
	{
		if ($bReference) {
			foreach ($traversable as &$mData) {
				$mData = static::rowToStdClass($mData);
			}
		} else {
			foreach ($traversable as $k => $mData) {
				$traversable[$k] = static::rowToStdClass($mData);
			}
		}

		return $traversable;
	}

	/**
	 * @throws AfrDatabaseConnectionException
	 */
	protected function trimDbName(string &$sDbName, bool $bErrorOnEmpty): void
	{
		$sDbName = trim($sDbName);
		if ($bErrorOnEmpty && strlen($sDbName) < 1) {
			throw new AfrDatabaseConnectionException('Database name is empty');
		}
	}

	/**
	 * @throws AfrDatabaseConnectionException
	 */
	protected function trimTblName(string &$sTblName, bool $bErrorOnEmpty): void
	{
		$sTblName = trim($sTblName);
		if ($bErrorOnEmpty && strlen($sTblName) < 1) {
			throw new AfrDatabaseConnectionException('Table name is empty');
		}
	}

	/**
	 * @throws AfrDatabaseConnectionException
	 */
	protected function trimCellName(string &$sCellName, bool $bErrorOnEmpty): void
	{
		$sCellName = trim($sCellName);
		if ($bErrorOnEmpty && strlen($sCellName) < 1) {
			throw new AfrDatabaseConnectionException('Cell name is empty');
		}
	}

}
