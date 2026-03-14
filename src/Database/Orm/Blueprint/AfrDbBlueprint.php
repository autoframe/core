<?php

namespace Autoframe\Core\Database\Orm\Blueprint;

class AfrDbBlueprint implements AfrOrmBlueprintInterface
{
	use AfrBlueprintUtils;

	/**
	 * Db blueprint.
	 */
	public static function dbBlueprint(array $aToMerge = []): array
	{

		//sql_mode=NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION
		//SET @@sql_mode := REPLACE(@@sql_mode, 'NO_ZERO_DATE', '');
		//SET GLOBAL sql_mode = 'modes';
		//SET SESSION sql_mode = 'modes';
		#init-connect=\'SET NAMES utf8\'
		#collation_server=utf8_unicode_ci
		#character_set_server=utf8
		#character-set-server=utf8mb4
		#collation-server=utf8mb4_general_ci


		return static::mergeBlueprint([
			self::DB_NAME => null,
			self::IF_NOT_EXIST => null,
			self::CHARSET => 'utf8mb4', //utf8
			self::COLLATION => 'utf8mb4_general_ci', //utf8_unicode_ci
			self::COMMENT => null,

			self::CON_ALIAS => null, //mandatory or derived from pdo resource PDO_CONNECTION
			self::PDO_CONNECTION => null, //derived from CON_ALIAS
			self::DB_TABLES => [],//string table names as keys and FQCN's as valeus

		], $aToMerge);
	}


}
