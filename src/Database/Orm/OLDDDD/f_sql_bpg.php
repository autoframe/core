<?php

function write_sql_log_bpg_thf($sql)
{
    $sql = trim($sql);

    if (!preg_match('/^\bupdate\b|^\binsert\b|^\bdelete\b/i', $sql)) return;
    if (preg_match('/^INSERT INTO _html_page/i', $sql)) return;

    $userId = isset($GLOBALS['session']) ? $GLOBALS['session']->getValue('user_id') : '0';
    if ($userId == '') $userId = 0;
    $sql = "INSERT INTO admin_new.logs (user_id, query, date_time) VALUES (" . $userId . ", '" . mysql_real_escape_string($sql) . "', '" . date("Y-m-d H-i-s") . "')";
    $rs = mysql_query($sql);
    if (!$rs) {
        $error = mysql_errno() . ":" . mysql_error() . ". \n\n" . $sql;
        error_log("SQL_ERR: {$error}", 4);
        logErrorBpg($error);
    }
}

function logErrorBpg($error, $type = 'sql_err')
{
    $session = array();
    if (!defined('NO_SESSION') || defined('NO_SESSION') && NO_SESSION == false) {
        //$session = $GLOBALS['session']->getValuesAsArray();
        $session = $_SESSION;
    }
    $dbg = debug_backtrace();
    //empty mail body so that mail error can easier be tracked
    if (isset($dbg[0]['args'][0]['body'])) $dbg[0]['args'][0]['body'] = '';
    //print_r($GLOBALS['session']);
    $log = "INSERT INTO admin_new.log_error
                    (created,info,type,get_var,post_var,files_var,session_var,call_trace)
                VALUES(
                    now(),
                    '" . mysql_real_escape_string($error) . "',
                    '" . mysql_real_escape_string($type) . "',
                    '" . mysql_real_escape_string(print_r($_GET, true)) . "',
                    '" . mysql_real_escape_string(print_r($_POST, true)) . "',
                    '" . mysql_real_escape_string(print_r($_FILES, true)) . "',
                    '" . mysql_real_escape_string(print_r($session, true)) . "',
                    '" . mysql_real_escape_string(print_r($dbg, true)) . "'
                )";
    mysql_query($log);
}

function prefixedTableFieldsWildcard($table, $alias_out = '', $alias_query = '', $db = '', $exclusion = array())
{
    $prefixed = array();
    if (!$alias_query) {
        $alias_query = $table;
    }
    if (!$alias_out) {
        $alias_out = $table;
    }
    if (!isset($GLOBALS['__sql_columns_structure_cache'][$db][$table])) {
        $GLOBALS['__sql_columns_structure_cache'][$db][$table] = multiple_query("SHOW COLUMNS FROM " . ($db ? "`$db`." : "") . "`$table`");
    }
    foreach ($GLOBALS['__sql_columns_structure_cache'][$db][$table] as $column) {
        $field_name = $column["Field"];
        if (in_array($field_name, $exclusion)) {
            continue;
        }
        $prefixed[] = "`{$alias_query}`.`{$field_name}` AS `" . ($db ? $db . '.' : '') . "{$alias_out}.{$field_name}`";
    }
    return implode(", ", $prefixed);
}


function replace_db_word($dbs = array('admin_new', 'dms_new', 'timetracking', 'invoice_app'), $word, $new_word)
{

    set_time_limit(0);
    foreach ($dbs as $db) {
        $q = "use `$db` ";
        sql_query($q);
        echo "<hr><hr><h1>$q</h1>";
        $tables = multiple_query("SHOW TABLES", 'Tables_in_' . $db);
        foreach ($tables as $table => $x) {
            if (substr_count($table, 'log')) {
                continue;
            }
            if (substr_count($table, '_view')) {
                continue;
            }
            if ($table == 'dms_file_extra') {
                continue;
            }
            echo "<hr><h3>$table</h3>";
            $colls = describe_table($table, $db);
            foreach ($colls as $col => $c) {
                if (substr_count($c['Type'], 'varchar') || substr_count($c['Type'], 'text')) {
                } else {
                    continue;
                }
                //echo "<h4>$col</h4>";
                $q = " UPDATE $db.$table SET `$col` = REPLACE(`$col`, '$word', '$new_word') WHERE `$col`  LIKE ('%$word%'); ";
                echo $q . "<br>\r\r";
                sql_query($q);
            }
            //if($table=='_session'){die('end of test');}
        }
        //executeQuery();
    }

}


