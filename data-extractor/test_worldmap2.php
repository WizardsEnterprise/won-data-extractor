<?php
require_once('../classes/WarOfNations2.class.php');
require_once('../classes/data/DataLoad.class.php');

$won = new WarOfNations();

$won->setDataLoadId(DataLoadDAO::initNewLoad($won->db, 'TEST_WORLDMAP2', 0));
DataLoadDAO::startLoad($won->db, $won->data_load_id);

$won->GetWorldMap(0, 0, 50, 50);

?>