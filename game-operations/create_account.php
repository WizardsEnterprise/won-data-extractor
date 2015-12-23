<?php
require_once('../classes/WarOfNations2.class.php');
require_once('../classes/data/DataLoad.class.php');

// Initialize Variables
$alliance_to_join = '101013232844480'; // 1st_HQ
$amount_needed = 58;
$amount_donated = 0;

// Login to Main
$won_main = new WarOfNations();
$won_main->setDataLoadId(DataLoadDAO::initNewLoad($won_main->db, 'OPERATION_CONCRETE', 0));
DataLoadDAO::startLoad($won_main->db, $won_main->data_load_id);
$won_main->Authenticate(false, 5);
$game_main = $won_main->GetGameOperations();

// Start Loop Here
while($amount_donated < $amount_needed) {
	// Create Alt
	$won_alt = new WarOfNations();
	$won_alt->setDataLoadId($won_main->data_load_id);

	// Create a new account
	$won_alt->auth->CreateNewPlayer();
	//$won_alt->Authenticate(false, 17); // Use if something fails

	usleep(1000000);

	// Join World 13
	if ($won_alt->auth->world_id != 13)
		$won_alt->auth->JoinNewWorld(13);

	usleep(1000000);

	// Buy Reinforced Concrete (180000)
	$game_alt = $won_alt->GetGameOperations();
	$game_alt->PurchaseItem('180000', 2); 

	usleep(1000000);

	// Change Name
	$new_name = 'Player'.mt_rand(1000000,9999999);
	$name_change = $game_alt->ChangeName($new_name);

	usleep(1000000);

	// Request Alliance
	$alliance_request = $game_alt->RequestAlliance($alliance_to_join);

	usleep(1000000);

	// Use Main - Accept into Alliance
	$game_main->AcceptAllianceRequest($won_alt->auth->player_id);

	usleep(1000000);

	// Donate Concrete
	$game_alt->DonateToAlliance('item', '180000', 2); // Reinforced Concrete
	$amount_donated += 2;
	usleep(1000000);

	// Announce Donation
	//$game_alt->SendChatMessage('\/guild_101013232844480', '2 Concrete Donated');

	//usleep(1000000);

	// Leave Alliance
	$game_alt->LeaveAlliance();

	echo "** Donation Status: $amount_donated/$amount_needed **\r\n\r\n";

	DataLoadDAO::operationComplete($won_main->db, $won_main->data_load_id);

	usleep(1000000);
	// Repeat...
}

DataLoadDAO::loadComplete($won_main->db, $won_main->data_load_id);

?>
