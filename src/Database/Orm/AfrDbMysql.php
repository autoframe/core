<?php

namespace Autoframe\Core\Database\Orm;

use Autoframe\Core\Database\Connection\AfrDbConnectionManagerFacade;
use Autoframe\Core\Database\Connection\Exception\AfrDatabaseConnectionException;
use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;
use PDO;
use PDOStatement;
use function Autoframe\Core\Database\Connection\prea;
use function Autoframe\Core\Database\Connection\s;

class AfrDbMysql extends AfrSingletonAbstractClass
{
	//TODO profiling
	protected PDO $pdoConn;

	/**
	 * Set connection alias.
	 * @param string|PDO $sAlias_or_oPDO
	 * @param array $aAttributes
	 * @return $this
	 * @throws AfrDatabaseConnectionException
	 */
	public function setConnectionAlias(
		$sAlias_or_oPDO,
		array $aAttributes = [[PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION]] //todo se va pune in alias
	): self
	{
		$this->pdoConn = $sAlias_or_oPDO instanceof PDO ?
			$sAlias_or_oPDO :
			AfrDbConnectionManagerFacade::getInstance()->getConnectionByAlias((string)$sAlias_or_oPDO);
		if (!empty($aAttributes)) {
			foreach ($aAttributes as $aPairs) {
				$this->pdoConn->setAttribute(...$aPairs);
			}
		}
		return $this;
	}

	/**
	 * Get pdo conn.
	 * @param string $sAlias
	 * @return PDO|null
	 */
	public function getPdoConn(string $sAlias): ?PDO
	{
		return !empty($this->pdoConn) ? $this->pdoConn : null;
	}

	/**
	 * Exec.
	 * @param string $statement
	 * @return false|int
	 */
	public function exec(string $statement)
	{
		//delete, update?, insert, on duplicate key?
		return $this->pdoConn->exec($statement);
	}

	//  https://www.php.net/manual/en/pdo.query.php

	/**
	 * Query.
	 * @param string $query
	 * @param int|null $fetchMode
	 * @param string $classname
	 * @param array $constructorArgs
	 * @return PDOStatement|false
	 */
	public function query(string $query,
	                      ?int   $fetchMode = PDO::FETCH_CLASS,
	                      string $classname,
	                      array  $constructorArgs
	): PDOStatement
	{
		//TODO 3X - N abordari ??
		// https://www.php.net/manual/en/pdo.query.php
		// PDO::query — Prepares and executes an SQL statement without placeholders
		return $this->pdoConn->query(...func_get_args());
	}

	function sql_query($str, $die = 1, $echo = 1)
	{
		write_sql_log_bpg_thf($str);
		$rez = mysql_query($str);
		if (!$rez) {
			global $debug_thorr;
			if ($debug_thorr == 1 || !$die) {
				if ($echo) {
					echo '<h3 style="color:red;">sql query eror: ' . $str . ' | ' . mysql_error() . '</h3>';
				}
				return 0;
			} else {
				e500();
			}
		}
		return $rez;
	}

	function count_query($str)
	{// $count=count_query("SELECT COUNT(*) FROM `tabel` WHERE `id`='$val' ");
		$rez = mysql_query($str);
		if (!$rez) {
			global $debug_thorr;
			if ($debug_thorr == 1) {
				die('count query eror: ' . $str . ' | ' . mysql_error());
			} else {
				e500();
			}
		}
		$data = mysql_fetch_array($rez);
		return @floor($data[0]);
	}

	function one_query($str)
	{// $data=one_query("SELECT `field` FROM `tabel` WHERE `id`='$val'  LIMIT 1 ");
		$rez = mysql_query($str);
		if (!$rez) {
			global $debug_thorr;
			if ($debug_thorr == 1) {
				debug_print_backtrace();
				die('one query eror: ' . $str . ' | ' . mysql_error() . '<br />Line:' . __LINE__ . '<br />File:' . __FILE__ . '<br />Func:' . __FUNCTION__);
				prea(get_defined_vars());
				prea(get_declared_classes());
				prea(get_declared_interfaces());
				prea(get_defined_functions());
			} else {
				e500();
			}
		}
		$data = mysql_fetch_array($rez);
		return $data[0];
	}

