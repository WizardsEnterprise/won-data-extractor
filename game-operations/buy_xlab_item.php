<?php
require_once(dirname(__FILE__) . '/../classes/WarOfNations2.class.php');
require_once(dirname(__FILE__) . '/../classes/data/DataLoad.class.php');

// Login to Main
$won_main = new WarOfNations();
$won_main->setDataLoadId(DataLoadDAO::initNewLoad($won_main->db, 'BUY_XLAB_ITEM', 0));
DataLoadDAO::startLoad($won_main->db, $won_main->data_load_id);
$won_main->Authenticate(false, 5);
$game_main = $won_main->GetGameOperations();

// 601003 = 2 x Quantum Processor (1 Chromium Fragment)
// 601004 = 2 x A.I. Neural Core (1 Chromium Fragment)
// 610000 = Chrome Key (10 Chromium Fragments)
// 610001 = 6 x Chromium Fragment (Blue Key)
// 610003 = Chromed Eclipse Crate (1 Chrome Key)

$game_main->PurchaseXlabItem(760026, 1); // Ruby Key

/*$game_main->PurchaseXlabItem(601003, 25); 
$game_main->PurchaseXlabItem(601003, 25); 
$game_main->PurchaseXlabItem(601003, 25); 
$game_main->PurchaseXlabItem(601003, 25); 
$game_main->PurchaseXlabItem(601003, 25); 
$game_main->PurchaseXlabItem(601003, 25); 
$game_main->PurchaseXlabItem(601003, 11); 
$game_main->PurchaseXlabItem(601004, 25); 
$game_main->PurchaseXlabItem(601004, 25); 
$game_main->PurchaseXlabItem(601004, 25); 
$game_main->PurchaseXlabItem(601004, 25); 
$game_main->PurchaseXlabItem(601004, 25); 
$game_main->PurchaseXlabItem(601004, 25); 
$game_main->PurchaseXlabItem(601004, 12); */

DataLoadDAO::loadComplete($won_main->db, $won_main->data_load_id);

?>
