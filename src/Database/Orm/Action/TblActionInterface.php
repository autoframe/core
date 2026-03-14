<?php

namespace Autoframe\Core\Database\Orm\Action;

use Autoframe\Core\Database\Orm\Blueprint\AfrOrmBlueprintInterface;
use Doctrine\DBAL\Types\Types;

interface TblActionInterface extends AfrOrmBlueprintInterface, EscapeInterface
{
	/**
	 * Get instance with conn alias and database and table.
	 */
	public static function getInstanceWithConnAliasAndDatabaseAndTable(
		string $sAlias,
		string $sDatabaseName,
		string $sTableName
	);

	/**
	 * Get instance using dbi table.
	 */
	public static function getInstanceUsingDbiTable(
		DbActionInterface $oDbActionInterface,
		string            $sTableName
	);

	/**
	 * Get instance using cnxi and database and table.
	 */
	public static function getInstanceUsingCnxiAndDatabaseAndTable(
		CnxActionInterface $oCnxActionInterface,
		string             $sDatabaseName,
		string             $sTableName
	);

	/**
	 * Get connexion instance.
	 */
	public function getConnexionInstance(): CnxActionInterface;

	/**
	 * Get database instance.
	 */
	public function getDatabaseInstance(): DbActionInterface;

	/**
	 * Get name conn alias.
	 */
	public function getNameConnAlias(): string; //singleton info

	/**
	 * Get name database.
	 */
	public function getNameDatabase(): string; //singleton info

	/**
	 * Get name table.
	 */
	public function getNameTable(): string; //singleton info

	/**
	 * Tbl list all siblings.
	 */
	public function tblListAllSiblings(bool $bSelfInclusive = true): array;

	/**
	 * Tbl list all siblings with charset.
	 */
	public function tblListAllSiblingsWithCharset(bool $bSelfInclusive = true): array;

	//obvious it exists, otherwise no entity :D
	/**
	 * Tbl exists.
	 */
	public function tblExists(string $sTblName, string $sDbName): bool;

	/**
	 * Tbl get charset and collation.
	 */
	public static function tblGetCharsetAndCollation(): array;

	/**
	 * Tbl set charset and collation.
	 */
	public static function tblSetCharsetAndCollation(string $sCharset, string $sCollation = ''): bool;


//https://stackoverflow.com/questions/67093/how-do-i-rename-a-mysql-database-change-schema-name
//SELECT CONCAT('RENAME TABLE admin_new.', table_name, ' TO NNNEEEWWWW.', table_name, '; ') FROM information_schema.TABLES WHERE table_schema='admin_new';
//    public static function dbRename(string $sDbFrom, string $sDbTo): bool;


//todo o clasa de datatype de coloana cu o metoda to sql care sa fie plugged in aici si sa aiba / faca manage la primary data types
	/** Poate fac aici o singura metoda cu cheia sa fie numele db-ului la returen */
	/**
	 * Col list all.
	 */
	public static function colListAll(): array; //numai numele

	/**
	 * Col list all with properties.
	 */
	public static function colListAllWithProperties(): array;//???? wtf

	/**
	 * Col exists.
	 */
	public static function colExists(string $sColName): bool;

	/**
	 * Col rename.
	 */
	public static function colRename(string $sColNameFrom, string $sColNameTo): bool;

	//flags primary key
	/**
	 * Col create type.
	 */
	public static function colCreateType(string $sColName, string $sDataType, array $aOptions = []): bool;

	/**
	 * Col create int.
	 */
	public static function colCreateInt(string $sColName, string $sDataType = Types::INTEGER, array $aOptions = []): bool;

	// SHOW CREATE DATABASE|TABLE ****

	/**
	 * Col create full.
	 */
	public static function colCreateFull(
		string $sColName,
		string $sType,
		string $sCharset = 'utf8mb4',
		string $sCollate = 'utf8mb4_general_ci',
		array  $aOptions = []
	): bool;

	/**
	 * Pdo interact.
	 */
	public function pdoInteract(): PdoInteractInterface;


	/**
	 * protected function createIndexName($type, array $columns)
	 * {
	 * $index = strtolower($this->prefix.$this->table.'_'.implode('_', $columns).'_'.$type);
	 *
	 * return str_replace(['-', '.'], '_', $index);
	 * }
	 *
	 * protected function addColumnDefinition($definition)
	 * {
	 * $this->columns[] = $definition;
	 *
	 * if ($this->after) {
	 * $definition->after($this->after);
	 *
	 * $this->after = $definition->name;
	 * }
	 *
	 * return $definition;
	 * }
	 *
	 * public function after($column, Closure $callback)
	 * {
	 * $this->after = $column;
	 *
	 * $callback($this);
	 *
	 * $this->after = null;
	 * }
	 */


