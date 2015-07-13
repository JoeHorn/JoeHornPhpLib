<?php
/*
 * Simple LDAP class
 *
 * @author    Joe Horn <joehorn@gmail.com>
 * @category    Class
 * @copyright    Copyright (c) 2013-2015, Joe Horn
 * @license    http://www.opensource.org/licenses/bsd-license.php The BSD License
 */
class ClassLDAP {
    private $_ip        = '';
    private $_port      = 389;
    private $_bindDN    = '';
    private $_passwd    = '';

    private $_conn      = null;
    private $_entries   = array();

    public  $_showLog   = true;
    private $_logText   = '';

    private function _log($str) {
        $this->_logText .= $str . PHP_EOL;
        if ( $this->_showLog ) {
            echo $str . PHP_EOL;
        }
    }

    public function connect($ip , $port = 389 , $bindDN = '' , $passwd = '') {
        $this->_ip      = $ip;
        $this->_port    = $port;
        $this->_bindDN  = $bindDN;
        $this->_passwd  = $passwd;

        $conn = ldap_connect($this->_ip , $this->_port);

        if ( !$conn ) {
            $this->_log( "Error : ldap://{$this->_ip}:{$this->_port}/ connect failed" );
            $this->_conn = null;
        } else {
            ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);

            if ( !empty($this->_bindDN) && !empty($this->_passwd) ) {
                $bind = ldap_bind($conn , $this->_bindDN , $this->_passwd);
            } else {
                $bind = ldap_bind($conn);
            }

            if ( !$bind ) {
                $this->_log( "Error : ldap://{$this->_ip}:{$this->_port}/ bind failed." );
                $this->_conn = null;
            } else {
                $this->_conn = $conn;
            }
        }
        return $this->_conn;
    }

    public function deleteEntry($dn) {
        if ( !$this->_conn && !$this->_connect($this->_ip , $this->_port , $this->_bindDN , $this->_passwd) ) {
            $this->_log( "Error : No connection." );
            return false;
        } else if ( !ldap_delete($this->_conn , $dn) ) {
            ldap_get_option($this->_conn , LDAP_OPT_ERROR_STRING , $errStr);
            $this->_log( "[DELETE ENTRY FAILED] DN: '$dn' , ERR: '$errStr'" );
            return false;
        } else {
            $this->_log( "[DELETE ENTRY SUCCESS] DN: '$dn'" );
            return true;
        }
    }

    public function deleteValue($dn , $attr , $value) {
        $tmpEntry[$attr] = $value;

        if ( !$this->_conn && !$this->_connect($this->_ip , $this->_port , $this->_bindDN , $this->_passwd) ) {
            $this->_log( "Error : No connection." );
            return false;
        } else if ( !ldap_mod_del($this->_conn, $dn, $tmpEntry) ) {
            ldap_get_option($this->_conn, LDAP_OPT_ERROR_STRING, $errStr);
            $this->_log(
                "[DELETE VALUE FAILED] DN: '$dn' , ATTR: '$attr' , VAL: '$value'" . PHP_EOL .
                "                      ERR: '$errStr'"
            );
            return false;
        } else {
            $this->_log( "[DELETE VALUE SUCCESS] DN: '$dn' , ATTR: '$attr' , VAL: '$value'" );
            return true;
        }
    }

    public function insertEntry($dn , $entry) {
        /*
         * Prepare Entry for insertion
         */
        foreach ( $entry as $attrName => $attr ) {
            if ( strtolower($attrName) == 'dn' || strtolower($attrName) == 'count' || is_int($attrName) ) {
                unset($entry[$attrName]);
            } else if ( is_array($attr) && isset($entry[$attrName]['count']) ) {
                unset($entry[$attrName]['count']);
            }

            if ( is_array($attr) && empty($attr) ) {
                unset($entry[$attrName]);
            }
        }

        if ( !$this->_conn && !$this->_connect($this->_ip , $this->_port , $this->_bindDN , $this->_passwd) ) {
            $this->_log( "Error : No connection." );
            return false;
        } else if ( !ldap_add($this->_conn , $dn , $entry) ) {
            ldap_get_option($this->_conn , LDAP_OPT_ERROR_STRING , $errStr);
            $this->_log( "[INSERT ENTRY FAILED] DN: '$dn' , ERR: '$errStr'" );
            return false;
        } else {
            $this->_log( "[INSERT ENTRY SUCCESS] DN: '$dn'" );
            return true;
        }
    }

    public function insertValue( $dn , $attr , $value ) {
        $tmpEntry[$attr] = $value;

        if ( !$this->_conn && !$this->_connect($this->_ip , $this->_port , $this->_bindDN , $this->_passwd) ) {
            $this->_log( "Error : No connection." );
            return false;
        } else if ( !ldap_mod_add($this->_conn, $dn, $tmpEntry) ) {
            ldap_get_option($this->_conn, LDAP_OPT_ERROR_STRING, $errStr);
            $this->_log(
                "[INSERT VALUE FAILED] DN: '$dn' , ATTR: '$attr' , VAL: '$value'" . PHP_EOL .
                "                      ERR: '$errStr'"
            );
            return false;
        } else {
            $this->_log( "[INSERT VALUE SUCCESS] DN: '$dn' , ATTR: '$attr' , VAL: '$value'" );
            return true;
        }
    }

    public function mailLog($subject , $mailAddr) {
        if ( !empty($this->_logText) ) {
            mail($mailAddr , $subject , $this->_logText);
        }
    }

    public function replaceAttr($dn , $attr , $new , $old = null) {
        $do = true;
        $insStr = '';
        $delStr = '';

        if ( isset($new['count']) ) {
            unset($new['count']);
        }
        $tmpEntry[$attr] = $new;

        if ( is_array($new) && is_array($old) ) {
            if ( isset($old['count']) ) {
                unset($old['count']);
            }

            $valueToDel = array_diff($old , $new);
            $valueToIns = array_diff($new , $old);

            if ( count($valueToIns) == 0 && count($valueToDel) == 0 ) {
                $do = false;
            } else {
                if ( count($valueToIns) > 0 ) {
                    $insStr = '[INS] ';
                    foreach ( $valueToIns as $insValue ) {
                        $insStr .= "'$insValue' ";
                    }
                }
                if ( count($valueToDel) > 0 ) {
                    $delStr = '[DEL] ';
                    foreach ( $valueToDel as $delValue ) {
                        $delStr .= "'$delValue' ";
                    }
                }
            }
        }

        if ( $do ) {
            if ( !$this->_conn && !$this->_connect($this->_ip , $this->_port , $this->_bindDN , $this->_passwd) ) {
                $this->_log( "Error : No connection." );
                $this->_entries = array();
            } else if ( !ldap_mod_replace($this->_conn, $dn, $tmpEntry) ) {
                ldap_get_option($this->_conn, LDAP_OPT_ERROR_STRING, $errStr);
                $this->_log(
                    "[REPLACE ATTR FAILED] DN: '$dn' , ATTR: '$attr'\n" .
                    "                      ERR: '$errStr'"
                );
                return false;
            } else {
                $this->_log( "[REPLACE ATTR SUCCESS] DN: '$dn' , ATTR: '$attr'" );
                if ( !empty($insStr) ) {
                    $this->_log( $insStr );
                }
                if ( !empty($delStr) ) {
                    $this->_log( $delStr );
                }
                return true;
            }
        }

    }

    public function search($baseDN , $filter) {
        if ( !$this->_conn && !$this->_connect($this->_ip , $this->_port , $this->_bindDN , $this->_passwd) ) {
            $this->_log( "Error : No connection." );
            $this->_entries = array();
        } else {
            $result = ldap_search($this->_conn , $baseDN, $filter);
            if ( !$result ) {
                $this->_log( "Error : search failed on $baseDN (Filter: $filter)" );
                $this->_entries = array();
            } else {
                $this->_entries = ldap_get_entries($this->_conn, $result);
            }
        }
        return $this->_entries;
    }
}