function prefixedTableFieldsToInputName($composed_field_name)
{
    $out = 0;
    foreach (explode('.', $composed_field_name) as $component) {
        if (!$component) {
            continue;
        }
        if (!$out) {
            $out = $component;
        } else {
            $out .= "[{$component}]";
        }
    }
    return $out;
}

function tableFieldsListWildcard($table, $alias = '', $db = '', $exclusion = array())
{
    $list = array();
    if (!$alias) {
        $alias = $table;
    }
    if (!isset($GLOBALS['__sql_columns_structure_cache'][$db][$table])) {
        $GLOBALS['__sql_columns_structure_cache'][$db][$table] = multiple_query("SHOW COLUMNS FROM " . ($db ? "`$db`." : "") . "`$table`");
    }
    foreach ($GLOBALS['__sql_columns_structure_cache'][$db][$table] as $column) {
        $field_name = $column["Field"];
        if (in_array($field_name, $exclusion)) {
            continue;
        }
        $list[] = "`{$alias}`.`{$field_name}`";
    }
    return implode(", ", $list);
}

//////////// FUNCTII MYSQL ////////////////////////////////////////////////
//http://www.asfromania.ro/consumatori/baza-de-date-cedam/cedam

function db_disconnect()
{
    global $th_mysql_cfg;
    @mysql_close($th_mysql_cfg['connection_res']);
}

function db_connect($th_mysql_cfg)
{
//    prea($th_mysql_cfg);	die; 
    $th_mysql_cfg['connection_res'] = @mysql_connect($th_mysql_cfg['host'], $th_mysql_cfg['username'], $th_mysql_cfg['pass']) or e500($th_mysql_cfg['db_connection_err_msg']);
    if ($th_mysql_cfg['auto_disconnect_on_script_finish']) {
        register_shutdown_function('db_disconnect');
    }

    // mysql_set_charset — Sets the client character set
    if ($th_mysql_cfg['set_utf8_default_connection_charset'] === true) {
        mysql_query("SET character_set_results = 'utf8', character_set_client = 'utf8', character_set_connection = 'utf8', character_set_database = 'utf8', character_set_server = 'utf8'", $th_mysql_cfg['connection_res']);
    }
    $db = $th_mysql_cfg['database_name'];

    if ($db != '' and !@mysql_select_db($db)) {//auto create db...
        mysql_query("CREATE DATABASE `$db` DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;");
        if (!@mysql_select_db($db)) {
            e500($th_mysql_cfg['select_db_err_msg']);
        }
    }

    return $th_mysql_cfg; // mysql_close($dbcnx);
}

if ($th_mysql_cfg['auto_connect']) {
    $th_mysql_cfg = db_connect($th_mysql_cfg);
} //connecting to the database

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

/////////////// PROCEDURA RAPIDA..... COPY+PASTE
/*  $k=0;//contor rezultate curente
$res=mysql_query("SELECT * FROM `tabel` WHERE `id`='$val'");
while($data=mysql_fetch_assoc($res)){
	echo $k; 
	$k++;
	}
if($k==0){echo ' nici un rezultat...';}*/

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

/*
$uniue_slug=array(//table_name => slug_colum
	'tbl_categories'=>'safe_name',
	'tbl_producers'=>'safe_name',
	'filtre_grupuri'=>'safe_name',
	'macrocategorii'=>'nume_safe',
	);
*/

