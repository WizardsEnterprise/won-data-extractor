<?php
function get_resource($donation_array, $resource_id) {
	foreach($donation_array as $donation) {
		if($donation['id'] == $resource_id)
			return $donation['quantity'];
	}
	return 0;
}

$response = '{'.file_get_contents('alliance_json_20141015_1.txt').'}';

//echo $response.'<br/><br/>';

$data = json_decode($response, true);

$members = $data['responses'][0]['return_value']['player_guild']['members'];

//echo "<pre>";
//print_r($members);
//echo "</pre>";

foreach($members as $member) {
	echo $member['player_name'].', ';
	echo get_resource($member['donations'], 1).', ';
	echo get_resource($member['donations'], 2).', ';
	echo get_resource($member['donations'], 3).', ';
	echo get_resource($member['donations'], 4).', ';
	echo get_resource($member['donations'], 5).', ';
	echo get_resource($member['donations'], 6)."\r\n";
	
}

?>