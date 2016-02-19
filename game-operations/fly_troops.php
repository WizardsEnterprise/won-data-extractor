<?php
require_once(dirname(__FILE__) . '/../classes/WarOfNations2.class.php');
require_once(dirname(__FILE__) . '/../classes/data/DataLoad.class.php');

set_time_limit(0);

/*************** Helper Functions ***************/
// Sort the base list in order of most recently attacked
function base_sort_last_attacked($a, $b) {
    if ($a['time_last_attacked_ts'] == $b['time_last_attacked_ts']) {
        return 0;
    }
    return ($a['time_last_attacked_ts'] > $b['time_last_attacked_ts']) ? -1 : 1;
}

/*************** Initialize Settings ***************/
$device_id = false;
$first_run = true;
$max_army_size = 1900; // Keep this the same across all bases.  Minimum of all bases' army size
$max_waves = 10; // Max number of waves that can be sent from 1 base
$preferred_distance = 700; // Preferred flight distance, use this to balance flight time

// Timing Settings
$seconds_between_waves = 2;
$seconds_between_bases = 5;
$hours_between_sessions = 6;

// Order to fly troops out of base (most valuable first)
$fly_order = array('Railgun Tank', 'Titan', 'Hellfire', 'Centurion', 'Hailstorm', 'Arachnid',
				   'Hawk', 'Hammerhead', 'Rocket Truck', 'Transport', 'Bomber', 'Helicopter', 
				   'Tank', 'Jeep', 'Artillery');

// Unit to use for ensuring all waves travel the same speed
$slow_unit = 'Artillery';

/*************** Program Setup ***************/
// Need this initalized so that we can use the database!
$won = new WarOfNations();

// Initalize the data load tracker
$won->setDataLoadId(DataLoadDAO::initNewLoad($won->db, 'FLY_TROOPS', 0));
DataLoadDAO::startLoad($won->db, $won->data_load_id);

// Get the Device ID to use (main account)
if($device_id == false) {
	$device_id = PgrmConfigDAO::getConfigProperty($won->db, 'value1', 'MAIN_DEVICE_ID');
}

// Authenticate
$auth_result = $won->Authenticate(false, $device_id, true); 
$last_session_time = time();
//$auth_result = json_decode(file_get_contents('auth_result.json'), true);

//print_r($auth_result['responses'][0]['return_value']['player_towns']);
//print_r($auth_result['responses'][0]['return_value']['player_town_reserves']);

// Get an instance of our game operations class
$game = $won->GetGameOperations();
$unit_map = $game::GetUnitMap();

$log_seq = 0;
$func_log_id = DataLoadLogDAO::startFunction($won->db, $won->data_load_id, 'FlyTroops', 'Main');