	function many_query($str)
	{// $data=many_query("SELECT * FROM `tabel` WHERE `id`='$val'  LIMIT 1 ");
		$rez = mysql_query($str);
		if (!$rez) {
			global $debug_thorr;
			if ($debug_thorr == 1) {
				debug_print_backtrace();
				die('many query eror: ' . $str . ' | ' . mysql_error());
			} else {
				e500();
			}
		}
		$data = mysql_fetch_array($rez, 1);
		return $data;
	}

	function many_qa($tablename, $where, $return_query = false)
	{
		$q = "SELECT * FROM `$tablename` WHERE $where LIMIT 1 ";
		if ($return_query) {
			return $q;
		}
		return many_query($q);
	}

	function insert_query($str)
	{// insert_query("INSERT INTO `tabel` SET `col`='".q($val)."' , `col2`='".q($val2)."'");
		write_sql_log_bpg_thf($str);
		if (!mysql_query($str)) {
			global $debug_thorr;
			if ($debug_thorr == 1) {
				die('insert query eror: ' . $str . ' | ' . mysql_error());
			} else {
				e500();
			}
		} else {
			return mysql_insert_id();
		}
	}

	function insert_qa($tablename, $a, $keys_to_exclude = array('id'), $setify_only_keys = array(), $return_query = false)
	{
		$a = form_data_prepare($tablename, $a, $keys_to_exclude, $setify_only_keys);
		if (is_array($a) && count($a) && is_string($tablename) && strlen($tablename) > 0) {
			$q = "INSERT INTO `$tablename` SET ";
			/*		$q="INSERT INTO `$tablename` SET ";
					foreach($a as $i=>$v){
						if($v===NULL){$q.=" `$i`= NULL ,";}
						else{$q.=" `$i`='".q($v)."',";}
						}
					$q=trim($q,', ');*/
			$q = "INSERT INTO `$tablename` " . setify_query($a);
			if ($return_query) {
				return $q;
			}
			return insert_query($q);
		}
		return false;
	}

	function update_query($str)
	{    // update_query("UPDATE `tabel` SET `col`='".q($val)."' , `col2`='".q($val2)."' WHERE `index`='4' LIMIT 1");
		write_sql_log_bpg_thf($str);
		if (!mysql_query($str)) {
			global $debug_thorr;
			if ($debug_thorr == 1) {
				debug_print_backtrace();
				die('update query eror: ' . $str . ' | ' . mysql_error());
			} elseif ($debug_thorr < 0) {
			} else {
				e500();
			}
		} else {
			return mysql_affected_rows();
		}
	}

	function update_qa($tablename, $a, $where, $limit = 'LIMIT 1', $return_query = false)
	{
		if (strlen($limit) < 1) {
			$limit = 'LIMIT 1';
		}
		if (is_array($a) && count($a) > 0 && is_string($tablename) && strlen($tablename) > 0) {
			$q = "UPDATE `$tablename` " . setify_query($a) . " WHERE $where $limit";
			if ($return_query) {
				return $q;
			} else {
				return update_query($q);
			}
		} else {
			return 0;
		}
	}

	function update_qaf($tablename, $a, $where, $limit = 'LIMIT 1', $keys_to_exclude = array('id'), $setify_only_keys = array(), $return_query = false)
	{
		$a = form_data_prepare($tablename, $a, $keys_to_exclude, $setify_only_keys);
		return update_qa($tablename, $a, $where, $limit, $return_query);
	}


	function delete_query($str)
	{// delete_query("DELETE FROM `tabel` WHERE `index`='3' LIMIT 1");
		write_sql_log_bpg_thf($str);
		if (!mysql_query($str)) {
			global $debug_thorr;
			if ($debug_thorr == 1) {
				die('delete query eror: ' . $str . ' | ' . mysql_error());
			} else {
				e500();
			}
		} else {
			return 1;
		}
	}

