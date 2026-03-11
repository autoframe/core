<?php
/*	http://code.tutsplus.com/tutorials/why-you-should-be-using-phps-pdo-for-database-access--net-12059
	http://wiki.hashphp.org/PDO_Tutorial_for_MySQL_Developers */

header('Cache-Control: no-cache, no-store, must-revalidate'); // HTTP 1.1.
header('Pragma: no-cache'); // HTTP 1.0.
header('Expires: 0'); // Proxies.

function e500($str='eroare generica 500'){	die($str);	} $debug_thorr=0;

$th_mysql_cfg=array(
	'db_type'=>'mysql',// print_r(PDO::getAvailableDrivers())  ->   Array ( [0] => mysql [1] => odbc [2] => sqlite ) 
	"tbl_prefix"=>"thf_",
	"auto_connect"=>true,
	"database_name"=>"db_shortcut",
	"username"=>"cristi",
	"pass"=>"kogalniceanu14",
	"host"=>"localhost",
	"port"=>NULL, //NULL is the default port
	"admin_mail"=>"xelan_god@yahoo.com",
	"set_utf8_default_connection_charset"=>true,	//for compatibility reasons
	"charset"=>'utf8',							//pdo mode
	"auto_disconnect_on_script_finish"=>true,	//register shutdown function
	"db_connection_err_msg"=>"Temporary Mentenance Procedures! Database connection timeout.",
	"select_db_err_msg"=>"Temporary Mentenance Procedures! Database selection timeout.",
	"connection_res"=>false,	//do not modify! used for connection resource and register shutdown function over sql server
	"pdo_errmode"=>'exception', //	silent	warning		exception
	"pdo_emulate_prepares"=>false,	
	'time_zone'=>'+2:00',
	); /*
PDO::ERRMODE_SILENT acts like mysql_* where you must check each result and then look at $pdo->errorInfo(); to get the error details.
PDO::ERRMODE_WARNING throws PHP Warnings
PDO::ERRMODE_EXCEPTION throws PDOException. In my opinion this is the mode you should use. It acts very much like or die(mysql_error()); when it isn't caught, but unlike or die() the PDOException can be caught and handled gracefully if you choose to do so.*/

$th_mysql_cfg=array(
	'db_type'=>'sqlite',// print_r(PDO::getAvailableDrivers())  ->   Array ( [0] => mysql [1] => odbc [2] => sqlite ) 
	"tbl_prefix"=>"thf_",
	"auto_connect"=>true,
	"database_name"=>"./test.sqli",
	"username"=>"cristi",
	"pass"=>"kogalniceanu14",
	"host"=>"localhost",
	"port"=>NULL, //NULL is the default port
	"admin_mail"=>"xelan_god@yahoo.com",
	"set_utf8_default_connection_charset"=>true,	//for compatibility reasons
	"charset"=>'utf8',							//pdo mode
	"auto_disconnect_on_script_finish"=>true,	//register shutdown function
	"db_connection_err_msg"=>"Temporary Mentenance Procedures! Database connection timeout.",
	"select_db_err_msg"=>"Temporary Mentenance Procedures! Database selection timeout.",
	"connection_res"=>false,	//do not modify! used for connection resource and register shutdown function over sql server
	"pdo_errmode"=>'exception', //	silent	warning		exception
	"pdo_emulate_prepares"=>false,	
	'time_zone'=>'+2:00',
	);

//The ->quote() method quotes strings so they are safe to use in queries. This is your fallback if you're not using prepared statements.
function qp($unsafe,$trim_margins=1){global $pdo,$th_mysql_cfg;
	if(!isset($th_mysql_cfg['db_type']) || $th_mysql_cfg['db_type']=='mysql_old'){$unsafe="'".q($unsafe)."'";}
	else{$unsafe= $pdo->quote($unsafe);}
	if($trim_margins){return substr($unsafe,1,-1);} else{ return $unsafe; }
	}
function q($str,$param=NULL){/*  cand adaug in sql direct din post, si get; inainte de get fac un entities_decode
	( ' )default mode;   1( " )double quote;   2(&quot; &#039;)paranoia mode */
	if($param==1){return str_replace('"','\"',$str);}
	elseif($param==2){$str=str_replace('"','&quot;',$str); return str_replace("'",'&#039;',$str);}
	else{ // \x00, \n, \r, \, ', " and \x1a
		$str=str_replace(chr(92),chr(92).chr(92),$str); //chr(92)=\
		$str=str_replace("'","''",$str); //versiune clasica :P $str=str_replace("'","\'",$str);
		return $str;
		}
	}
