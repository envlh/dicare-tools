<?php

class db {
    
    private static $connected = false;
    private static $mysqli;
    
    private function __construct() {
    }
    
    // opens connection if not already established
    private static function open() {
        if (self::$connected != true) {
            self::$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
            if (self::$mysqli->connect_errno != 0) {
                throw new Exception('Error establishing database connection'."\n".'MySQL Error #'.self::$mysqli->connect_errno."\n".self::$mysqli->connect_error);
            }
            self::$mysqli->set_charset('utf8mb4');
            self::$mysqli->autocommit(false);
            self::$mysqli->options(MYSQLI_OPT_LOCAL_INFILE, true);
            self::$connected = true;
        }
    }
    
    // secures string
    public static function sec($val) {
        self::open();
        return self::$mysqli->real_escape_string($val);
    }
    
    /**
     * Executes query and returns result.
     * @param string $query
     * @return mysqli_result
     * @throws Exception
     */
    public static function query($query) {
        self::open();
        $result = @self::$mysqli->query($query);
        if ($result === false) {
            throw new Exception('MySQL Error #'.self::$mysqli->errno."\n".$query."\n".self::$mysqli->error);
        }
        return $result;
    }
    
    // cached query
    public static function cachedQuery($query, $endpoint_id, $cache = 60 * 60 * 24 * 33) {
        $query = trim($query);
        $query_hash = self::getQueryHash($query);
        $cache = max(300, $cache);
        $res = db::query('SELECT `query`, `response`, `last_update` FROM `query` WHERE `query_hash` = 0x'.$query_hash.' AND `endpoint_id` = \''.db::sec($endpoint_id).'\' AND `last_update` >= NOW() - INTERVAL '.$cache.' SECOND');
        if ($res->num_rows == 1) {
            $o = $res->fetch_object();
            if ($o->query !== $query) {
                throw new Exception('Internal error (SHA1 collision: 0x'.$query_hash.').');
            }
            $data = json_decode(gzdecode($o->response));
        }
        else {
            $res = db::query('SELECT GET_LOCK(\''.$endpoint_id.'_sql_lock\', 20) AS `lock`');
            if (($res->num_rows !== 1) || ($res->fetch_object()->lock !== '1')) {
                throw new Exception('Server is busy, try again later.');
            }
            $data = array();
            $res = self::query($query);
            while ($row = $res->fetch_object()) {
                $data[] = $row;
            }
            db::query('INSERT INTO `query`(`query_hash`, `endpoint_id`, `last_update`, `query`, `response`) VALUES(0x'.$query_hash.', \''.db::sec($endpoint_id).'\', NOW(), \''.db::sec($query).'\', 0x'.bin2hex(gzencode(json_encode($data))).') ON DUPLICATE KEY UPDATE `last_update` = NOW(), `response` = 0x'.bin2hex(gzencode(json_encode($data))));
            db::commit();
            db::query('SELECT RELEASE_LOCK(\''.$endpoint_id.'_sql_lock\')');
        }
        return $data;
    }
    
    // commits transaction
    public static function commit() {
        self::$mysqli->commit();
    }
    
    // rollbacks transaction
    public static function rollback() {
        self::$mysqli->rollback();
    }
    
    // last inserted id
    public static function insert_id() {
        return self::$mysqli->insert_id;
    }
    
    // number of affected rows
    public static function affected_rows() {
        return self::$mysqli->affected_rows;
    }
    
    // closes connection
    public static function close() {
        if (self::$connected != false) {
            self::$connected = false;
            self::$mysqli->close();
        }
    }
    
    // generates query hash
    private static function getQueryHash($query) {
        return sha1($query);
    }
    
}

?>