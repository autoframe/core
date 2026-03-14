<?php

namespace Autoframe\Core\Database\Orm\Action\Mysql;

use Autoframe\Core\Database\Orm\Exception\AfrOrmException;

trait BpToSql
{

	/**
	 * Blueprint to table sql.
	 * @throws AfrOrmException
	 */
	public static function blueprintToTableSql(array $aBlueprint): string
	{
		if (
			empty($aBlueprint[static::TBL_NAME]) ||
			empty($aBlueprint[static::ENGINE]) ||
			empty($aBlueprint[static::COLUMNS])
		) {
			throw new AfrOrmException('Invalid table blueprint: ' . print_r($aBlueprint, true));
		}
		$sSql = 'CREATE TABLE ';
		$sSql .= !empty($aBlueprint[static::IF_NOT_EXIST]) ? 'IF NOT EXISTS ' : '';
		$sSql .= !empty($aBlueprint[static::PREFER_DB_NAME]) ? static::encapsulateDbTblColName($aBlueprint[static::PREFER_DB_NAME]) . '.' : '';
		$sSql .= static::encapsulateDbTblColName($aBlueprint[static::TBL_NAME]) . ' (';

		foreach ($aBlueprint[static::COLUMNS] as $sColName => $aProps) {
			$sSql .= static::$__aEol[0] . "\t " . self::parseCreateColumnSql($sColName, $aProps) . ',';
		}
		$sSql = rtrim($sSql, ' ,'); //remove commas
		$sCommaNlT = ',' . static::$__aEol[0] . "\t ";

		if (!empty($aBlueprint[static::PRIMARY_KEY])) {
			$sSql .= $sCommaNlT . 'PRIMARY KEY (' . self::encapsulateDbTblColName($aBlueprint[static::PRIMARY_KEY]) . ')';
		}

		foreach ([
			         'UNIQUE KEY' => static::UNIQUE_KEYS,
			         'KEY' => static::KEYS,
			         'FULLTEXT KEY' => static::FULLTEXT_KEYS,
		         ]
		         as $sSqlKeyType => $sBlueprintKey) {
			if (!empty($aBlueprint[$sBlueprintKey])) {
				foreach ($aBlueprint[$sBlueprintKey] as $sKeyName => $aKeyArr) {
					$sTree = !empty($aKeyArr[static::KEY_TREE]) ? ' ' . $aKeyArr[static::KEY_TREE] : '';
					unset($aKeyArr[static::KEY_TREE]);

					$sSql .= $sCommaNlT . $sSqlKeyType . ' ' . static::exportUniqueKey($sKeyName, $aKeyArr) . $sTree;
				}
			}
		}
		if (!empty($aBlueprint[static::CONSTRAINTS])) {
			foreach ($aBlueprint[static::CONSTRAINTS] as $aConstTypes) {
				$sSql .= substr($sCommaNlT, 0, -1);
				if (!empty($aConstTypes['CONSTRAINT'])) {
					$sSql .= ' CONSTRAINT ' . static::encapsulateDbTblColName($aConstTypes['CONSTRAINT']);
				}
				if (!empty($aConstTypes['FOREIGN KEY'])) {
					$sSql .= ' FOREIGN KEY ' . static::exportUniqueKey('', $aConstTypes['FOREIGN KEY']);
				}
				if (!empty($aConstTypes['REFERENCES'])) {
					$sSql .= ' REFERENCES ';
					$aRefs = $aConstTypes['REFERENCES'];
					if (!empty($aRefs[static::PREFER_DB_NAME])) {
						$sSql .= static::encapsulateDbTblColName($aRefs[static::PREFER_DB_NAME]) . '.';
					}
					if (!empty($aRefs[static::TBL_NAME])) {
						$sSql .= static::encapsulateDbTblColName($aRefs[static::TBL_NAME]);
					}
					unset($aRefs[static::PREFER_DB_NAME]);
					unset($aRefs[static::TBL_NAME]);
					$sSql .= static::exportUniqueKey('', $aRefs);
				}

				foreach (['ON DELETE', 'ON UPDATE',] as $sCoType) {
					if (!empty($aConstTypes[$sCoType])) {
						$sSql .= ' ' . $sCoType . ' ' . $aConstTypes[$sCoType];
					}
				}
			}
			/*
  CONSTRAINt `fkmut2` FOREIGN KEy (`int_defa``ult'_none_unsigned`, `8b_bigint`) REFERENCES `dbx`.article (`id`, `user_id`) ON DELETE SET NULL ON UPDATE CASCADE
 * */
		}


		$sSql .= static::$__aEol[0] . ' ) ';
		$sSql .= 'ENGINE=' . $aBlueprint[static::ENGINE] . ' ';
		$sSql .= !empty($aBlueprint[static::AUTOINCREMENT]) ? 'AUTO_INCREMENT=' . $aBlueprint[static::AUTOINCREMENT] . ' ' : '';
		$sSql .= !empty($aBlueprint[static::CHARSET]) ? 'DEFAULT CHARSET=' . $aBlueprint[static::CHARSET] . ' ' : '';
		$sSql .= !empty($aBlueprint[static::COLLATION]) ? 'COLLATE=' . $aBlueprint[static::COLLATION] . ' ' : '';
		$sSql .= !empty($aBlueprint[static::COMMENT]) ? 'COMMENT=' . static::encapsulateCellValue($aBlueprint[static::COMMENT]) . ' ' : '';

		return $sSql;
	}

	protected static function exportUniqueKey(string $sUniqueKey, array $aKeys): string
	{
		return ($sUniqueKey ? static::encapsulateDbTblColName($sUniqueKey) : '') . ' (' .
			implode(
				',',
				array_map(fn($sVal) => static::encapsulateDbTblColName($sVal), $aKeys)
			) . ')';
	}


}
