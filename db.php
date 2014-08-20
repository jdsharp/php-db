<?php

if ( !function_exists('db_log') ) {
	function db_log($msg) {
		echo $msg . "\n";
	}
}

if ( !function_exists('db_decode_rows') ) {
	function db_decode_rows($rows) {
		return $rows;
	}
}

if ( !function_exists('db_encode_object') ) {
	function db_encode_object($object) {
		return $object;
	}
}

if ( !function_exists('db_encode_where_data') ) {
	function db_encode_where_data($where) {
		return $where;
	}
}

// ------------------------------------------------------------
// These are utility methods that assist with managing the 
// creation and use of objects.
// ------------------------------------------------------------

function uuid($binary = false) {
	mt_srand((double)microtime() * 10000);
	$charid = md5(uniqid(rand(), true));
	$hyphen = chr(45);// "-"
	$uuid =  substr($charid, 0, 8) . $hyphen
			.substr($charid, 8, 4) . $hyphen
			.substr($charid,12, 4) . $hyphen
			.substr($charid,16, 4) . $hyphen
			.substr($charid,20,12);

	if ( $binary === true ) {
		return uuid_to_bin($uuid);
	}
	return $uuid;
}

function uuid_to_bin($uuid) {
	return pack('H*', str_replace('-', '', $uuid));
}

function uuid_to_hex($uuid) {
	$hex = unpack('H*', $uuid);
	$hex = $hex[1];
	$hex = preg_replace('/([0-9a-f]{8})([0-9a-f]{4})([0-9a-f]{4})([0-9a-f]{4})([0-9a-f]{12})/', "$1-$2-$3-$4-$5", $hex);
	return $hex;
}

function db_hex_literal($hex)
{
	return "x'" . str_replace('-', '', $hex) . "'";
}

function db_pack_guids($obj)
{
	$vars = array_keys(get_object_vars($obj));
	foreach ( $vars AS $k ) {
		if ( substr($k, -5) == '_guid' ) {
			$obj->{$k} = uuid_to_bin($obj->{$k});
		}
	}
	return $obj;
}

function db_unpack_guids($obj)
{
	$vars = array_keys(get_object_vars($obj));
	foreach ( $vars AS $k ) {
		if ( substr($k, -5) == '_guid' ) {
			$obj->{$k} = uuid_to_hex($obj->{$k});
		}
	}
	return $obj;
}

// This safely maps values in an array only to an objects properties
function map_array_to_object($array, $object, $props = null)
{
	if ( $props === null ) {
		$props = array_keys( get_object_vars($object) );
	}
	foreach ( $props AS $p ) {
		if ( isset($array[$p]) ) {
			$object->{$p} = $array[$p];
		}
	}

	return $object;
}

function map_object_to_array($object, $props = null)
{
	if ( $props === null ) {
		$props = array_keys( get_object_vars($object) );
	}
	$ret = array();
	foreach ( $props AS $p ) {
		$ret[$p] = $object->{$p};
	}

	return $ret;
}

function unset_object_keys_except($obj, $keysToInclude)
{
	$keys = array_keys( get_object_vars($obj) );
	foreach ( $keys AS $k ) {
		if ( !in_array($k, $keysToInclude) ) {
			unset($obj->{$k});
		}
	}
	return $obj;
}

function db_object_factory($obj, $keys, $data = null, $guid = null)
{
	if ( $keys === true ) {
		if ( is_array($data) ) {
			$keys = array_keys($data);			
		} else {
			// TODO: This hsouldn't be a stdClass, it should be the $obj
			return new stdClass;
		}
	}

	$o = new $obj;
	$nativeKeys   = array_keys( get_object_vars($o) );
	$preserveKeys = $keys;
	
	if ( in_array('created', $nativeKeys) ) {
		$o->created = time();
		$preserveKeys[] = 'created';
	}

	if ( $guid !== null ) {
		$o->{$guid} = uuid();
		$preserveKeys[] = $guid;
	}

	$o = unset_object_keys_except($o, $preserveKeys);

	if ( is_array($data) ) {
		$o = map_array_to_object($data, $o, $keys);
	}
	
	return $o;
}