	function multiple_query($str, $id_fieldname = NULL, $safe = false)
	{// $rows=multiple_query("SELECT * FROM `tabel` WHERE `id`='$val'  LIMIT 100 ");
		$fields = array();
		global $debug_thorr;
		if (!$res = mysql_query($str)) {
			if ($debug_thorr == 1) {
				debug_print_backtrace();
				die('multiple query eror: ' . $str . ' | ' . mysql_error());
			} else {
				e500();
			}
		} else {
			while ($data = mysql_fetch_assoc($res)) {
				if ($id_fieldname) {
					if ($safe) {
						$fields[s($data[$id_fieldname])] = $data;
					} else {
						$fields[$data[$id_fieldname]] = $data;
					}
				} else {
					$fields[] = $data;
				}
			}
		}
		return $fields;
	}

//function setify_query($a){ if(is_array($a) && count($a)){	$q=" SET ";		foreach($a as $i=>$v){$q.=" `$i`='".q($v)."',";} return trim($q,', ');		} return NULL;	}
	function setify_query($a, $set = ' SET ')
	{
		if (is_array($a) && count($a)) {
			$q = " SET ";
			$q = $set;
			foreach ($a as $i => $v) {
				if ($v === NULL && false) {
					$q .= " `$i`= NULL ,";
				} else {
					$q .= " `$i`='" . q($v) . "',";
				}
			}

			return trim($q, ', ');
		}
		return NULL;
	}

	function describe_table($tablename, $db = NULL, $server = -1)
	{
		global $th_mysql_cfg;
		//!!!!!!!!!!!!!!!!!!! get current server id;
		$tablename = '`' . trim($tablename, '`') . '`';
		if ($db) {
			$db = '`' . trim($db, '`') . '`';
			$tablename = $db . '.' . $tablename;
		}
		if (!isset($th_mysql_cfg['describe_cache'][$db][$tablename])) {
			$th_mysql_cfg['describe_cache'][$db][$tablename] = multiple_query("DESCRIBE $tablename ", 'Field', false);
		}
		return $th_mysql_cfg['describe_cache'][$db][$tablename];
	}


//SHOW INDEX FROM `work_day_associates`  //'Column_name'
//INSERT INTO work_day_associates set team_id=25,associate_id=1557, work_day_id=64, hours=3.8   ON DUPLICATE KEY UPDATE hours=3.81


	function insert_update($tablename, $a, $keys_to_exclude = array('id'), $setify_only_keys = array(), $return_query = false)
	{
		$a = form_data_prepare($tablename, $a, $keys_to_exclude, $setify_only_keys);
		if (is_array($a) && count($a) > 0 && is_string($tablename) && strlen($tablename) > 0) {

			foreach (table_indexes($tablename) as $index) {// exclude keys from update command
				if ($index['Non_unique'] < 1 && !in_array($index['Column_name'], $keys_to_exclude)) {
					$keys_to_exclude[] = $index['Column_name'];
				}

			}
			$u = form_data_prepare($tablename, $a, $keys_to_exclude, $setify_only_keys);
			//prea($a); prea($u);
			if (!is_array($u) || count($u) < 1) {
				return false;
			}

			$q = "INSERT INTO `$tablename` " . setify_query($a) . "
		ON DUPLICATE KEY UPDATE " . setify_query($u, '');
			if ($return_query) {
				return $q;
			}

			$mysql_insert_id_prev = @mysql_insert_id();
			if (!mysql_query($q)) {
				global $debug_thorr;
				if ($debug_thorr == 1) {
					debug_print_backtrace();
					die('update query eror: ' . $q . ' | ' . mysql_error());
				} else {
					e500();
				}
			} else {
				$mysql_insert_id_new = @mysql_insert_id();
				if ($mysql_insert_id_prev !== $mysql_insert_id_new) {
					return floor($mysql_insert_id_new);
				}//this was an insert event
				return @mysql_affected_rows() . '.000';//this was a update event return string
			}
			//return insert_query($q);
		} else {
			return false;
		}
	}

