<?php

$project_root = dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'example-01';
require_once( $project_root . '/init.php' );

// This is the path to the folder where the .sql files exist.
$path = DB_SQL_PATH;
if ( is_dir($path) ) {
	$d = dir($path);
	$files = array();
	while ( false !== ( $entry = $d->read() ) ) {
		if ( strtolower(substr($entry, -4)) == '.sql' ) {
			$files[$entry] = file_get_contents($path . '/' . $entry);
		}
	}
	$d->close();
}

$pdo = db_conn();
$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

echo "Searching: $path\n";
echo "Found:     " . count($files) . " files\n";
echo "\n";
foreach ( $files AS $file => $content ) {
	echo "Running: $file\n";
	// Remove all comments (that contain a #)
	$content = preg_split('/#.*$/m', $content);
	$content = implode('', $content);
	$content = trim($content);

	// Break apart any statements that may exist in the single file
	$statements = explode(';', $content);
	foreach ( $statements AS $s ) {
		try {
			$s = trim($s);
			// Ignore empty lines
			if ( empty($s) ) {
				continue;
			}
			$s = $s . ';';

			$line_length = 80;
			$line = array_shift(explode("\n", $s));
			$line = substr($line, 0, $line_length);
			if ( strlen($line) < ($line_length - 1) ) {
				$line = $line . ' ' . implode('', array_fill(0, $line_length - strlen($line) - 1, '.'));
			}

			db_query($s);
			echo "    > " . $line . "...OK\n";
		} catch (PDOException $e) {
			echo "    > " . $line . "...ERROR\n";
			echo "      | SQL ERROR CODE: " . $e->errorInfo[0] . "\n";
			$msg = $e->errorInfo[2];

			$lines = array();

			$chunks = explode("\n", $msg);
			foreach ( $chunks AS $chunk ) {
				$line = '';
				$parts = explode(' ', $chunk);
				while ( ($p = array_shift($parts)) !== null ) {
					if ( ( strlen($line) + strlen($p) ) > $line_length ) {
						$lines[] = $line;
						$line = $p;
					} else {
						$line .= ($line == '' ? '' : ' ') . $p;
					}
				}
				$lines[] = $line;
			}
			echo "      | " . implode("\n      | ", $lines) . "\n";
			echo "      |\n";
			echo "      | Exiting...\n";
			break 2;
		}
	}
	echo "\n";
}

echo "Finished.\n";