// ------------------------------------------------------------
// Core Database Methods
// ------------------------------------------------------------

/**
 * db_conn() is a singleton that returns the PDO database connection 
 * (handles a single connection internally)
 */ 
function db_conn($conn = null, $user = null, $pass = null)
{
	static $pdo = null;

	if ( $conn === null && $pdo === null ) {
		// Attempt to connect to the database the first time it is used
		if ( !defined('DB_CONN') ) {
			db_log('DB_CONN constant is not defined');
			return false;
		}
		// Attempt to connect
		return db_conn(DB_CONN, DB_USER, DB_PASS);
	}
	
	if ( $conn !== null ) {
		$pdo = new PDO($conn, $user, $pass);
	}
	return $pdo;
}

/**
 * db_error() extracts any error messages from the last executed statement
 */
function db_error($statement, $query = null, $data = null)
{
	if ( !$statement ) {
		$obj = db_conn();
	} else {
		$obj = $statement;
	}
	$err = $obj->errorInfo();
	$msg  = $err[0] . ': ' . $err[2] . "\n\n";
	$msg .= "QUERY: $query\n\n";
	ob_start();
	var_dump($data);
	$msg .= ob_get_clean();
	$msg .= "\n";
	db_log($msg);
}

/**
 * db_query() runs a prepared and executed query returning the PDO statement object
 */
function db_query($query, $data = null)
{
	if ( $data === null ) {
		$data = array();
	}
	if ( !is_array($data) ) {
		$data = array($data);
	}

	$pdo  = db_conn();
	$stmt = $pdo->prepare($query);
	if ( $stmt !== false ) {
		if ( $stmt->execute($data) !== false ) {
			return $stmt;
		}
	}
	db_error($stmt, $query, $data);
	return false;
}

/**
 * db_select() method is a wrapper around db_query() that returns all of the data
 * in the form of an array of objects.
 */
function db_select($query, $data = null, $class = null)
{
	if ( $class === null ) {
		$class = 'stdClass';
	}

	$stmt = db_query($query, $data);
	if ( $stmt !== false ) {
		$data = $stmt->fetchAll(PDO::FETCH_CLASS, $class);
		if ( $data && count($data) == 0 ) {
			return null;
		}
		return db_decode_rows($data);
	}
	return false;
}

/**
 * db_row_count() is a wrapper around db_query() that returns the row count of the query.
 */
function db_row_count($query, $data = null)
{
	$stmt = db_query($query, $data);
	if ( $stmt !== false ) {
		return $stmt->rowCount();
	}
	return false;
}



// ------------------------------------------------------------
// These methods below are utility methods for generating SQL 
// statements
// ------------------------------------------------------------

/**
 * db_prepare_where_statement() constructs a where clause for a 
 * SQL statement.
 */
function db_prepare_where_statement($where, $data = null)
{
	$ret = new stdClass;
	$ret->where = '';
	$ret->data  = array();

	if ( is_array($where) ) {
		$where = db_encode_where_data($where);

		$tmp  = array();
		foreach ( $where AS $column => $value ) {
			$tmp[]       = $column . '=?';
			$ret->data[] = $value;
		}
		$ret->where = ' WHERE ' . implode(' AND ', $tmp);
	} else if ( $where !== null ) {
		$ret->where = ' ' . $where;
		$ret->data  = $data;
	}
	return $ret;
}

/**
 * db_construct_query() is a utility method to handle proper string
 * concatenation for a SQL statement.
 */
function db_construct_query($query, $where = null, $data = null)
{
	$w = db_prepare_where_statement($where, $data);
	$ret = new stdClass;
	$ret->query = $query . $w->where;
	$ret->data  = $w->data;
	return $ret;
}

if ( !function_exists('db_map_table_class') ) {
	/**
	 * db_map_table_class() given a SQL table, returns the appropriate 
	 * PHP Class. It may be passed an array as array('table', 'php_class')
	 */
	function db_map_table_class($table)
	{
		$class = 'stdClass';
		if ( is_array($table) ) {
			$class = $table[1];
			$table = $table[0];
		} else if ( class_exists($table) ) {
			$class = $table;
		}
		return array($table, $class);
	}
}

