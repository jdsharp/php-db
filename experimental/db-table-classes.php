<?php
/*
 * This file opens .sql files in a given folder and parses out all of the field names
 * and then creates php classes of the table name with all of the fields as properties.
 */

// This generates a class with the associated properties
function db_generate_class($config)
{
	$name = $config['table'];
	$extends = false;
	if ( class_exists($config['table']) ) {
		$name = $config['table'] . '_fields';
		$extends = $config['table'];
	}
	
	$cls = 'class ' . $name . ( $extends ? ' extends ' . $extends : '' ) . " {\n";
	foreach ( $config['props'] AS $k => $v ) {
		$cls .= '  public $' . $k . " = '';\n";
	}
	$cls .= "}";
	return $cls;
}

if ( !defined('DB_SQL_PATH') ) {
	die('DB_SQL_PATH is not defined');
}

// Load a list of all of our sql files
$sqlFiles = array();
$path     = DB_SQL_PATH;
$d        = dir($path);
while (false !== ($entry = $d->read())) {
	if ( substr($entry, -4) == '.sql' ) {
		$sqlFiles[] = $path . $entry;
	}
}
$d->close();

$cacheModified = 0;
if ( file_exists(DB_SQL_CACHE) ) {
	$cacheModified = filemtime(DB_SQL_CACHE);
}

$sqlFilesUpdated = false;
foreach ( $sqlFiles AS $f ) {
	if ( filemtime($f) >= $cacheModified ) {
		$sqlFilesUpdated = true;
		break;
	}
}

if ( $sqlFilesUpdated === true ) {
	$generated = '<' . "?php\n// GENERATED " . date('r') . "\n\n";
	// Iterate over each sql file, extract the contents and generate a PHP class
	foreach ( $sqlFiles AS $f ) {
		$content = file_get_contents($f);
		$tmp     = db_parseSqlToConfig($content);
		if ( $tmp !== false ) {
			$cls = db_generateClass($tmp);
			$generated .= '// ' . basename($f) . "\n" . $cls . "\n\n";
		} else {
			$generated .= '// FAILED TO GENERATE ' . basename($f) . "\n";
		}
	}
	if ( !file_put_contents(DB_SQL_CACHE, $generated) ) {
		die('Failed to generate ' . DB_SQL_CACHE);
	}
}

require_once(DB_SQL_CACHE);