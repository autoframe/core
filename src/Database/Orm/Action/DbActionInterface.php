<?php

namespace Autoframe\Core\Database\Orm\Action;

use Autoframe\Core\Database\Connection\Exception\AfrDatabaseConnectionException;
use Autoframe\Core\Database\Orm\Blueprint\AfrOrmBlueprintInterface;

interface DbActionInterface extends AfrOrmBlueprintInterface, EscapeInterface
{

    /**
     * Get instance with conn alias and database.
     * @param string $sConnAlias
     * @param string $sDatabaseName
     * @throws AfrDatabaseConnectionException
     */
    public static function getInstanceWithConnAliasAndDatabase(
        string $sConnAlias,
        string $sDatabaseName
    );

    /**
     * Get instance using cnxi and database.
     */
    public static function getInstanceUsingCnxiAndDatabase(
        CnxActionInterface $oCnxActionInterface,
        string $sDatabaseName
    );
    /**
     * Get connexion instance.
     */
    public function getConnexionInstance():CnxActionInterface;

    /**
     * Get name conn alias.
     */
    public function getNameConnAlias(): string; //singleton info
    /**
     * Get name database.
     */
    public function getNameDatabase(): string; //singleton info

    /**
     * CnxActionInterface -> cnxGetDatabaseCharsetAndCollation(string $sDbName): array;
     * @return array
     */
    public function dbGetCharsetAndCollation(): array;

    /**
     * CnxActionInterface -> cnxSetDatabaseCharsetAndCollation(string $sDbName, string $sCharset, string $sCollation = ''): bool;
     * @param string $sCharset
     * @param string $sCollation
     * @return bool
     */
    public function dbSetCharsetAndCollation(string $sCharset, string $sCollation = ''): bool;


    /** Poate fac aici o singura metoda cu cheia sa fie numele db-ului la returen */
    /**
     * Db get tbl list.
     */
    public function dbGetTblList(string $sLike = ''): array;


    /** CnxAction::cnxGetAllDatabaseNamesWithCharset()
     * $aRow[self::CON_ALIAS] = $this->getNameConnAlias();
     * $aRow[self::DB_NAME] = $aRow['SCHEMA_NAME'];
     * $aRow[self::CHARSET] = $aRow['DEFAULT_CHARACTER_SET_NAME'] ?? 'utf8';
     * $aRow[self::COLLATION] = $aRow['DEFAULT_COLLATION_NAME'] ?? $aRow[self::CHARSET] . '_general_ci';
 */
    public function dbGetTblListWithCharset(string $sLike = ''): array;

    /**
     * Db show create table.
     * @param string $sTblName
     * @return string
     */
    public function dbShowCreateTable(string $sTblName): string;

    /**
     * Db tbl exists.
     */
    public function dbTblExists(string $sTblName): bool;

    /**
     * Db get tbl charset and collation.
     */
    public function dbGetTblCharsetAndCollation(string $sTblName): array;
    /**
     * Db set tbl charset and collation.
     */
    public function dbSetTblCharsetAndCollation(string $sTblName, string $sCharset, string $sCollation = ''): bool;


    // SHOW CREATE TABLE ****
    /**
     * Db create tbl.
     */
    public function dbCreateTbl(
        string $sTblName,
        string $sCharset = 'utf8mb4',
        string $sCollate = 'utf8mb4_general_ci', //todo: _900_ai_ci  compatibility
        array  $aOptions = []
    ): bool;
    /**
     * Db rename tbl.
     */
    public function dbRenameTbl(string $sTblFrom, string $sTblTo): bool;

    //todo: cross tech implementation
    /**
     * Db copy table.
     * @param string $sTableNameFrom
     * @param string $sTableNameTo
     * @param string|object|null $mOtherDatabase
     * @return bool
     */
    public function dbCopyTable(
        string $sTableNameFrom,
        string $sTableNameTo,
        $mOtherDatabase = null
    ): bool;


    //todo: cross tech implementation like create, copy populate, remove old tbl on success
    /**
     * Db move table to other database.
     * @param string $sTableName
     * @param string|object $mOtherDatabase
     * @return bool
     */
    public function dbMoveTableToOtherDatabase(string $sTableName, $mOtherDatabase): bool;

    /**
     * Db empty table.
     */
    public function dbEmptyTable(string $sTableName): bool;
    /**
     * Db drop table.
     */
    public function dbDropTable(string $sTableName): bool;

    /**
     * DESCRIBE db.tablename
     * @param string $sTableName
     * @return array
     * @throws AfrDatabaseConnectionException
     */
    public function dbDescribeTable(string $sTableName): array;

    /**
     * SHOW INDEX FROM `db`.`tbl`
     * @param string $sTableName
     * @return array
     * @throws AfrDatabaseConnectionException
 */
    public function dbShowIndexFromTable(string $sTableName): array;


    /**
     * Pdo interact.
     */
    public function pdoInteract(): PdoInteractInterface;


}