	/*
	#1075 - Incorrect table definition; there can be only one auto column and it must be defined as a key
	ALTER TABLE `muta#ble` ADD CONSTRAINT `fkmut1` FOREIGN KEY (`fkid`) REFERENCES `mutable`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
	\n\t\v  sunt inlocuite in ''

	CREATE TABLE if not exist `muta#ble` (
	  `id` int(11) NOT NULL AUTO_INCREMENT,
	  `fkid` int(11) NOT NULL,
	  `int_defa``ult'_none_unsigned` int(10) unsigned NOT NULL,
	  `int_default_none_signed` int(10) unsigned zerofill NOT NULL,
	  `int_default_none_null` int(11) DEFAULT NULL,
	  `int_default_null_null` int(11) DEFAULT NULL,
	  `1b_tinyint` TINYINT(4) NOT NULL DEFAULT '22',
	  `2b_smallint` smallint(6) NOT NULL,
	  `3b_mediumint` mediumint(9) NOT NULL,
	  `8b_bigint` bigint(20) NOT NULL,
	  `decimalX` decimal(10,2) NOT NULL,
	  `floatX` float NOT NULL,
	  `double_floatX2` double NOT NULL,
	  `date` date NOT NULL,
	  `dt` datetime NOT NULL,
	  `t` time NOT NULL,
	  `ts` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
	  `char_0_255_padded_with_spaces` char(2) NOT NULL,
	  `varchar_0-65535` varchar(20) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
	  `tinytxt_2_1` tinytext CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
	  `txt_2_2` text CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
	  `medtxt_2_3` mediumtext CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
	  `longtxt_2_4` longtext CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
	  `binary_as_chr_but_01` binary(4) NOT NULL,
	  `varbinary_as_varchr_but_01` varbinary(6) NOT NULL,
	  `tinyblob_2_1` tinyblob DEFAULT NULL COMMENT 'defau''lt tr"ebui`e s)a fi(e null',
	  `blob_2_16` blob DEFAULT NULL COMMENT 'default trebuie sa fie null',
	  `medblob_2_24` mediumblob DEFAULT NULL COMMENT 'default trebuie sa fie null',
	  `longblob_2_32` longblob DEFAULT NULL COMMENT 'default trebuie sa fie null',
	  `enum_64k` enum('a','b','c','') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
	  `set_max_64_vals` set('d','e','f','') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
	  `json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`json`)),
	  PRIMARY KEY (`id`),
	  UNIQUE KEY `1b_tinyint` (`1b_tinyint`),
	  KEY `fkmut1` (`fkid`),
	  KEY `2b_smallint` (`2b_smallint`),
	  CONSTRAINT `fkmut1` FOREIGN KEY (`fkid`) REFERENCES `mutable` (`id`)
	) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci COMMENT='yha-comment!';

	--
	-- Dumping data for table `mutable`
	--

	INSERT INTO `mutable` (`id`, `int_default_none_unsigned`, `int_default_none_signed`, `int_default_none_null`, `int_default_null_null`, `1b_tinyint`, `2b_smallint`, `3b_mediumint`, `8b_bigint`, `decimalX`, `floatX`, `double_floatX2`, `date`, `dt`, `t`, `ts`, `char_0_255_padded_with_spaces`, `varchar_0-65535`, `tinytxt_2_1`, `txt_2_2`, `medtxt_2_3`, `longtxt_2_4`, `binary_as_chr_but_01`, `varbinary_as_varchr_but_01`, `tinyblob_2_1`, `blob_2_16`, `medblob_2_24`, `longblob_2_32`, `enum_64k`, `set_max_64_vals`, `json`) VALUES
	(2, 3, 0000000007, NULL, NULL, 1, 2, 3, 8, '3.37', 3.37646, 3.3764645353, '2024-05-16', '2024-05-15 23:04:56', '00:08:57', '2024-05-15 21:07:31', 'a', 'b', 'r', 'r', 'r', 'r', 0x01010000, 0x010101, NULL, NULL, NULL, NULL, 'b', 'd,f', '{\"YHA\":true}');
	COMMIT;

	REPLACE INTO `mutable` (`id`, `int_default_none_unsigned`, `int_default_none_signed`, `int_default_none_null`, `int_default_null_null`, `1b_tinyint`, `2b_smallint`, `3b_mediumint`, `8b_bigint`, `decimalX`, `floatX`, `double_floatX2`, `date`, `dt`, `t`, `ts`, `char_0_255_padded_with_spaces`, `varchar_0-65535`, `tinytxt_2_1`, `txt_2_2`, `medtxt_2_3`, `longtxt_2_4`, `binary_as_chr_but_01`, `varbinary_as_varchr_but_01`, `tinyblob_2_1`, `blob_2_16`, `medblob_2_24`, `longblob_2_32`, `enum_64k`, `set_max_64_vals`, `json`) VALUES
	(2, 3, 0000000007, NULL, NULL, 1, 2, 3, 8, '3.37', 3.37646, 3.3764645353, '2024-05-16', '2024-05-15 23:04:56', '00:08:57', '2024-05-15 21:07:31', 'a', 'b', 'r', 'r', 'r', 'r', 0x01010000, 0x010101, NULL, NULL, NULL, NULL, 'b', 'd,f', '{\"YHA\":true}');

	 * */
}