	function table_indexes($tablename, $db = NULL, $server = -1)
	{
		global $th_mysql_cfg;
		//!!!!!!!!!!!!!!!!!!! get current server id;
		$tablename = '`' . trim($tablename, '`') . '`';
		if ($db) {
			$db = '`' . trim($db, '`') . '`';
			$tablename = $db . '.' . $tablename;
		}
		if (!isset($th_mysql_cfg['table_indexes'][$db][$tablename])) {
			$th_mysql_cfg['table_indexes'][$db][$tablename] = multiple_query("SHOW INDEX FROM $tablename ");
		}
		return $th_mysql_cfg['table_indexes'][$db][$tablename];
	}


//RETURNS array; INPUT form_array = $_POST;  keys_to_exclude from SET OR keep only this keys
	function form_data_prepare($table_name, $form_array, $keys_to_exclude = array('id'), $setify_only_keys = array())
	{
		$out = array();
		$d = describe_table($table_name);
		if (count($d) < 2 || !is_array($form_array)) {
			return $out;
		}
		if ($setify_only_keys && !is_array($setify_only_keys) && is_string($setify_only_keys) && strlen($setify_only_keys)) {
			$setify_only_keys = explode(',', $setify_only_keys);
		}
		if ($keys_to_exclude && !is_array($keys_to_exclude) && is_string($keys_to_exclude) && strlen($keys_to_exclude)) {
			$keys_to_exclude = explode(',', $keys_to_exclude);
		}

		if (is_array($setify_only_keys) && count($setify_only_keys)) {//only this keys
			foreach ($setify_only_keys as $sk) {
				if (isset($d[$sk]) && isset($form_array[$sk])) {
					$out[$sk] = $form_array[$sk];
				}
			}
		} else {
			foreach ($form_array as $k => $v) {
				if (isset($d[$k]) && !in_array($k, $keys_to_exclude)) {
					$out[$k] = $v;
				}
			}
		}
		return $out;
	}

	function thf_paginate_limit_sql($total_results = 1, $curent_page = 1, $results_per_page = 50)
	{
		$total_pages = ceil($total_results / $results_per_page);
		$curent_page = floor($curent_page);
		if ($curent_page < 1) {
			$curent_page = 1;
		} elseif ($curent_page > $total_pages) {
			$curent_page = $total_pages;
		}
		if ($curent_page < 2) {
			return ' LIMIT ' . $results_per_page;
		}
		return ' LIMIT ' . (($curent_page - 1) * $results_per_page) . ', ' . $results_per_page;
	}

	function get_sql_operator_from($fieldname, $operator, $fieldvalue)
	{
		$out = " `$fieldname` ";
		if ($operator == 'IS NULL') {
			return $out . " IS NULL ";
		}
		if ($operator == 'IS NOT NULL') {
			return $out . " IS NOT NULL ";
		}
		if ($operator == '=') {
			return $out . "='" . q($fieldvalue) . "' ";
		}
		if ($operator == '!=') {
			return $out . "!='" . q($fieldvalue) . "' ";
		}
		if ($operator == '>') {
			return $out . ">'" . q($fieldvalue) . "' ";
		}
		if ($operator == '<') {
			return $out . "<'" . q($fieldvalue) . "' ";
		}
		if ($operator == 'like') {
			return $out . "LIKE '" . q($fieldvalue) . "' ";
		}
		if ($operator == 'like%.%') {
			return $out . "LIKE'%" . q($fieldvalue) . "%' ";
		}
		if ($operator == 'like%.') {
			return $out . "LIKE'%" . q($fieldvalue) . "' ";
		}
		if ($operator == 'like.%') {
			return $out . "LIKE'" . q($fieldvalue) . "%' ";
		}
		if ($operator == 'notlike%.%') {
			return $out . "NOT LIKE'%" . q($fieldvalue) . "%' ";
		}
	}

