<?php

require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'init.php');

class animals {
	var $id = '';
	var $updated = '';
	var $created = '';
	var $animal_guid = '';
	var $animal_key = '';
	var $description = '';
}

$a = new animals;
unset($a->id);
unset($a->updated);
$uuid = $a->animal_guid = uuid();
$a->animal_key  = 'horse';
$a->description = 'A horse is a very cool animal!';

echo "Insert record:\n";
$ret = db_save($a);
var_dump($ret);

echo $uuid . "\n";

echo "Fetch by id:\n";
$ret = db_fetch('animals', array('id' => $ret));
var_dump($ret);

echo "Fetch by GUID:\n";
$ret = db_fetch('animals', array('animal_guid' => $uuid));
var_dump($ret);