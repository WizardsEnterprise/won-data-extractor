<?php
require_once('../classes/WarOfNations2.class.php');
require_once('../classes/data/DataLoad.class.php');

// Need this initalized so that we can use the database!
$won = new WarOfNations();

// Initalize the data load tracker
$won->setDataLoadId(DataLoadDAO::initNewLoad($won->db, 'SUBSCRIBE_SERVICE', 0));
DataLoadDAO::startLoad($won->db, $won->data_load_id);

// Get an instance of our game operations class
$game = $won->GetGameOperations();

// Subscribe
$val = $game->SubscribeUplink();

?>