<?php

namespace Autoframe\Core\Database\Connection;

use Autoframe\Core\Database\Orm\Action\CnxActionFacade;
use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;
use Autoframe\Core\Database\Connection\Exception\AfrDatabaseConnectionException;
use Autoframe\Core\Env\Exception\AfrEnvException;
use Autoframe\Core\InterfaceToConcrete\AfrVendorPath;
use Autoframe\Core\Afr\Afr;
use PDO;
use Closure;
use Throwable;

class AfrDbConnectionManagerClass extends AfrSingletonAbstractClass implements AfrDbConnectionManagerInterface
{
	protected array $aAliases = [];
	protected array $aConnections = [];
	protected string $sDataLayerNamespace;
	protected string $sDataLayerPath = '';

	/**
	 * @param string|null $sDataLayerNamespace
	 * @return string or default namespace: Autoframe\DataLayer\
	 * @throws AfrEnvException
	 */
	public function dataLayerNamespace(string $sDataLayerNamespace = null): string
	{
		if (!empty($sDataLayerNamespace)) { //set
			return $this->sDataLayerNamespace = $sDataLayerNamespace;
		}
		if (!empty($this->sDataLayerNamespace)) { //get if exists
			return $this->sDataLayerNamespace;
		}

		return $this->sDataLayerNamespace = Afr::app()->env()->getEnv('AFRDATALAYERNAMESPACE','Autoframe\\DataLayer\\');
	}

	/**
	 * @param string|null $sDataLayerPath
	 * @return string
	 * @throws AfrEnvException
	 */
	public function dataLayerPath(string $sDataLayerPath = null): string
	{
		if (!empty($sDataLayerPath)) {//set
			return $this->sDataLayerPath = $sDataLayerPath;
		} elseif (empty($this->sDataLayerPath)) {//set if empty
			if (!empty($sDp = Afr::app()->env()->getEnv('DATALAYERPATH'))) {
				return $this->sDataLayerPath = $sDp;
			}
			$ds = DIRECTORY_SEPARATOR;
			//list($sComposerJsonPath, $sUpperVendorPath) = $this->detectComposerAndVendorPath();
//			$aComposer = json_decode(file_get_contents($sComposerJsonPath), true);

			$sUpperVendorPath = AfrVendorPath::getBaseDirPath();
			$aComposer = AfrVendorPath::getComposerJson();
			if (!empty($aComposer['autoload']["psr-4"][$this->sDataLayerNamespace])) {
				return $this->sDataLayerPath = $sUpperVendorPath . $ds .
					rtrim(
						str_replace(
							'/',
							$ds,
							$aComposer['autoload']['psr-4'][$this->sDataLayerNamespace]),
						$ds
					);
			}
			if (is_dir($sUpperVendorPath . $ds . 'DataLayer')) {
				return $this->sDataLayerPath = $sUpperVendorPath . $ds . 'DataLayer';
			}
			if (is_dir($sUpperVendorPath . $ds . 'src' . $ds . 'DataLayer')) {
				return $this->sDataLayerPath = $sUpperVendorPath . $ds . 'src' . $ds . 'DataLayer';
			}
		}

		return $this->sDataLayerPath;
	}

	/*
		protected function detectComposerAndVendorPath(): array //TODO FIX ME AFTER MERGE WITH AFR CORE!
		{
			AfrVendorPath::getComposerJson();
			$sV = DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR;
			$pos = strrpos(__DIR__, $sV);
			if ($pos !== false) {
				$sUpperVendorPath = substr(__DIR__, 0, $pos);
				$sComposer = $sUpperVendorPath . DIRECTORY_SEPARATOR . 'composer.json';
				if (is_readable($sComposer)) {
					return [$sComposer, $sUpperVendorPath];
				}
			}
			$aDirs = explode(DIRECTORY_SEPARATOR, __DIR__);
			array_pop($aDirs);
			for ($i = 0; $i < 3; $i++) {
				array_pop($aDirs);
				$sUpperVendorPath = implode(DIRECTORY_SEPARATOR, $aDirs);
				$sComposer = $sUpperVendorPath . DIRECTORY_SEPARATOR . 'composer.json';
				if (is_readable($sComposer)) {
					return [$sComposer, $sUpperVendorPath];
				}
			}
			throw new AfrDatabaseConnectionException(
				'Please set the DataLayer directory path by $_ENV[DATALAYERPATH] OR ' . __CLASS__ . '->dataLayerPath(PATH)'
			);
		}
	*/

