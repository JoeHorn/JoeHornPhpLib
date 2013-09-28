<?php
/*
 * MySQL class using PDO.
 *
 * @author	Joe Horn <joehorn@gmail.com>
 * @category	Class
 * @copyright	Copyright (c) 2013, Joe Horn
 * @license	http://www.opensource.org/licenses/bsd-license.php The BSD License
 */
class ClassPdoMySQL {
	/*
	 * PDO object
	 *
	 * @var		object	$PDO
	 */
	private $PDO = null;

	/*
	 * Error Information
	 *
	 * @var		array	$ErrInfo
	 */
	public $ErrInfo = array('','','');

	/*
	 * Constructor function
	 *
	 * @param	string $DB_NAME		Database name
	 * @param	string $DB_HOST		Database server IP/hostname, default: 'localhost'
	 * @param	string $DB_USERNAME	Database username, default: 'root'
	 * @param	string $DB_PASSWORD	Database password, default: ''
	 * @param	string $Charset	Database connection charset, default: 'UTF8'
	 */
	function __construct ( $DB_NAME, $DB_HOST = 'localhost', $DB_USERNAME = 'root', $DB_PASSWORD = '' , $Charset = 'UTF8') {
		try {
			$this->PDO = New PDO(
				"mysql:host=$DB_HOST;dbname=$DB_NAME",
				$DB_USERNAME, $DB_PASSWORD,
				array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES $Charset")
			);
			$this->ErrInfo = $this->PDO->errorInfo();
		} catch (Exception $e) {
			$this->ErrInfo = array('','',$e->getMessage());
			throw $e;
		}
	}

	/*
	 * Execute SQL statement
	 *
	 * @access	public
	 * @param	string $sql		SQL statement
	 * @return	PDOStatement		Executed PDOStatement
	 */
	public function exec ( $sql ) {
		if ( !is_object($this->PDO) ) {
			$this->ErrInfo = array('','','No PDO connection.');
			return null;
		} else {
			$st = $this->PDO->query($sql);
			if ( !$st ) {
				$this->ErrInfo = $this->PDO->errorInfo();
			} else {
				$this->ErrInfo = $st->errorInfo();
			}
			return $st;
		}
	}

	/*
	 * Prepare & Execute SQL statement
	 *
	 * @access	public
	 * @param	string $sql		SQL statement
	 * @param	array $params		An array with parameters
	 * @return	PDOStatement		Executed PDOStatement
	 */
	public function execPrepared ( $sql , $params ) {
		try {
			if ( !is_object($this->PDO) ) {
				$this->ErrInfo = array('','','No PDO connection.');
				return null;
			} else {
				$st = $this->PDO->prepare($sql);
				if ( !$st ) {
					$this->ErrInfo = $this->PDO->errorInfo();
				} else {
					$st->execute($params);
					$this->ErrInfo = $st->errorInfo();
				}
				return $st;
			}
		} catch (Exception $e) {
			$this->ErrInfo = array('','',$e->getMessage());
			throw $e;
		}
	}

	/*
	 * Get last insert ID
	 *
	 * @access	public
	 * @return	string			Last insert ID
	 */
	public function getLastInsertId() {
		if ( !is_object($this->PDO) ) {
			$this->ErrInfo = array('','','No PDO connection.');
			return null;
		} else {
			$id = $this->PDO->lastInsertId();
			$this->ErrInfo = $this->PDO->errorInfo();
			return $id;
		}
	}

	/*
	 * Get single row
	 *
	 * @access	public
	 * @param	string $sql		SQL statement
	 * @return	array			An array contains one data row
	 */
	public function getRow ( $sql , $fetch_mode = PDO::FETCH_BOTH ) {
		if ( !is_object($this->PDO) ) {
			$this->ErrInfo = array('','','No PDO connection.');
			return null;
		} else {
			$st = $this->PDO->query($sql);
			if ( !$st ) {
				$this->ErrInfo = $this->PDO->errorInfo();
				return Array();
			} else {
				$row = $st->fetch($fetch_mode);
				$this->ErrInfo = $st->errorInfo();
				return $row;
			}
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
		if ( !is_object($this->PDO) ) {
			$this->ErrInfo = array('','','No PDO connection.');
			return null;
		} else {
			$st = $this->PDO->query($sql);
			if ( !$st ) {
				$this->ErrInfo = $this->PDO->errorInfo();
				return Array();
			} else {
				$row = $st->fetchAll($fetch_mode);
				$this->ErrInfo = $st->errorInfo();
				return $row;
			}
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
		if ( !is_object($this->PDO) ) {
			$this->ErrInfo = array('','','No PDO connection.');
			return null;
		} else {
			$st = $this->PDO->query($sql);
			if ( !$st ) {
				$this->ErrInfo = $this->PDO->errorInfo();
				return '';
			} else {
				$id = $this->PDO->lastInsertId();
				$this->ErrInfo = $this->PDO->errorInfo();
				return $id;
			}
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
		if ( !is_object($this->PDO) ) {
			$this->ErrInfo = array('','','No PDO connection.');
			return null;
		} else {
			$quotedStr = $this->PDO->quote($str);
			$this->ErrInfo = $this->PDO->errorInfo();
			return $quotedStr;
		}
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
		if ( !is_object($this->PDO) ) {
			$this->ErrInfo = array('','','No PDO connection.');
		} else {
			$upAction = strtoupper($action);

			switch ( $upAction ) {
				case 'C':
				case 'COMMIT':
					$result = $this->PDO->commit();
					break;
				case 'R':
				case 'COMMIT':
					$result = $this->PDO->rollBack();
					break;
				default:
					$result = $this->PDO->beginTransaction();
					break;
			}
			$this->ErrInfo = $this->PDO->errorInfo();
		}
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
		if ( !is_object($this->PDO) ) {
			$this->ErrInfo = array('','','No PDO connection.');
			return null;
		} else {
			$affectedRows = $this->PDO->exec($sql);
			$this->ErrInfo = $this->PDO->errorInfo();
			return $affectedRows;
		}
	}
}
?>