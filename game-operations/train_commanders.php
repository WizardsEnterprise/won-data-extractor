<?php
require_once('../classes/WarOfNations2.class.php');
require_once('../classes/data/DataLoad.class.php');

date_default_timezone_set('America/Chicago');

// Start with the jeeping step? Useful if something goes wrong while we're holding the base
$start_with_jeeps = false;

// Set this to false only if there's already a wave in the base to recall
$first = true;

// What was the cap hold army ID to be recalled? This is a hack!
$cap_army = array('id' => 42); // Use this when start_with_jeeps == true
$last_cap_army = array('id' => 42); // Use this when first == false

//$current = strtotime('2015-07-23 04:57:54');
//$training_landing_time = 1437645510;
//$training_landing_time->setTimezone(new DateTimeZone('America/Chicago'));

//echo "$current\r\n";
//echo "$training_landing_time\r\n";

//echo $training_landing_time - $current;

//die();

// Pause before starting
//usleep(45 * 60 * 1000000);

// TODO: Build something so that I can control this remotely
// TODO: If we run out of commanders to jeep with, make the program wait some time and then try to start again

// Need this initalized so that we can use the database!
$won = new WarOfNations();

// Initalize the data load tracker
$won->setDataLoadId(DataLoadDAO::initNewLoad($won->db, 'TRAIN_COMMANDERS', 0));
DataLoadDAO::startLoad($won->db, $won->data_load_id);

// Send a text message to tell us that this is starting.  This is really just a test of the texting feature.
$won->sendWarningText('Starting Commander Training!', false);

// Initialize Settings
$training_base_id = 2;

// Banner 38: 101013177372837
// Zion 38: 101013171988840
// HQ 38: 101013100928452
// My 34: 101013101930604
$npc_id = '101013177372837'; 

/* 
Commander IDs:
 207: Hellborn
 208: Iron Wing
 209: Fury Forge
 210: Dark Wolf
 211: The Guardian

 289: Echo

 348: Blister
 349: Cinderblock 
 350: Hyperion
 351: Proton

 400: Omega

 401: Athena
 402: Apollo

 403: Adama
 404: Big Blue
 405: Bayonet
 406: Char
*/
$comms_to_train = array(403, 404, 405, 406);

// Comms to train settings
$comm_train_min_lvl = 90;
$comm_train_max_lvl = 99;

// Comms to jeep settings
$comm_jeep_max_lvl = 80;
$num_jeeps = 8;
$training_comm_min_energy = 0; // Drain them all the way and we can refill if needed
$jeep_comm_min_energy = 1; // Keep 1 energy so that we can swap empty comms to another base
$extra_comm_min_energy = 1; // Keep 1 energy just in case we need them for something
$jeep_increment = 0; // Don't touch this

// Comms to hold base settings
$comm_hold_min_lvl = 90;
$comm_hold_min_energy = 1;

// Unit settings
$training_units_to_send = array('Jeep' => 1, 
			   'Helicopter' => 50, // 25 for 34, 50 for 38
			   'Hailstorm' => 25, // 10 for 34, 25 for 38
			   'Artillery' => 872, // 914 for 34, 872 for 38
			   'Railgun Tank' => 250,
			   'Transport' => 1,
			   'Bomber' => 1);

// This gets used when we have strong comms to hold the base with
$strong_cap_hold_units_to_send = array('Jeep' => 1, 
			   'Helicopter' => 100, // 200 on 38
			   'Hailstorm' => 150, 
			   'Artillery' => 949);

// This gets used when we need to resort to weak caps
$weak_cap_hold_units_to_send = array('Jeep' => 1, 
			   'Helicopter' => 10, 
			   'Hailstorm' => 10, 
			   'Artillery' => 50);

$jeep_units_to_send = array('Jeep' => 1);

// Other operational/timing settings
$minutes_before_jeep = 6;
$seconds_before_second_cap = 10;
$seconds_pause_between_jeeps = 7;
$seconds_pause_before_recall = 10;
$seconds_pause_between_rounds = 5;

