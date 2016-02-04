<?php
require_once(dirname(__FILE__) . '/../vendor/autoload.php');
use WebSocket\Client;

class UplinkService {
	// Database
	public $db;
	
	// Data Extractor
	public $de;
	
	// Current Data Load ID
	public $data_load_id;
	
	// Authentication Information
	private $auth;

	// Socket Client
	private $client;

	function __construct($db, $de, $dlid, $auth) {
		$this->db = $db;
		$this->de = $de;
		$this->data_load_id = $dlid;
		$this->auth = $auth;
	}
	
	public function setDataLoadId($dlid) {
		$this->data_load_id = $dlid;
	}

	public function Subscribe() {
		//[{"transaction_time":"1448131541333","platform":"android","session_id":"6867564","start_sequence_num":0,"iphone_udid":"8c9c72c38515837f4957843075bcac39","wd_player_id":0,"locale":"en-US","_explicitType":"Session","client_build":"486","game_name":"HCGame","api_version":"1","mac_address":"14:10:9F:D6:7B:33","end_sequence_num":0,"req_id":1,"player_id":101013624609676,"language":"en","client_version":"2.5.2"},[{"service":"uplinkservice.uplinkservice","method":"subscribe","_explicitType":"Command","params":[]}]]
		//[{"transaction_time":"$$transaction_time$$","platform":"$$device_platform$$","session_id":"$$session_id$$","start_sequence_num":1,"iphone_udid":"$$device_id$$","wd_player_id":0,"locale":"en-US","_explicitType":"Session","client_build":"$$client_build$$","game_name":"$$game_name$$","api_version":"$$api_version$$","mac_address":"$$mac_address$$","end_sequence_num":0,"req_id":1,"player_id":$$player_id$$,"language":"en","game_data_version":"$$game_data_version$$","client_version":"$$client_version$$"},[{"service":"uplinkservice.uplinkservice","method":"subscribe","_explicitType":"Command","params":[]}]]
	
		$log_seq = 0;
		$func_args = func_get_args();
		$func_log_id = DataLoadLogDAO::startFunction($this->db, $this->data_load_id, __CLASS__,  __FUNCTION__, $func_args);

		echo "Subscribing...\n";

		$result = $this->de->MakeRequest('SUBSCRIBE_UPLINK');
		if(!$result) return false;

		if($result['status'] == 'OK') {
			echo "Subscribed!\n";

			DataLoadLogDAO::logEvent2($this->db, $func_log_id, $log_seq++, 'INFO', "Subscribed to Uplink Service");	

			$uplink_info = $result['responses'][0]['return_value']['uplink_info'];
		} else {
			echo "Failed to Subscribe!\n";

			DataLoadLogDAO::completeFunction($this->db, $func_log_id, 'Failed to Subscribe to Uplink Service', 1);

			return false;

		}

		$socket_addr = "ws://{$uplink_info['stream']['hostname']}:{$uplink_info['stream']['port']}/websocket";
		echo "Connecting to $socket_addr... ";

		DataLoadLogDAO::logEvent2($this->db, $func_log_id, $log_seq++, 'INFO', "Opening Connection to Socket: $socket_addr");	
		$this->client = new Client($socket_addr);
		DataLoadLogDAO::logEvent2($this->db, $func_log_id, $log_seq++, 'INFO', "Successfully opened connection to socket");	

		echo "Connected!\n";

		$this->client->setTimeout(30);

		$player_id = $this->auth->player_id;
		$token = $uplink_info['token'];
		$msg = '{"payload":{"hub":"war-of-nations-and","id":"'.$player_id.'","token":"'.$token.'"},"type":"subscribe"}';

		echo "Sending: $msg\n";

		DataLoadLogDAO::logEvent2($this->db, $func_log_id, $log_seq++, 'INFO', "Sending subscribe message to $socket_addr", $msg);	
		$this->client->send($msg);
		DataLoadLogDAO::logEvent2($this->db, $func_log_id, $log_seq++, 'INFO', "Successfully sent message to socket");	

		DataLoadLogDAO::completeFunction($this->db, $func_log_id, 'Successfully Subscribed to Uplink Socket');

		echo "Sent!\n\n";

		return true;
	}

