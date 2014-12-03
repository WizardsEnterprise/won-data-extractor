<?php
require_once('../classes/WarOfNations.class.php');
require_once('../classes/data/DataLoad.class.php');

$debug = false;

$top_x_players = 10000;
$top_x_alliances = 1000;
$interval = 50;

/*
=======================================================
============== Authenticate the Phone =================
=======================================================
*/


$won = new WarOfNations(0);

$won->setDataLoadId(DataLoadDAO::initNewLoad($won->db, 'PLAYER_LEADERBOARDS', $top_x_players/$interval));
DataLoadDAO::startLoad($won->db, $won->data_load_id);

$won->Authenticate();

/*
=======================================================
===================== Do Stuff ========================
=======================================================
*/

echo "Getting Top $top_x_players Player Leaderboards\r\n";
$start = 0;
while($start < $top_x_players) {
	echo "Getting Player Leaderboard $start - ".($start + $interval)."<br/>\r\n";
	DataLoadDAO::operationComplete($won->db, $won->data_load_id);
	$won->GetLeaderboard(1, $start);
	$start += $interval;
}
echo "Done!<br/>\r\n";

DataLoadDAO::loadComplete($won->db, $won->data_load_id);

$won->setDataLoadId(DataLoadDAO::initNewLoad($won->db, 'ALLIANCE_LEADERBOARDS', $top_x_alliances/$interval));
DataLoadDAO::startLoad($won->db, $won->data_load_id);

echo "\r\nGetting Top $top_x_alliances Alliance Leaderboards\r\n";
$start = 0;
while($start < $top_x_alliances) {
	echo "Getting Alliance Leaderboard $start - ".($start + $interval)."<br/>\r\n";
	DataLoadDAO::operationComplete($won->db, $won->data_load_id);
	$won->GetLeaderboard(2, $start);
	$start += $interval;
}
echo "Done!<br/>\r\n";

DataLoadDAO::loadComplete($won->db, $won->data_load_id);

?>