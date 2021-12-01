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
	
}

?>