function db_available_types(){ $types=array();	$available=PDO::getAvailableDrivers(); foreach($available as $at){$types[$at]=$at;} return $types;}

function db_sitch($new_th_mysql_cfg){	global $th_mysql_cfg,$pdo; // CONECT TO OTHER DATABASES TYPES
	if($pdo || $th_mysql_cfg['connection_res']){db_disconnect();}
	$th_mysql_cfg=$new_th_mysql_cfg;	db_connect();
	}

function db_disconnect(){	global $th_mysql_cfg,$pdo;
		if(!isset($th_mysql_cfg['db_type']) || $th_mysql_cfg['db_type']=='mysql_old'){return @mysql_close($th_mysql_cfg['connection_res']);}
		if( isset($th_mysql_cfg['db_type']) && $th_mysql_cfg['db_type']=='mysql'){$pdo=NULL; return 1;}
	elseif( isset($th_mysql_cfg['db_type']) && $th_mysql_cfg['db_type']=='sqlite'){$pdo=NULL; return 1;}
	elseif( isset($th_mysql_cfg['db_type']) && $th_mysql_cfg['db_type']=='sybase'){$pdo=NULL; return 1;}
	elseif( isset($th_mysql_cfg['db_type']) && $th_mysql_cfg['db_type']=='mssql'){$pdo=NULL; return 1;}
	elseif( isset($th_mysql_cfg['db_type']) && $th_mysql_cfg['db_type']=='pgsql'){
		$pdo->query('SELECT pg_terminate_backend(pg_backend_pid());');
		$pdo=NULL; 	return 1;	}
	else{return 0;}
	}

