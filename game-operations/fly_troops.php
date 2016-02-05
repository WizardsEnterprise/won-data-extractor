<?php
require_once(dirname(__FILE__) . '/../classes/WarOfNations2.class.php');
require_once(dirname(__FILE__) . '/../classes/data/DataLoad.class.php');

/*************** Helper Functions ***************/
function base_pair_sort($a, $b) {
    if ($a['over_minimum'] == $b['over_minimum']) {
        return 0;
    }
    return ($a['over_minimum'] < $b['over_minimum']) ? -1 : 1;
}

// Sort the base list in order of most recently attacked
function base_priority_sort($a, $b) {
    if ($a['time_last_attacked_ts'] == $b['time_last_attacked_ts']) {
        return 0;
    }
    return ($a['time_last_attacked_ts'] > $b['time_last_attacked_ts']) ? -1 : 1;
}

/*************** Initialize Settings ***************/
$first_run = true;
$max_army_size = 1900; // Keep this the same across all bases
$max_waves = 10;
$preferred_distance = 700; // Use this to balance flight time

$seconds_between_waves = 2;
$seconds_between_bases = 5;

// Order to fly troops out of base (most valuable first)
$fly_order = array('Railgun Tank', 'Titan', 'Hellfire', 'Centurion', 'Hailstorm', 'Arachnid',
				   'Hawk', 'Hammerhead', 'Rocket Truck', 'Transport', 'Bomber', 'Helicopter', 
				   'Tank', 'Jeep', 'Artillery');

$slow_unit = 'Artillery';

/*************** Program Setup ***************/
// Need this initalized so that we can use the database!
$won = new WarOfNations();

// Initalize the data load tracker
$won->setDataLoadId(DataLoadDAO::initNewLoad($won->db, 'FLY_TROOPS', 0));
DataLoadDAO::startLoad($won->db, $won->data_load_id);

