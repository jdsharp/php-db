<?php
// Have this run against example-01
require_once( dirname( dirname(__FILE__) ) . '/example-01/init.php' );

function load_populate_data($src)
{
	$file  = basename($src);
	$table = preg_replace('/^([^.]+)\..+/', '$1', $file);
	$data  = file_get_contents($src);
	$lines = explode("\n", $data);

	if ( !class_exists($table) ) {
		$table = 'stdClass';
	}

	// Extract the headers
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
		$obj  = new $table;

		$created = false;
		if ( isset($obj->created) ) {
			$created = true;
		}

		// Go through and unset any fields
		$obj = unset_object_keys_except($obj, $keys);
		if ( $created ) {
			$obj->created = time();
		}
		
		foreach ( $keys AS $i => $k ) {
			if ( strpos($line[$i], '\n') !== false ) {
				$line[$i] = str_replace('\n', "\n", $line[$i]);
			}
			$obj->{$k} = $line[$i];
		}
		$data[] = $obj;
	}

	return $data;
}

$files = array();

$path = DB_SQL_DATA_PATH;
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

echo "Searching: $path\n";
echo "    Found: " . count($files) . " file" . (count($files) > 1 ? 's' : '') . "\n";
echo "\n";

foreach ( $files AS $file ) {
	echo "Reading: $file\n";
	$table = preg_replace('/^([^.]+)\..+/', '$1', $file);
	$data  = load_populate_data($path . DIRECTORY_SEPARATOR . $file);
	echo "    > Found " . count($data) . " records\n";

	$saved = 0;
	foreach ( $data AS $obj ) {
		$ret = db_save($table, $obj);
		if ( $ret !== false ) {
			$saved++;
		}
	}
	echo "    > Saved $saved records\n";
}
echo "Finished.\n";
