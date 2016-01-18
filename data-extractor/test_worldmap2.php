<?php
require_once(dirname(__FILE__) . '/../classes/WarOfNations2.class.php');
require_once(dirname(__FILE__) . '/../classes/data/DataLoad.class.php');

$won = new WarOfNations();

$won->setDataLoadId(DataLoadDAO::initNewLoad($won->db, 'TEST_WORLDMAP2', 0));
DataLoadDAO::startLoad($won->db, $won->data_load_id);

$won->GetWorldMap(-1000, 1400, 100, 100);

DataLoadDAO::loadComplete($won->db, $won->data_load_id);

?>