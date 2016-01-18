<?php
require_once(dirname(__FILE__) . '/../classes/WarOfNations2.class.php');
require_once(dirname(__FILE__) . '/../classes/data/DataLoad.class.php');


require('../vendor/autoload.php');

use WebSocket\Client;

// Need this initalized so that we can use the database!
$won = new WarOfNations();

// Initalize the data load tracker
$won->setDataLoadId(DataLoadDAO::initNewLoad($won->db, 'SUBSCRIBE_SERVICE', 0));
DataLoadDAO::startLoad($won->db, $won->data_load_id);

// Get an instance of our game operations class
//$game = $won->GetGameOperations();

// Subscribe
//$uplink_info = $game->SubscribeUplink();

/*
GET /websocket HTTP/1.1
Upgrade: websocket
Connection: Upgrade
Host: 54.144.7.216
Origin: http://54.144.7.216
Sec-WebSocket-Key: enpmCter6z2Nx5VqQmNtTw==
Sec-WebSocket-Version: 13

GET /websocket HTTP/1.1
host: 54.144.7.216:8080
user-agent: websocket-client-php
connection: Upgrade
upgrade: websocket
sec-websocket-key: SGU5W1MmMmIzcWtkMzYxVQ==
sec-websocket-version: 13
*/

/*
$socket_addr = "ws://{$uplink_info['stream']['hostname']}:{$uplink_info['stream']['port']}/websocket";
echo "Connecting to $socket_addr... ";

$client = new Client($socket_addr);
echo "Connected!\r\n";

$player_id = '101013849913487';
$token = $uplink_info['token'];
$msg = '{"payload":{"hub":"war-of-nations-and","id":"'.$player_id.'","token":"'.$token.'"},"type":"subscribe"}';

echo "Sending: $msg\r\n";
$client->send($msg);
echo "Sent!\r\n\r\n";

while(true){
	try {
		echo 'Receiving: ['.$client->receive()."]\r\n\r\n";
	} 
	catch (Exception $ex) {
		echo "Error Reading From Connection\r\n";
	}

	// 30 Seconds between Heartbeat
	usleep(30000000);

	$msg = '{"payload":{},"type":"heartbeat"}';
	echo "Sending: $msg\r\n";
	$client->send($msg);
}
*/

$uplink = $won->GetUplinkService();

$uplink->Subscribe();

$uplink->Run();

?>