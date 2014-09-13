<?php
require_once('classes/WarOfNations.class.php');

$debug = false;

/*
=======================================================
============== Authenticate the Phone =================
=======================================================
*/

$won = new WarOfNations(0);
//$won->CreateNewPlayer();
$won->Authenticate();

echo "<br/><br/><br/><br/><br/>===============================================================================<br/><br/><br/><br/><br/>";

/*
=======================================================
===================== Do Stuff ========================
=======================================================
*/

echo "Getting Player Leaderboards 1 - 10000<br/>\r\n";
$start = 0;
while($start < 10000) {
	echo "Getting Player Leaderboard $start - ".($start + 50)."<br/>\r\n";
	$won->GetLeaderboard(1, $start);
	$start += 50;
}
echo "Done!<br/>\r\n";

echo "Getting Alliance Leaderboards 1 - 1000<br/>\r\n";
$start = 0;
while($start < 1000) {
	echo "Getting Alliance Leaderboard $start - ".($start + 50)."<br/>\r\n";
	$won->GetLeaderboard(2, $start);
	$start += 50;
}
echo "Done!<br/>\r\n";

?>