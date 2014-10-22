<http>
	<head>
		<title>War of Nations World Mapper</title>
	</head>
<body>
<?php

require_once('classes/WarOfNations.class.php');
require_once('classes/data/DataLoad.class.php');

/*
=======================================================
============== Authenticate the Phone =================
=======================================================
*/

$won = new WarOfNations(0);

$won->setDataLoadId(DataLoadDAO::initNewLoad($won->db, 'WORLD_MAP'));
DataLoadDAO::startLoad($won->db, $won->data_load_id);

//$won->CreateNewPlayer();
$won->Authenticate();

echo "<br/>\r\n<br/>\r\n<br/>\r\n<br/>\r\n<br/>\r\n===============================================================================<br/>\r\n<br/>\r\n<br/>\r\n<br/>\r\n<br/>\r\n";

/*
=======================================================
===================== Do Stuff ========================
=======================================================
*/

$radius = 1472;
//$radius = 200;
$r2 = pow($radius, 2);
$interval = 95;
$r2_offset = pow($radius + $interval, 2) - $r2;

// If we want to get a scan of an area around a base, change these values
$inner_x = 0;
$inner_y = 0;

$cur_x = 0;
$cur_y = 0;
$cur_quadrant = 0; // 0 = top left, 1 = top right, 2 = bottom right, 3 = bottom left
$count = 0;
$status = true;
// x^2 + y^2 = r^2
// y = sqrt(r^2 - x^2)
// x = sqrt(r^2 - y^2)

//ob_end_flush();

$start = microtime(true);

//try {
while($cur_quadrant <= 3) {
	while(sqrt(pow($cur_y, 2) - pow($cur_y/2, 2)) < $radius + ($cur_quadrant > 1 ? 0 : $interval)) {
		while(pow($cur_x, 2) + pow($cur_y, 2) - pow($cur_y/2, 2) <= $r2 + ($cur_quadrant == 2 ? 0 : $r2_offset)) {
			switch($cur_quadrant) {
				case 0:
					$tx = $inner_x + (-1 * $cur_x);
					$ty = $inner_y + (-1 * $cur_y);
					break;
				case 1:
					$tx = $inner_x + $cur_x;
					$ty = $inner_y + (-1 * $cur_y);
					break;
				case 2:
					$tx = $inner_x + $cur_x;
					$ty = $inner_y + $cur_y;
					break;
				case 3:
					$tx = $inner_x + (-1 * $cur_x);
					$ty = $inner_y + $cur_y;
					break;
			}
			
			$count++;
			echo "$tx, $ty<br/>\r\n";
			$status = $won->GetWorldMap($tx, $ty, 100, 100);
			if(!$status) {
				echo "Error: Failed to load map for $tx, $ty<br/>\r\n";
				break;
			}
			
			$cur_x += $interval;
			usleep(500000); // Wait half a second between calls - just because
		}
		if(!$status) break;
		$cur_y += $interval;
		$cur_x = 0;
	}
	if(!$status) break;
	$cur_quadrant++;
	$cur_y = 0;
}

$end = microtime(true);

echo "$count calls to map service in ".($end - $start)." seconds.<br/>\r\n";

DataLoadDAO::loadComplete($won->db, $won->data_load_id);

/*
0, -380
-95, -380
-190, -380
-285, -380
*/

/*$won->GetWorldMap(0, -380, 100, 100);
$won->GetWorldMap(-95, -380, 100, 100);
$won->GetWorldMap(-190, -380, 100, 100);
$won->GetWorldMap(-285, -380, 100, 100);*/
//$won->GetWorldMap(-380, -380, 100, 100);
//$won->GetWorldMap(-1520, -380, 100, 100);

# CODE THAT NEEDS IMMEDIATE FLUSHING

//ob_start();




?>
</body>
</html>