function db_connect($unused='') {	global $th_mysql_cfg,$pdo; 	//    prea($th_mysql_cfg);	die;
	//old script back compatibility
	if(!isset($th_mysql_cfg['db_type'])){$th_mysql_cfg['db_type']='mysql_old';}
	if(!isset($th_mysql_cfg['charset']) || isset($th_mysql_cfg['set_utf8_default_connection_charset']) && $th_mysql_cfg['set_utf8_default_connection_charset']){$th_mysql_cfg['charset']='utf8';}
	
	if($th_mysql_cfg['db_type']=='mysql_old'){ //old script back compatibility
		$th_mysql_cfg['connection_res'] = mysql_connect($th_mysql_cfg['host'], $th_mysql_cfg['username'],$th_mysql_cfg['pass']) or e500($th_mysql_cfg['db_connection_err_msg']);
		if($th_mysql_cfg['auto_disconnect_on_script_finish']){register_shutdown_function('db_disconnect');}
		if($th_mysql_cfg['set_utf8_default_connection_charset']===true){ mysql_query("SET character_set_results = 'utf8', character_set_client = 'utf8', character_set_connection = 'utf8', character_set_database = 'utf8', character_set_server = 'utf8'", $th_mysql_cfg['connection_res']); }
		if($th_mysql_cfg['database_name']!='' and !@mysql_select_db($th_mysql_cfg['database_name'])){//auto create db...
			mysql_query("CREATE DATABASE `".$th_mysql_cfg['database_name']."` DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;");
			if(!@mysql_select_db($th_mysql_cfg['database_name'])){e500($th_mysql_cfg['select_db_err_msg']);} 
			}		
		return 1; 
		}
	else{//PDO
		$db_available_types=db_available_types();
		if(isset($db_available_types[$th_mysql_cfg['db_type']])){
/* try {
  $pdo = new PDO('cubrid:host=$host;port=8001;dbname=platin',  $user, $pass); #cubrid

  $pdo = new PDO("sybase:host=$host;dbname=$dbname, $user, $pass");# PDO_DBLIB was linked against the Sybase ct-lib libraries
  $pdo = new PDO("mssql:host=$host;dbname=$dbname, $user, $pass"); # PDO_DBLIB was linked against the Microsoft SQL Server libraries
  $pdo = new PDO("dblib:host=$host;dbname=$dbname, $user, $pass"); # PDO_DBLIB was linked against the FreeTDS libraries.

  $pdo = new PDO("firebird:host=$host;dbname=$dbname", $user, $pass);# PDO_FIREBIRD without the port

  $pdo = new PDO("ibm:DRIVER={IBM DB2 ODBC DRIVER};DATABASE=testdb;HOSTNAME=11.22.33.444;PORT=56789;PROTOCOL=TCPIP;", $user, $pass);#  PDO_IBM DSN

  $pdo = new PDO("informix:host=host.domain.com; service=9800; database=common_db; server=ids_server; protocol=onsoctcp; EnableScrollableCursors=1", "testuser", "tespass"); #PDO_INFORMIX

  $pdo = new PDO("mysql:host=$host;port=3307;dbname=$dbname", $user, $pass);# MySQL with PDO_MYSQL
  $pdo = new PDO("mysql:unix_socket=/tmp/mysql.sock;dbname=testdb", $user, $pass);# MySQL with PDO_MYSQL

  $pdo = new PDO("oci:dbname=192.168.10.145:1521/mydb;charset=CL8MSWIN1251", "testuser", "tespass"); #Oracle Instant Client PDO_OCI

   #PDO_ODBC
  $pdo = new PDO("odbc:DRIVER={IBM DB2 ODBC DRIVER};HOSTNAME=localhost;PORT=50000;DATABASE=SAMPLE;PROTOCOL=TCPIP;UID=db2inst1;PWD=ibmdb2;", "testuser", "tespass"); #IBM DB2 uncataloged connection
  $pdo = new PDO("odbc:Driver={Microsoft Access Driver (*.mdb)};Dbq=C:\\db.mdb;Uid=Admin", "testuser", "tespass"); #Microsoft Access PDO_ODBC

  $pdo = new PDO('pgsql:host=$host;port=5432;dbname=platin',  $user, $pass); #postgre

  $pdo = new PDO("sqlite:my/database/path/database.db"); # SQLite Database
  $pdo = new PDO("sqlite::memory:"); # SQLite Database
  $pdo = new PDO("sqlite:"); # SQLite Database

}	catch(PDOException $e) {    echo $e->getMessage();} */
			$connect=$th_mysql_cfg['db_type'].':';
			if($th_mysql_cfg['db_type']=='sqlite'){
				
				$connect.=$th_mysql_cfg['database_name']; // $pdo = new PDO("sqlite:my/database/path/database.db"); # SQLite Database
				try {$pdo = new PDO($connect);} catch(PDOException $e){e500($e->getMessage());}
				}
			else{
				if(isset($th_mysql_cfg['host']) && $th_mysql_cfg['host']!=''){$connect.='host='.$th_mysql_cfg['host'].';';}
				if(isset($th_mysql_cfg['port']) && $th_mysql_cfg['port']!=''){$connect.='port='.$th_mysql_cfg['port'].';';}
				if(isset($th_mysql_cfg['charset']) && $th_mysql_cfg['charset']!=''){$connect.='charset='.$th_mysql_cfg['charset'].';';}
				if(isset($th_mysql_cfg['database_name']) && $th_mysql_cfg['database_name']!=''){$connect.='dbname='.$th_mysql_cfg['database_name'].';';}
				$connect=rtrim($connect,';');
				//$pdo = new PDO("mssql:host=$host;dbname=$dbname, $user, $pass"); # MS SQL Server
				//$pdo = new PDO("sybase:host=$host;dbname=$dbname, $user, $pass");# Sybase with PDO_DBLIB
				//$pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);# MySQL with PDO_MYSQL
				//$pdo = new PDO('pgsql:host=$host;port=5432;dbname=platin',  $user, $pass); #postgre
				try {$pdo = new PDO($connect,$th_mysql_cfg['username'],$th_mysql_cfg['pass']);}
				catch(PDOException $e){e500($th_mysql_cfg['db_connection_err_msg'].'<br /><br />'.$e->getMessage());}

				if($th_mysql_cfg['pdo_emulate_prepares']=='silent'){$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);}
				elseif($th_mysql_cfg['pdo_emulate_prepares']=='warning'){$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);}
				else{$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);}				
				$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $th_mysql_cfg['pdo_emulate_prepares']);
				$pdo->exec("SET time_zone = '".$th_mysql_cfg['time_zone']."'");
				if($th_mysql_cfg['auto_disconnect_on_script_finish']){@register_shutdown_function('db_disconnect');}				
				}
			}
		else{e500('Database driver type:'.$th_mysql_cfg['db_type'].' is not supported / installed on the server...');}
		}		
    } 

if($th_mysql_cfg['auto_connect']){db_connect();	} //connecting to the database


