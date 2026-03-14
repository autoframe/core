<?php

namespace Autoframe\Core\Session;


use SessionHandlerInterface;

// TODO implement
if(0){

	if (in_array($this->session_status(), [PHP_SESSION_DISABLED, PHP_SESSION_ACTIVE])) {
		$sessionHandler = new AfrDbSession(
			DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, 'session_handler_table', 3600 * 6
		);
		session_set_save_handler($sessionHandler, true);
	}
}


/**
 * A PHP session handler to keep session data within a MySQL database
 *
 * @author 	Manuel Reinhard <manu@sprain.ch>
 * @link		https://github.com/sprain/PHP-MySQL-Session-Handler
 */

class AfrDbSession implements SessionHandlerInterface
{

	/**
	 * a database MySQLi connection resource
	 * @var resource
	 */
	protected $dbConnection;

	/**
	 * the name of the DB table which handles the sessions
	 * @var string
	 */
	protected $dbTable;

	private $_db_host;
	private $_db_user;
	private $_db_password;
	private $_db_db;
	private $_life_time;

	/**
	 * Is session started.
	 */
	public function isSessionStarted():bool
	{
		return in_array(session_status(), [PHP_SESSION_DISABLED, PHP_SESSION_ACTIVE]);
	}


	/**
	 * Create a new instance.
	 */
	public function __construct($dbHost, $dbUser, $dbPassword, $dbDatabase, $db_table, $life_time=null)
	{
		$this->_db_host = $dbHost;
		$this->_db_user = $dbUser;
		$this->_db_password = $dbPassword;
		$this->_db_db = $dbDatabase;
		$this->dbTable = $db_table;
		$this->_life_time = $life_time ? : ini_get('session.gc_maxlifetime');
	}

	/**
	 * Open.
	 */
	public function open($save_path, $session_name)
	{
		$this->dbConnection = new mysqli($this->_db_host, $this->_db_user, $this->_db_password, $this->_db_db);
		if (mysqli_connect_error()) {
			return false;
		}
		$sql = sprintf("DELETE FROM %s WHERE `modified` + `lifetime` < '%s'", $this->dbTable, time());
		$this->dbConnection->query($sql);
		return true;
	}


	/**
	 * Set db data if no connection is being injected
	 * @param 	string	$dbHost
	 * @param	string	$dbUser
	 * @param	string	$dbPassword
	 * @param	string	$dbDatabase
	 */
	public function setDbDetails($dbHost, $dbUser, $dbPassword, $dbDatabase)
	{
		$this->dbConnection = new mysqli($dbHost, $dbUser, $dbPassword, $dbDatabase);

		if (mysqli_connect_error()) {
			throw new Exception('Connect Error (' . mysqli_connect_errno() . ') ' . mysqli_connect_error());
		}
	}

	/**
	 * Inject DB connection from outside
	 * @param 	object	$dbConnection	expects MySQLi object
	 */
	public function setDbConnection($dbConnection)
	{
		$this->dbConnection = $dbConnection;
	}

	/**
	 * Inject DB connection from outside
	 * @param 	object	$dbConnection	expects MySQLi object
	 */
	public function setDbTable($dbTable)
	{
		$this->dbTable = $dbTable;
	}

	/**
	 * Close the session
	 * @return bool
	 */
	public function close()
	{
		return $this->dbConnection->close();
	}

	/**
	 * Read the session
	 * @param int session id
	 * @return string string of the sessoin
	 */
	public function read($id)
	{
		$sql = sprintf("SELECT data FROM %s WHERE id = '%s'", $this->dbTable, $this->dbConnection->escape_string($id));
		if ($result = $this->dbConnection->query($sql)) {
			if ($result->num_rows && $result->num_rows > 0) {
				$record = $result->fetch_assoc();
				return $record['data'];
			} else {
				return '';
			}
		} else {
			return '';
		}

	}

	/**
	 * Write the session
	 * @param int session id
	 * @param string data of the session
	 */
	public function write($id, $data)
	{

		$sql = sprintf("REPLACE INTO %s VALUES('%s', '%s', %d, %d)",
			$this->dbTable,
			$this->dbConnection->escape_string($id),
			$this->dbConnection->escape_string($data),
			time(),
			$this->_life_time);
		return $this->dbConnection->query($sql);
	}

	/**
	 * Destoroy the session
	 * @param int session id
	 * @return bool
	 */
	public function destroy($id)
	{
		$sql = sprintf("DELETE FROM %s WHERE `id` = '%s'", $this->dbTable, $this->dbConnection->escape_string($id));
		return $this->dbConnection->query($sql);
	}

	/**
	 * Garbage Collector
	 * @param int life time (sec.)
	 * @return bool
	 * @see session.gc_divisor      100
	 * @see session.gc_maxlifetime 1440
	 * @see session.gc_probability    1
	 * @usage execution rate 1/100
	 *        (session.gc_probability/session.gc_divisor)
	 */
	public function gc($max)
	{
		$sql = sprintf("DELETE FROM %s WHERE `modified` + `lifetime` < '%s'", $this->dbTable, time());
		return $this->dbConnection->query($sql);
	}
}
