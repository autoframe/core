<?php

namespace Autoframe\Core\Database\Orm\Action;

use Autoframe\Core\Database\Orm\Blueprint\AfrOrmBlueprintInterface;
use Autoframe\Core\Database\Connection\Exception\AfrDatabaseConnectionException;
use Closure;
use Autoframe\Core\Arr\Export\AfrArrExportArrayAsStringClass;

class OrmTypeDescriptor implements AfrOrmBlueprintInterface
{
//TODO add / update / flush pe static, iar getter pe instanta singletoana?
// la destructor sa fac dump pe hdd, iar la constructor sa fac load de pe hdd / opcache?

	protected static array $aData = [];


	protected static array $aColModel = [
		self::COL_NAME => null,//str
		self::COL_TYPE => 'int/varchar/....', //from SYNTAX
		self::COL_TYPE_ATTRIBUTES => [],  // syntaxGetaDataTypeMap

		self::COL_DEFAULT => null, //IF !D_NULL then cast null to datatype
		self::D_NULL => null,//bool
		self::KEY => null, //PRI | MUL | null  !! PRI = AUTOINCREMENT UNIQUE
		self::COL_EXTRA => null,

		// INTS: for cast to
		// self::AUTOINCREMENT =>null, //bool
		// self::COL_UNSIGNED =>null,//bool

		//   STRINGS
		//    self::CHARSET =>null,
		//    self::COLLATION =>null,

	];
	protected static bool $bDataChanged = false;

	protected static $instance;
	protected static string $sCacheFilePath = __DIR__ . DIRECTORY_SEPARATOR . 'OrmTypeDescriptor_cache.php';

	public static bool $register_shutdown_function_destructor = true;

	/**
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


	final public static function getInstance(): self
	{
		if (empty(self::$instance)) {
			self::$instance = new static();
			if (self::$register_shutdown_function_destructor) {
				register_shutdown_function(function () {
					self::destructor();
				});
			}
		}
		return self::$instance;
	}

	final protected function __construct()
	{
		//$this->oDb = DbActionFacade::withConnAliasAndDatabase(   $sConAlias,   $sDbName  );
		if (is_file(self::$sCacheFilePath)) {
			//  self::$aData = (include self::$sCacheFilePath);
			self::$aData = unserialize(file_get_contents(self::$sCacheFilePath));
			//      self::$aData = unserialize(include self::$sCacheFilePath);
		}
	}

	/**
	 * Clean up resources before the instance is destroyed.
	 */
	public function __destruct()
	{
		self::destructor();

	}

	protected static function destructor()
	{

		if (self::$bDataChanged) {
			file_put_contents(
				self::$sCacheFilePath,
				//   "<?php\n return " . var_export(self::$aData, true) . ";\n"
				//"<?php\n return '" .str_replace(['\\', "'"], ['\\\\', "\\'" ], serialize(self::$aData))."';"
				serialize(self::$aData)
			);
		}
		self::$bDataChanged = false;
	}


