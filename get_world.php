<http>
	<head>
		<title>War of Nations World Mapper</title>
	</head>
<body>
<?php

require_once('WarOfNations.class.php');

$debug = false;

/*
=======================================================
============== Authenticate the Phone =================
=======================================================
*/

$won = new WarOfNations(0);
//$won->CreateNewPlayer();
$won->Authenticate();

echo "<br/>\r\n<br/>\r\n<br/>\r\n<br/>\r\n<br/>\r\n===============================================================================<br/>\r\n<br/>\r\n<br/>\r\n<br/>\r\n<br/>\r\n";

/*
=======================================================
===================== Do Stuff ========================
=======================================================
*/

$inner_radius = 0;
$radius = 1500;
$r2 = pow($radius, 2);
$cur_x = 0;
$cur_y = 0;
$cur_quadrant = 0; // 0 = top left, 1 = top right, 2 = bottom right, 3 = bottom left
$count = 0;
$status = true;
// x^2 + y^2 = r^2
// y = sqrt(r^2 - x^2)
// x = sqrt(r^2 - y^2)

//ob_end_flush();



//try {
while($cur_quadrant <= 1) {
	while($cur_y < $radius) {
		while(pow($cur_x, 2) + pow($cur_y, 2) <= $r2) {
			switch($cur_quadrant) {
				case 0:
					$tx = -1 * $cur_x;
					$ty = -1 * $cur_y;
					break;
				case 1:
					$tx = $cur_x;
					$ty = -1 * $cur_y;
					break;
				case 2:
					$tx = $cur_x;
					$ty = $cur_y;
					break;
				case 3:
					$tx = -1 * $cur_x;
					$ty = $cur_y;
					break;
			}
		
			$count++;
			echo "$tx, $ty<br/>\r\n";
			$status = $won->GetWorldMap($tx, $ty, 100, 100);
			if(!$status) {
				echo "Error: Failed to load map for $tx, $ty<br/>\r\n";
				break;
			}
			
			$cur_x += 95;
			usleep(1000000); // Wait a second between calls
		}
		if(!$status) break;
		$cur_y += 95;
		$cur_x = 0;
	}
	if(!$status) break;
	$cur_quadrant++;
	$cur_y = 0;
}

echo "$count calls to map service.";

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
//$won->GetWorldMap(-475, -380, 100, 100);

# CODE THAT NEEDS IMMEDIATE FLUSHING

//ob_start();




?>
</body>
</html>