<?php
require_once('../classes/WarOfNations2.class.php');
require_once('../classes/data/DataLoad.class.php');

date_default_timezone_set('America/Chicago');

$to      = '2488604846@vtext.com';
$subject = 'No Subject';
$message = 'ringer on | Comm Training Starting!';
$headers = 'From: corndog822@gmail.com' . "\r\n" .
    'X-Mailer: PHP/' . phpversion();

$flag = mail($to, $subject, $message, $headers);

if(!$flag)
	echo "E-Mail Failed\r\n";
else
	echo "E-Mail Sent\r\n";

//print_r(timezone_identifiers_list());
//die();

// Initialize Settings
$training_base_id = 2;
$npc_id = '101013100928452';

// Comms to train settings
$comm_train_min_lvl = 50;
$comm_train_max_lvl = 69;

// Comm 207: Hellborn
// Comm 208: Iron Wing
// Comm 209: Fury Forge
// Comm 210: Dark Wolf
// Comm 211: The Guardian
$comms_to_train = array(207, 208, 209, 210, 211);

// Comms to jeep settings
$comm_jeep_max_lvl = 50;
$num_jeeps = 8;
$jeep_comm_min_energy = 1;

// Other operational settings
$minutes_before_jeep = 6;
$seconds_pause_between_jeeps = 10;
$seconds_pause_before_recall = 15;
$seconds_pause_between_rounds = 10;

// Do work!
$won = new WarOfNations();

$won->setDataLoadId(DataLoadDAO::initNewLoad($won->db, 'TRAIN_COMMANDERS', 0));
DataLoadDAO::startLoad($won->db, $won->data_load_id);

// Authenticate ourselves
//$auth_result = json_decode(file_get_contents('auth_result.json'), true);
$auth_result = $won->Authenticate(true); 

//print_r($auth_result['responses'][0]['return_value']['player_commanders']);

// Get an instance of our game operations class
$game = $won->GetGameOperations();

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

	if(count($training_commanders) == 0 || count($jeeping_commanders) < $num_jeeps) {
		echo date_format(new DateTime(), 'H:i:s')." | Out of commanders.  Qutting.\r\n";
		break;
	}

	//print_r($training_commanders);
	//print_r($jeeping_commanders);

	//die();

	// Select the commander to train
	$current_training_comm = $training_commanders[0];
	echo date_format(new DateTime(), 'H:i:s')." | Current training comm: {$current_training_comm['id']}\r\n";
	$cap_army = $game->SendCapture($npc_id, 1, 1, $current_training_comm['id']);

	if(!$cap_army) {

		$to      = '2488604846@vtext.com';
		$subject = 'No Subject';
		$message = 'ringer on | Capture Failed!';
		$headers = 'From: corndog822@gmail.com' . "\r\n" .
		    'X-Mailer: PHP/' . phpversion();

		mail($to, $subject, $message, $headers);

		die(date_format(new DateTime(), 'H:i:s')." | Capture Failed! Quitting.\r\n");
	}

	// Wait until our occupation has started
	usleep($cap_army['delta_time_to_destination'] * rand(1000000, 1200000));
	echo date_format(new DateTime(), 'H:i:s')." | Occupation has begun\r\n";

	// Kill some time before we start jeeping
	usleep($minutes_before_jeep * 60 * rand(900000, 1100000));

	echo date_format(new DateTime(), 'H:i:s')." | Starting Jeeps...\r\n";
	for($i = 1; $i <= $num_jeeps; $i++) {
		// Pause so it's not obvious that we're not really playing
		usleep($seconds_pause_between_jeeps * rand(900000, 1100000));

		$current_jeeping_comm = $jeeping_commanders[$i-1];
		echo date_format(new DateTime(), 'H:i:s')." | Jeeping comm #$i: {$current_jeeping_comm['id']}\r\n";
		$jeep_army = $game->SendAttack($npc_id, 1, 1, $current_jeeping_comm['id']);

		if(!$jeep_army) 
			echo "Jeep Failed!\r\n";
	}

	// Wait until our last jeep has landed
	usleep($jeep_army['delta_time_to_destination'] * rand(1000000, 1200000));

	// Now wait a little while longer before we recall just to seem human
	usleep($seconds_pause_before_recall * rand(900000, 1100000));
	$recall_army = $game->RecallArmy($cap_army['id']);

	if(!$recall_army) {
		// TODO: If recall fails, sound an alarm somehow/text me, etc
		$to      = '2488604846@vtext.com';
		$subject = 'No Subject';
		$message = 'ringer on | Army Recall Failed!';
		$headers = 'From: corndog822@gmail.com' . "\r\n" .
		    'X-Mailer: PHP/' . phpversion();

		mail($to, $subject, $message, $headers);

		die(date_format(new DateTime(), 'H:i:s')." | Recall Failed! Quitting.\r\n");
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