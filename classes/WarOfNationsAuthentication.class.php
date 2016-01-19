<?php
require_once(dirname(__FILE__) . '/data/DataLoadLog.class.php');
require_once(dirname(__FILE__) . '/data/Device.class.php');
require_once(dirname(__FILE__) . '/data/World.class.php');
require_once(dirname(__FILE__) . '/data/PgrmPlayers.class.php');
require_once(dirname(__FILE__) . '/data/PgrmUsers.class.php');

class WarOfNationsAuthentication {
	// Database
	public $db;
	
	// Data Extractor
	public $de;
	
	// Current Data Load ID
	public $data_load_id;
	
	// Device info
	private $local_device_id;
	public $device_id;
	public $mac_address;
	public $device_platform;
	public $device_version;
	public $device_type;
	public $use_proxy;
	
	// Player/session info
	public $authenticated = false;
	public $user_id;
	public $world_id;
	public $player_id;
	public $session_id;
	
	function __construct($db, $de, $dlid) {
		$this->db = $db;
		$this->de = $de;
		$this->data_load_id = $dlid;
	}
	
	public function setDataLoadId($dlid) {
		$this->data_load_id = $dlid;
	}
	
	private static function generate_session_id() {
		return mt_rand(1000000,9999999);
	}
	
	private static function generate_device_id() {
		return md5('this is a fake '.mt_rand(1000000,9999999));
	}
	
	private static function generate_mac_address() {
		return str_pad(dechex(mt_rand(0,255)), 2, '0').':'.str_pad(dechex(mt_rand(0,255)), 2, '0').':'.str_pad(dechex(mt_rand(0,255)), 2, '0').':'.str_pad(dechex(mt_rand(0,255)), 2, '0').':'.str_pad(dechex(mt_rand(0,255)), 2, '0').':'.str_pad(dechex(mt_rand(0,255)), 2, '0'); //40:0E:85:0D:77:29
	}
	