	/**
	 * @param string $sCnxA
	 * @return void
	 * @throws AfrDatabaseConnectionException
	 */
	protected static function initAlias(string $sCnxA): void
	{
		$aAliasModel = [
			self::SPECS => [
				self::CON_ALIAS => $sCnxA,
				self::TIMEZONE => null,
				self::CHARSET => null,
				self::COLLATION => null,
				//USED?? NOPE LA CACHE DAR DA LA CONNECT CU DSN
			],
			self::DB_LIST => null,//databases as keys =>[]
		];
		$oThis = static::getInstance(); //init autoload
		if (empty(self::$aData[$sCnxA])) {
			self::$aData[$sCnxA] = $aAliasModel;
			self::$bDataChanged = true;
		} elseif (empty(self::$aData[$sCnxA][self::SPECS])) {
			self::$aData[$sCnxA][self::SPECS] = $aAliasModel[self::SPECS];
			self::$bDataChanged = true;
		}
		if (!in_array(self::DB_LIST, array_keys(self::$aData[$sCnxA]))) {
			self::$aData[$sCnxA][self::DB_LIST] = $aAliasModel[self::DB_LIST];
			self::$bDataChanged = true;
		}

		if (self::$aData[$sCnxA][self::SPECS][self::TIMEZONE] === null) {
			self::$bDataChanged = true;
			self::$aData[$sCnxA][self::SPECS][self::TIMEZONE] =
				CnxActionFacade::withConnAlias($sCnxA)->cnxGetTimezone();
		}

		if (
			self::$aData[$sCnxA][self::SPECS][self::CHARSET] === null ||
			self::$aData[$sCnxA][self::SPECS][self::COLLATION] === null
		) {
			self::$bDataChanged = true;
			$aCharset = CnxActionFacade::withConnAlias($sCnxA)->cnxGetConnectionCharsetAndCollation();
			self::$aData[$sCnxA][self::SPECS][self::CHARSET] = $aCharset[self::CHARSET];
			self::$aData[$sCnxA][self::SPECS][self::COLLATION] = $aCharset[self::COLLATION];
		}

		if (self::$aData[$sCnxA][self::DB_LIST] === null) {
			self::$bDataChanged = true;
			self::$aData[$sCnxA][self::DB_LIST] = [];
			$oCnx = CnxActionFacade::withConnAlias($sCnxA);
			foreach ($oCnx->cnxGetAllDatabaseNames() as $sDbName) {
				self::$aData[$sCnxA][self::DB_LIST][$sDbName] = []; //blank init to prevent looping
			}
		}
	}

	/**
	 * @param string $sCnxA
	 * @param string $sDbName
	 * @return void
	 * @throws AfrDatabaseConnectionException
	 */
	protected static function initDb(string $sCnxA, string $sDbName): void
	{
		$aDbModel = [
			self::SPECS => [
				self::DB_NAME => $sDbName,
				self::CHARSET => null,
				self::COLLATION => null,
			],
			self::DB_TABLES => null, //tables as keys =>[]
		];
		static::initAlias($sCnxA);

		if (empty(static::$aData[$sCnxA][self::DB_LIST][$sDbName])) {
			static::$aData[$sCnxA][self::DB_LIST][$sDbName] = $aDbModel;
			self::$bDataChanged = true;
		} elseif (empty(static::$aData[$sCnxA][self::DB_LIST][$sDbName][self::SPECS])) {
			static::$aData[$sCnxA][self::DB_LIST][$sDbName][self::SPECS] = $aDbModel[self::SPECS];
			self::$bDataChanged = true;
		}
		if (!in_array(self::DB_TABLES, array_keys(static::$aData[$sCnxA][self::DB_LIST][$sDbName]))) {
			static::$aData[$sCnxA][self::DB_LIST][$sDbName][self::DB_TABLES] = $aDbModel[self::DB_TABLES];
			self::$bDataChanged = true;
		}

		if ( //specs
			static::$aData[$sCnxA][self::DB_LIST][$sDbName][self::SPECS][self::CHARSET] === null ||
			static::$aData[$sCnxA][self::DB_LIST][$sDbName][self::SPECS][self::COLLATION] === null
		) {
			self::$bDataChanged = true;
			$aInfo = CnxActionFacade::withConnAlias($sCnxA)->cnxGetAllDatabaseNamesWithCharset($sDbName)[$sDbName] ?? [];
			if (empty($aInfo)) {
				$aInfo = [self::CHARSET => false, self::COLLATION => false];
			}
			static::$aData[$sCnxA][self::DB_LIST][$sDbName][self::SPECS][self::CHARSET] = $aInfo[self::CHARSET];
			static::$aData[$sCnxA][self::DB_LIST][$sDbName][self::SPECS][self::COLLATION] = $aInfo[self::COLLATION];
		}

		if (static::$aData[$sCnxA][self::DB_LIST][$sDbName][self::DB_TABLES] === null) {
			static::$aData[$sCnxA][self::DB_LIST][$sDbName][self::DB_TABLES] = [];
			self::$bDataChanged = true;
			foreach (DbActionFacade::withConnAliasAndDatabase($sCnxA, $sDbName)->dbGetTblList() as $sTblName) {
				static::$aData[$sCnxA][self::DB_LIST][$sDbName][self::DB_TABLES][$sTblName] = null;
			}
		}


	}