	public function Run() {
		$heartbeat_msg = '{"payload":{},"type":"heartbeat"}';

		$log_seq = 0;
    	$func_args = func_get_args();
		$func_log_id = DataLoadLogDAO::startFunction($this->db, $this->data_load_id, __CLASS__,  __FUNCTION__, $func_args);

		$seconds_between_heartbeat = 30;
		$closed = false;
        while(1){
            DataLoadLogDAO::logEvent2($this->db, $func_log_id, $log_seq++, 'INFO', "Send heartbeat message");	

            echo "Sending: $heartbeat_msg\n----------\n";
			$this->client->send($heartbeat_msg);
			$last_heartbeat = microtime(true);
			DataLoadDAO::operationComplete($this->db, $this->data_load_id);

			// This loop controls how many times we will try go receive in between heartbeats
    		//for($i = 0; $i < 1; $i++) {
			// Continue reading until we run out of content to read, disconnect, or reach our heartbeat time
            while(1){
	            try {
	            	$message_handled = false;
	            	$opcode = '';

	            	DataLoadLogDAO::logEvent2($this->db, $func_log_id, $log_seq++, 'INFO', "Listening...");	

	            	$seconds_since_heartbeat = (microtime(true) - $last_heartbeat);
	            	echo "Seconds Since Heartbeat: $seconds_since_heartbeat\n";
	            	$new_timeout = round(($seconds_between_heartbeat - $seconds_since_heartbeat), 0);
	            	if($new_timeout <= 0) 
	            		break;

	            	echo "New Heartbeat Timeout: $new_timeout seconds\n";

	            	echo "Receiving: ";
	            	$this->client->setTimeout($new_timeout);
	            	$data = $this->client->receive();
	            	$opcode = $this->client->getLastOpcode();

					echo "$opcode\n";
					DataLoadLogDAO::logEvent2($this->db, $func_log_id, $log_seq++, 'MESSAGE', "Receive Complete [$opcode]");//, $data);	

					// Handle special cases here
	            	switch($opcode) {
	            		case 'ping': // Respond with pong
	            			echo "Sending Pong\n----------\n";
	            			DataLoadLogDAO::logEvent2($this->db, $func_log_id, $log_seq++, 'INFO', "Sending pong message");	

	            			$this->client->send('', 'pong');
	            			$message_handled = true;
	            			break;
	            		case 'close':
	            			echo "Received Close.  Disconnecting...\n";
	            			DataLoadLogDAO::logEvent2($this->db, $func_log_id, $log_seq++, 'INFO', "Sending pong message");	

	            			$message_handled = true;
	            			$closed = true;
	            			break;
	            	}

	            	// If we already handled this message, go receive a new one
	            	if($message_handled) break;

	            	try {
	            		echo "Trying to decode string\n";
	            		$decoded_data = @gzdecode($data);
	            		if ($decoded_data == false) {
	            			echo "String not compressed\n$data\n==========\n";
	            			DataLoadLogDAO::logEvent2($this->db, $func_log_id, $log_seq++, 'MESSAGE', "[$opcode] Message Not Compressed", $data);	
	            		}
	            		else {
	            			$data = $decoded_data;
	            			
	            			echo "DECODED:\n$data\n==========\n";
							DataLoadLogDAO::logEvent2($this->db, $func_log_id, $log_seq++, 'MESSAGE', "Decoded [$opcode] message", $data);	
	            		}
	            	} 
	            	catch (Exception $ex) {
	            		echo "Error\n";
	            	}
				} 
				catch (Exception $ex) {
					echo "No Data Found\n";
					DataLoadLogDAO::logEvent2($this->db, $func_log_id, $log_seq++, 'ERROR', "No Data Found [$opcode]", $ex->getMessage(), 1);
					//usleep(5000000);
					//break;
				}
			}
	        //}

	        if($closed)
        		break;
        }

		DataLoadLogDAO::completeFunction($this->db, $func_log_id, 'Finished with Listener, this should never happen');
	}
}



?>