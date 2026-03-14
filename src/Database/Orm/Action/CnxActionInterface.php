<?php

namespace Autoframe\Core\Database\Orm\Action;

use Autoframe\Core\Database\Connection\Exception\AfrDatabaseConnectionException;
use Autoframe\Core\Database\Orm\Blueprint\AfrOrmBlueprintInterface;
use Autoframe\Core\Database\Connection\AfrDbConnectionManagerInterface;

interface CnxActionInterface extends AfrOrmBlueprintInterface, EscapeInterface
{

	/**
	 * Get instance with conn alias.
	 * @param string $sAlias
	 * @return self
	 * @throws AfrDatabaseConnectionException
	 */
	public static function getInstanceWithConnAlias(string $sAlias): self;

	/**
	 * Get name driver.
	 * @return string
	 * @throws AfrDatabaseConnectionException
	 */
	public function getNameDriver(): string; //from cnx manager

	/**
	 * Get name conn alias.
	 */
	public function getNameConnAlias(): string; //singleton info

	/**
	 * Cnx get all database names.
	 * @param string $sDbNameLike filter database name like or %startsWith or containing %part%
	 * @return array
	 */
	public function cnxGetAllDatabaseNames(string $sDbNameLike = ''): array;


	/**
	 * The response array should contain the keys: self::DB_NAME, self::CHARSET, self::COLLATION
	 * @param string $sDbNameLike
	 * @return array
	 * @throws AfrDatabaseConnectionException
	 */
	public function cnxGetAllDatabaseNamesWithCharset(string $sDbNameLike = ''): array;

	/**
	 * Get database instance.
	 */
	public function getDatabaseInstance(string $sDbName): DbActionInterface;

	/**
	 * Cnx database exists.
	 */
	public function cnxDatabaseExists(string $sDbName): bool;

	/**
	 * Cnx get database charset and collation.
	 */
	public function cnxGetDatabaseCharsetAndCollation(string $sDbName): array;

	/**
	 * Cnx set database charset and collation.
	 */
	public function cnxSetDatabaseCharsetAndCollation(string $sDbName, string $sCharset, string $sCollation = ''): bool;


	/**
	 * Cnx create database using default charset.
	 */
	public function cnxCreateDatabaseUsingDefaultCharset(string $sDbName, array $aOptions = [], bool $bIfNotExists = false): bool;

	/**
	 * Cnx create database using charset.
	 */
	public function cnxCreateDatabaseUsingCharset(
		string $sDbName,
		string $sCharset = 'utf8mb4',           //todo: _900_ai_ci  compatibility
		string $sCollate = 'utf8mb4_general_ci', //todo: _900_ai_ci
		array  $aOptions = [],
		bool   $bIfNotExists = false
	): bool;


	/**
	 * Cnx get all collation charsets.
	 * @param string $sLike
	 * @param bool $bWildcard
	 * @return array
	 * @throws AfrDatabaseConnectionException
	 */
	public function cnxGetAllCollationCharsets(string $sLike = '', bool $bWildcard = false): array;


	/**
	 * Retrieves all available character sets from the database.
	 *
	 * @return array An array of character set names.
	 * @throws AfrDatabaseConnectionException If there is an error connecting to the database.
	 */
	public function cnxGetAllCharsets(): array;  //SELECT * FROM `information_schema`.`CHARACTER_SETS` ORDER BY `CHARACTER_SETS`.`CHARACTER_SET_NAME` DESC;

	/**
	 * Retrieves all the collations from the database.
	 *
	 * @return array An array containing all the collations.
	 * @throws AfrDatabaseConnectionException If there is an issue with the database connection.
	 */
	public function cnxGetAllCollations(): array; //SHOW COLLATION     //SELECT * FROM `information_schema`.`CHARACTER_SETS` ORDER BY `CHARACTER_SETS`.`CHARACTER_SET_NAME` DESC;

	/**
	 * Cnx get connection charset and collation.
	 * @return string[]
	 * @throws AfrDatabaseConnectionException
	 */
	public function cnxGetConnectionCharsetAndCollation(): array;

	/**
	 * Cnx set connection charset and collation.
	 */
	public function cnxSetConnectionCharsetAndCollation(string $sCharset = 'utf8mb4',
	                                                    string $sCollation = 'utf8mb4_general_ci',
	                                                    bool   $character_set_server = true,
	                                                    bool   $character_set_database = false
	): bool;

	/**
	 * Cnx show create database.
	 * @param string $sDbName
	 * @return string
	 */
	public function cnxShowCreateDatabase(string $sDbName): string;

	//https://stackoverflow.com/questions/2934258/how-do-i-get-the-current-time-zone-of-mysql
	//https://phoenixnap.com/kb/change-mysql-time-zone
	//https://www.db4free.net/

	/**
	 * Cnx set timezone.
	 */
	public function cnxSetTimezone(string $sTimezone = '+00:00'): bool;

	/**
	 * Cnx get timezone.
	 */
	public function cnxGetTimezone(): string; //'+00:00';

	/**
	 * Pdo interact.
	 */
	public function pdoInteract(): PdoInteractInterface;


	/**
	 * USE database
	 * @param string $sDatabaseName
	 * @return DbActionInterface
	 * @throws AfrDatabaseConnectionException
	 */
	public function cnxUseDatabase(string $sDatabaseName): DbActionInterface;


	/**
	 * SELECT database()
	 * @return string|null
	 * @throws AfrDatabaseConnectionException
	 */
	public function cnxUsedDatabase(): ?string;

	/**
	 * Get afr db connection manager instance.
	 */
	public function getAfrDbConnectionManagerInstance(): AfrDbConnectionManagerInterface;

	/**
	 * Cnx drop database.
	 */
	public function cnxDropDatabase(string $sDbName): bool;

	/**
	 * Syntax geta data type map.
	 */
	public function syntaxGetaDataTypeMap(): array;

	/**
	 * Cnx flush orm cache.
	 */
	public function cnxFlushOrmCache(): bool;

	/**
	 * Get orm type descriptor.
	 */
	public function getOrmTypeDescriptor(): OrmTypeDescriptor;

}
