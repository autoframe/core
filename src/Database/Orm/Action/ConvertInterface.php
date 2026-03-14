<?php

namespace Autoframe\Core\Database\Orm\Action;

use Autoframe\Core\Database\Orm\Blueprint\AfrOrmBlueprintInterface;
use Autoframe\Core\Database\Orm\Exception\AfrOrmException;

interface ConvertInterface extends AfrOrmBlueprintInterface #, CnxActionSingletonInterface
{
	/**
	 * Blueprint to table sql.
	 * @throws AfrOrmException
	 */
	public static function blueprintToTableSql(array $aBlueprint): string;

	/**
	 * Encapsulate db tbl col name.
	 */
	public static function encapsulateDbTblColName(string $sDatabaseOrTableName): string;

	/**
	 * Encapsulate cell value.
	 */
	public static function encapsulateCellValue($mData);

	/**
	 * Parse extract quoted value.
	 * @param string $sText
	 * @param string $sQuot
	 * @param int $iStartOffset
	 * @return string[]
	 * @throws AfrOrmException
	 */
	public static function parseExtractQuotedValue(
		string $sText,
		string $sQuot,
		int    $iStartOffset = 0
	): array;

	/**
	 * Parse create table blueprint.
	 */
	public static function parseCreateTableBlueprint(string $sTableSql): array;

}
