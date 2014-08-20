<?php
/**
 * db_parse_sql_to_config parses a SQL create table statement to extract out the field names.
 */
function db_parse_sql_to_config($sql)
{
	$config = array();
	if ( preg_match("/^CREATE TABLE.*`([a-zA-Z_0-9]+)`\s*\(([^;]+)\n\).*;$/m", $sql, $matched) ) {
		$config['table'] = trim($matched[1]);
		
		if ( preg_match_all('/\s*`([a-zA-Z0-9-_]+)`\s+([a-zA-Z0-9]+)/', $matched[2], $fields, PREG_SET_ORDER) ) {
			foreach ( $fields AS $f ) {
				$tmp = array( $f[1], strtolower($f[2]) );
				if ( $f[1] == 'id' ) {
					$tmp[2] = array('pk' => true, 'autoIncrement' => true);
				}
				if ( $f[1] == 'created' ) {
					$tmp[2] = array('defaultExpr' => 'NOW()');
				}
				$config['props'][$f[1]] = $tmp;
			}
			return $config;
		}
	}
	return false;
}