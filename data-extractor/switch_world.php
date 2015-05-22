<?php
require_once('../classes/WarOfNations2.class.php');
require_once('../classes/data/DataLoad.class.php');

$won = new WarOfNations();

$won->setDataLoadId(DataLoadDAO::initNewLoad($won->db, 'SWITCH_WORLD', 0));
DataLoadDAO::startLoad($won->db, $won->data_load_id);

$won->SwitchWorld(22);

DataLoadDAO::loadComplete($won->db, $won->data_load_id);

?>