	// Authenticate a device into the game
	public function Authenticate($new = false, $device_id = false, $return_full_response = false) {
		/*
		POST /hc//index.php/json_gateway?svc=BatchController.authenticate_iphone HTTP/1.1
		Accept: application/json
		Content-type: application/json; charset=UTF-8;
		X-Signature: d9bf3c4270e31b7e5d88d80d017d2a32
		X-Timestamp: 1410113097
		User-Agent: Dalvik/1.4.0 (Linux; U; Android 2.3.4; DROID3 Build/5.5.1_84_D3G-66_M2-10)
		Host: gcand.gree-apps.net
		Connection: Keep-Alive
		Content-Length: 630
		Accept-Encoding: gzip

		[{"mac_address":"c8:aa:21:40:0a:2a","identifier_for_vendor":"8763af18eb4deace1840060a3bd9086b","app_uuid":"8763af18eb4deace1840060a3bd9086b","udid":"8763af18eb4deace1840060a3bd9086b"},{"android_version":"2.3.4","platform":"android","transaction_time":1410113097979,"session_id":"8509859","data_connection_type":"WiFi","client_static_table_data":{"active":"hc_20140829_38449","using":"hc_20140829_38449"},"device_type":"DROID3","seconds_from_gmt":-18000,"game_data_version":"hc_20140829_38449","client_build":"251","game_name":"HCGame","client_version":"1.8.4"},[{"service":"start.game","method":"load","_explicitType":"Command"}]]
		*/
	
		$log_seq = 0;
		$func_args = func_get_args();
		$func_log_id = DataLoadLogDAO::startFunction($this->db, $this->data_load_id, __CLASS__,  __FUNCTION__, $func_args);

		// If we've previously been authenticated, don't re-authenticate as a different account
		if($this->authenticated && $new === false && $device_id === false) {
			$new = false;
			$device_id = $this->local_device_id;
		}

		// If we're not making a new device, then get the active device from our database
		if(!$new) {
			if(!($device_id === false))
				$device = DeviceDAO::getDeviceById($this->db, $device_id);
			else
				$device = DeviceDAO::getActiveDevice($this->db);
			
			$this->local_device_id = $device['id'];
			$this->device_id = $device['device_uuid'];
			$this->mac_address = $device['mac_address'];
			$this->device_platform = $device['platform'];
			$this->device_version = $device['version'];
			$this->device_type = $device['device_type'];
			$this->use_proxy = $device['use_proxy'];
		} else {
			DataLoadLogDAO::logEvent2($this->db, $func_log_id, $log_seq++, 'INFO', 'Creating New Device');
		
			$this->device_id = self::generate_device_id();
			$this->mac_address = self::generate_mac_address();
			$this->device_platform = PgrmConfigDAO::getConfigProperty($this->db, 'value1', 'NEW_DEVICE', 'PLATFORM');
			$this->device_version = PgrmConfigDAO::getConfigProperty($this->db, 'value1', 'NEW_DEVICE', 'VERSION');
			$this->device_type = PgrmConfigDAO::getConfigProperty($this->db, 'value1', 'NEW_DEVICE', 'TYPE');
			$this->use_proxy = 1;
		}

		if($this->use_proxy == 0)
			$this->de->DisableProxy();
		
		// Cache our device information as parameters in the data extractor
		$this->de->AddCacheParam('device_id', $this->device_id);
		$this->de->AddCacheParam('mac_address', $this->mac_address);
		$this->de->AddCacheParam('device_platform', $this->device_platform);
		$this->de->AddCacheParam('device_version', $this->device_version);
		$this->de->AddCacheParam('device_type', $this->device_type);
		$this->de->AddCacheParam('session_id', self::generate_session_id());
	
		$log_msg = "Device ID: {$this->device_id}\r\nMAC Address: {$this->mac_address}\r\nDevice Platform: {$this->device_platform}\r\nDevice Version: {$this->device_version}\r\nDevice Type: {$this->device_type}";
		DataLoadLogDAO::logEvent2($this->db, $func_log_id, $log_seq++, 'DATA', "Authenticating as Device ID: {$this->device_id}", $log_msg);	

		echo "Authenticating...\r\n";
		
		$response = $this->de->MakeRequest('AUTHENTICATE');
		if(!$response) return false;

		echo "Done!\r\n\r\n";
	
		// Save the player ID and Session ID
		$this->user_id = $response['metadata']['user']['id'];
		$this->player_id = $response['metadata']['user']['active_player_id'];
		$this->session_id = $response['session']['session_id'];
		
		// Cache the player ID so that we have it for other requests later
		$this->de->AddCacheParam('player_id', $this->player_id);
	
		//$log_msg = "Player ID: {$this->player_id}, Session ID: {$this->session_id}";
		
		// If this was a new device, then save it to the database for future use
		if($new) {
			$device = new Device();
			$device->device_uuid = $this->device_id;
			$device->mac_address = $this->mac_address;
			$device->platform = $this->device_platform;
			$device->version = $this->device_version;
			$device->device_type = $this->device_type;
			$device->use_proxy = 1;
		
			DataLoadLogDAO::logEvent2($this->db, $func_log_id, $log_seq++, 'DATA', "Saving New Device", print_r($device, true));	

			$this->local_device_id = DeviceDAO::insertDevice($this->db, $device);

			if($this->db->hasError()) {
				echo 'Error creating Device: ';
				print_r($this->db->getError());
				echo "\r\n";
				
				$log_msg = print_r($device, true)."\r\n\r\n".print_r($this->db->getError(), true);
				DataLoadLogDAO::logEvent2($this->db, $func_log_id, $log_seq++, 'ERROR', "Error Saving New Device", $log_message, 1);	
			}

			$this->local_user_id = PgrmUserDAO::createUser($this->db, $this->user_id, $this->local_device_id);
		
			if($this->db->hasError()) {
				echo 'Error creating User: ';
				print_r($this->db->getError());
				echo "\r\n";
				
				$log_msg = print_r($device, true)."\r\n\r\n".print_r($this->db->getError(), true);
				DataLoadLogDAO::logEvent2($this->db, $func_log_id, $log_seq++, 'ERROR', "Error Saving New User", $log_msg, 1);	
			}
		}
	
		// Get the local World ID from our database
		if(!$new)
			$this->world_id = (int)WorldDAO::getLocalIdFromGameId($this->db, $response['metadata']['player']['world_id']);
		
		$this->authenticated = true;
		
		DataLoadLogDAO::completeFunction($this->db, $func_log_id, "Authenticated into World {$this->world_id} as player {$this->player_id}");

		// This allows other features of the program to use additional information about the user if needed after authenticating
		if($return_full_response)
			return $response;
		return true;
	}

