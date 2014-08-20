<?php

require_once( dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'db.conf.php');

// We'll go through and any column that ends in '_guid' convert the row from the binary 
// representation to it's Longform HEX
function db_decode_rows($rows)
{
	foreach ( $rows AS $k => $v ) {
		$rows[$k] = db_unpack_guids($v);
	}
	return $rows;
}

function db_encode_where_data($where) {
	foreach ( $where AS $k => $v ) {
		if ( substr($k, -5) == '_guid' ) {
			$where[$k] = uuid_to_bin($v);
		}
	}
	return $where;
}

function db_encode_object($obj)
{
	$obj = db_pack_guids($obj);
	if ( isset($obj->created) && empty($obj->created) ) {
		$obj->created = time();
	}
	return $obj;
}
// The above methods need to be defined before db.php is included

// This is a verbose require line, you can make it more terse
require_once(dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'db.php');