function uniue_slug($slug = '')
{
    global $uniue_slug;
    $tmp_match = array();
    $tmp_match2 = array();
    if (!isset($uniue_slug) || !is_array($uniue_slug)) {
        $uniue_slug = array();
    }
    foreach ($uniue_slug as $tbl => $col) {
        $multiple = multiple_query("SELECT `$col` FROM `$tbl`");
        foreach ($multiple as $data) {
            if (!isset($tmp_match[$data[$col]][$tbl])) {
                $tmp_match[$data[$col]][$tbl] = 1;
            } else {
                $tmp_match[$data[$col]][$tbl]++;
            }

            if (!isset($tmp_match2[$data[$col]][$tbl])) {
                $tmp_match2[$data[$col]][$tbl] = 1;
            } else {
                $tmp_match2[$data[$col]][$tbl]++;
            }
        }
    }
    if (strlen($slug)) {//unique slug
        foreach ($tmp_match as $safe => $tbl_array) {
            if ($slug == $safe) {
                return FALSE;
            }
        }
        return TRUE;
    } else {// slug status:
        $out = '';
        foreach ($tmp_match as $safe => $tbl_array) {
            $te = '';
            $td = '';
            $tt = '';
            if ($safe == '') {
                foreach ($tbl_array as $tk => $tv) {
                    $te .= $tk . '(' . $tv . '); ';
                }
                $out .= 'Empty slug in table(s): ' . $te . PHP_EOL;
            } else {
                foreach ($tbl_array as $tk => $tv) {
                    if ($tv > 1) {
                        $td .= $tk . '(' . $tv . '); ';
                    }
                    $tt .= $tk . ', ';
                }
                if (count($tbl_array) > 1) {
                    $out .= 'Duplicate entry `' . $safe . '` in tables: ' . trim($tt, ' ,') . PHP_EOL;
                }
                if (strlen($td)) {
                    $out .= 'Duplicate entry `' . $safe . '` in table: ' . $td . PHP_EOL;
                    $td = '';
                }
            }
        }
        if ($out == '') {
            return TRUE;
        } else {
            return $out;
        }
    }

}


function get_server_value($colum_name)
{
    return one_query("SELECT `value` FROM `thf_server` WHERE `var`='" . q($colum_name) . "'  LIMIT 1 ");
}

function submit_field($name = 'send', $val = 'Trimite', $disabled = 0, $class = 'submit', $class_over = 'submit_over')
{
    echo '<input type="submit" name="' . $name . '" id="' . $name . '" value="' . $val . '" class="' . $class . '" onmouseover="this.className=\'' . $class_over . '\';" onmouseout="this.className=\'' . $class . '\';" ' . ($disabled != 0 ? 'disabled="disabled"' : '') . ' />';
}

function captcha_field($fieldname = 'security_code', $maxlength = 5)
{
    echo '<input name="' . $fieldname . '" type="text" class="camp_mic" id="' . $fieldname . '" style="text-transform:uppercase;" ' . "onblur=\"input_validate( 'security_code', 'camp_mic', 'camp_red_mic' );\" onclick=\"input_validate('security_code','camp_sel_mic','camp_red_mic');\" onkeyup=\"input_validate('security_code','camp_sel_mic','camp_red_mic');\" " . 'value="" maxlength="' . $maxlength . '"  />';
}