	// Tell the game that we've completed the tutorial
	private function FinishTutorial() {
		/*
		POST /hc//index.php/json_gateway?svc=BatchController.call HTTP/1.1
		Accept: application/json
		Content-type: application/json; charset=UTF-8;
		X-Signature: 8dfc74f04d5ffa443db2de0bb4c42754
		X-Timestamp: 1410113199
		User-Agent: Dalvik/1.4.0 (Linux; U; Android 2.3.4; DROID3 Build/5.5.1_84_D3G-66_M2-10)
		Host: gcand.gree-apps.net
		Connection: Keep-Alive
		Content-Length: 536
		Accept-Encoding: gzip

		[{"transaction_time":"1410113200198","platform":"android","session_id":"8509859","start_sequence_num":3,"iphone_udid":"8763af18eb4deace1840060a3bd9086b","wd_player_id":0,"locale":"en-US","_explicitType":"Session","client_build":"251","game_name":"HCGame","api_version":"1","mac_address":"c8:aa:21:40:0a:2a","end_sequence_num":3,"req_id":1,"player_id":67174,"language":"en","game_data_version":"hc_20140903_38604","client_version":"1.8.4"},[{"service":"profile.profile","method":"finish_tutorial","_explicitType":"Command","params":[]}]]
		*/

		$log_seq = 0;
		$func_args = func_get_args();
		$func_log_id = DataLoadLogDAO::startFunction($this->db, $this->data_load_id, __CLASS__,  __FUNCTION__, $func_args);

		// Finish the tutoiral
		echo "Finishing Tutorial...\r\n";
		$params = array();
		$params['tut_player_id'] = '67174';
		$result = $this->de->MakeRequest('FINISH_TUTORIAL', $params);

		if(!$result) return false;
		echo "Done!\r\n";
	
		if($this->debug_level >= 30) {
			echo '<pre>';
			print_r($result);
			echo '</pre>';
		}
		
		// Save our new player to the database
		$this->player_id = $result['metadata']['player']['player_id'];
		$this->world_id = WorldDAO::getLocalIdFromGameId($this->db, $result['metadata']['player']['world_id']);
	
		// Cache the player ID so that we have it for other requests later
		$this->de->AddCacheParam('player_id', $this->player_id);

		echo "New Player ID: {$this->player_id} in World {$this->world_id}\r\n\r\n";
		$new_local_player_id = PgrmPlayerDAO::joinNewWorld($this->db, $this->player_id, $this->local_user_id, $this->world_id);

		if($this->db->hasError()) {
			echo 'Error joining world: ';
			print_r($this->db->getError());
			echo "\r\n";
			
			$log_msg = print_r($response, true)."\r\n\r\n".print_r($this->db->getError(), true);
			DataLoadLogDAO::logEvent2($this->db, $func_log_id, $log_seq++, 'ERROR', "Error saving player into World {$this->world_id} as player {$this->player_id}", $log_msg, 1);	
		}

		DataLoadLogDAO::completeFunction($this->db, $func_log_id, "Completed Tutorial and joined World {$this->world_id} as player {$this->player_id}");
	}

	// In order to initialize a player into the world, you need to authenticate as a new device
	// and then tell the game that you've completed the tutorial.
	public function CreateNewPlayer() {
		echo "Authenticating as New Device...\r\n";
		$this->Authenticate(true, false);
		echo "Done!\r\n";	
	
		$this->FinishTutorial();
	}

