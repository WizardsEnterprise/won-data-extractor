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
$npc_id = '101013110493867'; //'101013100928452';

// Comms to train settings
$comm_train_min_lvl = 90;
$comm_train_max_lvl = 99;

/* 
Commander IDs:
 207: Hellborn
 208: Iron Wing
 209: Fury Forge
 210: Dark Wolf
 211: The Guardian

 348: Blister
 349: Proton
 350: Hyperion
 351: Cinderblock
*/
$comms_to_train = array(207, 208, 209, 210, 211);

// Comms to jeep settings
$comm_jeep_max_lvl = 60;
$num_jeeps = 8;
$training_comm_min_energy = 0; // Drain them all the way and we can refill if needed
$jeep_comm_min_energy = 1; // Keep 1 energy so that we can swap empty comms to another base
$extra_comm_min_energy = 1; // Keep 1 energy just in case we need them for something

// Unit settings
$training_units_to_send = array('Jeep' => 1, 
			   'Helicopter' => 50, 
			   'Hailstorm' => 10, 
			   'Artillery' => 889, //849
			   'Railgun Tank' => 250); //250

$cap_hold_units_to_send = array('Artillery' => 1);

$jeep_units_to_send = array('Jeep' => 1);

// Other operational/timing settings
$minutes_before_jeep = 6;
$seconds_before_second_cap = 10;
$seconds_pause_between_jeeps = 7;
$seconds_pause_before_recall = 10;
$seconds_pause_between_rounds = 5;

// Authenticate ourselves
//$auth_result = json_decode(file_get_contents('auth_result.json'), true);
$auth_result = $won->Authenticate(true); 

//print_r($auth_result['responses'][0]['return_value']['player_commanders']);

// Get an instance of our game operations class
$game = $won->GetGameOperations();