//creez un camp in functie de ce este in baza de date
function form_field($tbl_name, $tbl_col, $filed_name_overwrite = '', $templ = 0, $valuee = '', $required = 0, $maxlen = 0, $html_safe_fillter = 0, $redaonly = 0, $disabled = 0)
{
    global $describe_tbl;    //structura tabelului
    //$templ=0 : TXT MARE;	$templ=1 : TXT MIC;	$templ=2 : PASS MARE;	$templ=3 : PASS MIC;	$templ=4 : TEXTAREA;
    // TO DO: $input_type=   checkbox	radio

    /*<span id="countdescriere">0</span> caractere  (<?php echo $rezervare_txt_max_len; ?> max)
    <script type="text/javascript">setInterval("textarea_len(<?php echo $rezervare_txt_max_len; ?>,'descriere','countdescriere')", 300 );</script>*/

    if ($filed_name_overwrite == '') {
        $filed_name_overwrite = $tbl_col;
    }// in caz ca numele la campul din form este diferit de numele la campul din tbl
    if ($valuee == '') {
        $valuee = trim($_POST[$filed_name_overwrite]);
    }//default value
    if ($html_safe_fillter == 1) {
        $valuee = h($valuee);
    }
    if ($tbl_name != '' && $tbl_col != '') {
        if (!is_array($describe_tbl[$tbl_name])) {
            $res = mysql_query("DESCRIBE `$tbl_name` "); //extragere maxlength
            while ($data = mysql_fetch_array($res, 1)) {
                $describe_tbl[$tbl_name][$data['Field']] = $data;
            }
        }
        $info_type = $describe_tbl[$tbl_name][$tbl_col]['Type'];

        if ($info_type == 'text') {
            $maxlength = '';
        } elseif ($info_type == 'datetime') {
            $maxlength = 'maxlength="20" ';
        } elseif (substr_count($info_type, '(') == 1 && substr_count($info_type, ')') == 1) {
            $tmp = explode('(', $info_type);
            $tmp = explode(')', $tmp[1]);
            $maxlength = 'maxlength="' . $tmp[0] . '" ';
        } else {
            die('form_field : unsupported table type...');
        }

    } elseif ($maxlen > 0) {
        $maxlength = 'maxlength="' . $maxlen . '" ';
    }

    $template[0]['type'] = '<input type="text" ';
    $template[0]['name'] = 'name="' . $filed_name_overwrite . '" id="' . $filed_name_overwrite . '" ';
    $template[0]['value'] = 'value="' . $valuee . '" ';
    $template[0]['maxlength'] = $maxlength;
    $template[0]['redaonly'] = ($redaonly == 1 ? 'readonly="readonly" ' : '');
    $template[0]['disabled'] = ($disabled == 1 ? 'disabled="disabled" ' : '');
    $template[0]['checked'] = ($checked == 1 ? 'checked="checked" ' : '');//  pt checkbox sau pt radio???
    $template[0]['class_neselectat'] = 'camp';
    $template[0]['class_selectat'] = 'camp_sel';
    $template[0]['class_red'] = 'camp_red';

    $template[1] = $template[0];
    $template[1]['class_neselectat'] = 'camp_mic';
    $template[1]['class_selectat'] = 'camp_sel_mic';
    $template[1]['class_red'] = 'camp_red_mic';

    $template[2] = $template[0];
    $template[2]['type'] = '<input type="password" ';
    $template[2]['value'] = '';
    if ($templ == 2) {
        $valuee = '';
    } //unset pt pass

    $template[3] = $template[2];
    $template[3]['class_neselectat'] = 'camp_mic';
    $template[3]['class_selectat'] = 'camp_sel_mic';
    $template[3]['class_red'] = 'camp_red_mic';

    $template[4] = $template[0];
    $template[4]['type'] = '<textarea ';
    $template[4]['class_neselectat'] = 'camp_termeni';
    $template[4]['class_selectat'] = 'camp_termeni_sel';
    $template[4]['class_red'] = 'camp_termeni_red';

    if ($required == 1 && $templ != 4) {//validare java
        $rq = 'onclick="input_validate(' . "'" . $filed_name_overwrite . "','" . $template[$templ]['class_selectat'] . "','" . $template[$templ]['class_red'] . "'" . ');" ';
        $rq .= 'onkeyup="input_validate(' . "'" . $filed_name_overwrite . "','" . $template[$templ]['class_selectat'] . "','" . $template[$templ]['class_red'] . "'" . ');" ';
        $rq .= 'onblur="input_validate(' . "'" . $filed_name_overwrite . "','" . $template[$templ]['class_neselectat'] . "','" . $template[$templ]['class_red'] . "'" . ');" ';
        //$rq.=' onchange="input_validate('."'".$filed_name_overwrite."','".$template[0]['class_selectat']."','".$template[0]['class_red']."'".');" ';
    } elseif ($required == 1 && $templ == 4) {
        die('textfield required cu ingrosare pe rosu neimplementat!!!!! in f_sql');
        //??? neimplementat... validate de lungime! + clasa culoare
    } else {//select simplu
        $rq = 'onclick="this.className=' . "'" . $template[$templ]['class_selectat'] . "'" . ';" ';
        $rq .= 'onblur="this.className=' . "'" . $template[$templ]['class_neselectat'] . "'" . ';" ';
    }

    if ($templ == 4) {//textarea
        echo $template[$templ]['type'];
        echo $template[$templ]['name'];
        //echo 'cols="45" rows="5" ';
        echo $template[$templ]['redaonly'];
        echo $template[$templ]['disabled'];
        if ($required == 1 && $_POST['send'] != '' && $valuee == '') {
            echo 'class="' . $template[$templ]['class_red'] . '" ';
        } else {
            echo 'class="' . $template[$templ]['class_neselectat'] . '" ';
        }
        echo $rq;
        echo '>' . $valuee . '</textarea>';
    } else {
        echo $template[$templ]['type'];
        echo $template[$templ]['name'];
        echo $template[$templ]['value'];
        echo $template[$templ]['maxlength']; //if != checkbox && radiob????
        echo $template[$templ]['redaonly'];
        echo $template[$templ]['disabled'];
        echo $template[$templ]['checked'];//  pt checkbox sau pt radio???
        if ($required == 1 && $_POST['send'] != '' && $valuee == '') {
            echo 'class="' . $template[$templ]['class_red'] . '" ';
        } else {
            echo 'class="' . $template[$templ]['class_neselectat'] . '" ';
        }
        echo $rq . ' />';
    }


}


