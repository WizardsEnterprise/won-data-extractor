<?php
require_once('../classes/WarOfNations2.class.php');
require_once('../classes/data/DataLoad.class.php');

// TODO: Build something so that I can control this remotely
// TODO: If we run out of commanders to jeep with, make the program wait some time and then try to start again

date_default_timezone_set('America/Chicago');

// Need this initalized so that we can use the database!
$won = new WarOfNations();

// Initalize the data load tracker
$won->setDataLoadId(DataLoadDAO::initNewLoad($won->db, 'TRAIN_COMMANDERS', 0));
DataLoadDAO::startLoad($won->db, $won->data_load_id);

// Send a text message to tell us that this is starting.  This is really just a test of the texting feature.
$won->sendWarningText('Starting Commander Training!', false);

// Initialize Settings
$training_base_id = 2;
$npc_id = '101013100928452';

// Comms to train settings
$comm_train_min_lvl = 70;
$comm_train_max_lvl = 79;

// Comm 207: Hellborn
// Comm 208: Iron Wing
// Comm 209: Fury Forge
// Comm 210: Dark Wolf
// Comm 211: The Guardian
$comms_to_train = array(207, 208, 209, 210, 211);

// Comms to jeep settings
$comm_jeep_max_lvl = 60;
$num_jeeps = 8;
$jeep_comm_min_energy = 1;

// Unit settings
$cap_units_to_send = array('Jeep' => 1, 
			   'Helicopter' => 50, 
			   'Hailstorm' => 50, 
			   'Artillery' => 1032, //849
			   'Railgun Tank' => 67); //250

$jeep_units_to_send = array('Jeep' => 1);

// Other operational settings
$minutes_before_jeep = 6;
$seconds_pause_between_jeeps = 10;
$seconds_pause_before_recall = 15;
$seconds_pause_between_rounds = 10;

// Authenticate ourselves
//$auth_result = json_decode(file_get_contents('auth_result.json'), true);
$auth_result = $won->Authenticate(true); 

//print_r($auth_result['responses'][0]['return_value']['player_commanders']);

// Get an instance of our game operations class
$game = $won->GetGameOperations();

$log_seq = 0;
while(true){
	// Get commanders that are in the training base
	$training_commanders = array();
	$jeeping_commanders = array();
	$extra_jeep_commanders = array();
	foreach($auth_result['responses'][0]['return_value']['player_commanders'] as $comm) {
		// If the commander is in our designated training base and has energy
		if($comm['town_id'] == $training_base_id && $comm['last_update_energy_value'] > $jeep_comm_min_energy) {
			// Determine whether this commander is for training or jeeping
			if(in_array($comm['commander_id'], $comms_to_train)) {
				if($comm['level'] >= $comm_train_min_lvl && $comm['level'] <= $comm_train_max_lvl)
					$training_commanders[] = $comm;
				elseif($comm['last_update_energy_value'] > 1) // Never let our big comms run out of energy
					$extra_jeep_commanders[] = $comm;
			}
			elseif($comm['level'] <= $comm_jeep_max_lvl)
				$jeeping_commanders[] = $comm;
		}
	}

	$jeeping_commanders = array_merge($jeeping_commanders, $extra_jeep_commanders);

	// If we're out of commanders to train, stop now.
	if(count($training_commanders) == 0 ) {
		echo date_format(new DateTime(), 'H:i:s')." | Out of commanders to train.  Qutting.\r\n";
		break;
	}

	DataLoadLogDAO::logEvent($won->db, $won->data_load_id, 'TRAIN_COMMANDERS', $log_seq++, 'TRAINING_COMMANDERS', null, print_r($training_commanders, true));
	DataLoadLogDAO::logEvent($won->db, $won->data_load_id, 'TRAIN_COMMANDERS', $log_seq++, 'JEEPING_COMMANDERS', null, print_r($jeeping_commanders, true));

	echo "Commanders left to train: ".count($training_commanders)."\r\n";
	echo "Commanders left to jeep: ".count($jeeping_commanders)."\r\n";

	// Select the commander to train
	$current_training_comm = $training_commanders[0];
	echo date_format(new DateTime(), 'H:i:s')." | Current training comm: {$current_training_comm['id']}\r\n";

	// Send a capture
	$cap_army = $game->SendCapture($npc_id, 1, 1, $current_training_comm['id'], $cap_units_to_send);

	// If we failed, send a text message and quit.
	if(!$cap_army) {
		sendWarningText('Capture Failed!  Quitting Training.');

		echo date_format(new DateTime(), 'H:i:s')." | Capture Failed! Quitting.\r\n";
		break;
	}

	// Log 1 attack on the NPC as a completed operation
	DataLoadDAO::operationComplete($won->db, $won->data_load_id);

	// Wait until our occupation has started
	usleep($cap_army['delta_time_to_destination'] * rand(1000000, 1200000));
	echo date_format(new DateTime(), 'H:i:s')." | Occupation has begun\r\n";

	// If we're out of commanders to jeep with, recall our cap and stop.
	if(count($jeeping_commanders) == 0 ) {
		echo date_format(new DateTime(), 'H:i:s')." | Out of commanders to jeep.  Qutting.\r\n";

		// Now wait a little while longer before we recall just to seem human
		usleep($seconds_pause_before_recall * rand(900000, 1100000));
		$recall_army = $game->RecallArmy($cap_army['id']);

		if(!$recall_army) {
			sendWarningText('Army Recall Failed!', true);

			echo date_format(new DateTime(), 'H:i:s')." | Recall Failed! Quitting.\r\n";
			break;
		}

		break;
	}

	// Kill some time before we start jeeping since this makes us need less jeeps to fully refill the base
	usleep($minutes_before_jeep * 60 * rand(900000, 1100000));

	// Send the necessary jeeps to refill the base
	echo date_format(new DateTime(), 'H:i:s')." | Starting Jeeps...\r\n";
	for($i = 1; $i <= $num_jeeps; $i++) {
		// Pause so it's not obvious that we're not really playing
		usleep($seconds_pause_between_jeeps * rand(900000, 1100000));

		// Select the next commander to jeep with
		$current_jeeping_comm = $jeeping_commanders[$i-1];
		echo date_format(new DateTime(), 'H:i:s')." | Jeeping comm #$i: {$current_jeeping_comm['id']}\r\n";
		$jeep_army = $game->SendAttack($npc_id, 1, 1, $current_jeeping_comm['id'], $jeep_units_to_send);

		// This should never happen but isn't a huge deal anyway
		if(!$jeep_army) 
			echo "Jeep Failed!\r\n";
	}

	// Wait until our last jeep has landed
	usleep($jeep_army['delta_time_to_destination'] * rand(1000000, 1200000));

	// Now wait a little while longer before we recall just to seem human
	usleep($seconds_pause_before_recall * rand(900000, 1100000));
	$recall_army = $game->RecallArmy($cap_army['id']);

	// If we failed to recall the cap, send an alert and quit the program
	if(!$recall_army) {
		sendWarningText('Army Recall Failed!', true);

		echo date_format(new DateTime(), 'H:i:s')." | Recall Failed! Quitting.\r\n";
		break;
	}

	// Wait until our recalled capture wave has returned
	usleep($recall_army['delta_time_to_destination'] * rand(1000000, 1200000));

	// Pause for a little while because real humans don't take actions instantly
	usleep($seconds_pause_between_rounds * rand(900000, 1100000));

	// Re-Sync our player data so that we can use this to decide which commanders to use next round
	$auth_result = $game->SyncPlayer();

}

DataLoadDAO::loadComplete($won->db, $won->data_load_id);
?>