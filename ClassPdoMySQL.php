<?php
/*
 * MySQL class using PDO.
 *
 * @author	Joe Horn <joehorn@gmail.com>
 * @category	Class
 * @copyright	Copyright (c) 2013, Joe Horn
 * @license	http://www.opensource.org/licenses/bsd-license.php The BSD License
 */
class ClassPdoMySQL extends PDO {
	/*
	 * Constants for transaction control
	 */
	const begin	= 'BEGIN';
	const commit	= 'COMMIT';
	const rollback	= 'ROLLBACK';

	/*
	 * Error Information
	 *
	 * @var		array	$ErrInfo
	 */
	public $ErrInfo = array('','','');

	/*
	 * Constructor function
	 *
	 * @param	string	$db_name	Database name
	 * @param	string	$db_host	Database server IP/hostname, default: 'localhost'
	 * @param	string	$db_username	Database username, default: 'root'
	 * @param	string	$db_password	Database password, default: ''
	 * @param	string	$charset	Database connection charset, default: 'UTF8'
	 * @param	boolean $persist	Create Database persistent connections, default: true
	 */
	function __construct ( $db_name, $db_host = 'localhost', $db_username = 'root', $db_password = '', $charset = 'UTF8', $persist = true) {
		try {
			parent::__construct(
				"mysql:host=$db_host;dbname=$db_name",
				$db_username, $db_password,
				array(
					PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES $charset" ,
					PDO::ATTR_PERSISTENT => $persist
				)
			);
			$this->ErrInfo = parent::errorInfo();
		} catch (Exception $e) {
			$this->ErrInfo = array('','',$e->getMessage());
		}
	}

	/*
	 * Execute SQL statement
	 *
	 * @access	public
	 * @param	string		$sql		SQL statement
	 * @param	array		$bindParams	An array with parameters
	 * @return	PDOStatement			Executed PDOStatement
	 */
	public function exec ( $sql , $bindParams = array() ) {
		try {
			if ( empty($bindParams) ) {
				$st = parent::query($sql);
				if ( !$st ) {
					$this->ErrInfo = parent::errorInfo();
				} else {
					$this->ErrInfo = $st->errorInfo();
				}
				return $st;
			} else {
				$st = parent::prepare($sql);
				if ( !$st ) {
					$this->ErrInfo = parent::errorInfo();
				} else {
					$st->execute($bindParams);
					$this->ErrInfo = $st->errorInfo();
				}
				return $st;
			}
		} catch (Exception $e) {
			$this->ErrInfo = array('','',$e->getMessage());
		}
	}

	/*
	 * Get last insert ID
	 *
	 * @access	public
	 * @return	string			Last insert ID
	 */
	public function getLastInsertId() {
		$id = parent::lastInsertId();
		$this->ErrInfo = parent::errorInfo();
		return $id;
	}

	/*
	 * Get single row
	 *
	 * @access	public
	 * @param	string $sql		SQL statement
	 * @return	array			An array contains one data row
	 */
	public function getRow ( $sql , $fetch_mode = PDO::FETCH_BOTH ) {
		$st = parent::query($sql);
		if ( !$st ) {
			$this->ErrInfo = parent::errorInfo();
			return array();
		} else {
			$row = $st->fetch($fetch_mode);
			$this->ErrInfo = $st->errorInfo();
			return $row;
		}
	}

	/*
	 * Get multi rows
	 *
	 * @access	public
	 * @param	string $sql		SQL statement
	 * @return	array			An array contains data rows
	 */
	public function getRows ( $sql , $fetch_mode = PDO::FETCH_BOTH ) {
		$st = parent::query($sql);
		if ( !$st ) {
			$this->ErrInfo = parent::errorInfo();
			return array();
		} else {
			$row = $st->fetchAll($fetch_mode);
			$this->ErrInfo = $st->errorInfo();
			return $row;
		}
	}

	/*
	 * Insert function
	 *
	 * @access	public
	 * @param	string $sql		SQL statement
	 * @return	string			Last insert ID
	 */
	public function insert ( $sql ) {
		$st = parent::query($sql);
		if ( !$st ) {
			$this->ErrInfo = parent::errorInfo();
			return '';
		} else {
			return self::getLastInsertId();
		}
	}

	/*
	 * Quote string function
	 *
	 * @access	public
	 * @param	string $str		String want to be quoted
	 * @return	string			Quoted string
	 */
	public function quote ( $str ) {
		$quotedStr = parent::quote($str);
		$this->ErrInfo = parent::errorInfo();
		return $quotedStr;
	}

	/*
	 * Transaction function
	 *
	 * @access	public
	 * @param	string $action		Transaction actions
	 * @return	boolean			Transaction action results
	 */
	public function transaction ( $action ) {
		$result = null;
		$upAction = strtoupper($action);

		switch ( $upAction ) {
			case 'C':
			case 'COMMIT':
				$result = parent::commit();
				break;
			case 'R':
			case 'ROLLBACK':
				$result = parent::rollBack();
				break;
			default:
				$result = parent::beginTransaction();
				break;
		}
		$this->ErrInfo = parent::errorInfo();
		return $result;
	}

	/*
	 * Update function
	 *
	 * @access	public
	 * @param	string $sql		SQL statement
	 * @return	int			Affected rows. It may be FALSE if error occurred.
	 */
	public function update ( $sql ) {
		$affectedRows = parent::exec($sql);
		$this->ErrInfo = parent::errorInfo();
		return $affectedRows;
	}
}
?>
