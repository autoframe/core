<?php

namespace Autoframe\Core\Database\Orm\Action\Mysql;

use Autoframe\Core\Database\Orm\Blueprint\AfrDbBlueprint;
use Autoframe\Core\Database\Orm\Blueprint\AfrTableBlueprint;
use Autoframe\Core\Database\Orm\Exception\AfrOrmException;

trait SqlToBp
{
	/**
	 * Parse create table blueprint.
	 * @throws AfrOrmException
	 */
	public static function parseCreateTableBlueprint(
		string $sTableSql,
		string $sDatabaseName = null,
		string $sConnAlias = null,
		\PDO   $PDO = null
	): array
	{
		list($aReadBuffersTbl, $aReadBuffersCols, $aQuotedTexts, $aTblAttributes) = static::parseTabBlueprintStep1($sTableSql);
		$aReadBuffersColsWorkCopy = $aReadBuffersCols;

		$aTabBlueprint = AfrTableBlueprint::tableBlueprint();

		$aTabBlueprint[static::DB_NAME] = $sDatabaseName ?? $aTabBlueprint[static::DB_NAME];
		$aTabBlueprint[static::CON_ALIAS] = $sConnAlias ?? $aTabBlueprint[static::CON_ALIAS];


		self::parseTabBlueprintHead($aReadBuffersTbl[0], $sTableSql, $aTabBlueprint, $aQuotedTexts);
		self::parseTabBlueprintBody($aTblAttributes, $aQuotedTexts, $aTabBlueprint, $aReadBuffersColsWorkCopy);
		self::parseTabBlueprintCols($aTblAttributes, $aQuotedTexts, $aTabBlueprint, $aReadBuffersColsWorkCopy);


		//    return [$aTabBlueprint, $aReadBuffersTbl, $aReadBuffersCols, $aQuotedTexts, $aTblAttributes, $aReadBuffersColsWorkCopy];
		return $aTabBlueprint;
	}


	/**
	 * Get selected db.
	 */
	public static function getSelectedDb(string $sConnAlias = null, \PDO $PDO = null): ?string
	{
		return 'SELECT DATABASE() as dbName; ';
	}

