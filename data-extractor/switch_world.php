<?php
require_once(dirname(__FILE__) . '/../classes/WarOfNations2.class.php');
require_once(dirname(__FILE__) . '/../classes/data/DataLoad.class.php');

if(count($argv) == 2) {
	$world = $argv[1];
	echo "Got Command Line Argument for World [$world]\r\n";
} else {
	$world = 13;
	echo "No Command Line Argument Found.  Using Default World [$world]\r\n";
}

$won = new WarOfNations();

$won->setDataLoadId(DataLoadDAO::initNewLoad($won->db, 'SWITCH_WORLD', 0, "World $world"));
DataLoadDAO::startLoad($won->db, $won->data_load_id);

$won->SwitchWorld($world);

DataLoadDAO::loadComplete($won->db, $won->data_load_id);

?>