	protected function processDsnCustomVariables(string &$sDSN, string $sVarKey, string $sDefault): string
	{
		if (strpos($sDSN, $sVarKey)) {
			$aFQCN_PDO = explode($sVarKey, $sDSN);
			$aPdoClass = explode(';', $aFQCN_PDO[1]);
			$sDefault = trim($aPdoClass[0], ';=:& ');
			$aPdoClass[0] = rtrim($aFQCN_PDO[0], '; ');//replace with dsn
			$sDSN = implode(';', $aPdoClass);
		}
		return $sDefault;
	}

	/**
	 * @param string $sAlias
	 * @param string $sDSN
	 * @param string|null $username
	 * @param string|null $password
	 * @param array|null $options
	 * @return AfrDbConnectionManagerInterface
	 * @throws AfrDatabaseConnectionException
	 */
	public function defineConnectionAlias(
		string  $sAlias,
		string  $sDSN,
		?string $username = null,
		?string $password = null,
		?array  $options = null
	): AfrDbConnectionManagerInterface
	{
		if (empty($sAlias)) {
			throw new AfrDatabaseConnectionException('Please provide a database connection namespace!');
		}
		$sPdoClass = $this->processDsnCustomVariables($sDSN, static::FQCN_PDO, '\PDO');
		$sDialectOrmActionNamespace = $this->processDsnCustomVariables($sDSN, static::CUSTOM_DIALECT_CNX_NS, '');

		/*if (strpos($sDSN, static::FQCN_PDO)) {
			$aFQCN_PDO = explode(static::FQCN_PDO, $sDSN);
			$aPdoClass = explode(';', $aFQCN_PDO[1]);
			$sPdoClass = trim($aPdoClass[0], ';=:& ');
			$aPdoClass[0] = rtrim($aFQCN_PDO[0], '; ');//replace with dsn
			$sDSN = implode(';', $aPdoClass);
		} else {
			$sPdoClass = '\PDO';
		}
		if (strpos($sDSN, static::CUSTOM_DIALECT_CNX_NS)) {
			$aDsn = explode(static::CUSTOM_DIALECT_CNX_NS, $sDSN);
			$aCustomDialect = explode(';', $aDsn[1]);
			$sDialectOrmActionNamespace = trim($aCustomDialect[0], ';=:& ');
			$aCustomDialect[0] = rtrim($aDsn[0], '; ');//replace with dsn
			$sDSN = implode(';', $aCustomDialect);
		} else {
			$sDialectOrmActionNamespace = '';
		}
		*/

		$aInfo = $this->parseDSNInfo($sDSN);
		$aPDOArgs = array_slice(func_get_args(), 1);
		if ($sDSN !== $aInfo[static::DSN]) {
			$aPDOArgs[0] = $aInfo[static::DSN]; //possible to load from get_cfg_var('pdo.dsn.' . $sDSN);
		}

		$this->aAliases[$sAlias] = [
			static::PDO_ARGS => $aPDOArgs,
			static::INFO => $aInfo,
			static::PDO_INSTANCE_KEY => $this->getConnectionIndex($aInfo),
			static::CLOSURE => null,
			static::FQCN_PDO => $sPdoClass,
			static::CUSTOM_DIALECT_CNX_NS => $sDialectOrmActionNamespace,
		];
		return $this;
	}