	/**
	 * Parse creat database blueprint.
	 * @throws AfrOrmException
	 */
	public static function parseCreatDatabaseBlueprint(string $sDatabaseSql = ''): array
	{
		if (empty($sDatabaseSql)) {
			$sDatabaseSql = "CREATE DATABASE IF NOT EXISTS `adela` /*!40100 DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci */ COMMENT 'akjg'";
		}

		$bDirectiveComment = false; // /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_swedish_ci */
		$bMultiLineComment = false;    // /*
		$SingleLineComment = false; //-- comment || where 'a'=1; #some thing

		$iLength = strlen($sDatabaseSql);
		$sDatabaseSql .= str_repeat(static::$__aSpace[0], 8); //prevent stack overflow for utf8 variations

		$aQuotedTexts = [];

		$sCleanSql = $sDirective = '';
		for ($l = 0; $l < $iLength; $l++) {
			$bNotCommentOrQuot = !$bMultiLineComment && !$SingleLineComment;
			$sCh1 = $sDatabaseSql[$l];
			$sCh12 = $sCh1 . $sDatabaseSql[$l + 1];
			//    $sCh123 = $sCh12 . $sTableSql[$l + 2];


			if ($bNotCommentOrQuot && $sCh12 === '/*') {//enter multi line comment
				$bMultiLineComment = true;
				if (substr($sDatabaseSql, $l, 8) === '/*!40100') {
					$bDirectiveComment = true;
					$l += 6;
				}
				$l += 1;
			} elseif ($bMultiLineComment && $sCh12 === '*/') {
				$l += 1;
				$bMultiLineComment = $bDirectiveComment = false;
			} elseif ($bNotCommentOrQuot && ($sCh1 === '#' || $sCh12 === '--')) { //enter single line comment
				$SingleLineComment = true;
				$l += $sCh12 === '--' ? 1 : 0;
			} elseif ($SingleLineComment && (in_array($sCh1, static::$__aEol) || in_array($sCh12, static::$__aEol))) {
				$l += 0;
				$SingleLineComment = false;
			} elseif ($bNotCommentOrQuot && in_array($sCh1, static::$__aQuotes)) {
				$aQuoted = static::parseExtractQuotedValue($sDatabaseSql, $sCh1, $l);
				$sQuotedKey = 'AFR' . strtoupper(md5($aQuoted[static::QUOTED]));//35
				$aQuotedTexts[$sQuotedKey] = $aQuoted; //extract the quoted values
				$sCleanSql .= static::$__aQuotes[0] . $sQuotedKey;
				$l = $aQuoted[static::END_OFFSET];
			} elseif ($bNotCommentOrQuot && $sCh1 == static::$__sEndStatement) {
				break;
			} elseif ($bDirectiveComment) {
				$bytes = static::charBytesOrd($sCh1);
				static::parseCreateTableAppendToBuffer($sDirective, substr($sDatabaseSql, $l, $bytes)); //append utf8 chars
				$l += $bytes - 1;//-1 fix pointer
			} elseif ($bNotCommentOrQuot) {//buffer fill
				$bytes = static::charBytesOrd($sCh1);
				static::parseCreateTableAppendToBuffer($sCleanSql, substr($sDatabaseSql, $l, $bytes)); //append utf8 chars
				$l += $bytes - 1;//-1 fix pointer
			}
		}
		$sDirective = trim($sDirective);
		$sCleanSql = trim($sCleanSql);

		$aDbBp = AfrDbBlueprint::dbBlueprint();
		if ($aDbBp[static::IF_NOT_EXIST] = !(stripos($sCleanSql, 'IF NOT EXISTS ') === false)) {
			$sCleanSql = str_ireplace('IF NOT EXISTS ', '', $sCleanSql);
		}

		$sColSql = $sCleanSql . ($sDirective ? ' ' . $sDirective : '');

		foreach (static::$__aDbAttributes as $sMapKey => $sAttr) {
			$iAttrStartPos = stripos($sColSql, $sAttr);
			if ($iAttrStartPos === false) {
				continue;
			}
			$iAttrEndPops = $iAttrStartPos + strlen($sAttr); //prepare to find the end of the attribute
			$sColSql .= static::$__aSpace[0];
			$iEnd = strpos($sColSql, static::$__aSpace[0], $iAttrEndPops + 1); //include the space or first char in offset
			$sValue = trim(substr($sColSql, $iAttrEndPops, $iEnd - $iAttrEndPops));

			$aDbBp[$sMapKey] = static::parseRestoreQuotedTextValue($sValue, $aQuotedTexts);

			$sColSql = trim(substr($sColSql, 0, $iAttrStartPos) . substr($sColSql, $iEnd));
		}
		$sCleanSql = trim($sColSql);


		return [$aDbBp, $sCleanSql, $sDirective, $aQuotedTexts];


		list($aReadBuffersTbl, $aReadBuffersCols, $aQuotedTexts, $aTblAttributes) = static::parseTabBlueprintStep1($sDatabaseSql);
		$aReadBuffersColsWorkCopy = $aReadBuffersCols;

		$aTabBlueprint = AfrTableBlueprint::tableBlueprint();

		self::parseTabBlueprintHead($aReadBuffersTbl[0], $sDatabaseSql, $aTabBlueprint, $aQuotedTexts);
		self::parseTabBlueprintBody($aTblAttributes, $aQuotedTexts, $aTabBlueprint, $aReadBuffersColsWorkCopy);
		self::parseTabBlueprintCols($aTblAttributes, $aQuotedTexts, $aTabBlueprint, $aReadBuffersColsWorkCopy);

		//    return [$aTabBlueprint, $aReadBuffersTbl, $aReadBuffersCols, $aQuotedTexts, $aTblAttributes, $aReadBuffersColsWorkCopy];
		return $aTabBlueprint;
	}