function last_insert_id_query(){	global $th_mysql_cfg,$pdo;
	if($th_mysql_cfg['db_type']=='mysql_old'){return mysql_insert_id();}
	else{return  $pdo->lastInsertId();}
	}

function count_query($str,$replace_star_count=1){// $count=count_query("SELECT COUNT(*) FROM `tabel` WHERE `id`='$val' "); 
	global $th_mysql_cfg,$pdo,$debug_thorr;
	if($th_mysql_cfg['db_type']=='mysql_old'){
		$rez=mysql_query($str);
		if(!$rez){if($debug_thorr==1){die('count query eror: '.$str.' | '.mysql_error());}	else{e500();}	}
		$data=mysql_fetch_array($rez);	return $data[0];
		}
	else{
		if($replace_star_count){$str=str_replace('COUNT(*)','*',$str);}
		try{ $stmt = $pdo->query($str); $rows=$stmt->rowCount(); }
		catch(PDOException $e) {if($debug_thorr==1){die('count query eror: '.$str.' | '. $e->getMessage());}	else{e500();}	}
		return $rows;
		}
	}
	
function one_query($str){// $data=one_query("SELECT `field` FROM `tabel` WHERE `id`='$val'  LIMIT 1 "); 
	global $th_mysql_cfg,$pdo,$debug_thorr;
	if($th_mysql_cfg['db_type']=='mysql_old'){
		$rez=mysql_query($str);	
		if(!$rez){if($debug_thorr==1){die('one query eror: '.$str.' | '.mysql_error());}else{e500();} }
		$data=mysql_fetch_array($rez);	
		}
	else{
		try{ $stmt = $pdo->query($str); $data = $stmt->fetch(PDO::FETCH_NUM); }
		catch(PDOException $e) {if($debug_thorr==1){die('one query eror: '.$str.' | '. $e->getMessage());}	else{e500();}	}
		}
	return $data[0];
	}
	
function many_query($str){// $data=many_query("SELECT * FROM `tabel` WHERE `id`='$val'  LIMIT 1 ");
	global $th_mysql_cfg,$pdo,$debug_thorr;
	if($th_mysql_cfg['db_type']=='mysql_old'){
		$rez=mysql_query($str);
		if(!$rez){if($debug_thorr==1){die('many query eror: '.$str.' | '.mysql_error());}else{e500();} }
		$data=mysql_fetch_array($rez,1);	
		}
	else{
		try{ $stmt = $pdo->query($str); $data = $stmt->fetch(PDO::FETCH_ASSOC); }
		catch(PDOException $e) {if($debug_thorr==1){die('many query eror: '.$str.' | '. $e->getMessage());}	else{e500();}	}
		}
	return $data;
	}
	

function multiple_query($str){// $multiple=multiple_query("SELECT * FROM `tabel` WHERE `id`='$val'  LIMIT 100 ");
	global $th_mysql_cfg,$pdo,$debug_thorr;	$rows=array();
	if($th_mysql_cfg['db_type']=='mysql_old'){	
		if(!$res=mysql_query($str)){if($debug_thorr==1){die('multiple query eror: '.$str.' | '.mysql_error());} 	else{e500();}}
		else{ while($data=mysql_fetch_array($res,1)){$rows[]=$data;} }
		}
	else{
		try{ $stmt = $pdo->prepare($str);$stmt->execute(); $rows = $stmt->fetchAll(PDO::FETCH_ASSOC); }
		catch(PDOException $e) {if($debug_thorr==1){die('multiple query eror:  '.$str.' | '. $e->getMessage());}	else{e500();}	}
		}
	return $rows;
	}


function insert_query($str){// insert_query("INSERT INTO `tabel` SET `col`='".q($val)."' , `col2`='".q($val2)."'");
	global $th_mysql_cfg,$pdo,$debug_thorr;
	if($th_mysql_cfg['db_type']=='mysql_old'){
		if(!mysql_query($str)){	if($debug_thorr==1){die('insert query eror: '.$str.' | '.mysql_error());}else{e500();}}
		else{return 1;}
		}
	else{
		try{ $result = $pdo->exec($str); }
		catch(PDOException $e) {if($debug_thorr==1){die('insert query eror: '.$str.' | '. $e->getMessage());}	else{e500();}	}
		return $result;
		}
	}