	/**
	 * @param string $sAlias
	 * @param PDO $pdo
	 * @param string $sDriver Types: mysql, sqlite, pgsql, mssql, cubrid, sybase, dblib, firebird, ibm, informix, oci, odbc
	 * @param string $sDialectOrmActionNamespace
	 * @return void
	 * @throws AfrDatabaseConnectionException
	 */
	public function defineConnectionAliasUsingPDOInstance(string $sAlias, PDO $pdo, string $sDriver, string $sDialectOrmActionNamespace = ''): AfrDbConnectionManagerInterface
	{
		if (empty($sAlias)) {
			throw new AfrDatabaseConnectionException('Please provide a database connection namespace!');
		}
		$sDriver = strtolower($sDriver);
		if (!isset(static::DRIVERS_PORTS[$sDriver])) {
			throw new AfrDatabaseConnectionException('Invalid driver type: "' . $sDriver . '"');
		}

		$sHash = __FUNCTION__ . $this->pdoToHash($pdo);
		$aInfo = [
			static::DRIVER => $sDriver,
			static::DSN => $sHash,
			static::HOST => $sHash,
		];

		$this->aAliases[$sAlias] = [
			static::PDO_ARGS => [],
			static::INFO => $aInfo,
			static::PDO_INSTANCE_KEY => $this->getConnectionIndex($aInfo),
			static::CLOSURE => null,
			static::FQCN_PDO => get_class($pdo),
			static::CUSTOM_DIALECT_CNX_NS => $sDialectOrmActionNamespace,

		];
		$this->aConnections[$this->aAliases[$sAlias][static::PDO_INSTANCE_KEY]] = $pdo;
		return $this;
	}

	/**
	 * @param object $obj
	 * @return string
	 */
	public function pdoToHash(object $obj): string
	{
		return '#' . get_class($obj) . '#' . spl_object_id($obj) . '#' . spl_object_hash($obj);
	}

	/**
	 * @param string $sAlias
	 * @param Closure $oClosure
	 * @return AfrDbConnectionManagerInterface
	 * @throws AfrDatabaseConnectionException
	 */
	public function defineAliasClosure(
		string  $sAlias,
		Closure $oClosure
	): AfrDbConnectionManagerInterface
	{
		if (empty($this->aAliases[$sAlias])) {
			throw new AfrDatabaseConnectionException('Database Alias is undefined for Closure!');
		}

		$this->aAliases[$sAlias][static::CLOSURE] = $oClosure->bindTo($this, $this);
		return $this;
	}

	/**
	 * @param string $sAlias
	 * @param string $sDialectOrmActionNamespace
	 * @return AfrDbConnectionManagerInterface
	 * @throws AfrDatabaseConnectionException
	 */
	public function defineCustomDialectCnxNs(
		string $sAlias,
		string $sDialectOrmActionNamespace
	): AfrDbConnectionManagerInterface
	{
		if (!isset($this->aAliases[$sAlias])) {
			throw new AfrDatabaseConnectionException(
				'Unknown connection alis: ' . $sAlias
			);
		}
		$this->aAliases[$sAlias][static::CUSTOM_DIALECT_CNX_NS] = $sDialectOrmActionNamespace;
		return $this;
	}

	/**
	 * @param $sAlias
	 * @return PDO
	 * @throws AfrDatabaseConnectionException
	 */
	public function getConnectionByAlias($sAlias): PDO
	{
		if (!$this->isConnected($sAlias)) {
			return $this->connect($sAlias);
		}
		return $this->aConnections[$this->aAliases[$sAlias][static::PDO_INSTANCE_KEY]];
	}

	/**
	 * @param $sAlias
	 * @return array|null as follows: [
	 * AfrDbConnectionManagerInterface::PDO_ARGS => $aPDOArgs,
	 * AfrDbConnectionManagerInterface::INFO => $aInfo,
	 * AfrDbConnectionManagerInterface::CONN_INDEX => $this->getConnectionIndex($aInfo),
	 * AfrDbConnectionManagerInterface::CLOSURE => null|CLOSURE,
	 * AfrDbConnectionManagerInterface::FQCN_PDO => $sPdoClass,
	 * ];
	 */
	public function getAliasInfo($sAlias): ?array
	{
		if (!empty($this->aAliases[$sAlias])) {
			$aReturn = $this->aAliases[$sAlias];
			foreach ([1, 2] as $i => $value) {
				if (isset($aReturn[static::PDO_ARGS][$i])) {
					$aReturn[static::PDO_ARGS][$i] = '**hidden**';
				}
			}
			return $aReturn;
		}
		return null;

	}

	/**
	 * @param string $sAlias
	 * @return bool
	 */
	public function isConnected(string $sAlias): bool
	{
		return !empty($this->aConnections[$this->aAliases[$sAlias][static::PDO_INSTANCE_KEY]]);
	}


