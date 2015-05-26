<?php
require_once('../classes/WarOfNations2.class.php');
require_once('../classes/data/DataLoad.class.php');

$radius = 1472;
$r2 = pow($radius, 2);
$interval = 95; // Normal: 95
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
// Recommended setting for detailed scanning: 1350
$min_radius = 0;


/*
=======================================================
============== Authenticate the Phone =================
=======================================================
*/

$won = new WarOfNations();

$won->setDataLoadId(DataLoadDAO::initNewLoad($won->db, 'WORLD_MAP', 0));
DataLoadDAO::startLoad($won->db, $won->data_load_id);

$won->Authenticate();

/*
=======================================================
===================== Do Stuff ========================
=======================================================
*/

$start = microtime(true);

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

?>