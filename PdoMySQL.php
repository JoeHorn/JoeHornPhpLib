<?php
/*
 * MySQL class using PDO.
 *
 * @author	Joe Horn <joehorn@gmail.com>
 * @category	Class
 * @copyright	Copyright (c) 2013-2015, Joe Horn
 * @license	http://www.opensource.org/licenses/bsd-license.php The BSD License
 */
class PdoMySQL extends PDO {
	/*
	 * Error Information
	 *
	 * @access	private
	 * @var		array	$ErrInfo
	 */
	private $ErrInfo = array('00000',null,null);

	/*
	 * Constructor function
	 *
	 * @param	string	$db_name	Database name
	 * @param	string	$db_username	Database username, default: 'root'
	 * @param	string	$db_password	Database password, default: ''
	 * @param	string	$charset	Database connection charset, default: 'UTF8'
	 * @param	string	$db_host	Database server IP/hostname, default: 'localhost'
	 * @param	integer	$db_port	Database server port, default: 3306
	 * @param	boolean $persist	Create Database persistent connections, default: true
	 */
	function __construct (
		$db_name,
		$db_username = 'root',
		$db_password = '',
		$charset = 'UTF8',
		$db_host = 'localhost',
		$db_port = 3306,
		$persist = true
	) {
		try {
			parent::__construct(
				"mysql:host=$db_host;port=$db_port;dbname=$db_name",
				$db_username, $db_password,
				array(
					PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES $charset" ,
					PDO::ATTR_PERSISTENT => $persist
				)
			);
		} catch (Exception $e) {
			throw $e;
		}
	}

	/*
	 * Reset error information
	 *
	 * @access	private
	 */
	private function resetErrInfo() {
		$this->ErrInfo = array('00000',null,null);
	}

	/*
	 * Retrieve error information
	 *
	 * @access	public
	 * @return	array		An array with error information
	 */
	public function errorInfo() {
		$myErrInfo = $this->ErrInfo;

		if ( empty($myErrInfo[1]) && empty($myErrInfo[2]) ) {
			return parent::errorInfo();
		} else {
			return $myErrInfo;
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
	public function execPrepared ( $sql , $bindParams ) {
		$this->resetErrInfo();

		try {
			$st = parent::prepare($sql);
			if ( !$st ) {
				$this->ErrInfo = parent::errorInfo();
			} else {
				$st->execute($bindParams);
				$this->ErrInfo = $st->errorInfo();
			}
			return $st;
		} catch (Exception $e) {
			throw $e;
		}
	}

	/*
	 * Get single row
	 *
	 * @access	public
	 * @param	string $sql		SQL statement
	 * @return	array			An array contains one data row
	 */
	public function getRow ( $sql , $fetchMode = PDO::FETCH_BOTH ) {
		$this->resetErrInfo();

		$st = parent::query($sql);
		if ( !$st ) {
			$this->ErrInfo = parent::errorInfo();
			return array();
		} else {
			$row = $st->fetch($fetchMode);
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
	public function getRows ( $sql , $fetchMode = PDO::FETCH_BOTH ) {
		$this->resetErrInfo();

		$st = parent::query($sql);
		if ( !$st ) {
			$this->ErrInfo = parent::errorInfo();
			return array();
		} else {
			$row = $st->fetchAll($fetchMode);
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
		$this->resetErrInfo();

		$st = parent::query($sql);
		if ( !$st ) {
			$this->ErrInfo = parent::errorInfo();
			return '';
		} else {
			$this->ErrInfo = $st->errorInfo();
			return parent::lastInsertId();
		}
	}

	/*
	 * Extended quote function ( Support array to be put in WHERE ... IN ... clause )
	 *
	 * @access	public
	 * @param	mixed $var		Variable to be quoted
	 * @return	string			Quoted string
	 */
	public function quote ( $var , $parameterType = PDO::PARAM_STR ) {
		$this->resetErrInfo();

		if ( !is_array($var) ) {
			return parent::quote($var, $parameterType);
		} else {
			foreach ( $var as $k => $v ) {
				$tmpArr[$k] = parent::quote($v, $parameterType);
			}
			return implode(', ', $tmpArr);
		}
	}

	/*
	 * Update function
	 *
	 * @access	public
	 * @param	string $sql		SQL statement
	 * @return	int			Affected rows count
	 */
	public function update ( $sql ) {
		$this->resetErrInfo();

		$affectedRows = parent::exec($sql);
		$this->ErrInfo = parent::errorInfo();

		if ( $affectedRows == false ) {
			$affectedRows = 0;
		}
		return $affectedRows;
	}
}
?>