	/**
	 * @param string $sAlias
	 * @return string Types: mysql, sqlite, pgsql, mssql, cubrid, sybase, dblib, firebird, ibm, informix, oci, odbc
	 * @throws AfrDatabaseConnectionException
	 */
	public function getDriverType(string $sAlias): string
	{
		if (!isset($this->aAliases[$sAlias][static::INFO][static::DRIVER])) {
			throw new AfrDatabaseConnectionException(
				'Unknown driver type for database connection alis: ' . $sAlias
			);
		}
		return $this->aAliases[$sAlias][static::INFO][static::DRIVER];
	}

	/**
	 * @param string $sAlias
	 * @return string
	 * @throws AfrDatabaseConnectionException
	 */
	public function getCustomDialectCnxNs(string $sAlias): string
	{
		if (!isset($this->aAliases[$sAlias][static::CUSTOM_DIALECT_CNX_NS])) {
			throw new AfrDatabaseConnectionException(
				'Unknown Dialect Cnx Ns for connection alis: ' . $sAlias
			);
		}
		return $this->aAliases[$sAlias][static::CUSTOM_DIALECT_CNX_NS];
	}


	/**
	 * @return void
	 * @throws AfrDatabaseConnectionException
	 */
	public function connectToAll(): void
	{
		foreach ($this->aAliases as $sAlias => $aConfig) {
			$this->connect($sAlias);
		}
	}

	/** Sets connection index to null */
	public function flushConnection(string $sAlias): void
	{
		if ($this->isConnected($sAlias)) {
			$this->aConnections[$this->aAliases[$sAlias][static::PDO_INSTANCE_KEY]] = null;
			$this->aAliases[$sAlias][static::PDO_INSTANCE_KEY] = null;
		}
	}

	/**
	 * @param array $aInfo
	 * @return string
	 */
	protected function getConnectionIndex(array $aInfo): string
	{
		$host = $aInfo[static::HOST] ?? 'host';
		$host = ($host === '127.0.0.1' ? 'localhost' : $host);
		return $aInfo[static::DRIVER] . '@' . $host . ($aInfo[static::PORT] ? ':' . $aInfo[static::PORT] : '');
	}