while(true) {
	// Check configuration to make sure we aren't supposed to stop - this is the kill switch
	$stop = PgrmConfigDAO::getConfigProperty($won->db, 'value1', 'STOP_TROOP_FLYER');
	if($stop == 'Y') {
		echo "Stop Signal Detected!\n";
		DataLoadLogDAO::logEvent2($won->db, $func_log_id, $log_seq++, 'INFO', 'Stop Signal Detected!');	
		break;
	}

	// If enough time has passed, start out with a new session
	if((time() - $last_session_time) > ($hours_between_sessions * 60 * 60)) {
		DataLoadLogDAO::logEvent2($won->db, $func_log_id, $log_seq++, 'INFO', "At least $hours_between_sessions hour(s) have passed. Starting new session.\n");	

		// Save the data load for later, we don't want a new one
		$data_load_id = $won->data_load_id;

		// Re-initialize the WarOfNations object
		unset($won);
		$won = new WarOfNations();

		// Set the data load back
		$won->setDataLoadId($data_load_id);

		// Reauthenticate to start a new session
		$auth_result = $won->Authenticate(false, $device_id, true); 
		$last_session_time = time();
	} else {
		// Otherwise, if this isn't the first run, call player sync to get updated information
		if(!$first_run) {
			$auth_result = $game->SyncPlayer();
		}
	}

	if(!$auth_result) {
		echo "Sync or Authentication Failed. Quitting.\n";
		DataLoadLogDAO::logEvent2($won->db, $func_log_id, $log_seq++, 'ERROR', 'Sync or Authentication Failed. Quitting.');	
		break;
	}

	// Setup/clear out our lists
	$bases = array();
	$base_distances = array();
	$base_pairs = array();
	$flights = array();

	/*************** Gather Base/Troop Info ***************/
	// Gather base information
	//  We'll use the coordinates later to calculate the distances between bases, 
	//  and the time last attacked to determine the other of bases to fly troops from.
	foreach($auth_result['responses'][0]['return_value']['player_towns'] as $base) {
		$bases[$base['id']] = array('name' => $base['display_name'],
									'hex_x' => $base['hex_x'],
									'hex_y' => $base['hex_y'],
									'time_last_attacked_ts' => $base['time_last_attacked_ts'],
									'units' => array());
	}

	// Sort the bases in priority order (will sort by last time attacked)
	uasort($bases, 'base_sort_last_attacked');

	// Fill in the units currently available at each base so that we can fly them
	foreach($auth_result['responses'][0]['return_value']['player_town_reserves'] as $reserve) {
		$bases[$reserve['town_id']]['units'] = array();
		foreach($reserve['units'] as $unit) {
			$bases[$reserve['town_id']]['units'][$unit_map[$unit['unit_id']]] = $unit['amount'];
		}
	}

	// Find the bases that currently have troops flying to/from them, and exclude these bases
	// Program can have unpredictable behavior if we try to fly trrops from these bases before all waves have landed
	// Save the arrival time so that we can use ithem later.
	foreach($auth_result['responses'][0]['return_value']['player_deployed_armies'] as $army) {
		// Make sure the target is one of our bases, or it's a returning army
		if($army['target_player_id'] == $won->auth->player_id || (array_key_exists('is_returning_home', $army) && $army['is_returning_home'] == 1)) {
			$time_to_destination = $army['time_to_destination_ts'];
			$arrival_time = $time_to_destination;
			
			// If the army is returning, only use the base ID of the sending base
			if((array_key_exists('is_returning_home', $army) && $army['is_returning_home'] == 1)) {
				$sending_base_id = $army['town_id'];
				$target_base_id = false;
			} else {
			// Otherwise, use both base IDs so that we don't do something stupid
				$sending_base_id = $army['town_id'];
				$target_base_id = $army['target_town_id'];
			}

			// Save this trip into our flights list for both bases and remove both bases from list to fly
			// Make sure we save the latest arrival/departure time for each base so that everything has a chance to land

			// We always have a sending base, so handle these first
			if(!array_key_exists($sending_base_id, $flights) || $flights[$sending_base_id] < $arrival_time)
				$flights[$sending_base_id] = $arrival_time;

			if(array_key_exists($sending_base_id, $bases))
				unset($bases[$sending_base_id]);

			// If the wave is not on its return trip, handle the target base as well
			if($target_base_id !== false) {
				if(!array_key_exists($target_base_id, $flights) || $flights[$target_base_id] < $arrival_time) 
					$flights[$target_base_id] = $arrival_time;

				if(array_key_exists($target_base_id, $bases))
					unset($bases[$target_base_id]);
			}
		}
	}

	// Sort our flight list in order of soonest to land
	asort($flights);

	print_r($flights);
	print_r($bases);

	DataLoadLogDAO::logEvent2($won->db, $func_log_id, $log_seq++, 'INFO', 'Base Arrival Times: ['.count($flights).']', 'Current time: '.time()."\n".print_r($flights, true));
	DataLoadLogDAO::logEvent2($won->db, $func_log_id, $log_seq++, 'INFO', 'Bases to Fly: ['.count($bases).']', print_r($bases, true));	

	// If we don't have a pair of bases available to fly to/from, wait for another base to be ready
	if(count($bases) < 2) {
		// Turn the flights list back into actual time deltas
		// We do this here instead of storing deltas above to account for delays in sending flights
		$current_ts = time();
		foreach($flights as $index => $arrival_ts) {
			$flights[$index] = $arrival_ts - $current_ts;
		}

		// Log the relative arrival times
		DataLoadLogDAO::logEvent2($won->db, $func_log_id, $log_seq++, 'INFO', 'Relative Base Flight Times: ['.count($flights).']', print_r($flights, true));

		// Wait for the next wave to land, add 10 seconds for safety
		$seconds_to_sleep = array_shift($flights) + 10;
		echo "Only ".count($bases)." base(s) available, waiting $seconds_to_sleep seconds for next wave to land\n\n";
		DataLoadLogDAO::logEvent2($won->db, $func_log_id, $log_seq++, 'INFO', "Only ".count($bases)." base(s) available, waiting $seconds_to_sleep seconds for next wave to land");	

		// This should never happen
		if($seconds_to_sleep < 0)
			die("Error: Time to Wait is less than 0.  Quitting.\n\n");

		sleep($seconds_to_sleep);

		// Have to reset first_run here or we're in trouble
		$first_run = false;

		// Go back to the top and try again
		continue;
	}

	/*************** Determine Base Pairs to fly between ***************/
	// Calculate distance between each base
	foreach($bases as $base_idA => $baseA) {
		$over_minimum = 0;
		foreach($bases as $base_idB => $baseB) {
			if($base_idA == $base_idB) continue;

			// This isn't exact but gets pretty close
			$distance = sqrt(pow(($baseA['hex_x'] + $baseA['hex_y']/2) - ($baseB['hex_x'] + $baseB['hex_y']/2), 2) + pow($baseA['hex_y'] - $baseB['hex_y'], 2));

			// Store the distance between this pair of bases
			$base_distances[$base_idA][$base_idB] = $distance;
		}

		// Sort the flight options for this base from shortest to longest distance
		asort($base_distances[$base_idA]);
	}

	print_r($base_distances);
	DataLoadLogDAO::logEvent2($won->db, $func_log_id, $log_seq++, 'INFO', 'Base Distances: ['.count($base_distances).']', print_r($base_distances, true));	

	// Bases will be paired off starting with the base that has been attacked most recently
	//  This is done just in case we have an odd number of flight paths currently available
	//  The base that hasn't been attacked in the longest time will be left sitting
	//  Decided this was less risky that potentially sending 20 waves to the same base

	// Selected flight path will be the closest base over the preferred distance
	//  We do this to preserve the longest flight paths (ie. to Homebase) are saved for bases
	//  that lack other reasonable options

	// Need this to decide when we're done pairing bases (so that we don't "pair" an odd number)
	$odd_number_fix = count($bases) % 2;
	foreach($base_distances as $base_idA => $base_distance_list) {
		// If there is already a match for this base, move on to the next one
		if(array_key_exists($base_idA, $base_pairs)) continue;

		// Remove any bases that have already been paired from our list
		$base_distance_list = array_diff_key($base_distance_list, $base_pairs);

		// Find the second to last item so that we can reference it later (we don't want "over_minimum");
		$base_ids = array_keys($base_distance_list);
		$lastBaseId = array_pop($base_ids);

		foreach($base_distance_list as $base_idB => $base_distance) {
			// If this base pair is over the prefered distance, or is the last base in the list, set this as a pair
			// Don't get greedy, settle for the shortest flight available over the preferred distance
			if($base_distance > $preferred_distance || $base_idB == $lastBaseId) {
				$base_pairs[$base_idA] = $base_idB;
				$base_pairs[$base_idB] = $base_idA;
				break;
			}
		}

		// Stop adding base pairs after we have the right number
		if(count($base_pairs) == count($bases) - $odd_number_fix) break;
	}

	print_r($base_pairs);
	DataLoadLogDAO::logEvent2($won->db, $func_log_id, $log_seq++, 'INFO', 'Base Pairs: ['.count($base_pairs).']', print_r($base_pairs, true));	

	/***************** Actually Fly Troops ********************/

	// Iterate through bases, in sorted order by most recently attacked
	// Fly troops, starting with most expensive, each wave including 1 slow unit (artillery) for speed
	// Keep list of each time to destination so that we can determine how long to wait until
	//  waking up to fly more troops again
	foreach($bases as $base_id => $base) {
		$wave_count = 0;

		// If this base doesn't have a pairing, skip to the next one
		if(!array_key_exists($base_id, $base_pairs)) 
			continue;

		echo "\n\n=================================\n";
		print_r($base);
		// Create up to max_waves waves at this base
		while($wave_count < $max_waves && array_sum($base['units']) > 0) {
			$wave = array();
			$total_units = 0;

			// Increment the count of waves sent from this base
			$wave_count++;

			// Build a wave, starting with the slow unit (if available)
			if(array_key_exists($slow_unit, $base['units']) && $base['units'][$slow_unit] > 0) {
				$wave[$slow_unit] = 1;
				$base['units'][$slow_unit] -= 1;
				$total_units += 1;
			}

			// Loop through our units in designated flight order
			foreach($fly_order as $unit) {
				// Figure out how much space we have left on the wave
				$space_remaining = $max_army_size - $total_units;

				// If we're out of space, stop building this wave
				if($space_remaining == 0) break;

				// If this unit is available in this base, add it to the ave
				if(array_key_exists($unit, $base['units']) && $base['units'][$unit] > 0) {
					// Determine how many units to send: the lesser of the available units or space remaining in wave
					$units_sent = min($base['units'][$unit], $space_remaining);

					// Add these units to the wave
					if(array_key_exists($unit, $wave))
						$wave[$unit] += $units_sent;
					else
						$wave[$unit] = $units_sent;

					// Decrement available units in the base
					$base['units'][$unit] -= $units_sent;

					// Increment the total units sent in this wave
					$total_units += $units_sent;
				}
			}

			echo "Wave #$wave_count from {$base['name']}\n";
			print_r($wave);
			DataLoadLogDAO::logEvent2($won->db, $func_log_id, $log_seq++, 'INFO', "Wave #$wave_count from {$base['name']}", print_r($wave, true));	

			// Look up which base to send these units to
			$target_base_id = $base_pairs[$base_id];

			// Send the units 
			$army = $game->SendArmyToTown($base_id, $target_base_id, $wave);

			// If we failed to send the wave, stop, and attempt to figure out why
			if(!$army) {
				die("Error Detected. Quitting.\n\n");	
			}

			// Calculate our arrival time, and save the latest arrival to each base
			$time_to_destination = $army['time_to_destination_ts'];
			$arrival_time = $time_to_destination;

			// If the sending base doesn't already have a flight leaving it or this flight is arriving sooner, save it
			if(!array_key_exists($base_id, $flights) || $flights[$base_id] < $arrival_time) {
				$flights[$base_id] = $arrival_time;
			}

			// If this base doesn't already have a flight going to it or this flight is arriving sooner, save it
			if(!array_key_exists($target_base_id, $flights) || $flights[$target_base_id] < $arrival_time) {
				$flights[$target_base_id] = $arrival_time;
			}

			// Wait for a little bit
			sleep($seconds_between_waves);
		}
		// Log an operation complete after each base flown
		DataLoadDAO::operationComplete($won->db, $won->data_load_id);

		// Pause shortly
		sleep($seconds_between_bases);
	}

	// Sort list of flight times so that we can easily find the shortest
	asort($flights);

	// Turn the flights list back into actual time deltas
	// We do this here instead of storing them above to account for delays in sending flights
	$current_ts = time();
	foreach($flights as $index => $arrival_ts) {
		$flights[$index] = $arrival_ts - $current_ts;
	}

	print_r($flights);

	DataLoadLogDAO::logEvent2($won->db, $func_log_id, $log_seq++, 'INFO', 'Flights After Sending: ['.count($flights).']', print_r($flights, true));	

	$seconds_to_sleep = array_shift($flights) + 10;
	echo "Waiting $seconds_to_sleep seconds for next wave to land\n\n";
	DataLoadLogDAO::logEvent2($won->db, $func_log_id, $log_seq++, 'INFO', "Waiting $seconds_to_sleep seconds for next wave to land");	

	// Wait for the next wave to land before we wake back up
	sleep($seconds_to_sleep);

	// Not our first run anymore!
	$first_run = false;
}

DataLoadLogDAO::completeFunction($won->db, $func_log_id, 'Stop Signal Detected.  Stopping.');
DataLoadDAO::loadComplete($won->db, $won->data_load_id);
?>