	public function JoinNewWorld($world_id) {
		/*
		POST /hc//index.php/json_gateway?svc=BatchController.call HTTP/1.1
		Accept: application/json
		Content-type: application/json; charset=UTF-8;
		X-Signature: 004d3e4c635fd823773950f50a999aad
		X-Timestamp: 1410114993
		User-Agent: Dalvik/1.4.0 (Linux; U; Android 2.3.4; DROID3 Build/5.5.1_84_D3G-66_M2-10)
		Host: gcand.gree-apps.net
		Connection: Keep-Alive
		Content-Length: 541
		Accept-Encoding: gzip

		[{"transaction_time":"1410114994026","platform":"android","session_id":"51507","start_sequence_num":1,"iphone_udid":"8763af18eb4deace1840060a3bd9086b","wd_player_id":0,"locale":"en-US","_explicitType":"Session","client_build":"251","game_name":"HCGame","api_version":"1","mac_address":"c8:aa:21:40:0a:2a","end_sequence_num":1,"req_id":1,"player_id":101013596288193,"language":"en","game_data_version":"hc_20140903_38604","client_version":"1.8.4"},[{"service":"world.world","method":"join_world","_explicitType":"Command","params":[101001]}]]
		*/

		$log_seq = 0;
		$func_args = func_get_args();
		$func_log_id = DataLoadLogDAO::startFunction($this->db, $this->data_load_id, __CLASS__,  __FUNCTION__, $func_args);

		echo "Joining World $world_id...\r\n";

		$params = array();
		$params['game_world_id'] = WorldDAO::getGameIdFromLocalId($this->db, $world_id);

		$response = $this->de->MakeRequest('JOIN_NEW_WORLD', $params);
		if(!$response) return false;

		$this->world_id = $world_id;
		$this->player_id = $response['metadata']['player']['player_id'];

		$local_user_id = PgrmUserDAO::getLocalIdFromGameId($this->db, $this->user_id);
		$new_local_player_id = PgrmPlayerDAO::joinNewWorld($this->db, $this->player_id, $local_user_id, $this->world_id);

		if($this->db->hasError()) {
			echo 'Error joining world: ';
			print_r($this->db->getError());
			echo "\r\n";
			
			$log_msg = print_r($response, true)."\r\n\r\n".print_r($this->db->getError(), true);
			DataLoadLogDAO::logEvent2($this->db, $func_log_id, $log_seq++, 'ERROR', "Error saving player into World {$this->world_id} as player {$this->player_id}", $log_msg, 1);	
		}

		echo "Joined!\r\n";

		DataLoadLogDAO::completeFunction($this->db, $func_log_id, "Joined World {$this->world_id} as player {$this->player_id}");

		// Authenticate into our new world
		return $this->Authenticate();
	}

	public function SwitchWorld($world_id) {
		/*
		POST /hc//index.php/json_gateway?svc=BatchController.call HTTP/1.1
		x-newrelic-id: UgMDWFFADQYCUFFUBw==
		Accept: application/json
		Content-type: application/json; charset=UTF-8;
		X-Signature: 51931c0ffc90f6ac0e64e5f6bb1a7bbd
		X-Timestamp: 1424498999
		User-Agent: Dalvik/1.6.0 (Linux; U; Android 4.2.2; Droid4X-MAC Build/JDQ39E)
		Host: gcand.gree-apps.net
		Connection: Keep-Alive
		Accept-Encoding: gzip
		Content-Length: 545

		[{"transaction_time":"1424498999746","platform":"android","session_id":"6283633","start_sequence_num":1,"iphone_udid":"92639d40a61db79e7d8c01479b7638fc","wd_player_id":0,"locale":"en-US","_explicitType":"Session","client_build":"360","game_name":"HCGame","api_version":"1","mac_address":"14:10:9F:D6:7B:33","end_sequence_num":1,"req_id":1,"player_id":101019808535303,"language":"en","game_data_version":"hc_20150218_47131","client_version":"1.9.8"},[{"service":"world.world","method":"switch_world","_explicitType":"Command","params":[101013]}]]
		[{"transaction_time":"1410114994026","platform":"android","session_id":"51507  ","start_sequence_num":1,"iphone_udid":"8763af18eb4deace1840060a3bd9086b","wd_player_id":0,"locale":"en-US","_explicitType":"Session","client_build":"251","game_name":"HCGame","api_version":"1","mac_address":"c8:aa:21:40:0a:2a","end_sequence_num":1,"req_id":1,"player_id":101013596288193,"language":"en","game_data_version":"hc_20140903_38604","client_version":"1.8.4"},[{"service":"world.world","method":"join_world","_explicitType":"Command","params":[101001]}]]

		*/

		$log_seq = 0;
		$func_args = func_get_args();
		$func_log_id = DataLoadLogDAO::startFunction($this->db, $this->data_load_id, __CLASS__,  __FUNCTION__, $func_args);

		echo "Switching to World $world_id...\r\n";

		$params = array();
		$params['game_world_id'] = WorldDAO::getGameIdFromLocalId($this->db, $world_id);

		$response = $this->de->MakeRequest('SWITCH_WORLD', $params);
		if(!$response) return false;

		$success = $response['responses'][0]['return_value']['success'];
		
		if($success != 1)
			return false;

		$this->world_id = $world_id;
		$this->player_id = $response['metadata']['player']['player_id'];

		DataLoadLogDAO::completeFunction($this->db, $func_log_id, "Switched to World {$this->world_id} as player {$this->player_id}");

		// Authenticate into our new world
		return $this->Authenticate();
	}
}
?>