function pool($print_div = 1, $voteaza_id = '')
{
    if (count_query("SELECT COUNT(*) FROM `pools` WHERE `finalizat`='nu' ") > 0) {//form voruti
        $pool = many_query("SELECT * FROM `pools` WHERE `finalizat`='nu' LIMIT 1 ");
        $out = '';

        if ($print_div == 1) {
            $out .= '<div id="pool_container" onmouseout="rem_fx(this);" onmouseover="add_fx(this);">';
        }
        $out .= '<div align="center" style="padding:5px 0 0 0;"><strong>' . h($pool['titlu']) . '</strong></div>';

        if (is_numeric($voteaza_id) && $_SESSION['pool'] != 'votat') {//voteaza
            $val = one_query("SELECT `hits` FROM `pools_op` WHERE `id`='" . q($voteaza_id) . "'  LIMIT 1 ");
            $val++;
            update_query("UPDATE `pools_op` SET `hits`='$val' WHERE `id`='" . q($voteaza_id) . "' LIMIT 1 ");
            $_SESSION['pool'] = 'votat';
        }
        if ($_SESSION['pool'] != 'votat') {
            $i = 0;
            $res = mysql_query("SELECT * FROM `pools_op` WHERE `pool`= $pool[id] ORDER BY `id` ASC");
            while ($data = mysql_fetch_array($res, 1)) {
                $out .= '<div><label><input type="radio" name="vot" value="' . $data['id'] . '" id="vot_' . $i . '" />' . h($data['optiune']) . '</label></div>';
                $i++;
            }
            $out .= '<div align="center" style="padding:5px 0 0 0;"><input type="button" name="save" id="save" value="Votează" class="submit" ';
            $out .= 'onmouseover="this.className=\'submit_over\';" onmouseout="this.className=\'submit\';" onclick="pool_send(' . $i . ');" /></div>';
        } else {
            $total_hits = 0;
            $res = mysql_query("SELECT * FROM `pools_op` WHERE `pool`= $pool[id] ORDER BY `id` ASC");
            while ($data = mysql_fetch_array($res, 1)) {
                $total_hits += $data['hits'];
            }
            $res = mysql_query("SELECT * FROM `pools_op` WHERE `pool`= $pool[id] ORDER BY `id` ASC");
            while ($data = mysql_fetch_array($res, 1)) $out .= '<p>' . round((100 * $data['hits'] / $total_hits), 1) . '% ' . h($data['optiune']) . '</p>';
            $out .= '<p align="center"><strong>Total ' . $total_hits . ' voturi.</strong></p>';
        }

        if ($print_div == 1) {
            $out .= '</div>';
        } elseif ($print_div == -1) {
            $_SESSION['pool'] = 'nu';
        }
        echo $out;
    }//end of form voruti
}//end of pool


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


