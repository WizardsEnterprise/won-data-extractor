<?php
require_once(dirname(__FILE__) . '/../classes/WarOfNations2.class.php');
require_once(dirname(__FILE__) . '/../classes/data/DataLoad.class.php');

$debug = false;

$top_x_players = 10000;
$top_x_alliances = 1000;
$interval = 50;

if(count($argv) == 2) {
	$world = $argv[1];
	echo "Got Command Line Argument for World [$world]\r\n";
} else {
	$world = 13;
	echo "No Command Line Argument Found.  Using Default World [$world]\r\n";
}

/*
=======================================================
============== Authenticate the Phone =================
=======================================================
*/

$won = new WarOfNations();

$won->setDataLoadId(DataLoadDAO::initNewLoad($won->db, 'PLAYER_LEADERBOARDS', $top_x_players/$interval, "World $world"));
DataLoadDAO::startLoad($won->db, $won->data_load_id);

$won->Authenticate();

// Check if we're in the correct world
if($won->auth->world_id != $world) {
	// Switch world if not
	if($won->SwitchWorld($world) === false) {
		// If we didn't switch successfully, join a new world
		$won->JoinNewWorld($world);
	}
}

/*
=======================================================
===================== Do Stuff ========================
=======================================================
*/

echo "Getting Top $top_x_players Player Leaderboards\r\n";
$start = 0;
while($start < $top_x_players) {
	echo "Getting Player Leaderboard $start - ".($start + $interval)."\r\n";
	DataLoadDAO::operationComplete($won->db, $won->data_load_id);
	$won->GetLeaderboard(1, $start);
	$start += $interval;
}
echo "Done!\r\n";

DataLoadDAO::loadComplete($won->db, $won->data_load_id);

$won->setDataLoadId(DataLoadDAO::initNewLoad($won->db, 'ALLIANCE_LEADERBOARDS', $top_x_alliances/$interval, "World $world"));
DataLoadDAO::startLoad($won->db, $won->data_load_id);

echo "\r\nGetting Top $top_x_alliances Alliance Leaderboards\r\n";
$start = 0;
while($start < $top_x_alliances) {
	echo "Getting Alliance Leaderboard $start - ".($start + $interval)."\r\n";
	DataLoadDAO::operationComplete($won->db, $won->data_load_id);
	$won->GetLeaderboard(2, $start);
	$start += $interval;
}
echo "Done!\r\n";

DataLoadDAO::loadComplete($won->db, $won->data_load_id);

?>