	/**
	 * @throws AfrOrmException
	 */
	protected static function parseTabBlueprintStep1(string $sTableSql): array
	{

//        $bDirectiveComment = false; // /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_swedish_ci */
		$bMultiLineComment = false;    // /*
		$SingleLineComment = false; //-- comment || where 'a'=1; #some thing
		$iInsideParenthesisLevel = 0; // (...)

		$iLength = strlen($sTableSql);
		$sTableSql .= str_repeat(static::$__aSpace[0], 6); //prevent stack overflow for utf8 variations

		$aReadBuffersTbl = [];
		$aReadBuffersCols = [];
		$aQuotedTexts = [];

		$sCurrentBuffer = '';
		for ($l = 0; $l < $iLength; $l++) {
			$bNotCommentOrQuot = !$bMultiLineComment && !$SingleLineComment;
			$sCh1 = $sTableSql[$l];
			$sCh12 = $sCh1 . $sTableSql[$l + 1];
			//    $sCh123 = $sCh12 . $sTableSql[$l + 2];


			if ($bNotCommentOrQuot && $sCh12 === '/*') {//enter multi line comment
				$bMultiLineComment = true;
				$l += 1;
			} elseif ($bMultiLineComment && $sCh12 === '*/') {
				$l += 1;
				$bMultiLineComment = false;
				// $sCurrentBuffer .= static::$__aSpace[0];
			} elseif ($bNotCommentOrQuot && ($sCh1 === '#' || $sCh12 === '--')) { //enter single line comment
				$SingleLineComment = true;
				$l += $sCh12 === '--' ? 1 : 0;
			} elseif ($SingleLineComment && (in_array($sCh1, static::$__aEol) || in_array($sCh12, static::$__aEol))) {
				//$sCurrentBuffer .= static::$__aEol[0];
				$l += 0;
				$SingleLineComment = false;
			} elseif ($bNotCommentOrQuot && in_array($sCh1, static::$__aQuotes)) {
				$aQuoted = static::parseExtractQuotedValue($sTableSql, $sCh1, $l);
				$sQuotedKey = 'AFR' . strtoupper(md5($aQuoted[static::QUOTED]));//35
				$aQuotedTexts[$sQuotedKey] = $aQuoted; //extract the quoted values
				$sCurrentBuffer .= '`' . $sQuotedKey;
				$l = $aQuoted[static::END_OFFSET];
			} elseif ($bNotCommentOrQuot && $sCh1 === '(') {
				if ($iInsideParenthesisLevel < 1) {
					$aReadBuffersTbl[0] = trim($sCurrentBuffer);
					$sCurrentBuffer = '';
				} else {
					$sCurrentBuffer .= $sCh1;
				}
				$iInsideParenthesisLevel++;
			} elseif ($bNotCommentOrQuot && $sCh1 === ')') {
				if ($iInsideParenthesisLevel === 1) {
					$sCurrentBuffer = trim($sCurrentBuffer);
					if (strlen($sCurrentBuffer)) { //read last col
						$aReadBuffersCols[] = $sCurrentBuffer;
						$sCurrentBuffer = '';
					}
				} else {
					$sCurrentBuffer .= $sCh1;
				}
				$iInsideParenthesisLevel--;

			} elseif ($bNotCommentOrQuot && $sCh1 == static::$__sEndStatement && $iInsideParenthesisLevel < 1) {
				break;
			} elseif ($bNotCommentOrQuot && $sCh1 === static::$__sComma && $iInsideParenthesisLevel === 1) {
				//read cell details
				$sCurrentBuffer = trim($sCurrentBuffer);
				if (strlen($sCurrentBuffer)) {
					$aReadBuffersCols[] = $sCurrentBuffer;
					$sCurrentBuffer = '';
				}
			} elseif ($bNotCommentOrQuot) {//buffer fill
				$bytes = static::charBytesOrd($sCh1);
				static::parseCreateTableAppendToBuffer($sCurrentBuffer, substr($sTableSql, $l, $bytes)); //append utf8 chars
				$l += $bytes - 1;//-1 fix pointer
			}
			//else {} //skipped chars

		}
		$aReadBuffersTbl[1] = $sCurrentBuffer;
		$aTblAttributes = self::parseOrganiseEqualValues($sCurrentBuffer);

		if (empty($aTblAttributes)) {
			throw new AfrOrmException('Unable to parse correctly the table sql: ' . $sTableSql);
		}


		return [$aReadBuffersTbl, $aReadBuffersCols, $aQuotedTexts, $aTblAttributes];
	}


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
	): array
	{
		if (empty($sQuot)) {
			$sQuot = substr($sText, $iStartOffset, 1);
		}
		if (!in_array($sQuot, static::$__aQuotes)) {
			throw new AfrOrmException('Invalid quot style (' . $sQuot . ')');
		}

		$aReturn = [
			static::QUOTED => null,
			static::COL_NAME => null,
			static::END_OFFSET => $iStartOffset,
		];

		$aEscapeChars = ['\\' . $sQuot];
		$aEscapeChars[] = $sQuot . $sQuot; // 'mysql', 'sqlite'


		$iLength = strlen($sText);
		for ($i = $iStartOffset; $i < $iLength; $i++) {
			$sCurrentChr = substr($sText, $i, 1);
			$sNextChr = ($i + 1 === $iLength) ? '' : substr($sText, $i + 1, 1);
			$sCurrentAndNext = $sCurrentChr . $sNextChr;
			$aReturn[static::END_OFFSET] = $i;

			if ($i === $iStartOffset) {
				$aReturn[static::QUOTED] = $sCurrentChr;
				$aReturn[static::COL_NAME] = $sCurrentChr === $sQuot ? '' : $sCurrentChr;
			} else {

				if (in_array($sCurrentAndNext, $aEscapeChars)) {
					//escape quot character
					$aReturn[static::QUOTED] .= $sCurrentAndNext;
					$aReturn[static::COL_NAME] .= $sNextChr;
					$i++;
					$aReturn[static::END_OFFSET] = $i;
				} elseif ($sCurrentChr === $sQuot) {
					//end
					$aReturn[static::QUOTED] .= $sCurrentChr;
					$aReturn[static::COL_NAME] .= '';
					break;
				} else {
					//add to read buffer
					$aReturn[static::QUOTED] .= $sCurrentChr;
					$aReturn[static::COL_NAME] .= $sCurrentChr;
				}
			}
		}
		if (strpos($aReturn[static::COL_NAME], '\\') !== false) {
			$aReturn[static::COL_NAME] = stripcslashes($aReturn[static::COL_NAME]);
		}
		// \r\n\t\v         // NOTE: In MySQL, `\f` and `\v` have no representation,
		return $aReturn;
	}

	protected static function parseCreateTableAppendToBuffer(string &$sBuffer, string $sChar): void
	{
		if (in_array($sChar, static::$__aSpace)) {
			if (substr($sBuffer, -1, 1) !== static::$__aSpace[0]) {
				$sBuffer .= static::$__aSpace[0]; //add only one space, or tab or enter
			}
		} else {
			$sBuffer .= $sChar;
		}
	}


	/**
	 * @param string $sCurrentBuffer
	 * @return array
	 */
	protected static function parseOrganiseEqualValues(string $sCurrentBuffer): array
	{
		$sSpace = static::$__aSpace[0];
		$sEq = '=';
		$sCurrentBuffer = trim(str_replace(
			[$sSpace . $sEq, $sEq . $sSpace, $sSpace . $sEq . $sSpace],
			$sEq,
			$sCurrentBuffer
		));
		$aTblAttributes = [];
		$sCurrentBuffer .= $sSpace; //read last attribute
		$iLength = strlen($sCurrentBuffer);
		$bValue = false;
		$sAttribute = $sValue = '';
		for ($l = 0; $l < $iLength; $l++) {
			$sCh = substr($sCurrentBuffer, $l, 1);
			if ($sCh === $sEq) {
				$bValue = true;
			} elseif ($bValue && $sCh === $sSpace) {
				$aTblAttributes[strtoupper($sAttribute)] = $sValue;
				$sAttribute = $sValue = '';
				$bValue = false;
			} elseif (!$bValue) {
				$sAttribute .= $sCh;
			} else {
				$sValue .= $sCh;
			}
		}
		return $aTblAttributes;
	}


	/**
	 * @param $aReadBuffersTbl
	 * @param string $sTableSql
	 * @param array $aTabBlueprint
	 * @param $aQuotedTexts
	 * @throws AfrOrmException
	 */
	protected static function parseTabBlueprintHead($aReadBuffersTbl, string $sTableSql, array &$aTabBlueprint, $aQuotedTexts): void
	{
		$sTblHead = $aReadBuffersTbl ?? null;
		if (empty($sTblHead) || stripos($sTblHead, 'CREATE TABLE ') === false) {
			throw new AfrOrmException('Invalid parse table for SQL: ' . $sTableSql);
		}
		if ($aTabBlueprint[static::IF_NOT_EXIST] = !(stripos($sTblHead, 'IF NOT EXISTS') === false)) {
			$sTblHead = str_ireplace('IF NOT EXISTS', '', $sTblHead);
		}
		$sTblHead = trim(str_ireplace('CREATE TABLE ', '', $sTblHead));
		$aDbAndTableName = explode(static::$__sGlueDbTblCol, explode(static::$__aSpace[0], $sTblHead)[0]);
		$iCount = count($aDbAndTableName);
		if ($iCount === 2) {
			$aTabBlueprint[static::TBL_NAME] = static::parseRestoreQuotedText($aDbAndTableName[1], $aQuotedTexts);
			$aTabBlueprint[static::PREFER_DB_NAME] = static::parseRestoreQuotedText($aDbAndTableName[0], $aQuotedTexts);
		} elseif ($iCount === 1) {
			$aTabBlueprint[static::TBL_NAME] = static::parseRestoreQuotedText($aDbAndTableName[0], $aQuotedTexts);
			$aTabBlueprint[static::PREFER_DB_NAME] = null; //not specified, so we inherit
		} else {
			throw new AfrOrmException('Invalid table name in: ' . $sTblHead);
		}
	}

	/**
	 * @param string $sText
	 * @param array $aQuotedTexts
	 * @return float|int|mixed|string
	 */
	protected static function parseRestoreQuotedText(string $sText, array $aQuotedTexts)
	{
		$sText = trim($sText, ' ');
		if (substr($sText, 0, 1) === static::$__sTildeQuot && isset($aQuotedTexts[substr($sText, 1, 35)])) {
			return $aQuotedTexts[substr($sText, 1)][static::COL_NAME];
		}
		if ($iKeyStart = strpos($sText, static::$__sTildeQuot . 'AFR')) {
			$sKey = substr($sText, $iKeyStart + 1, 35);
			if (isset($aQuotedTexts[$sKey][static::COL_NAME])) {
				return substr($sText, 0, $iKeyStart) .
					$aQuotedTexts[$sKey][static::COL_NAME] .
					substr($sText, $iKeyStart + 36);
			}
		}


		if (is_numeric($sText)) { //cast to number
			$sText += 0;
		}

		return $sText;
	}

	/**
	 * @param string $sText
	 * @param array $aQuotedTexts
	 * @return float|int|mixed|string|null
	 */
	protected static function parseRestoreQuotedTextValue(string $sText, array $aQuotedTexts)
	{
		$sText = trim($sText);
		if (strtoupper($sText) === 'NULL') {
			return null;
		}
		return static::parseRestoreQuotedText($sText, $aQuotedTexts);
	}


	/**
	 * @param array $aTblAttributes
	 * @param array $aQuotedTexts
	 * @param array $aTabBlueprint
	 * @param array $aReadBuffersCols
	 * @return void
	 */
	protected static function parseTabBlueprintBody(
		array $aTblAttributes,
		array $aQuotedTexts,
		array &$aTabBlueprint,
		array &$aReadBuffersCols
	): void
	{
		$aTabBlueprint[static::ENGINE] = static::parseRestoreQuotedText($aTblAttributes['ENGINE'] ?? 'MyISAM', $aQuotedTexts);
		$aTabBlueprint[static::CHARSET] = static::parseRestoreQuotedText($aTblAttributes['DEFAULT CHARSET'] ?? 'UTF8MB4', $aQuotedTexts);
		$aTabBlueprint[static::COLLATION] = static::parseRestoreQuotedText($aTblAttributes['COLLATE'] ?? 'UTF8MB4_general_ci', $aQuotedTexts);
		if (isset($aTblAttributes['AUTO_INCREMENT'])) {
			$aTabBlueprint[static::AUTOINCREMENT] = static::parseRestoreQuotedText($aTblAttributes['AUTO_INCREMENT'], $aQuotedTexts);
		}
		if (isset($aTblAttributes['COMMENT'])) {
			$aTabBlueprint[static::COMMENT] = static::parseRestoreQuotedText($aTblAttributes['COMMENT'], $aQuotedTexts);
		}
		foreach ($aReadBuffersCols as $iKey => &$sColSql) {
			if (substr($sColSql, 0, 1) === static::$__aQuotes[0]) {
				continue; //skip encapsulated values because they are not for sure attributes
			}
			if (stripos($sColSql, 'PRIMARY KEY') !== false) {
				$aTabBlueprint[static::PRIMARY_KEY] = static::parseRestoreQuotedText(
					trim(str_ireplace('PRIMARY KEY', '', $sColSql), ' ()'),
					$aQuotedTexts
				);
				unset($aReadBuffersCols[$iKey]);
			} elseif (stripos($sColSql, 'UNIQUE KEY') !== false) {
				$sColSql = str_ireplace('UNIQUE KEY', '', $sColSql);
				self::parseTabBodyKeyParser($sColSql, $aQuotedTexts, $aTabBlueprint, static::UNIQUE_KEYS);
				unset($aReadBuffersCols[$iKey]);
			} elseif (stripos($sColSql, 'FULLTEXT KEY') !== false) {
				$sColSql = str_ireplace('FULLTEXT KEY', '', $sColSql);
				self::parseTabBodyKeyParser($sColSql, $aQuotedTexts, $aTabBlueprint, static::FULLTEXT_KEYS);
				unset($aReadBuffersCols[$iKey]);
			} elseif (stripos(substr($sColSql, 0, 4), 'KEY ') !== false) {
				self::parseTabBodyKeyParser(substr($sColSql, 4), $aQuotedTexts, $aTabBlueprint, static::KEYS);
				unset($aReadBuffersCols[$iKey]);
			} elseif (stripos(substr($sColSql, 0, 11), 'CONSTRAINT ') !== false) {
				self::parseOrganiseConstraintValues($sColSql, $aQuotedTexts, $aTabBlueprint);
				unset($aReadBuffersCols[$iKey]);
			}

		}
	}


	protected static function parseTabBodyKeyParser(string $sColSql, array $aQuotedTexts, array &$aTable, string $sKeyType): void
	{
		$aKeyNameAndCols = explode('(', $sColSql);
		$aColsAndTree = explode(')', $aKeyNameAndCols[1]);
		$sUniqueKeyName = static::parseRestoreQuotedText(
			trim($aKeyNameAndCols[0]),
			$aQuotedTexts
		);


		$aTable[$sKeyType][$sUniqueKeyName] = array_map(
			function ($sVal) use ($aQuotedTexts) {
				return static::parseRestoreQuotedText(
					$sVal,
					$aQuotedTexts
				);
			},
			explode(',', $aColsAndTree[0])
		);
		$aTable[$sKeyType][$sUniqueKeyName][self::KEY_TREE] =
			!empty($aColsAndTree[1]) && trim($aColsAndTree[1]) ?
				trim($aColsAndTree[1]) : null;
	}


	/**
	 * @param array $aTblAttributes
	 * @param array $aQuotedTexts
	 * @param array $aTabBlueprint
	 * @param array $aReadBuffersCols
	 * @return void
	 * @throws AfrOrmException
	 */
	protected static function parseTabBlueprintCols(
		array $aTblAttributes,
		array $aQuotedTexts,
		array &$aTabBlueprint,
		array &$aReadBuffersCols
	): void
	{
		foreach ($aReadBuffersCols as $iKey => &$sColSql) {
			$sColSql = trim($sColSql);
			$sColSqlOriginal = $sColSql;
			$aColSqlParts = explode(static::$__aSpace[0], $sColSql);
			if (count($aColSqlParts) < 2) {
				throw new AfrOrmException('Unable to parse correctly the table sql column: ' . $sColSql);
			}

			//key
			$sKey = static::parseRestoreQuotedText($aColSqlParts[0], $aQuotedTexts);
			$sColSql = substr($sColSql, strlen($aColSqlParts[0]) + 1);
			list($iNextSpacePosition, $iNextParenthesisPosition, $sType) = self::parseTabBlueprintColsGetNextTherm($sColSql);

			$sType = strtoupper($sType);
			$aTypeAttributes = [];
			if ($iNextSpacePosition > $iNextParenthesisPosition) {
				$sTypeAttributes = substr(
					$sColSql,
					$iNextParenthesisPosition + 1,
					strpos($sColSql, ')') - strlen($sType) - 1
				);
				foreach (explode(',', $sTypeAttributes) as $sTypeAttribute) {
					//$mAttribute = static::getQuotedText($sTypeAttribute, $aQuotedTexts);
					$mAttribute = static::parseRestoreQuotedTextValue($sTypeAttribute, $aQuotedTexts);
					$aTypeAttributes[] = $mAttribute;
				}
				$sColSql = substr($sColSql, strlen($sType . $sTypeAttributes) + 3);
			} else {
				$sColSql = substr($sColSql, strlen($sType) + 1);
			}

			$aTabBlueprint[self::COLUMNS][$sKey] = [
				static::COL_TYPE => $sType,
				static::COL_TYPE_ATTRIBUTES => $aTypeAttributes,
				//    'TypeMap' => static::$__aDataTypeMap[$sType],
				static::FLAGS => [],
			];

			//['NOT NULL', 'UNSIGNED', 'AUTO_INCREMENT']
			foreach (static::$__aDataFlags as $sFlag) {
				if (stripos($sColSql, $sFlag) !== false) {
					$aTabBlueprint[self::COLUMNS][$sKey][self::FLAGS][$sFlag] = true;
				}
				$sColSql = str_ireplace($sFlag, '', $sColSql);
				$sColSql = str_replace('  ', static::$__aSpace[0], $sColSql);
			}

			//['CHARACTER SET', 'COLLATE', 'DEFAULT', 'COMMENT', 'ON UPDATE', 'CHECK',]
			foreach (static::$__aDataAttributes as $sAttr) {
				$iAttrStartPos = stripos($sColSql, $sAttr);
				if ($iAttrStartPos === false) {
					continue;
				}
				$iAttrEndPops = $iAttrStartPos + strlen($sAttr); //prepare to find the end of the attribute

				$sColSql .= static::$__aSpace[0];
				$iEnd = strpos($sColSql, static::$__aSpace[0], $iAttrEndPops + 1); //include the space or first char in offset
				$sValue = trim(substr($sColSql, $iAttrEndPops, $iEnd - $iAttrEndPops));

				if ($sAttr === 'CHECK') { // CHECK (json_valid(`json`))
					$aTabBlueprint[self::COLUMNS][$sKey][self::COL_ATTR][$sAttr] =
						static::parseRestoreQuotedText($sValue, $aQuotedTexts);
					$iJsonValid = stripos($sValue, 'json_valid');
					if ($iJsonValid !== false) {
						$aTabBlueprint[self::COLUMNS][$sKey][self::JSON_FLAG] = [
							self::JSON_FLAG => true,
							self::COL_NAME => static::parseRestoreQuotedText(
								trim(substr($sValue, $iJsonValid + 10), ' ()'),
								$aQuotedTexts
							),
						];
					}
				} else {
					$aTabBlueprint[self::COLUMNS][$sKey][self::COL_ATTR][$sAttr] = static::parseRestoreQuotedTextValue($sValue, $aQuotedTexts);
				}
				$sColSql = trim(substr($sColSql, 0, $iAttrStartPos) . substr($sColSql, $iEnd));
			}
			$sColSql = trim($sColSql);
			$aTabBlueprint[self::COLUMNS][$sKey][static::LEFT_OVER_PARSE] = $sColSql;
			//$aTabBlueprint[self::COLUMNS][$sKey][]=$sColSqlOriginal;
			//if($sColSql){   echo '~'.$sColSql.'~';        debug_print_backtrace();die;        }

			unset($aReadBuffersCols[$iKey]);
		}
	}


	/**
	 * @param $sColSql
	 * @return array
	 */
	protected static function parseTabBlueprintColsGetNextTherm($sColSql): array
	{
		//type and values: int(11); enum('a','b','c');
		$iSomeImplausibleBigInt = 99999999;
		$iNextSpacePosition = strpos($sColSql, static::$__aSpace[0]);
		if ($iNextSpacePosition === false) {
			$iNextSpacePosition = $iSomeImplausibleBigInt;
		}
		$iNextParenthesisPosition = strpos($sColSql, '(');
		if ($iNextParenthesisPosition === false) {
			$iNextParenthesisPosition = $iSomeImplausibleBigInt;
		}
		$sType = substr($sColSql, 0, min($iNextSpacePosition, $iNextParenthesisPosition));
		return array($iNextSpacePosition, $iNextParenthesisPosition, $sType);
	}


	/**
	 * @param string $sColSql
	 * @param array $aQuotedTexts
	 * @param array $aTabBlueprint
	 * @return array
	 */
	protected static function parseOrganiseConstraintValues(string &$sColSql, array $aQuotedTexts, array &$aTabBlueprint): array
	{
		foreach (static::$__aKeyWordsTbl[1] as $sConstraint) {
			$sColSql = str_ireplace($sConstraint, '!' . $sConstraint . '=', $sColSql);
		}
		$aConstraintProps = [];
		foreach (explode('!', $sColSql) as $sConstraintSegment) {
			if (empty($sConstraintSegment)) {
				continue;
			}
			$aGroupValues = explode('=', $sConstraintSegment);
			$sCurrentKey = strtoupper(trim($aGroupValues[0]));

			$aKeyNameAndCols = explode('(', $aGroupValues[1]);
			$aColsAndTree = explode(')', $aKeyNameAndCols[1] ?? '');
			if ($sCurrentKey === 'FOREIGN KEY') {
				$aConstraintProps[$sCurrentKey] = array_map(
					function ($sVal) use ($aQuotedTexts) {
						return static::parseRestoreQuotedText(
							$sVal,
							$aQuotedTexts
						);
					},
					explode(',', $aColsAndTree[0])
				);
			} elseif ($sCurrentKey === 'REFERENCES') {
				$aConstraintProps[$sCurrentKey] = array_map(
					function ($sVal) use ($aQuotedTexts) {
						return static::parseRestoreQuotedText(
							$sVal,
							$aQuotedTexts
						);
					},
					explode(',', $aColsAndTree[0])
				);
				$aConstraintProps[$sCurrentKey] = array_merge(
					$aConstraintProps[$sCurrentKey],
					static::getQuotedDbTblTexts(
						$aKeyNameAndCols[0],
						$aQuotedTexts
					)
				);
			} else {
				$aConstraintProps[$sCurrentKey] =
					static::parseRestoreQuotedText(
						$aGroupValues[1],
						$aQuotedTexts
					);
			}

		}
		$sConstraintKey = $aConstraintProps['CONSTRAINT'] ?? '#CONSTRAINT';
		//unset($aConstraintProps['CONSTRAINT']);
		$aTabBlueprint[static::CONSTRAINTS][$sConstraintKey] = $aConstraintProps;
		return array($sColSql, $aTabBlueprint);
	}

	/**
	 * @param string $sColName
	 * @param array $aProps
	 * @return string
	 */
	protected static function parseCreateColumnSql(string $sColName, array $aProps): string
	{
		$sSql = self::encapsulateDbTblColName($sColName) . ' ';
		$sSql .= $aProps[static::COL_TYPE];
		$sSql .= !empty($aProps[static::COL_TYPE_ATTRIBUTES]) ?
			'(' . implode(
				',',
				array_map(fn($sVal) => static::encapsulateCellValue($sVal), $aProps[static::COL_TYPE_ATTRIBUTES])
			) . ') ' : ' ';

		$sSql .= !empty($aProps[static::FLAGS]) ?
			implode(' ', array_keys($aProps[static::FLAGS])) . ' ' : '';

		foreach ($aProps[static::COL_ATTR] ?? [] as $sAttr => $mVal) {
			$bInline =
				in_array($sAttr, ['CHARACTER SET', 'COLLATE']) ||
				$sAttr === 'CHECK' ||
				in_array($sAttr, ['DEFAULT', 'ON UPDATE']) && stripos((string)$mVal, 'CURRENT_TIMESTAMP()') === 0;

			$sSql .= $sAttr . ' ' . ($bInline ? $mVal : static::encapsulateCellValue($mVal)) . ' ';
		}
		$sSql .= !empty($aProps[static::LEFT_OVER_PARSE]) ? $aProps[static::LEFT_OVER_PARSE] . ' ' : '';
		return rtrim($sSql);
	}


	protected static function getQuotedDbTblTexts(string $sText, array $aQuotedTexts): array
	{
		$sText = trim($sText, ' ()');
		$aDbAndTableName = explode(static::$__sGlueDbTblCol, $sText);
		if (count($aDbAndTableName) === 2) {
			return [
				static::PREFER_DB_NAME => static::parseRestoreQuotedText($aDbAndTableName[0], $aQuotedTexts),
				static::TBL_NAME => static::parseRestoreQuotedText($aDbAndTableName[1], $aQuotedTexts)
			];
		}
		return [
			static::TBL_NAME => static::parseRestoreQuotedText($aDbAndTableName[0], $aQuotedTexts),
			static::PREFER_DB_NAME => null
		];
	}

	/**
	 * @param string $sCh1
	 * @return int
	 */
	private static function charBytesOrd(string $sCh1): int
	{
		$iOrder = ord($sCh1);
		if ($iOrder < 128) {
			$bytes = 1;
		} elseif ($iOrder < 224) {
			$bytes = 2;
		} elseif ($iOrder < 240) {
			$bytes = 3;
		} elseif ($iOrder < 248) {
			$bytes = 4;
		} elseif ($iOrder == 252) {
			$bytes = 5;
		} else {
			$bytes = 6;
		}
		return $bytes;
	}


}