	/**
	 * @param string $sDSN
	 * @return array
	 * @throws AfrDatabaseConnectionException
	 */
	protected function parseDSNInfo(string $sDSN): array
	{
		if (strpos($sDSN, ':') === false) {
			$sDsnFromConfig = get_cfg_var('pdo.dsn.' . $sDSN);
			if ($sDsnFromConfig && strpos($sDsnFromConfig, ':') !== false) {
				$sDSN = $sDsnFromConfig;
			}
		}
		$sDriver = strtolower(explode(':', $sDSN)[0]);
		$sDSNSettings = substr($sDSN, strlen($sDriver) + 1);

		$aParts = [
			static::DRIVER => $sDriver,
			static::DSN => $sDSN,
		];

		parse_str(str_replace(';', '&', $sDSNSettings), $aRawParts);
		foreach ($aRawParts as $sKey => $sVal) {
			$aParts[strtolower(trim($sKey))] = trim($sVal);
		}
		if ($sDriver === 'mysql' && empty($aParts[static::CHARSET])) {
			$aParts[static::CHARSET] = 'utf8mb4';
			$aParts[static::DSN] .= ';charset=utf8mb4';
		}

		if (empty($aParts[static::HOST])) {
			if ($sDriver === 'sqlite') {
				$aParts[static::HOST] = trim(explode(';', $sDSNSettings)[0]);
				if ($aParts[static::HOST] == ':memory:') {
					$aParts[static::DBNAME] = trim($aParts[static::HOST], ':');
				} elseif (strlen($aParts[static::HOST])) {
					$aParts[static::DBNAME] = basename($aParts[static::HOST]);
				} else {
					$aParts[static::DBNAME] = $sDriver;
				}
				// :memory:
			} elseif ($sDriver === 'ibm' && !empty($aParts['hostname'])) {
				$aParts[static::HOST] = $aParts['hostname'];
			} elseif ($sDriver === 'mysql' && !empty($aParts['unix_socket'])) {
				$aParts[static::HOST] = $aParts['unix_socket'];
				$aParts[static::PORT] = null;
			} elseif ($sDriver === 'oci' && !empty($aParts['dbname'])) {
				$aTmp = explode(':', $aParts['dbname']);
				$aTmpDbn = explode('/', $aParts['dbname']);

				$aParts[static::HOST] = explode('/', $aTmp[0])[0];
				$aParts[static::DBNAME] = count($aTmpDbn) > 1 ? $aTmpDbn[1] : '';
				$aParts[static::PORT] = count($aTmp) > 1 ? explode('/', $aTmp[1])[0] : 1521;
			} elseif ($sDriver === 'odbc' && !empty($aParts['hostname'])) {
				$aParts[static::HOST] = $aParts['hostname'];
				$aParts[static::PORT] = $aParts[static::PORT] ?? 50000;
				if (!empty($aParts['database'])) {
					$aParts[static::DBNAME] = $aParts['database'];
				}
			} elseif ($sDriver === 'odbc' && !empty($aParts['dbq'])) {
				$aParts[static::HOST] = $aParts['dbq'];
				//    $aParts[static::DBNAME] = basename($aParts['dbq']);
				$aParts[static::PORT] = null;
			}
		}
		if (!empty($aParts[static::HOST]) && $sDriver === 'mssql') {
			foreach ([':', ','] as $sSplitBy) {
				if (strpos($aParts[static::HOST], $sSplitBy) !== false) {
					$aTmp = explode($sSplitBy, $aParts[static::HOST]);
					$aParts[static::HOST] = $aTmp[0];
					$aParts[static::PORT] = $aTmp[1];
					break;
				}
			}

		}

		if (empty($aParts[static::DBNAME]) && !empty($aParts['database'])) {
			$aParts[static::DBNAME] = $aParts['database'];
		}
		if (empty($aParts[static::DBNAME])) {
			$aParts[static::DBNAME] = '';
		}

		if ($sDriver === 'informix' && !empty($aParts['service'])) {
			$aParts[static::PORT] = $aParts['service'];
		}

		if (!isset($aParts[static::PORT])) {

			$aParts[static::PORT] = static::DRIVERS_PORTS[$sDriver] ?? null;
		}


		if (is_string($aParts[static::PORT]) && $aParts[static::PORT] !== null) {
			$aParts[static::PORT] = (int)$aParts[static::PORT];
		}

		if (
			empty($aParts[static::DRIVER]) ||
			!in_array($aParts[static::DRIVER], array_keys(static::DRIVERS_PORTS))
		) {
			throw new AfrDatabaseConnectionException('Unsupported driver type "' . ($aParts[static::DRIVER] ?? '') . '" ');
		}

		return $aParts;
	}

	/**
	 * @param string $sAlias
	 * @return PDO
	 * @throws AfrDatabaseConnectionException
	 */
	protected function connect(string $sAlias): PDO
	{
		if (empty($this->aAliases[$sAlias])) {
			throw new AfrDatabaseConnectionException('PDO connection configuration alias is not provided!');
		}
		$aConfig = $this->aAliases[$sAlias];
		if (!$this->isConnected($sAlias)) {
			if (!in_array($aConfig[static::INFO][static::DRIVER], PDO::getAvailableDrivers())) {
				throw new AfrDatabaseConnectionException(
					'Driver [' . $aConfig[static::INFO][static::DRIVER] . '] is not supported by PDO driver list: ' .
					implode(', ', PDO::getAvailableDrivers())
				);
			}
			try {
				//    debug_print_backtrace();
				$sFqcnPdo = $aConfig[static::FQCN_PDO];
				$this->aConnections[$aConfig[static::PDO_INSTANCE_KEY]] = new $sFqcnPdo(...$aConfig[static::PDO_ARGS]);
				try {
					//TODO: extend beside mysql, and remove this
					//$aConfig[static::INFO][static::DRIVER]
					if ($sSelectedDb = $this->aAliases[$sAlias][static::INFO][static::DBNAME] ?? null) {
						CnxActionFacade::withConnAlias($sAlias)->setDefaultDatabase(
							$sSelectedDb
						);
					}
				} catch (Throwable $oExUnknownDialect) {
				}

			} catch (Throwable $oEx) {
				throw new AfrDatabaseConnectionException(get_class($oEx) . '::' . $oEx->getMessage());
			}
		}
		if ($aConfig[static::CLOSURE] instanceof Closure) {//run once
			$aConfig[static::CLOSURE](
				$aConfig, $this->aConnections[$aConfig[static::PDO_INSTANCE_KEY]]
			);
			$this->aAliases[$sAlias][static::CLOSURE] = null;
		}

		return $this->aConnections[$aConfig[static::PDO_INSTANCE_KEY]];
	}


