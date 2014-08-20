<?php

require_once( dirname( dirname(__FILE__) ) . '/init.php' );

function load_populate_data($file)
{
	$db    = preg_replace('/^([^.]+)\..+/', '$1', $file);
	$src   = dirname( dirname(__FILE__) ) . '/sql-data/' . $file;
	$data  = file_get_contents($src);
	$lines = explode("\n", $data);

	$headers = array_shift($lines);
	preg_match_all("/(\w+)/", $headers, $matched);
	
	$keys = $matched[1];
	
	$data = array();
	foreach ( $lines AS $line ) {
		$line = trim($line);
		if ( empty($line) ) {
			continue;
		}
		$line = explode("\t", $line);
		$tmp  = new $db;

		$created = false;
		if ( isset($tmp->created) ) {
			$created = true;
		}

		// Go through and unset any fields
		$tmp = unset_object_keys_except($tmp, $keys);
		if ( $created ) {
			$tmp->created = time();
		}
		
		foreach ( $keys AS $i => $k ) {
			if ( strpos($line[$i], '\n') !== false ) {
				$line[$i] = str_replace('\n', "\n", $line[$i]);
			}
			$tmp->{$k} = $line[$i];
		}
		$data[] = $tmp;
	}

	return $data;
}

$files = array();

$path = dirname(dirname(__FILE__)) . '/sql-data';
if ( is_dir($path) ) {
	$d = dir($path);
	$files = array();
	while ( false !== ( $entry = $d->read() ) ) {
		if ( strtolower(substr($entry, -4)) == '.txt' ) {
			$files[] = $entry;
		}
	}
	$d->close();
}

foreach ( $files AS $file ) {
	$db   = preg_replace('/^([^.]+)\..+/', '$1', $file);
	$data = load_populate_data($file);
	echo "Found " . count($data) . " records for $file\n";
	$saved = 0;
	foreach ( $data AS $obj ) {
		$ret = db_save($db, $obj);
		if ( $ret !== false ) {
			$saved++;
		}
	}
	echo "\t$saved records saved.\n";
}
echo "Finished.\n";