// State variables
$recall_fails_in_a_row = 0;
$total_recall_failures = 0;

// Initializing Operational Variables
$time_before_second_cap = 0;
$time_before_recall_hold = 0;


// Authenticate ourselves
//$auth_result = json_decode(file_get_contents('auth_result.json'), true);
$auth_result = $won->Authenticate(true); 

//print_r($auth_result['responses'][0]['return_value']['player_commanders']);

// Get an instance of our game operations class
$game = $won->GetGameOperations();

$quitting = false;
$log_seq = 0;
while(true){
	// Sort commanders that are in the training base into groups
	$training_commanders = array();
	$jeeping_commanders = array();
	$extra_jeep_commanders = array();
	$holding_commanders = array();
	foreach($auth_result['responses'][0]['return_value']['player_commanders'] as $comm) {
		// If the commander is in our designated training base and has energy
		if(array_key_exists('town_id', $comm) && $comm['town_id'] == $training_base_id && $comm['last_update_energy_value'] > 0) {
			// Determine whether this commander is for training or jeeping
			if(in_array($comm['commander_id'], $comms_to_train) || 
				(count($comms_to_train) == 0 && $comm['max_bonus_points'] == 10000)) {
				if($comm['last_update_energy_value'] > $training_comm_min_energy && 
				   $comm['level'] >= $comm_train_min_lvl && $comm['level'] <= $comm_train_max_lvl)
						$training_commanders[] = $comm;
				elseif($comm['level'] >= $comm_hold_min_lvl && $comm['last_update_energy_value'] > $comm_hold_min_energy)
					$holding_commanders[] = $comm;
				elseif($comm['last_update_energy_value'] > $extra_comm_min_energy)
					$extra_commanders[] = $comm;
			}
			elseif($comm['level'] <= $comm_jeep_max_lvl && $comm['last_update_energy_value'] > $jeep_comm_min_energy)
				$jeeping_commanders[] = $comm;
			elseif($comm['level'] >= $comm_hold_min_lvl && $comm['last_update_energy_value'] > $comm_hold_min_energy)
				$holding_commanders[] = $comm;
		}
	}

	// Merge our jeeping commanders together with our "extra" commanders (ones already trained or too low level) 
	$jeeping_commanders = array_merge($jeeping_commanders, $extra_jeep_commanders);

	DataLoadLogDAO::logEvent($won->db, $won->data_load_id, 'TRAIN_COMMANDERS', $log_seq++, 'TRAINING_COMMANDERS', 'Number Remaining: '.count($training_commanders), print_r($training_commanders, true));
	DataLoadLogDAO::logEvent($won->db, $won->data_load_id, 'TRAIN_COMMANDERS', $log_seq++, 'HOLDING_COMMANDERS', 'Number Remaining: '.count($holding_commanders), print_r($holding_commanders, true));
	DataLoadLogDAO::logEvent($won->db, $won->data_load_id, 'TRAIN_COMMANDERS', $log_seq++, 'JEEPING_COMMANDERS', 'Number Remaining: '.count($jeeping_commanders), print_r($jeeping_commanders, true));

	echo "Commanders left to train: ".count($training_commanders)."\r\n";
	echo "Commanders left to hold: ".count($holding_commanders)."\r\n";
	echo "Commanders left to jeep: ".count($jeeping_commanders)."\r\n";

	// We're out of commanders to train, we're done!  Stop now!
	if(count($training_commanders) == 0 ) {
		$won->sendWarningText('Out of commanders to train.  Quitting.');
		echo date_format(new DateTime(), 'H:i:s')." | Out of commanders to train.  Quitting.\r\n";

		// If this was the first attack, we don't need to recall anything!
		if(!$first) {
			// Before we quit, we need to recall the last cap army
			$recall_army = $game->RecallArmy($last_cap_army['id']);

			// If we failed to recall the cap, send an alert and quit the program
			if(!is_array($recall_army)) {
				echo date_format(new DateTime(), 'H:i:s')." | Cap Hold Army Recall Failed! Reason: $recall_army.\r\n";
				DataLoadLogDAO::logEvent($won->db, $won->data_load_id, 'TRAINING_COMMANDERS', $log_seq++, 'RECALL_FAILED', "Cap Hold Army Recall Failed! Reason: $recall_army", null, 1);

				// If the reason we failed was because the army was no longer in the base, attempt to continue
				if($recall_army == "CAN'T_FIND_ARMY_TO_SEND_BACK_HOME") {
					// Ignore this error, the army is not in the base but we're quitting anyway so we don't care
				} else {
					// We failed to recall and we don't know why.  Sound the alarm!
					$won->sendWarningText("Cap Hold Army Recall Failed! Reason: $recall_army. Quitting.", true);

					echo "Quitting.\r\n";
				}
			}
		}

		break;
	}

	// If we just need to start with the jeep step, skip to there
	if(!($start_with_jeeps && $first)) {
		// Select the commander to train
		$current_training_comm = $training_commanders[0];
		
		// Send a capture
		// TODO: Figure out a way to get commander names
		echo date_format(new DateTime(), 'H:i:s')." | Sending training wave for commander: {$current_training_comm['id']}, level {$current_training_comm['level']}\r\n";
		$training_army = $game->SendCapture($npc_id, 1, 1, $current_training_comm['id'], $training_units_to_send);

		// If we failed, send a text message and quit.
		if(!$training_army) {
			$won->sendWarningText('Failed to send training wave!  Quitting Training.');

			echo date_format(new DateTime(), 'H:i:s')." | Failed to send training wave!  Quitting Training.\r\n";

			// If this isn't the first wave, we need to recall the cap holding army
			if(!$first) {
				// Before we quit, we need to recall the last cap army
				$recall_army = $game->RecallArmy($last_cap_army['id']);

				// If we failed to recall the cap, send an alert and quit the program
				if(!is_array($recall_army)) {
					echo date_format(new DateTime(), 'H:i:s')." | Cap Hold Army Recall Failed! Reason: $recall_army.\r\n";
					DataLoadLogDAO::logEvent($won->db, $won->data_load_id, 'TRAINING_COMMANDERS', $log_seq++, 'RECALL_FAILED', "Cap Hold Army Recall Failed! Reason: $recall_army", null, 1);

					// If the reason we failed was because the army was no longer in the base, attempt to continue
					if($recall_army == "CAN'T_FIND_ARMY_TO_SEND_BACK_HOME") {
						// Ignore this error, the army is not in the base but we're quitting anyway so we don't care
					} else {
						// We failed to recall and we don't know why.  Sound the alarm!
						$won->sendWarningText("Cap Hold Army Recall Failed! Reason: $recall_army. Quitting.", true);

						echo "Quitting.\r\n";
					}
				}
			}

			break;
		}

		/*
		Don't need this because the time is innacurate anyway!

		$training_landing_time = new DateTime("@{$training_army['time_to_destination_ts']}");
		$training_landing_time->setTimezone(new DateTimeZone('America/Chicago'));
		echo "Training wave arrives at ".date_format($training_landing_time, 'H:i:s'."\r\n");*/

		// Log 1 training attack on the NPC as a completed operation
		DataLoadDAO::operationComplete($won->db, $won->data_load_id);

		// Wait a little bit before trying to send the second capture
		$time_before_second_cap = $seconds_before_second_cap * rand(900000, 1100000);
		usleep($time_before_second_cap);

		// If we're out of strong comms to hold with, use the first jeeping comm
		// Set the "jeep increment" to be used later in determining which comms to jeep with
		if(count($holding_commanders) > 0){
			$holding_commander = $holding_commanders[0];
			$cap_hold_units_to_send = $strong_cap_hold_units_to_send;
			$jeep_increment = 0;
		} else {
			$holding_commander = $jeeping_commanders[0];
			$cap_hold_units_to_send = $weak_cap_hold_units_to_send;
			$jeep_increment = 1;
		}

		// Send a second capture so that we can recall the first immediately and avoid losing rails while in the base
		// This one will stay in the base while we jeep and be recalled before the next training capture is sent
		echo date_format(new DateTime(), 'H:i:s')." | Sending cap hold wave with commander: {$holding_commander['id']}\r\n";
		$cap_army = $game->SendCapture($npc_id, 1, 1, $holding_commander['id'], $cap_hold_units_to_send);

		// If we failed, send a text message, recall the original army, and quit.
		if(!$cap_army) {
			$won->sendWarningText('Failed to send cap hold wave!  Quitting Training.');

			// Now wait a little while longer before we recall just to seem human
			usleep($seconds_pause_before_recall * rand(900000, 1100000));
			$recall_army = $game->RecallArmy($training_army['id']);

			// If we failed to recall the cap, send an alert and quit the program
			if(!is_array($recall_army)) {
				echo date_format(new DateTime(), 'H:i:s')." | Training Army Recall Failed! Reason: $recall_army.\r\n";
				DataLoadLogDAO::logEvent($won->db, $won->data_load_id, 'TRAINING_COMMANDERS', $log_seq++, 'RECALL_FAILED', "Cap Hold Army Recall Failed! Reason: $recall_army", null, 1);

				// If the reason we failed was because the army was no longer in the base, attempt to continue
				if($recall_army == "CAN'T_FIND_ARMY_TO_SEND_BACK_HOME") {
					// Ignore this error, the army is not in the base but we're quitting anyway so we don't care
				} else {
					// We failed to recall and we don't know why.  Sound the alarm!
					$won->sendWarningText("Training Army Recall Failed! Reason: $recall_army. Quitting.", true);

					echo "Quitting.\r\n";
				}
			}

			// If this isn't the first wave, we need to recall the cap holding army
			if(!$first) {
				// Before we quit, we need to recall the last cap army
				$recall_army = $game->RecallArmy($last_cap_army['id']);

				// If we failed to recall the cap, send an alert and quit the program
				if(!is_array($recall_army)) {
					echo date_format(new DateTime(), 'H:i:s')." | Cap Hold Army Recall Failed! Reason: $recall_army.\r\n";
					DataLoadLogDAO::logEvent($won->db, $won->data_load_id, 'TRAINING_COMMANDERS', $log_seq++, 'RECALL_FAILED', "Cap Hold Army Recall Failed! Reason: $recall_army", null, 1);

					// If the reason we failed was because the army was no longer in the base, attempt to continue
					if($recall_army == "CAN'T_FIND_ARMY_TO_SEND_BACK_HOME") {
						// Ignore this error, the army is not in the base but we're quitting anyway so we don't care
					} else {
						// We failed to recall and we don't know why.  Sound the alarm!
						$won->sendWarningText("Cap Hold Army Recall Failed! Reason: $recall_army. Quitting.", true);

						echo "Quitting.\r\n";
					}
				}
			}

			echo date_format(new DateTime(), 'H:i:s')." | Second Capture Failed!  Quitting Training.\r\n";

			break;
		}

		// Calculate how much longer before our training wave will land
		$time_before_training_landing = $training_army['delta_time_to_destination'] * 1000000 - $time_before_second_cap;

		// If this is not the first wave, wait until just before our training wave will land and recall the holding cap
		if(!$first){
			// Pause until right before the training wave would hit. Sorry for ugly math.
			$time_before_recall_hold = $time_before_training_landing - 10 * rand(800000, 1000000);
			usleep($time_before_recall_hold);

			// Adjust the time before the training wave lands for time we just waited
			$time_before_training_landing = $time_before_training_landing - $time_before_recall_hold;
			
			// Verify that the next army hasn't already landed.  If it did, we're in trouble...
			/*$current_time = new DateTime();
			if($current_time > $training_landing_time) {
				// The current training wave has already landed, there are extra waves in the base.
				// Send an alert and try to recall everything.  In a future version, try to make this recoverable.

				$won->sendWarningText("Did not recall cap army before the training wave landed. Quitting.", true);
				echo date_format(new DateTime(), 'H:i:s')." | Did not recall cap army before the training wave landed. Quitting.\r\n";
				DataLoadLogDAO::logEvent($won->db, $won->data_load_id, 'TRAINING_COMMANDERS', $log_seq++, 'RECALL_FAILED', "Did not recall cap army before the training wave landed.", null, 1);

				break;
			}*/

			echo date_format(new DateTime(), 'H:i:s')." | Recalling the cap hold army #{$last_cap_army['id']}\r\n";
			$recall_army = $game->RecallArmy($last_cap_army['id']);

			// If we failed to recall the cap, send an alert and quit the program
			if(!is_array($recall_army)) {
				echo date_format(new DateTime(), 'H:i:s')." | Cap Hold Army Recall Failed! Reason: $recall_army.\r\n";
				DataLoadLogDAO::logEvent($won->db, $won->data_load_id, 'TRAINING_COMMANDERS', $log_seq++, 'RECALL_FAILED', "Cap Hold Army Recall Failed! Reason: $recall_army", null, 1);

				// Count
				$recall_fails_in_a_row = $recall_fails_in_a_row + 1;

				// If the reason we failed was because the army was no longer in the base, attempt to continue
				if($recall_army == "CAN'T_FIND_ARMY_TO_SEND_BACK_HOME") {
					$won->sendWarningText("Cap Hold Army Recall Failed! Reason: $recall_army. Continuing...", false);

					echo "Attempting to continue...\r\n";
				} else {
					// We failed to recall and we don't know why.  Sound the alarm!
					$won->sendWarningText("Cap Hold Army Recall Failed! Reason: $recall_army. Quitting.", true);

					echo "Quitting.\r\n";
					break;
				}
			} else {
				$recall_fails_in_a_row = 0;
			}

			if($recall_fails_in_a_row >= 3) {
				// We've failed several times in a row.  Sound the alarm!
				echo date_format(new DateTime(), 'H:i:s')." | Recall failed $recall_fails_in_a_row times in a row.\r\n";
				
				$won->sendWarningText("Recall failed $recall_fails_in_a_row times in a row. Quitting.", true);

				echo "Quitting.\r\n";
				break;
			}
		} 

		// Wait until our first cap arrives before we continue
		usleep($time_before_training_landing);
		echo date_format(new DateTime(), 'H:i:s')." | Occupation has begun\r\n";

		// Now wait until the second cap to hold the base has arrived
		usleep($time_before_second_cap);
		echo date_format(new DateTime(), 'H:i:s')." | Cap hold wave has landed\r\n";

		// Wait just a little while longer before we recall so that we seem human
		usleep($seconds_pause_before_recall * rand(900000, 1100000));

		echo date_format(new DateTime(), 'H:i:s')." | Recalling Training Army\r\n";
		$recall_army = $game->RecallArmy($training_army['id']);
	 	
	 	// If we failed to recall the training army, take action!
		if(!is_array($recall_army)) {
			$won->sendWarningText('Training Army Recall Failed!', true);

			echo date_format(new DateTime(), 'H:i:s')." | Training Army Recall Failed! Quitting.\r\n";

			// Now wait a little while longer before we recall the cap wave just to seem human
			usleep($seconds_pause_before_recall * rand(900000, 1100000));
			$recall_army = $game->RecallArmy($cap_army['id']);

			// If we failed to recall the cap, send an alert and quit the program
			if(!is_array($recall_army)) {
				echo date_format(new DateTime(), 'H:i:s')." | Cap Hold Army Recall Failed! Reason: $recall_army.\r\n";
				DataLoadLogDAO::logEvent($won->db, $won->data_load_id, 'TRAINING_COMMANDERS', $log_seq++, 'RECALL_FAILED', "Cap Hold Army Recall Failed! Reason: $recall_army", null, 1);

				// If the reason we failed was because the army was no longer in the base, attempt to continue
				if($recall_army == "CAN'T_FIND_ARMY_TO_SEND_BACK_HOME") {
					// Ignore this error, the army is not in the base but we're quitting anyway so we don't care
				} else {
					// We failed to recall and we don't know why.  Sound the alarm!
					$won->sendWarningText("Cap Hold Army Recall Failed! Reason: $recall_army. Quitting.", true);

					echo "Quitting.\r\n";
				}
			}

			break;
		}

		// Make sure we have enough commanders left to jeep with, otherwise recall our cap and stop.
		if(count($jeeping_commanders) < $num_jeeps + $jeep_increment) {
			$won->sendWarningText('Out of commanders to jeep.  Qutting.', false);
			echo date_format(new DateTime(), 'H:i:s')." | Out of commanders to jeep.  Quitting.\r\n";

			// Now wait a little while longer before we recall just to seem human
			usleep($seconds_pause_before_recall * rand(900000, 1100000));
			$recall_army = $game->RecallArmy($cap_army['id']);

			// If we failed to recall the cap, send an alert and quit the program
			if(!is_array($recall_army)) {
				echo date_format(new DateTime(), 'H:i:s')." | Cap Hold Army Recall Failed! Reason: $recall_army.\r\n";
				DataLoadLogDAO::logEvent($won->db, $won->data_load_id, 'TRAINING_COMMANDERS', $log_seq++, 'RECALL_FAILED', "Cap Hold Army Recall Failed! Reason: $recall_army", null, 1);

				// If the reason we failed was because the army was no longer in the base, attempt to continue
				if($recall_army == "CAN'T_FIND_ARMY_TO_SEND_BACK_HOME") {
					// Ignore this error, the army is not in the base but we're quitting anyway so we don't care
				} else {
					// We failed to recall and we don't know why.  Sound the alarm!
					$won->sendWarningText("Cap Hold Army Recall Failed! Reason: $recall_army. Quitting.", true);

					echo "Quitting.\r\n";
				}
			}

			break;
		}

		// Kill some time before we start jeeping since this makes us need less jeeps to fully refill the base
		usleep($minutes_before_jeep * 60 * rand(950000, 1050000));

	}

	// Send the necessary jeeps to refill the base
	echo date_format(new DateTime(), 'H:i:s')." | Starting Jeeps...\r\n";
	for($i = 1; $i <= $num_jeeps; $i++) {
		// Pause so it's not obvious that we're not really playing
		usleep($seconds_pause_between_jeeps * rand(900000, 1100000));

		// Select the next commander to jeep with. If we used one earlier to hold the base, skip the first one now
		$current_jeeping_comm = $jeeping_commanders[$i - (1 - $jeep_increment)];
		echo date_format(new DateTime(), 'H:i:s')." | Jeeping comm #$i: {$current_jeeping_comm['id']}\r\n";
		$jeep_army = $game->SendAttack($npc_id, 1, 1, $current_jeeping_comm['id'], $jeep_units_to_send);

		// This should never happen but isn't a huge deal anyway
		if(!$jeep_army) 
			echo "Jeep Failed!\r\n";
	}

	// Wait until our last jeep has landed
	// usleep($jeep_army['delta_time_to_destination'] * rand(1000000, 1200000));
	// Just kidding, we don't need to do that.  It's a waste of time.

	// Now wait a little while longer before we recall just to seem human
	usleep($seconds_pause_between_rounds * rand(900000, 1100000));

	// Need to save this information so that we can recall it next time around
	$last_cap_army = $cap_army;

	// Re-Sync our player data so that we can use this to decide which commanders to use next round
	$auth_result = $game->SyncPlayer();
	$first = false;
}

DataLoadDAO::loadComplete($won->db, $won->data_load_id);
?>