<?php

namespace Autoframe\Core\Database\Connection;

use Autoframe\Core\Database\Connection\Exception\AfrDatabaseConnectionException;
use Autoframe\Core\Database\Orm\Action\CnxActionInterface;
use Autoframe\Core\Database\Orm\Action\DbActionInterface;
use Autoframe\Core\Database\Orm\Action\TblActionInterface;
use Closure;
use PDO;

interface AfrDbConnectionManagerInterface
{
	const PDO_ARGS = 'aPdoArgs';
	const DRIVER = 'sDriver';
	const PDO_INSTANCE_KEY = 'sPDOInstanceKey';
	const CLOSURE = 'oClosure';
	const INFO = 'aInfo';
	const FQCN_PDO = 'sPdoFqcnClass';
	const CUSTOM_DIALECT_CNX_NS = 'sDialectOrmActionNamespace';

	const DSN = 'sDSN';
	const HOST = 'host';
	const PORT = 'port';
	const DBNAME = 'dbname';
	const CHARSET = 'sCharset';

	const DRIVERS_PORTS = [
		'cubrid' => 8001,
		'sybase' => 2638,
		'mssql' => 1433,
		'dblib' => 1433,
		'firebird' => 3050,
		'ibm' => 56789,
		'informix' => 9800,
		'mysql' => 3306,
		'oci' => 1521,
		'odbc' => 50000,
		'pgsql' => 5432,
		'sqlite' => null,
	];


	/**
	 * Data layer namespace.
	 * @param string|null $sDataLayerNamespace
	 * @return string or default namespace: Autoframe\DataLayer\
	 */
	public function dataLayerNamespace(string $sDataLayerNamespace = null): string;

	/**
	 * Data layer path.
	 * @param string|null $sDataLayerPath
	 * @return string
	 * @throws AfrDatabaseConnectionException
	 */
	public function dataLayerPath(string $sDataLayerPath = null): string;


	/**
	 * Define connection alias.
	 * @param string $sAlias
	 * @param string $sDSN
	 * @param string|null $username
	 * @param string|null $password
	 * @param array|null $options
	 * @return AfrDbConnectionManagerInterface
	 * @throws AfrDatabaseConnectionException
	 */
	public function defineConnectionAlias(string $sAlias, string $sDSN, ?string $username = null, ?string $password = null, ?array $options = null): AfrDbConnectionManagerInterface;


	/**
	 * Define connection alias using pdoinstance.
	 * @param string $sAlias
	 * @param PDO $pdo
	 * @param string $sDriver Types: mysql, sqlite, pgsql, mssql, cubrid, sybase, dblib, firebird, ibm, informix, oci, odbc
	 * @param string $sDialectOrmActionNamespace
	 * @return void
	 * @throws AfrDatabaseConnectionException
	 */
	public function defineConnectionAliasUsingPDOInstance(string $sAlias, PDO $pdo, string $sDriver, string $sDialectOrmActionNamespace = ''): AfrDbConnectionManagerInterface;


	/**
	 * Pdo to hash.
	 * @param object $obj
	 * @return string
	 */
	public function pdoToHash(object $obj): string;

	/**
	 * Define alias closure.
	 * @param string $sAlias
	 * @param Closure $oClosure
	 * @return $this
	 * @throws AfrDatabaseConnectionException
	 */
	public function defineAliasClosure(string $sAlias, Closure $oClosure): AfrDbConnectionManagerInterface;


	/**
	 * Define custom dialect cnx ns.
	 * @param string $sAlias
	 * @param string $sDialectOrmActionNamespace
	 * @return $this
	 * @throws AfrDatabaseConnectionException
	 */
	public function defineCustomDialectCnxNs(
		string $sAlias,
		string $sDialectOrmActionNamespace
	): AfrDbConnectionManagerInterface;

	/**
	 * Get connection by alias.
	 * @param $sAlias
	 * @return PDO
	 * @throws AfrDatabaseConnectionException
	 */
	public function getConnectionByAlias($sAlias): PDO;

	/**
	 * Get alias info.
	 * @param $sAlias
	 * @return array|null
	 */
	public function getAliasInfo($sAlias): ?array;

	/**
	 * Is connected.
	 * @param string $sAlias
	 * @return bool
	 */
	public function isConnected(string $sAlias): bool;


	/**
	 * Get driver type.
	 * @param string $sAlias
	 * @return string Types: mysql, sqlite, pgsql, mssql, cubrid, sybase, dblib, firebird, ibm, informix, oci, odbc
	 * @throws AfrDatabaseConnectionException
	 */
	public function getDriverType(string $sAlias): string;


	/**
	 * Get custom dialect cnx ns.
	 * @param string $sAlias
	 * @return string
	 * @throws AfrDatabaseConnectionException
	 */
	public function getCustomDialectCnxNs(string $sAlias): string;

	/**
	 * Connect to all.
	 * @return void
	 * @throws AfrDatabaseConnectionException
	 */
	public function connectToAll(): void;


	/** Sets connection index to null */
	/**
	 * Flush connection.
	 */
	public function flushConnection(string $sAlias): void;

	/**
	 * The method you use to get the Singleton's instance.
	 * @return AfrDbConnectionManagerInterface
	 */
	//public static function getInstance(): AfrDbConnectionManagerInterface;
	//public static function getInstance(): self;


}
