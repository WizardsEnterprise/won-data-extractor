<?php
require_once(dirname(__FILE__) . '/../classes/WarOfNations2.class.php');
require_once(dirname(__FILE__) . '/../classes/data/DataLoad.class.php');

$won = new WarOfNations();

$won->setDataLoadId(DataLoadDAO::initNewLoad($won->db, 'JOIN_WORLD', 0));
DataLoadDAO::startLoad($won->db, $won->data_load_id);

$won->JoinNewWorld(22);

DataLoadDAO::loadComplete($won->db, $won->data_load_id);

?>