/* backup the db to file.zip */
function backup_database($path = './backup/', $filename = NULL, $replace_db = true)
{
    $sql_str = export_database_str($replace_db);
    $dbn = get_dbn();
    $zip = new ZipArchive();
    if ($filename == NULL) {
        $filename = 'db_' . s($dbn) . '_' . date("Y-m-d_H-i-s") . '.zip';
    }
    if (is_file($path . $filename)) {
        unlink($path . $filename);
    }

    if ($zip->open($path . $filename, ZIPARCHIVE::CREATE) !== TRUE) {
        return false;
        die("cannot open <$filename>\n");
    }
    $zip->addFromString(s($dbn) . '_' . date("Y-m-d_H-i-s") . '.sql', $sql_str);
    //$zip->addFile($thisdir . "/too.php","/testfromfile.php");
    //echo "numfiles: " . $zip->numFiles . "\n";
    //echo "status:" . $zip->status . "\n";
    $zip->close();
    return $path . $filename;
    /* //save file
    $handle = fopen('db-backup-'.time().'-'.(md5(implode(',',$tables))).'.sql','w+');
    fwrite($handle,$return);
    fclose($handle);
    */
}

function restore_db($backup_path_file)
{
    if (!is_file($backup_path_file) || !is_readable($backup_path_file)) {
        return false;
    }
    $pathinfo = pathinfo($backup_path_file);
    $zip = new ZipArchive;
    $filename = array();
    if ($zip->open($backup_path_file) == TRUE) {
        $zip_elements = $zip->numFiles;
        for ($i = 0; $i < $zip_elements; $i++) {
            $filename[] = $zip->getNameIndex($i);
        }
    }
    $filename = $filename[count($filename) - 1];//last index :P
    $contents = '#Inport Contents:' . "\r\n";
    $fp = $zip->getStream($filename);
    if ($fp) {
        while (!feof($fp)) {
            $contents .= fread($fp, 2);
        }
        fclose($fp);
    } else {
        return false;
    }
    $queries = preg_split("/;+(?=([^'|^\\\']*['|\\\'][^'|^\\\']*['|\\\'])*[^'|^\\\']*[^'|^\\\']$)/", $contents);
    //prea($queries); die;
    foreach ($queries as $query) {
        if (strlen(trim($query)) > 0) mysql_query($query);
    }
    return true;
}


function convert_db_tables_to_engine($db = '', $from = 'InnoDB', $to = 'MyISAM', $server = -1)
{
    if (!$db) {
        $db = get_dbn($server);
    }
    if (!$db) {
        return array();
    } //no database selected
    $statements = multiple_query("SELECT  CONCAT('ALTER TABLE `', table_name, '` ENGINE=$to;') AS sql_statements
FROM    information_schema.tables AS tb
WHERE   table_schema = '$db'
AND     `ENGINE` = '$from'
AND     `TABLE_TYPE` = 'BASE TABLE'");


}


?>