	/**
	 * @param string $sCnxA
	 * @param string $sDbName
	 * @param string $sTblName
	 * @return void
	 * @throws AfrDatabaseConnectionException
	 */
	protected static function initTbl(string $sCnxA, string $sDbName, string $sTblName): void
	{
		$aTblModel = [
			self::SPECS => [
				self::TBL_NAME => $sTblName, //
				self::TBL_TYPE_TEMPORARY => null,
				self::TBL_TYPE => null, // 'BASE TABLE', 'VIEW' ,'SYSTEM VIEW' (INFORMATION_SCHEMA).
				self::ENGINE => null, // 'id',
				self::CHARSET => null,
				self::COLLATION => null,
				self::COMMENT => null,
				self::AUTOINCREMENT => null, //next AI ID //ON THE FLY !

				self::PRIMARY_KEY => null, // 'id',
				self::UNIQUE_KEYS => null, //insert_update [
				//[Key_name]=>[Column_name,Column_name]
				// ] //SHOW INDEX FROM admin_new.task => Non_unique=0 este unic
			],
			self::COLUMNS => null, //$aColModel,$aColModel...

		];

		static::initDb($sCnxA, $sDbName);

		if (empty(static::$aData[$sCnxA][self::DB_LIST][$sDbName][self::DB_TABLES][$sTblName])) {
			static::$aData[$sCnxA][self::DB_LIST][$sDbName][self::DB_TABLES][$sTblName] = $aTblModel;
			self::$bDataChanged = true;
		}

		if (empty(static::$aData[$sCnxA][self::DB_LIST][$sDbName][self::DB_TABLES][$sTblName][self::SPECS][self::CHARSET])) {
			$aRow = DbActionFacade::withConnAliasAndDatabase($sCnxA, $sDbName)
				->dbGetTblListWithCharset($sTblName)[$sTblName] ?? [];

			$sCharset = explode('_', $aRow['TABLE_COLLATION'] ?? '')[0];
			$sCharset = !empty($sCharset) ? $sCharset : 'utf8';
			static::$aData[$sCnxA][self::DB_LIST][$sDbName][self::DB_TABLES][$sTblName][self::SPECS] = array_merge(
				static::$aData[$sCnxA][self::DB_LIST][$sDbName][self::DB_TABLES][$sTblName][self::SPECS],
				[
					self::TBL_TYPE_TEMPORARY => ($aRow['TEMPORARY'] ?? '') === 'N',
					self::TBL_TYPE => $aRow['TABLE_TYPE'] ?? '', // 'BASE TABLE', 'VIEW' ,'SYSTEM VIEW' (INFORMATION_SCHEMA).
					self::ENGINE => $aRow['ENGINE'] ?? 'MyISAM',
					self::CHARSET => $sCharset,
					self::COLLATION => $aRow['TABLE_COLLATION'] ?? $sCharset . '_general_ci',
					self::COMMENT => $aRow['TABLE_COMMENT'] ?? '',
					self::AUTOINCREMENT => $aRow['AUTO_INCREMENT'] ?? null,
				]
			);
			self::$bDataChanged = true;
		}

		if (static::$aData[$sCnxA][self::DB_LIST][$sDbName][self::DB_TABLES][$sTblName][self::SPECS][self::UNIQUE_KEYS] === null) {
			static::$aData[$sCnxA][self::DB_LIST][$sDbName][self::DB_TABLES][$sTblName][self::SPECS][self::UNIQUE_KEYS] = [];
			$aRows = DbActionFacade::withConnAliasAndDatabase($sCnxA, $sDbName)
				->dbShowIndexFromTable($sTblName);
			foreach ($aRows as $aRow) {
				if ($aRow['Table'] !== $sTblName) {
					continue;
				}
				if ($aRow['Key_name'] === 'PRIMARY') {
					static::$aData[$sCnxA][self::DB_LIST][$sDbName][self::DB_TABLES][$sTblName][self::SPECS][self::PRIMARY_KEY] = $aRow['Column_name'];
				}
				$iUnique = (int)(!$aRow['Non_unique']); //reverse bool
				static::$aData[$sCnxA][self::DB_LIST][$sDbName][self::DB_TABLES][$sTblName][self::SPECS][self::UNIQUE_KEYS]
				[$iUnique] [$aRow['Key_name']][$aRow['Seq_in_index']] = $aRow['Column_name'];

			}
			self::$bDataChanged = true;
		}

		if (static::$aData[$sCnxA][self::DB_LIST][$sDbName][self::DB_TABLES][$sTblName][self::COLUMNS] === null) {
			static::$aData[$sCnxA][self::DB_LIST][$sDbName][self::DB_TABLES][$sTblName][self::COLUMNS] = [];
			$aRows = DbActionFacade::withConnAliasAndDatabase($sCnxA, $sDbName)
				->dbDescribeTable($sTblName);
			foreach ($aRows as $aRow) {
				static::initCol($sCnxA, $sDbName, $sTblName, $aRow);
			}
			self::$bDataChanged = true;
		}

	}

