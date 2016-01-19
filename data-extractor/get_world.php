<?php
require_once(dirname(__FILE__) . '/../classes/WarOfNations2.class.php');
require_once(dirname(__FILE__) . '/../classes/data/DataLoad.class.php');

$radius = 1472;
$r2 = pow($radius, 2);
$interval = 100; // Normal: 95
$scan_size = 100; // Normal: 100
$r2_offset = pow($radius + $interval, 2) - $r2;

// If we want to get a scan of an area around a base, change these values
$inner_x = 0;
$inner_y = 0;

$cur_x = 0;
$cur_y = 0;
$cur_quadrant = 0; // 0 = top left, 1 = top right, 2 = bottom right, 3 = bottom left
$count = 0;
$status = true;

// Change this to exclude any areas of the map that normally result in "out of bounds" errors.
// Recommended setting for detailed scanning: 1400
$min_radius = 0;

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

$won->setDataLoadId(DataLoadDAO::initNewLoad($won->db, 'WORLD_MAP', 0, "World $world"));
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

$start = microtime(true);

// Break the world into 4 quadrants
//  0 => -, -
//  1 => +, -
//  2 => +, +
//  3 => -, +
while($cur_quadrant <= 3) {
	// This loop controls our position on the Y axis
	//  We'll process the map in horizontal rows, starting from the center and going outward
	while(sqrt(pow($cur_y, 2) - pow($cur_y/2, 2)) < $radius + ($cur_quadrant > 1 ? 0 : $interval)) {
		// This loop controls our position on the X axis
		//  Make sure we remain inside the circle of the map, when we reach the edge, end this loop
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
			
			echo "Getting $tx, $ty\r\n";

			// Use this to get only outside of the map if wanted.
			if(sqrt(pow($cur_x, 2) + pow($cur_y, 2) - pow($cur_y/2, 2)) >= $min_radius){
				$count++;
				$status = $won->GetWorldMap($tx, $ty, $scan_size, $scan_size);

				if(!$status) {
					echo "Error: Failed to load map for $tx, $ty\r\n";
					break;
				}
				DataLoadDAO::operationComplete($won->db, $won->data_load_id);
				usleep(500000); // Wait half a second between calls - just because
			}

			$cur_x += $interval;
		}
		// We're done with the X axis for this row, Increment Y and reset X to 0
		if(!$status) break;
		$cur_y += $interval;
		$cur_x = 0;
	}

	// We're done with this quadant, Increment the quadrant and reset Y to 0
	if(!$status) break;
	$cur_quadrant++;
	$cur_y = 0;
}

$end = microtime(true);

echo "$count calls to map service in ".($end - $start)." seconds.\r\n";

// Finish up the map extraction by setting resource patch counts and archiving bases
$won->map->CompleteWorldMapExtraction();

DataLoadDAO::loadComplete($won->db, $won->data_load_id);

?>