<?php
/*
WARNING: This will handle forward all requests other than specifically handles versions to game servers 

Need to enable PHP on extensionless files in order for this to work
*/

header('Content-type: application/json; charset=utf-8;');
header('Content-Encoding: gzip');

$url = 'http://gcand.gree-apps.net'.$_SERVER['REQUEST_URI'];
$incoming_headers = getallheaders();
$raw_post_data = file_get_contents('php://input');

$postdata = json_decode($raw_post_data, true);

//$raw_post_data = str_replace('hc_NA_20151005_57853', 'hc_NA_20160121_63983', $raw_post_data);

// Intercept the Dungeon Request
if($_GET['svc'] == 'BatchController.call' && $postdata[1][0]['service'] == 'dungeons.dungeons' && $postdata[1][0]['method'] == 'unlock_dungeon_portal')
{
	// Get our campaign response from the file
	$raw_string = file_get_contents('campaign_response_merged.txt');

	//echo print_r(json_decode($raw_string, true), true);

	// Gzip encode the string
	$gzip_string = gzencode($raw_string, 9);

	// Respond with the string
	die($gzip_string);
} else {
	// Set the URL
	$ch = curl_init($url);

	// Collapse incoming headers
	$headers = array();
	foreach($incoming_headers as $key=>$value) {
		$headers[] = "$key: $value";
	}

	// Pass through the headers
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 

	// Pass through the POST data
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
	curl_setopt($ch, CURLOPT_POSTFIELDS, $raw_post_data);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	// Record our pass through URL
	file_put_contents('/var/log/won_response.log', "\nURL: $url\n", FILE_APPEND);
	file_put_contents('/var/log/won_response.log', $raw_post_data."\n", FILE_APPEND);

	// Execute the request
	$response_string = curl_exec($ch);

	if(!$response_string){
		$curl_error = curl_error($ch);
		file_put_contents('/var/log/won_response.log', "Error: $curl_error", FILE_APPEND);
	}

	// cleans up the curl request
	curl_close($ch);

	// Pass the response back to the client
	die($response_string);
}



?>