	/**
	 * @param string $sCnxA
	 * @param string $sDbName
	 * @param string $sTblName
	 * @param array $aRow
	 * @return void
	 */
	private static function initCol(string $sCnxA, string $sDbName, string $sTblName, array $aRow): void
	{
		$aCast = explode('(', $aRow['Type']);
		$aColModel = [
			self::COL_NAME => $aRow['Field'],//str
			self::COL_TYPE => isset($aCast[1]) ? '(' . $aCast[1] : '', // Eg: decimal(10,2) unsigned OR enum('a','b','c','')
			self::D_CAST => strtoupper($aCast[0]), // 'float/enum/int/varchar/....', //from SYNTAX
			self::COL_TYPE_ATTRIBUTES => [],//FROM language syntax trait
			//    (CnxActionFacade::withConnAlias($sCnxA)->syntaxGetaDataTypeMap()[$sCast] ?? []),
			self::COL_DEFAULT => $aRow['Default'], //IF !D_NULL then cast null to datatype
			self::D_NULL => $aRow['Null'] === 'YES',//default NULL
			self::KEY => $aRow['KEY'] ?? null, //PRI | MUL | null  !! PRI = AUTOINCREMENT UNIQUE
			self::COL_EXTRA => $aRow['Extra'],

			// INTS: for cast to
			// self::AUTOINCREMENT =>null, //bool
			// self::COL_UNSIGNED =>null,//bool

			//   STRINGS
			//    self::CHARSET =>null,
			//    self::COLLATION =>null,

		];
		if (strpos($aRow['Extra'], 'auto_increment') !== false) {
			$aColModel[self::AUTOINCREMENT] = true;
		}
		if (strpos($aRow['Type'], ' unsigned') !== false) {
			$aColModel[self::COL_UNSIGNED] = true;
		}

		static::$aData[$sCnxA][self::DB_LIST][$sDbName][self::DB_TABLES][$sTblName][self::COLUMNS][$aRow['Field']] = $aColModel;

	}

	/**
	 * Make cnx.
	 * @param CnxActionInterface $oCnx
	 * @param bool $bFullTables
	 * @param bool $bFlush
	 * @return array
	 * @throws AfrDatabaseConnectionException
	 */
	public function makeCnx(CnxActionInterface $oCnx, bool $bFullTables = false, bool $bFlush = false): array
	{
		$sAlias = $oCnx->getNameConnAlias();
		if ($bFlush) {
			$this->flushAlias($sAlias, false);
		}
		self::initAlias($sAlias);
		foreach (static::$aData[$sAlias][self::DB_LIST] as $sDbName => $mData) {
			$this->makeDb(
				$oCnx,
				$sDbName,
				$bFullTables,
				false
			);
		}

		return $this->getDataAlias($sAlias);
	}

	/**
	 * Make db.
	 * @param CnxActionInterface $oCnx
	 * @param string $sDbName
	 * @param bool $bFullTables
	 * @param bool $bFlush
	 * @return array
	 * @throws AfrDatabaseConnectionException
	 */
	public function makeDb(CnxActionInterface $oCnx, string $sDbName, bool $bFullTables, bool $bFlush = false): array
	{
		$sAlias = $oCnx->getNameConnAlias();
		if ($bFlush) {
			$this->flushDb($sAlias, $sDbName, false);
		}
		self::initDb($sAlias, $sDbName);
		if ($bFullTables) {
			$oDb = DbActionFacade::usingCnxiAndDatabase($oCnx, $sDbName);
			foreach (static::$aData[$sAlias][self::DB_LIST][$sDbName][self::DB_TABLES] as $sTblName => $mData) {
				$this->makeTbl($oDb, $sTblName, false);
			}
		}
		return $this->getDataDb($sAlias, $sDbName);
	}