// Authenticate
$auth_result = $won->Authenticate(false, 5, true); 
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

	// Setup our lists
	$bases = array();
	$base_distances = array();
	$base_pairs = array();
	$arrivals = array();

	// If this isn't the first run, call player sync to get updated information
	if(!$first_run) {
		$auth_result = $game->SyncPlayer();
	}

	/*************** Gather Base/Troop Info ***************/
	// Setup base information
	foreach($auth_result['responses'][0]['return_value']['player_towns'] as $base) {
		$bases[$base['id']] = array('name' => $base['display_name'],
									'hex_x' => $base['hex_x'],
									'hex_y' => $base['hex_y'],
									'time_last_attacked_ts' => $base['time_last_attacked_ts'],
									'units' => array());
	}

	// Sort the bases in priority order (will sort by last time attacked)
	uasort($bases, 'base_priority_sort');

	// Add available troop information to bases
	foreach($auth_result['responses'][0]['return_value']['player_town_reserves'] as $reserve) {
		$bases[$reserve['town_id']]['units'] = array();
		foreach($reserve['units'] as $unit) {
			$bases[$reserve['town_id']]['units'][$unit_map[$unit['unit_id']]] = $unit['amount'];
		}
	}

	// Exclude bases that we have troops flying to currently
	foreach($auth_result['responses'][0]['return_value']['player_deployed_armies'] as $army) {
		// Make sure the target is one of our bases, or it's a returning army
		if($army['target_player_id'] == $won->auth->player_id || $army['is_returning_home'] == 1) {
			$time_to_destination = $army['time_to_destination_ts'];
			$arrival_time = $time_to_destination;

			// Safe this arrival into our arrivals list
			$arrivals[$army['town_id']] = $arrival_time;

			// Exclude this base from our flight program
			if(array_key_exists($army['town_id'], $bases))
				unset($bases[$army['town_id']]);
		}
	}

	print_r($arrivals);
	print_r($bases);

	DataLoadLogDAO::logEvent2($won->db, $func_log_id, $log_seq++, 'INFO', 'Base Arrival Times: ['.count($arrivals).']', print_r($arrivals, true));
	DataLoadLogDAO::logEvent2($won->db, $func_log_id, $log_seq++, 'INFO', 'Bases to Fly: ['.count($bases).']', print_r($bases, true));	

	// If we don't have a pair of bases available to fly to/from, wait for another base to be ready
	if(count($bases) < 2) {
		// Turn the arrivals list back into actual time deltas
		// We do this here instead of storing deltas above to account for delays in sending flights
		$current_ts = time();
		foreach($arrivals as $index => $arrival_ts) {
			$arrivals[$index] = $arrival_ts - $current_ts;
		}

		// Wait for the next wave to land
		$seconds_to_sleep = array_shift($arrivals) + 10;
		echo "Only ".count($bases)." base(s) available, waiting $seconds_to_sleep seconds for next wave to land";
		DataLoadLogDAO::logEvent2($won->db, $func_log_id, $log_seq++, 'INFO', "Only ".count($bases)." base(s) available, waiting $seconds_to_sleep seconds for next wave to land");	

		if($seconds_to_sleep < 0)
			die("Error: Time to Wait is less than 0.  Quitting.");

		usleep($seconds_to_sleep * 1000000);

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

			$base_distances[$base_idA][$base_idB] = $distance;
			//$over_minimum += ($distance >= $preferred_distance) ? 1 : 0;
		}
		// Sort the flight options from shortest to longest distance
		asort($base_distances[$base_idA]);

		// Save the value for number of bases over the preferred/minimum distance
		//$base_distances[$base_idA]['over_minimum'] = $over_minimum;
	}

	//print_r($base_pairs);

	// Sort the base pairs in order of how many options we have for flying
	//uasort($base_distances, 'base_pair_sort');

	print_r($base_distances);
	DataLoadLogDAO::logEvent2($won->db, $func_log_id, $log_seq++, 'INFO', 'Base Distances: ['.count($base_distances).']', print_r($base_distances, true));	

	// Bases will be paired off starting with the base with the fewer options
	// Selected flight path will be the closest base over the preferred distance

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

	// Iterate through bases
	// Fly troops, starting with most expensive, each wave including 1 artillery for speed
	// Keep list of time to destination/calculate arrival times (microtime)

	foreach($bases as $base_id => $base) {
		$wave_count = 0;

		// If this base doesn't have a pairing, skip to the next one
		if(!array_key_exists($base_id, $base_pairs)) 
			continue;

		echo "\n\n=================================\n";
		print_r($base);
		// Create up to max_waves waves at this base
		while($wave_count <= $max_waves && array_sum($base['units']) > 0) {
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
					$base['units'][$unit] -= $units_sent;
					$total_units += $units_sent;
				}
			}

			echo "Wave #$wave_count from {$base['name']}\n";
			print_r($wave);
			DataLoadLogDAO::logEvent2($won->db, $func_log_id, $log_seq++, 'INFO', "Wave #$wave_count from {$base['name']}", print_r($wave, true));	

			$target_base_id = $base_pairs[$base_id];
			$army = $game->SendArmyToTown($base_id, $target_base_id, $wave);

			// If we failed to send the wave, attempt to figure out why
			if(!$army) {
				die("Error\n");	
			}

			// Calculate our arrival time, and save the latest arrival to each base
			$time_to_destination = $army['time_to_destination_ts'];
			$arrival_time = $time_to_destination;

			// If this base doesn't already have a flight going to it or this flight is arriving sooner, save it
			if(!array_key_exists($target_base_id, $arrivals) || $arrivals[$target_base_id] < $arrival_time) {
				$arrivals[$target_base_id] = $arrival_time;
			}

			usleep($seconds_between_waves * 1000000);
		}
		usleep($seconds_between_bases * 1000000);
	}

	// Sort list of arrival times
	// Sleep for the shortest required time
	// Sort our arrival list
	asort($arrivals);

	// Turn the arrivals list back into actual time deltas
	// We do this here instead of storing them above to account for delays in sending flights
	$current_ts = time();
	foreach($arrivals as $index => $arrival_ts) {
		$arrivals[$index] = $arrival_ts - $current_ts;
	}

	print_r($arrivals);

	DataLoadLogDAO::logEvent2($won->db, $func_log_id, $log_seq++, 'INFO', 'Arrivals After Sending: ['.count($arrivals).']', print_r($arrivals, true));	

	$seconds_to_sleep = array_shift($arrivals) + 10;
	echo "Waiting $seconds_to_sleep seconds for next wave to land";
	DataLoadLogDAO::logEvent2($won->db, $func_log_id, $log_seq++, 'INFO', "Waiting $seconds_to_sleep seconds for next wave to land");	

	usleep($seconds_to_sleep * 1000000);

	// Not our first run anymore!
	$first_run = false;
}

DataLoadLogDAO::completeFunction($won->db, $func_log_id, 'Stop Signal Detected.  Stopping.');
DataLoadDAO::loadComplete($won->db, $won->data_load_id);
?>