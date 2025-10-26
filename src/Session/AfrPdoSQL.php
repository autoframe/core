<?php

namespace Autoframe\Core\Session;

use PDO;
use SessionHandlerInterface;
use SessionUpdateTimestampHandlerInterface;

// TODO implement


class AfrPdoSQL implements SessionHandlerInterface
{
	protected ?PDO $dbh = null;

	protected string $dbTable;


	/**
	 * Set db data if no connection is being injected
	 * @param string $dbHost
	 * @param string $dbUser
	 * @param string $dbPassword
	 * @param string $dbDatabase
	 * @param string $dbCharset optional, default 'utf8'
	 */
	public function setDbDetails($dbHost, $dbUser, $dbPassword, $dbDatabase, $dbCharset = 'utf8')
	{

		//create db connection
		$this->dbh = new PDO("mysql:" .
			"host={$dbHost};" .
			"dbname={$dbDatabase};" .
			"charset={$dbCharset}",
			$dbUser,
			$dbPassword,
			array(
				PDO::ATTR_EMULATE_PREPARES => false,
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION //USE ERRMODE_SILENT FOR PRODUCTION!
			)
		);
	}//function


	/**
	 * Inject PDO from outside
	 * @param PDO $dbh expects PDO object
	 */
	public function setPDO(PDO $dbh)
	{
		$this->dbh = $dbh;
	}


	/**
	 * Set MySQL table to work with
	 * @param string $dbTable
	 */
	public function setDbTable(string $dbTable)
	{
		$this->dbTable = $dbTable;
	}


	/**
	 * Open the session
	 * @return bool
	 */
	public function open($save_path, $session_name)
	{
		//delete old session handlers
		return $this
				->dbh
				->prepare("DELETE FROM {$this->dbTable} WHERE timestamp < :limit")
				->execute(array(':limit' => time() - (3600 * 24)));
	}

	/**
	 * Close the session
	 * @return bool
	 */
	public function close()
	{
		$this->dbh = null;
		return true;
	}

	/**
	 * Read the session
	 * @param int session id
	 * @return string string of the sessoin
	 */
	public function read($id)
	{
		$stmt = $this->dbh->prepare("SELECT * FROM {$this->dbTable} WHERE id=:id");
		$stmt->execute(array(':id' => $id));

		$session = $stmt->fetch(PDO::FETCH_ASSOC);

		if ($session) {
			$ret = $session['data'];
		} else {
			$ret = false;
		}

		return $ret;
	}


	/**
	 * Write the session
	 * @param int session id
	 * @param string data of the session
	 */
	public function write($id, $data)
	{
		$stmt = $this->dbh->prepare("REPLACE INTO {$this->dbTable} (id,data,timestamp) VALUES (:id,:data,:timestamp)");
		$ret = $stmt->execute(
			array(':id' => $id,
				':data' => $data,
				'timestamp' => time()
			));

		return $ret;
	}

	/**
	 * Destroy the session
	 * @param int session id
	 * @return bool
	 */
	public function destroy($id)
	{
		$stmt = $this->dbh->prepare("DELETE FROM {$this->dbTable} WHERE id=:id");
		$ret = $stmt->execute(array(
			':id' => $id
		));

		return $ret;
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
		$stmt = $this->dbh->prepare("DELETE FROM {$this->dbTable} WHERE timestamp < :limit");
		$ret = $stmt->execute(array(':limit' => time() - intval($max)));

		return $ret;
	}

}//class