	/**
	 * Make tbl.
	 */
	public function makeTbl(DbActionInterface $oDba, string $sTblName, bool $bFlush = false): array
	{
		$sAlias = $oDba->getNameConnAlias();
		$sDbName = $oDba->getNameDatabase();
		if ($bFlush) {
			$this->flushTbl($sAlias, $sDbName, $sTblName);
		}
		self::initTbl($sAlias, $sDbName, $sTblName);
		return $this->getDataTbl($sAlias, $sDbName, $sTblName);
	}


	/**
	 * Set specs cnx.
	 * @param CnxActionInterface $oCnx
	 * @param string $sKey
	 * @param $mValue
	 * @return $this
	 * @throws AfrDatabaseConnectionException
	 */
	public function setSpecsCnx(CnxActionInterface $oCnx, string $sKey, $mValue): self
	{
		$sCnxA = $oCnx->getNameConnAlias();
		self::initAlias($sCnxA);
		if (
			!isset(self::$aData[$sCnxA][self::SPECS][$sKey]) ||
			self::$aData[$sCnxA][self::SPECS][$sKey] !== $mValue
		) {
			self::$aData[$sCnxA][self::SPECS][$sKey] = $mValue;
			self::$bDataChanged = true;
		}
		return $this;
	}

	/**
	 * Set specs db.
	 * @param CnxActionInterface $oCnx
	 * @param string $sDbName
	 * @param string $sKey
	 * @param $mValue
	 * @return $this
	 * @throws AfrDatabaseConnectionException
	 */
	public function setSpecsDb(
		CnxActionInterface $oCnx,
		string             $sDbName,
		string             $sKey,
		                   $mValue
	): self
	{
		$sCnxA = $oCnx->getNameConnAlias();

		self::initDb($sCnxA, $sDbName);
		if (
			!isset(static::$aData[$sCnxA][self::DB_LIST][$sDbName][self::SPECS][$sKey]) ||
			static::$aData[$sCnxA][self::DB_LIST][$sDbName][self::SPECS][$sKey] !== $mValue
		) {
			static::$aData[$sCnxA][self::DB_LIST][$sDbName][self::SPECS][$sKey] = $mValue;
			self::$bDataChanged = true;
		}

		return $this;
	}

	/**
	 * Set specs tbl.
	 * @param DbActionInterface $oDb
	 * @param string $sTblName
	 * @param string $sKey
	 * @param $mValue
	 * @return $this
	 */
	public function setSpecsTbl(
		DbActionInterface $oDb,
		string            $sTblName,
		string            $sKey,
		                  $mValue
	): self
	{
		$sDbName = $oDb->getNameDatabase();
		$sCnxA = $oDb->getNameConnAlias();
		self::initTbl($sCnxA, $sDbName, $sTblName);
		if (
			!isset(static::$aData[$sCnxA][self::DB_LIST][$sDbName][self::DB_TABLES][$sTblName][self::SPECS][$sKey]) ||
			static::$aData[$sCnxA][self::DB_LIST][$sDbName][self::DB_TABLES][$sTblName][self::SPECS][$sKey] !== $mValue
		) {
			static::$aData[$sCnxA][self::DB_LIST][$sDbName][self::DB_TABLES][$sTblName][self::SPECS][$sKey] = $mValue;
			self::$bDataChanged = true;
		}
		return $this;
	}

	/**
	 * Get specs alias.
	 * @param string $sCnxA
	 * @param string $sKey
	 * @param Closure|null $mCallback
	 * @return mixed|null
	 * @throws AfrDatabaseConnectionException
	 */
	public function getSpecsAlias(string $sCnxA, string $sKey, Closure $mCallback = null)
	{
		self::initAlias($sCnxA);
		if (empty(self::$aData[$sCnxA][self::SPECS][$sKey]) && $mCallback) {
			if (self::$aData[$sCnxA][self::SPECS][$sKey] = $mCallback()) {
				self::$bDataChanged = true;
			}
		}
		return self::$aData[$sCnxA][self::SPECS][$sKey] ?? null;
	}

