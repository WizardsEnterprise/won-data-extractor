<?php

require_once(dirname(__FILE__) . '/../classes/WarOfNations2.class.php');
require_once(dirname(__FILE__) . '/../classes/data/DataLoad.class.php');


require_once(dirname(__FILE__) . '/../vendor/autoload.php');

use WebSocket\Client;

// Need this initalized so that we can use the database!
$won = new WarOfNations();

// Initalize the data load tracker
$won->setDataLoadId(DataLoadDAO::initNewLoad($won->db, 'SUBSCRIBE_SERVICE', 0));
DataLoadDAO::startLoad($won->db, $won->data_load_id);

// Get an instance of our game operations class
$game = $won->GetGameOperations();

// Subscribe
$purchase = $game->CheckPurchaseBundle('jp.gree.warofnations.gold01');

?>