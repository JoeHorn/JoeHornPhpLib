<?php
/*
 * MySQL class using PDO.
 *
 * Major features :
 *
 *  - quote() supports array input
 *  - Combine error information from PDO & PDOStatement classes
 *  - Supports lazy connection ( Do real connect before executing MySQL command )
 *  - Re-connect & re-try MySQL command if gotten unknown error or "MySQL server has gone away"
 *
 * PHP version tested : 5.1 ~ 7.2
 *
 * Recent modified: 2019-01-08
 *
 * @author      Joe Horn <joehorn@gmail.com>
 * @category    Class
 * @copyright   Copyright (c) 2013-2019, Joe Horn
 * @license     http://www.opensource.org/licenses/bsd-license.php The BSD License
 */
if ( !class_exists('PdoMySQL') ) {
    class PdoMySQL {
        private $_dbName        = '';
        private $_dbUsername    = 'root';
        private $_dbPassword    = '';
        private $_dbHost        = 'localhost';
        private $_dbPort        = 3306;

        private $_charset       = 'UTF8';
        private $_persistent    = true;
        private $_lazy          = false;

        private $_conn          = null;

        private $_errInfo       = array('00000',null,null);

        /*
         * Constructor function
         *
         * @param    string     $db_name        Database name
         * @param    string     $db_username    Database username, default: 'root'
         * @param    string     $db_password    Database password, default: ''
         * @param    string     $charset        Database connection charset, default: 'UTF8'
         * @param    string     $db_host        Database server IP/hostname, default: 'localhost'
         * @param    integer    $db_port        Database server port, default: 3306
         * @param    boolean    $persist        Create Database persistent connections, default: true
         */
        function __construct (
            $dbName,
            $dbUsername = 'root',
            $dbPassword = '',
            $charset = 'UTF8',
            $dbHost = 'localhost',
            $dbPort = 3306,
            $persistent = true,
            $lazy = false
        ) {
            $this->_dbName      = $dbName;
            $this->_dbUsername  = $dbUsername;
            $this->_dbPassword  = $dbPassword;
            $this->_dbHost      = $dbHost;
            $this->_dbPort      = $dbPort;

            $this->_charset     = $charset;
            $this->_persistent  = $persistent;
            $this->_lazy        = $lazy;

            if ( !$this->_lazy ) {
                $this->connect();
            }
        }

        /*
         * Magic method for calling PDO methods .
         * This will do re-connect if unknown error or "MySQL server has gone away" happened
         */
        public function __call ( $method , $params ) {
            $this->_errInfo = array('00000',null,null);

            if ( !is_array($params) ) {
                $params = array($params);
            }

            if ( $this->_lazy ) {
                $this->connect();
            }

            if ( is_callable( array($this->_conn, $method), false) ) {
                $result = call_user_func_array(
                    array($this->_conn, $method),
                    $params
                );
                $this->_errInfo = $this->_conn->errorInfo();

                // Unknown error or "MySQL server has gone away" , re-connect & re-try
                $retry = 0;
                while ( 'HY000' == $this->_errInfo[0] && 10 > $retry ) {
                    $this->connect(true);
                    $result = call_user_func_array(
                        array($this->_conn, $method),
                        $params
                    );
                    $this->_errInfo = $this->_conn->errorInfo();
                    $retry++;
                }

                return $result;
            } else {
                error_log('[HERMES_LIBRARY_ERROR] ' . __CLASS__ . ' :' . " method ( $method ) not found!");
                return null;
            }
        }

        /*
         * Connect to MySQL ( for lazy connect )
         *
         * @access  private
         * @param   boolean $reConnect  Force to re-connect (even if connection has been established)
         */
        private function connect ( $reConnect = false ) {
            $this->_errInfo = array('00000',null,null);

            if ( null == $this->_conn || $reConnect ) {
                try {
                    $this->_conn = @new PDO(
                        "mysql:host={$this->_dbHost};port={$this->_dbPort};dbname={$this->_dbName}",
                        $this->_dbUsername, $this->_dbPassword,
                        array(
                            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->_charset}" ,
                            PDO::ATTR_PERSISTENT => $this->_persistent ,
                        )
                    );
                } catch (Exception $e) {
                    throw $e;
                }
            }
        }

        /*
         * Delete function
         *
         * @access  public
         * @param   string  $sql    SQL statement
         * @return  int             Deleted rows count, -1 while error occurs
         */
        public function delete ( $sql ) {
            $this->_errInfo = array('00000',null,null);

            $affectedRows = $this->__call('exec', $sql);

            if ( $affectedRows === false ) {
                $this->_errInfo = $this->_conn->errorInfo();
                $affectedRows = -1;
            }
            return $affectedRows;
        }

        /*
         * Retrieve error information
         *
         * @access  public
         * @return  array       An array with error information
         */
        public function errorInfo() {
            $myErrInfo = $this->_errInfo;

            if ( empty($myErrInfo[1]) && empty($myErrInfo[2]) ) {
                return $this->_conn->errorInfo();
            } else {
                return $myErrInfo;
            }
        }

        /*
         * Execute SQL statement
         *
         * @access  public
         * @param   string          $sql        SQL statement
         * @param   array           $bindParams An array with parameters
         * @return  PDOStatement                Executed PDOStatement
         */
        public function execPrepared ( $sql , $bindParams ) {
            $this->_errInfo = array('00000',null,null);

            $st = $this->__call('prepare', $sql);
            if ( !$st ) {
                $this->_errInfo = $this->_conn->errorInfo();
            } else {
                $res = $st->execute($bindParams);
                if ( false === $res ) {
                    $this->_errInfo = $st->errorInfo();
                }
            }
            return $st;
        }

        /*
         * Get single row
         *
         * @access  public
         * @param   string  $sql        SQL statement
         * @param   string  $fetchMode  Data rows fetch mode
         * @return  array               An array contains one data row
         */
        public function getRow ( $sql , $fetchMode = PDO::FETCH_BOTH ) {
            $this->_errInfo = array('00000',null,null);

            $st = $this->__call('query', $sql);
            if ( !$st ) {
                $this->_errInfo = $this->_conn->errorInfo();
                $row = array();
            } else {
                $row = $st->fetch($fetchMode);
                if ( false === $row ) {
                    $row = array();
                    $this->_errInfo = $st->errorInfo();
                }
            }
            return $row;
        }

        /*
         * Get multi rows
         *
         * @access  public
         * @param   string  $sql        SQL statement
         * @param   string  $fetchMode  Data rows fetch mode
         * @return  array               An array contains data rows
         */
        public function getRows ( $sql , $fetchMode = PDO::FETCH_BOTH ) {
            $this->_errInfo = array('00000',null,null);

            $st = $this->__call('query', $sql);
            if ( !$st ) {
                $this->_errInfo = $this->_conn->errorInfo();
                $row = array();
            } else {
                $row = $st->fetchAll($fetchMode);
                if ( false === $row ) {
                    $row = array();
                    $this->_errInfo = $st->errorInfo();
                }
            }
            return $row;
        }

        /*
         * Insert function
         *
         * @access  public
         * @param   string  $sql    SQL statement
         * @return  string          Last insert ID
         */
        public function insert ( $sql ) {
            $this->_errInfo = array('00000',null,null);

            $st = $this->__call('query', $sql);
            if ( !$st ) {
                $this->_errInfo = $this->_conn->errorInfo();
                return '';
            } else {
                // No need to reconnect , not using __call()
                return $this->_conn->lastInsertId();
            }
        }

        /*
         * Extended quote function ( Support array to be put in WHERE ... IN ... clause )
         *
         * @access  public
         * @param   mixed   $var            Variable to be quoted
           @param   mixed   $parameterType  Quoted variable type
         * @return  string                  Quoted string
         */
        public function quote ( $var , $parameterType = PDO::PARAM_STR ) {
            $this->_errInfo = array('00000',null,null);

            // Need $this->_conn ( PDO object )
            if ( $this->_lazy ) {
                $this->connect();
            }

            // PDO->quote() no need to reconnect , not using __call()
            if ( !is_array($var) ) {
                return is_null($var) ? 'NULL' : $this->_conn->quote($var, $parameterType);
            } else {
                $tmpArr = array();
                foreach ( $var as $k => $v ) {
                    $tmpArr[$k] = is_null($var) ? 'NULL' : $this->_conn->quote($v, $parameterType);
                }
                return implode(', ', $tmpArr);
            }
        }

        /*
         * Update function
         *
         * @access  public
         * @param   string  $sql    SQL statement
         * @return  int             Affected rows count, -1 while error occurs
         */
        public function update ( $sql ) {
            return $this->delete($sql);
        }
    }
}