/**
 * db_fetch() returns an array of objects with each object representing a row.
 */
function db_fetch($table, $where = null, $data = null)
{
	list($table, $class) = db_map_table_class($table);
	$q = db_construct_query('SELECT * FROM ' . $table, $where, $data);
	return db_select($q->query, $q->data, $class);
}

/**
 * db_fetch_single() returns a single record
 */
function db_fetch_single($table, $where = null, $data = null)
{
	list($table, $class) = db_map_table_class($table);
	$q = db_construct_query('SELECT * FROM ' . $table, $where, $data);
	$q->query .= ' LIMIT 1';
	$ret = db_select($q->query, $q->data, $class);
	if ( is_array($ret) ) {
		return array_shift($ret);
	}
	return $ret;
}

/**
 * db_fetch_fields() constructs a query with a list of just the fields specified
 */
function db_fetch_fields($table, $fields, $where = null, $data = null)
{
	if ( !is_array($table) ) {
		$table = array($table, 'stdClass');
	}
	list($table, $class) = db_map_table_class($table);

	if ( !is_array($fields) ) {
		$fields = array($fields);
	}

	$q = db_construct_query('SELECT ' . implode(',', $fields) . ' FROM ' . $table, $where, $data);
	$ret = db_select($q->query, $q->data, $class);
	return $ret;
}

/**
 * db_fetch_field() returns an array of values for the single field that was selected
 */
function db_fetch_field($table, $field, $where = null, $data = null)
{
	$ret = db_fetch_fields($table, $field, $where, $data);
	if ( is_array($ret) ) {
		$tmp = array();
		foreach ( $ret AS $r ) {
			$tmp[] = $r->{$field};
		}
		return $tmp;
	}
	return $ret;
}

/**
 * db_delete() constructs a delete SQL statement based upon the included where clause
 */
function db_delete($table, $where = null, $data = null)
{
	$w = db_prepare_where_statement($where, $data);
	if ( empty($w->where) ) {
		db_log('ERROR: Delete query with no WHERE clause');
		return false;
	}
	$query = 'DELETE FROM ' . $table . $w->where;
	return db_row_count($query, $w->data);
}

/**
 * Given an object, this method will construct an insert or update query. This is determined from the 
 * convention of the 'id' field of the object and if it has a value set, or if the 'where' clause is 
 * populated.
 */
function db_save($table, $object = null, $where = null, $data = null)
{
	$insert = true;
	
	if ( is_object($table) ) {
		$data   = $where;
		$where  = $object;
		$object = $table;
		$table  = get_class($object);
	}
	
	if ( $where === null ) {
		if ( !empty($object->id) ) {
			$where  = array('id' => $object->id);
			$insert = false;
		}
	} else {
		$insert = false;
	}
	
	if ( !is_object($object) ) {
		db_log('ERROR: No object passed');
		return false;
	}

	// Provide an opportunity for a user-space method to encode/modify any properties
	// such as setting "created" dates, etc.
	$object = db_encode_object($object, $insert);

	$data = array();
	if ( $insert ) {
		$props = get_object_vars($object);
		$keys  = array_keys($props);
		$query = 'INSERT INTO ' . $table . ' (' . implode(',', $keys) . ') VALUES (' . implode(',', array_fill(0, count($keys), '?') ) . ')';
		$data  = array_values($props);
	} else {
		$update = array();
		foreach ( get_object_vars($object) AS $k => $v ) {
			// Ignore updating any fields that are used in the where clause
			if ( is_array($where) && isset($where[$k]) ) {
				continue;
			}
			$update[] = $k . '=?';
			$data[]   = $v;
		}
		$w = db_prepare_where_statement($where, $data);
		if ( empty($w->where) ) {
			db_log('ERROR: No where clause found for update');
			return false;
		}
		$query = 'UPDATE ' . $table . ' SET ' . implode(', ', $update) . $w->where;
		$tmp  = array_merge($data, $w->data);
		$data = $tmp;
	}

	$stmt = db_query($query, $data);
	if ( $stmt ) {
		if ( $insert ) {
			$pdo = db_conn();
			return $pdo->lastInsertId();
		}
		return $stmt->rowCount();
	}
	return false;
}