	function sql_parser($str)
	{
		$sql = array();    //sql single command blocks
		$comment = false;    // / *
		$diez = false;    // where 'a'=1; #some thing
		$minus2x = false; //-- comment
		$squot = false; //single quot
		$dquot = false; //double quot
		$tquot = false; //tilda ` quot

		$len = strlen($str);
		$str .= '   ';//3x space at the end for safety
		$buffer = NULL;
		for ($l = 0; $l < $len; $l++) {
			$i1 = 0;
			$i2 = $i1 + 1;

			if (!$comment && !$diez && !$minus2x && !$squot && !$dquot && $str[$l] . $str[$l + 1] == '/*') {
				$comment = true;
				$l += $i2;
			}//enter comment procedure
			elseif ($comment && $str[$l] . $str[$l + 1] == '*/') {
				$l += $i2;
				$comment = false;
			} elseif (!$comment && !$diez && !$minus2x && !$squot && !$dquot && $str[$l] . $str[$l + 1] == '--') {
				$minus2x = true;
				$l += $i2;
			}//enter comment procedure
			elseif ($minus2x && $str[$l] == "\n") {
				$buffer .= ' ';
				$l += $i1;
				$minus2x = false;
			} elseif (!$comment && !$diez && !$minus2x && !$squot && !$dquot && $str[$l] == '#') {
				$diez = true;
				$l += $i1;
			}//enter comment procedure
			elseif ($diez && $str[$l] == "\n") {
				$buffer .= ' ';
				$l += $i1;
				$diez = false;
			} elseif (!$comment && !$diez && !$minus2x && !$squot && !$dquot && $str[$l] == "'") {
				$squot = true;
				$buffer .= $str[$l];
			}//squot
			elseif ($squot) {
				$buffer .= $str[$l];
				if ($str[$l] == "'" && $str[$l - 1] != chr(92)) {
					$squot = false;
				}
			} elseif (!$comment && !$diez && !$minus2x && !$squot && !$dquot && $str[$l] == '"') {
				$dquot = true;
				$buffer .= $str[$l];
			}//dquot
			elseif ($dquot) {
				$buffer .= $str[$l];
				if ($str[$l] == '"' && $str[$l - 1] != chr(92)) {
					$dquot = false;
				}
			} elseif (!$comment && !$diez && !$minus2x && !$squot && !$dquot && $str[$l] == ';') {
				$sql[] = trim($buffer . ';');
				$buffer = NULL;
			}//buffer dump
			elseif (!$comment && !$diez && !$minus2x && !$squot && !$dquot) {//buffer fill
				//$buffer.=$str[$l]; //utf-8 fix
				$char = ord($str[$l]);
				if ($char < 128) {
					$buffer .= $str[$l];
				} else {
					if ($char < 224) {
						$bytes = 2;
					} elseif ($char < 240) {
						$bytes = 3;
					} elseif ($char < 248) {
						$bytes = 4;
					} elseif ($char == 252) {
						$bytes = 5;
					} else {
						$bytes = 6;
					}
					$buffer .= substr($str, $l, $bytes);
					$l += $bytes - 1;//-1 pt ca se incrementeaza oricum din for
				}
			} else {
			}//skipped chars

		}
		if ($buffer && trim($buffer)) {
			$sql[] = trim($buffer);
			$buffer = NULL;
		}
		return $sql;
	}

	function sql_block_execute($sql_str, $debug = 0)
	{
		$true = 0;
		$false = 0;
		$arr = sql_parser($sql_str);
		foreach ($arr as $q) {
			if ($debug) {
				if (sql_query($q, 0, 1)) {
					$true++;
				} else {
					$false++;
				}
			} else {
				if (sql_query($q)) {
					$true++;
				} else {
					$false++;
				}
			}
		}
		return array('statements' => count($arr), 'true' => $true, 'false' => $false);
	}


////  OPTIMIZE ALL TABLES
	function optimize_database()
	{
		$result = mysql_query('SHOW TABLES') or die('Cannot get tables');
		while ($table = mysql_fetch_row($result)) {
			mysql_query('OPTIMIZE TABLE ' . $table[0]) or die('Cannot optimize ' . $table[0]);
		}
	}

