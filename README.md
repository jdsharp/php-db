php-db
======

Simple PHP Database PDO Wrapper

# MySQL Table Conventions

*Default Columns on All Tables*

The following columns are included on all tables. 

`id`      INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
`updated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
`created` INT(10) UNSIGNED NOT NULL DEFAULT 0,

# User Space Database Hooks

// This method may be defined and included prior to including db.php
// All rows returned will be passed to this method first for any altering/transformations
function db_decode_rows($rows) {
	foreach ($rows AS $k => $v) {
		$rows[$k]->created = date('Y-m-d H:i:s', $v->created);
	}
	return $rows;
}

// This method will be called first for any db_save() call. This allows for 
// encoding or setting any default properties.
function db_encode_object($obj) {
	if ( isset($obj->created) && empty($obj->created) ) {
		$obj->created = time();
	}
	return $obj;
}