$log_seq = 0;
$first = true;
while(true){
	// Sort commanders that are in the training base into groups
	$training_commanders = array();
	$jeeping_commanders = array();
	$extra_jeep_commanders = array();
	foreach($auth_result['responses'][0]['return_value']['player_commanders'] as $comm) {
		// If the commander is in our designated training base and has energy
		if($comm['town_id'] == $training_base_id && $comm['last_update_energy_value'] > 0) {
			// Determine whether this commander is for training or jeeping
			if(in_array($comm['commander_id'], $comms_to_train)) {
				if($comm['last_update_energy_value'] > $training_comm_min_energy && 
				   $comm['level'] >= $comm_train_min_lvl && $comm['level'] <= $comm_train_max_lvl)
						$training_commanders[] = $comm;
				elseif($comm['last_update_energy_value'] > $extra_comm_min_energy) // Never let our big comms run out of energy
					$extra_jeep_commanders[] = $comm;
			}
			elseif($comm['level'] <= $comm_jeep_max_lvl && $comm['last_update_energy_value'] > $jeep_comm_min_energy)
				$jeeping_commanders[] = $comm;
		}
	}

	// Merge our jeeping commanders together with our "extra" commanders (ones already trained or too low level) 
	$jeeping_commanders = array_merge($jeeping_commanders, $extra_jeep_commanders);

	// We're out of commanders to train, we're done!  Stop now!
	if(count($training_commanders) == 0 ) {
		$won->sendWarningText('Out of commanders to train.  Qutting.');
		echo date_format(new DateTime(), 'H:i:s')." | Out of commanders to train.  Qutting.\r\n";

		// Before we quit, we need to recall the last cap army
		$recall_army = $game->RecallArmy($last_cap_army['id']);

		// If we failed to recall the cap, send an alert and quit the program
		if(!is_array($recall_army)) {
			// If the reason we failed was because the army was no longer in the base, attempt to continue
			if($recall_army == "CAN'T_FIND_ARMY_TO_SEND_BACK_HOME") {
				$won->sendWarningText("Cap Hold Army Recall Failed! Reason: $recall_army. Continuing...", false);
				echo date_format(new DateTime(), 'H:i:s')." | Cap Hold Army Recall Failed! Reason: $recall_army.\r\n Attempting to continue...\r\n";
				DataLoadLogDAO::logEvent($won->db, $won->data_load_id, 'TRAINING_COMMANDERS', $log_seq++, 'RECALL_FAILED', "Cap Hold Army Recall Failed! Reason: $recall_army", null, 1);
			} else {
				$won->sendWarningText('Cap Hold Army Recall Failed!', true);

				echo date_format(new DateTime(), 'H:i:s')." | Cap Hold Army Recall Failed! Quitting.\r\n";
			}
		}

		break;
	}

	DataLoadLogDAO::logEvent($won->db, $won->data_load_id, 'TRAIN_COMMANDERS', $log_seq++, 'TRAINING_COMMANDERS', 'Number Remaining: '.count($training_commanders), print_r($training_commanders, true));
	DataLoadLogDAO::logEvent($won->db, $won->data_load_id, 'TRAIN_COMMANDERS', $log_seq++, 'JEEPING_COMMANDERS', 'Number Remaining: '.count($jeeping_commanders), print_r($jeeping_commanders, true));

	echo "Commanders left to train: ".count($training_commanders)."\r\n";
	echo "Commanders left to jeep: ".count($jeeping_commanders)."\r\n";

	// Select the commander to train
	$current_training_comm = $training_commanders[0];
	
	// Send a capture
	echo date_format(new DateTime(), 'H:i:s')." | Sending training wave for commander: {$current_training_comm['id']}\r\n";
	$training_army = $game->SendCapture($npc_id, 1, 1, $current_training_comm['id'], $training_units_to_send);

	// If we failed, send a text message and quit.
	if(!$training_army) {
		$won->sendWarningText('Initial Capture Failed!  Quitting Training.');

		echo date_format(new DateTime(), 'H:i:s')." | Initial Capture Failed!  Quitting Training.\r\n";

		// Before we quit, we need to recall the last cap army
		$recall_army = $game->RecallArmy($last_cap_army['id']);

		// If we failed to recall the cap, send an alert and quit the program
		if(!is_array($recall_army)) {
			// If the reason we failed was because the army was no longer in the base, attempt to continue
			if($recall_army == "CAN'T_FIND_ARMY_TO_SEND_BACK_HOME") {
				$won->sendWarningText("Cap Hold Army Recall Failed! Reason: $recall_army. Continuing...", false);
				echo date_format(new DateTime(), 'H:i:s')." | Cap Hold Army Recall Failed! Reason: $recall_army.\r\n Attempting to continue...\r\n";
				DataLoadLogDAO::logEvent($won->db, $won->data_load_id, 'TRAINING_COMMANDERS', $log_seq++, 'RECALL_FAILED', "Cap Hold Army Recall Failed! Reason: $recall_army", null, 1);
			} else {
				$won->sendWarningText('Cap Hold Army Recall Failed!', true);

				echo date_format(new DateTime(), 'H:i:s')." | Cap Hold Army Recall Failed! Quitting.\r\n";
			}
		}
		break;
	}

	// Log 1 training attack on the NPC as a completed operation
	DataLoadDAO::operationComplete($won->db, $won->data_load_id);

	// Wait a little bit before trying to send the second capture
	$time_before_second_cap = $seconds_before_second_cap * rand(1000000, 1200000);
	usleep($time_before_second_cap);

	// Send a second capture so that we can recall the first immediately and avoid losing rails while in the base
	// This one will stay in the base while we jeep and be recalled before the next training capture is sent
	// - Use the first "jeeping commander" to hold the base
	echo date_format(new DateTime(), 'H:i:s')." | Sending cap hold wave with commander: {$jeeping_commanders[0]['id']}\r\n";
	$cap_army = $game->SendCapture($npc_id, 1, 1, $jeeping_commanders[0]['id'], $cap_hold_units_to_send);

	// If we failed, send a text message, recall the original army, and quit.
	if(!$cap_army) {
		$won->sendWarningText('Second Capture Failed!  Quitting Training.');

		// Now wait a little while longer before we recall just to seem human
		usleep($seconds_pause_before_recall * rand(900000, 1100000));
		$recall_army = $game->RecallArmy($training_army['id']);

		if(!is_array($recall_army)) {
			$won->sendWarningText('Training Army Recall Failed!', true);

			echo date_format(new DateTime(), 'H:i:s')." | Training Army Recall Failed! Quitting.\r\n";
			break;
		}

		echo date_format(new DateTime(), 'H:i:s')." | Second Capture Failed!  Quitting Training.\r\n";
		break;
	}

	// If this not the first wave, wait until just before our training cap will land and recall the holding cap
	if(!$first){
		// Pause until right before the training wave would hit.  Subtract the time we already waited.
		usleep($training_army['delta_time_to_destination'] * rand(900000, 950000) - $time_before_second_cap);

		echo date_format(new DateTime(), 'H:i:s')." | Recalling the cap hold army #{$cap_army['id']}\r\n";
		$recall_army = $game->RecallArmy($last_cap_army['id']);

		// If we failed to recall the cap, send an alert and quit the program
		if(!is_array($recall_army)) {
			// If the reason we failed was because the army was no longer in the base, attempt to continue
			if($recall_army == "CAN'T_FIND_ARMY_TO_SEND_BACK_HOME") {
				$won->sendWarningText("Cap Hold Army Recall Failed! Reason: $recall_army. Continuing...", false);
				echo date_format(new DateTime(), 'H:i:s')." | Cap Hold Army Recall Failed! Reason: $recall_army.\r\n Attempting to continue...\r\n";
				DataLoadLogDAO::logEvent($won->db, $won->data_load_id, 'TRAINING_COMMANDERS', $log_seq++, 'RECALL_FAILED', "Cap Hold Army Recall Failed! Reason: $recall_army", null, 1);
			} else {
				$won->sendWarningText('Cap Hold Army Recall Failed!', true);

				echo date_format(new DateTime(), 'H:i:s')." | Cap Hold Army Recall Failed! Quitting.\r\n";
				break;
			}
		}
	} 

	// Wait until our reinforcements arrive to recall the original training wave
	usleep($cap_army['delta_time_to_destination'] * 1000000);
	echo date_format(new DateTime(), 'H:i:s')." | Occupation has begun\r\n";

	// Now wait a little while longer before we recall just to seem human
	usleep($seconds_pause_before_recall * rand(900000, 1100000));

	echo date_format(new DateTime(), 'H:i:s')." | Recalling Training Army\r\n";
	$recall_army = $game->RecallArmy($training_army['id']);
 
	if(!is_array($recall_army)) {
		$won->sendWarningText('Training Army Recall Failed!', true);

		echo date_format(new DateTime(), 'H:i:s')." | Training Army Recall Failed! Quitting.\r\n";
		break;
	}

	// Make sure we have enough commanders left to jeep with, otherwise recall our cap and stop.
	if(count($jeeping_commanders) < $num_jeeps + 1) {
		$won->sendWarningText('Out of commanders to jeep.  Qutting.', false);
		echo date_format(new DateTime(), 'H:i:s')." | Out of commanders to jeep.  Qutting.\r\n";

		// Now wait a little while longer before we recall just to seem human
		usleep($seconds_pause_before_recall * rand(900000, 1100000));
		$recall_army = $game->RecallArmy($cap_army['id']);

		if(!is_array($recall_army)) {
			$won->sendWarningText('Cap Hold Army Recall Failed!', true);

			echo date_format(new DateTime(), 'H:i:s')." | Cap Hold Army Recall Failed! Quitting.\r\n";
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
		$current_jeeping_comm = $jeeping_commanders[$i];
		echo date_format(new DateTime(), 'H:i:s')." | Jeeping comm #$i: {$current_jeeping_comm['id']}\r\n";
		$jeep_army = $game->SendAttack($npc_id, 1, 1, $current_jeeping_comm['id'], $jeep_units_to_send);

		// This should never happen but isn't a huge deal anyway
		if(!$jeep_army) 
			echo "Jeep Failed!\r\n";
	}

	// Wait until our last jeep has landed
	usleep($jeep_army['delta_time_to_destination'] * rand(1000000, 1200000));

	// Now wait a little while longer before we recall just to seem human
	usleep($seconds_pause_between_rounds * rand(900000, 1100000));
	
	/*
	I don't think we need this here at all.  Just need to NOT try to recall the army the first time 
	coming through up above...

	if($first) {
		echo date_format(new DateTime(), 'H:i:s')." | Recalling the cap hold army #{$cap_army['id']}\r\n";
		$recall_army = $game->RecallArmy($cap_army['id']);

		// If we failed to recall the cap, send an alert and quit the program
		if(!is_array($recall_army)) {
			// If the reason we failed was because the army was no longer in the base, attempt to continue
			if($recall_army == "CAN'T_FIND_ARMY_TO_SEND_BACK_HOME") {
				$won->sendWarningText("Cap Hold Army Recall Failed! Reason: $recall_army. Continuing...", false);
				echo date_format(new DateTime(), 'H:i:s')." | Cap Hold Army Recall Failed! Reason: $recall_army.\r\n Attempting to continue...\r\n";
				DataLoadLogDAO::logEvent($won->db, $won->data_load_id, 'TRAINING_COMMANDERS', $log_seq++, 'RECALL_FAILED', "Cap Hold Army Recall Failed! Reason: $recall_army", null, 1);
			} else {
				$won->sendWarningText('Cap Hold Army Recall Failed!', true);

				echo date_format(new DateTime(), 'H:i:s')." | Cap Hold Army Recall Failed! Quitting.\r\n";
				break;
			}
		}

		// Wait until our recalled capture wave has returned
		// Changed after adding second round of captures
		// - We no longer need to wait for it to come all the way back.  Take a short break and then send again
		//usleep($recall_army['delta_time_to_destination'] * rand(1000000, 1200000));

		// Pause for a little while because real humans don't take actions instantly
		usleep($seconds_pause_between_rounds * rand(900000, 1100000));
	}*/

	// Need to save this information so that we can recall it next time around
	$last_cap_army = $cap_army;

	// Re-Sync our player data so that we can use this to decide which commanders to use next round
	$auth_result = $game->SyncPlayer();
	$first = false;
}

DataLoadDAO::loadComplete($won->db, $won->data_load_id);
?>