	/**
	 * @param string $sStaticClass
	 * @param string $sAlias
	 * @return string
	 * @throws AfrDatabaseConnectionException
	 */
	public function resolveFacadeUsingAlias(string $sStaticClass, string $sAlias): string
	{
		$iSplit = (int)strrpos($sStaticClass, '\\');
		$sClassToCall = substr($sStaticClass, $iSplit + 1);
		if (substr($sClassToCall, -6, 6) === 'Facade') {
			$sClassToCall = substr($sClassToCall, 0, -6);// remove `Facade` from FQCN
		}

		//string The fully qualified namespace for the database custom dialect.
		$sDialectOrmActionNamespace = $this->getCustomDialectCnxNs($sAlias);
		if (empty($sDialectOrmActionNamespace)) {
			//  driver Types: mysql, sqlite, pgsql, mssql, cubrid, sybase, dblib, firebird, ibm, informix, oci, odbc
			return
				substr($sStaticClass, 0, $iSplit + 1) . // __NAMESPACE__\
				ucwords($this->getDriverType($sAlias)) . '\\' . $sClassToCall;
		}
		return rtrim($sDialectOrmActionNamespace, '\\') . '\\' . $sClassToCall;
	}


	/*
  $pdo = new PDO('cubrid:host=$host;port=8001;dbname=platin',  $user, $pass); #cubrid

  $pdo = new PDO("sybase:host=$host;dbname=$dbname, $user, $pass");# PDO_DBLIB was linked against the Sybase ct-lib libraries
  $pdo = new PDO("mssql:host=$host;dbname=$dbname, $user, $pass"); # PDO_DBLIB was linked against the Microsoft SQL Server libraries
  $pdo = new PDO("dblib:host=$host;dbname=$dbname, $user, $pass"); # PDO_DBLIB was linked against the FreeTDS libraries.

  $pdo = new PDO("firebird:host=$host;dbname=$dbname", $user, $pass);# PDO_FIREBIRD without the port

  $pdo = new PDO("ibm:DRIVER={IBM DB2 ODBC DRIVER};DATABASE=testdb;HOSTNAME=11.22.33.444;PORT=56789;PROTOCOL=TCPIP;", $user, $pass);#  PDO_IBM DSN

  $pdo = new PDO("informix:host=host.domain.com; service=9800; database=common_db; server=ids_server; protocol=onsoctcp; EnableScrollableCursors=1", "testuser", "tespass"); #PDO_INFORMIX
  $pdo = new PDO("mysql:host=$host;port=3307;dbname=$dbname;charset=utf8mb4", $user, $pass);# MySQL with PDO_MYSQL
  $pdo = new PDO("mysql:unix_socket=/tmp/mysql.sock;dbname=testdb", $user, $pass);# MySQL with PDO_MYSQL

  $pdo = new PDO("oci:dbname=192.168.10.145:1521/mydb;charset=CL8MSWIN1251", "testuser", "tespass"); #Oracle Instant Client PDO_OCI

   #PDO_ODBC
  $pdo = new PDO("odbc:DRIVER={IBM DB2 ODBC DRIVER};HOSTNAME=localhost;PORT=50000;DATABASE=SAMPLE;PROTOCOL=TCPIP;UID=db2inst1;PWD=ibmdb2;", "testuser", "tespass"); #IBM DB2 uncataloged connection
  $pdo = new PDO("odbc:Driver={Microsoft Access Driver (*.mdb)};Dbq=C:\\db.mdb;Uid=Admin", "testuser", "tespass"); #Microsoft Access PDO_ODBC

  $pdo = new PDO('pgsql:host=$host;port=5432;dbname=platin',  $user, $pass); #postgre

  $pdo = new PDO("sqlite:my/database/path/database.db"); # SQLite Database
  $pdo = new PDO("sqlite::memory:"); # SQLite Database array(PDO::ATTR_PERSISTENT => true)
  $pdo = new PDO("sqlite:"); # SQLite Database

	 * */

}