	/**
	 * Get specs db.
	 * @param CnxActionInterface $oCnx
	 * @param string $sDbName
	 * @param string $sKey
	 * @return mixed|null
	 */
	public function getSpecsDb(CnxActionInterface $oCnx,
	                           string             $sDbName,
	                           string             $sKey
	)
	{
		$sCnxA = $oCnx->getNameConnAlias();
		self::initDb($sCnxA, $sDbName);
		return static::$aData[$sCnxA][self::DB_LIST][$sDbName][self::SPECS][$sKey] ?? null;
	}

	/**
	 * Get specs tbl.
	 */
	public function getSpecsTbl(DbActionInterface $oDb,
	                            string            $sTblName,
	                            string            $sKey
	)
	{
		$sDbName = $oDb->getNameDatabase();
		$sCnxA = $oDb->getNameConnAlias();
		self::initTbl($sCnxA, $sDbName, $sTblName);
		return static::$aData[$sCnxA][self::DB_LIST][$sDbName][self::DB_TABLES][$sTblName][self::SPECS][$sKey] ?? null;
	}

	/**
	 * Get data alias.
	 */
	public function getDataAlias(string $sCnxA): ?array
	{
		return self::$aData[$sCnxA] ?? null;
	}

	/**
	 * Get data db.
	 */
	public function getDataDb(string $sCnxA, string $sDb): ?array
	{
		return self::$aData[$sCnxA][self::DB_LIST][$sDb] ?? null;
	}

	/**
	 * Get data tbl.
	 */
	public function getDataTbl(string $sCnxA, string $sDb, string $sTbl): ?array
	{
		return self::$aData[$sCnxA][self::DB_LIST][$sDb][self::DB_TABLES][$sTbl] ?? null;
	}

	/**
	 * Get data cols.
	 */
	public function getDataCols(string $sCnxA, string $sDb, string $sTbl): ?array
	{
		return self::$aData[$sCnxA][self::DB_LIST][$sDb][self::DB_TABLES][$sTbl][self::COLUMNS] ?? null;
	}

	/**
	 * Flush all.
	 */
	public function flushAll()
	{
		static::$aData = [];
	}

	/**
	 * Flush alias.
	 */
	public function flushAlias(string $sCnxA, bool $bKeepDatabases = false): bool
	{
		if (isset(static::$aData[$sCnxA])) {
			if ($bKeepDatabases) {
				foreach (static::$aData[$sCnxA] as $sKey => $sValue) {
					if ($sKey === self::DB_LIST) {
						continue;
					}
					unset(static::$aData[$sCnxA][$sKey]);
				}
			} else {
				unset(static::$aData[$sCnxA]);
			}
			self::$bDataChanged = true;
			return true;
		}
		return false;
	}

	/**
	 * Flush db.
	 */
	public function flushDb(string $sCnxA, string $sDbName, bool $bKeepTables = false): bool
	{
		if (isset(static::$aData[$sCnxA][self::DB_LIST][$sDbName])) {
			if ($bKeepTables) {
				foreach (static::$aData[$sCnxA][self::DB_LIST][$sDbName] as $sKey => $sValue) {
					if ($sKey === self::DB_TABLES) {
						continue;
					}
					unset(static::$aData[$sCnxA][self::DB_LIST][$sDbName][$sKey]);
				}
			} else {
				unset(static::$aData[$sCnxA][self::DB_LIST][$sDbName]);
			}
			self::$bDataChanged = true;
			return true;
		}
		return false;
	}

	/**
	 * Flush tbl.
	 */
	public function flushTbl(string $sCnxA, string $sDbName, string $sTbl): bool
	{
		if (isset(static::$aData[$sCnxA][self::DB_LIST][$sDbName][self::DB_TABLES][$sTbl])) {
			unset(static::$aData[$sCnxA][self::DB_LIST][$sDbName][self::DB_TABLES][$sTbl]);
			self::$bDataChanged = true;
			return true;
		}
		return false;
	}


}