function update_query($str){	// update_query("UPDATE `tabel` SET `col`='".q($val)."' , `col2`='".q($val2)."' WHERE `index`='4' LIMIT 1");
	global $th_mysql_cfg,$pdo,$debug_thorr;	
	if($th_mysql_cfg['db_type']=='mysql_old'){
		if(!mysql_query($str)){	if($debug_thorr==1){die('update query eror: '.$str.' | '.mysql_error());}else{e500();}	}
		else{return 1;}
		}
	else{
		try{ $affected_rows = $pdo->exec($str); }
		catch(PDOException $e) {if($debug_thorr==1){die('update query eror: '.$str.' | '. $e->getMessage());}	else{e500();}	}
		return $affected_rows;
		}	
	}
	
function delete_query($str){// delete_query("DELETE FROM `tabel` WHERE `index`='3' LIMIT 1");
	global $th_mysql_cfg,$pdo,$debug_thorr;	
	if($th_mysql_cfg['db_type']=='mysql_old'){	
		if(!mysql_query($str)){	if($debug_thorr==1){die('delete query eror: '.$str.' | '.mysql_error());}else{e500();} }
		else{return 1;}
		}
	else{
		try{ $affected_rows = $pdo->exec($str); }
		catch(PDOException $e) {if($debug_thorr==1){die('delete query eror: '.$str.' | '. $e->getMessage());}	else{e500();}	}
		return $affected_rows;
		}	
	}



$debug_thorr=1;
// tbl_sessions
// cron
//echo $pdo->query('select database()')->fetchColumn();

    $pdo->exec("CREATE TABLE Dogs (Id INTEGER PRIMARY KEY, Breed TEXT, Name TEXT, Age INTEGER)");    

    //insert some data...
    $pdo->exec("INSERT INTO Dogs (Breed, Name, Age) VALUES ('Labrador', 'Tank', 2);".
               "INSERT INTO Dogs (Breed, Name, Age) VALUES ('Husky', 'Glacier', 7); " .
               "INSERT INTO Dogs (Breed, Name, Age) VALUES ('Golden-Doodle', 'Ellie', 4);");

    //now output the data to a simple html table...
    print "<table border=1>";
    print "<tr><td>Id</td><td>Breed</td><td>Name</td><td>Age</td></tr>";
    $result = $pdo->query('SELECT * FROM Dogs');
    foreach($result as $row)
    {
     print_r($row);
    }
    print "</table>";

    // close the database connection

die;

$pdo->query("CREATE TABLE IN NOT EXIST  `test` (`id` INT NOT NULL AUTO_INCREMENT , `val` VARCHAR( 255 ) NOT NULL , PRIMARY KEY (  `id` ) )");
insert_query("INSERT INTO `test` SET `val`='".q(time())."'");
$multiple=multiple_query("SELECT * FROM `test` ");
echo '<pre>';
foreach($multiple as $data){
	print_r($data);
	}
echo '</pre>';


/*   ///////////////  COPY+PASTE

$res = $pdo->query("SELECT * from `tbl` WHERE `col`='".q()."' ");
$res->setFetchMode(PDO::FETCH_ASSOC); $k=0; //only one row loades in memory
while($data = $res->fetch()){$k++;	
	}
if($k==0){echo ' nici un rezultat...';}


$rows=multiple_query("SELECT * FROM `tabel` WHERE `id`='$val'  LIMIT 100 ");$k=0; //all loades in memory
foreach($rows as $k=>$data){
	}
if($k<1){}


$res = $pdo->query("SELECT * from `tbl` WHERE `col`='".q()."' ");
$rows = $res->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $data){
    echo $row->fieldname;
	}




$k=0;//contor rezultate curente
$res=mysql_query("SELECT * FROM `tabel` WHERE `id`='$val'");
while($data=mysql_fetch_array($res,1)){
	echo $k; 
	$k++;
	}
if($k==0){echo ' nici un rezultat...';}


PDO::FETCH_ASSOC: returns an array indexed by column name
PDO::FETCH_BOTH (default): returns an array indexed by both column name and number
PDO::FETCH_BOUND: Assigns the values of your columns to the variables set with the ->bindColumn() method
PDO::FETCH_CLASS: Assigns the values of your columns to properties of the named class. It will create the properties if matching properties do not exist
PDO::FETCH_INTO: Updates an existing instance of the named class
PDO::FETCH_LAZY: Combines PDO::FETCH_BOTH/PDO::FETCH_OBJ, creating the object variable names as they are used
PDO::FETCH_NUM: returns an array indexed by column number
PDO::FETCH_OBJ: returns an anonymous object with property names that correspond to the column names
*/


?>