	function get_dbn()
	{// get current selected database name
		$rez = mysql_query('SELECT database( );');
		if (!$rez) {
			die('get database name eror! ' . mysql_error());
		}
		$data = mysql_fetch_array($rez);
		return $data[0];
	}

	function get_db_tables()
	{
		$tables = array();
		$result = mysql_query('SHOW TABLES');
		while ($row = mysql_fetch_row($result)) {
			$tables[$row[0]] = $row[0];
		}
		ksort($tables);
		return $tables;
	}

	function is_db_table($tbl_name)
	{
		$tables = get_db_tables();
		foreach ($tables as $name) {
			if ($name == $tbl_name) {
				return true;
			}
		}
		return false;
	}

	function getTablePKeys($table_name, $db = '')
	{
		$list = array();
		if (!$db) {
			$db = get_dbn();
		}
		$keys = many_query("SELECT GROUP_CONCAT(`COLUMN_NAME`) as 'list' FROM  `information_schema`.`columns` 
	WHERE  `table_schema`='" . q($db) . "'  AND `table_name`='" . q($table_name) . "' AND `COLUMN_KEY` LIKE '%PRI%'");
		if ($keys && $keys['list']) {
			$list = explode(',', $keys['list']);
		}
		return $list;
	}

	function getTableUKeys($table_name, $db = '')
	{
		$list = array();
		if (!$db) {
			$db = get_dbn();
		}
		$keys = many_query("SELECT GROUP_CONCAT(`COLUMN_NAME`) as 'list' FROM  `information_schema`.`columns` 
	WHERE  `table_schema`='" . q($db) . "'  AND `table_name`='" . q($table_name) . "' AND `COLUMN_KEY` LIKE '%UNI%'");
		if ($keys && $keys['list']) {
			$list = explode(',', $keys['list']);
		}
		return $list;
	}


	function export_database_str($replace_db = false, $structure_only = false)
	{
		$dbn = get_dbn();
		$return = "# " . date("Y-m-d H:i:s") . " by thorr framework:
SET SQL_MODE=\"NO_AUTO_VALUE_ON_ZERO\";
" . ($replace_db == false ? '#' : '') . "DROP DATABASE IF EXISTS `$dbn`;
" . ($replace_db == false ? '#' : '') . "CREATE DATABASE `$dbn` DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;
USE `$dbn`;
";
		$tables = get_db_tables();

		//cycle through
		foreach ($tables as $table) {


			//$return.= 'DROP TABLE '.$table.';';
			$row2 = mysql_fetch_row(mysql_query('SHOW CREATE TABLE ' . $table));
			$row2[1] = preg_replace("\n", "\r\n", $row2[1]);
			$return .= "\r\n\r\n" . $row2[1] . ";\r\n\r\n";


			if ($structure_only) {
				continue;
			}
			$result = mysql_query('SELECT * FROM ' . $table);
			$num_fields = mysql_num_fields($result);
			for ($i = 0; $i < $num_fields; $i++) {
				while ($row = mysql_fetch_row($result)) {
					$return .= 'INSERT INTO `' . $table . '` VALUES( ';
					for ($j = 0; $j < $num_fields; $j++) {
						$row[$j] = addslashes($row[$j]);
						$row[$j] = preg_replace("\r\n", "\\r\\n", $row[$j]);
						if (isset($row[$j])) {
							$return .= '"' . $row[$j] . '"';
						} else {
							$return .= '""';
						}
						if ($j < ($num_fields - 1)) {
							$return .= ',';
						}
					}
					$return .= "); \r\n";
				}
			}
			$return .= "\r\n\r\n\r\n";
		}
		return $return;
	}

}
