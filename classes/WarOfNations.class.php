<?php
require_once('data/DatabaseFactory.class.php');
require_once('data/WorldMap.class.php');
require_once('data/World.class.php');
require_once('data/Player.class.php');
require_once('data/Guild.class.php');
require_once('data/Device.class.php');
require_once('data/Building.class.php');
require_once('data/Proxy.class.php');

class Constants {
	// Base URL
	public static $url_base = 'http://gcand.gree-apps.net/hc//index.php/json_gateway?svc=';
	
	// Service Endpoints
	public static $authenticate = 'BatchController.authenticate_iphone';
	public static $call = 'BatchController.call';
	
	// Game Information
	public static $game_data_version = 'hc_20140908_38941';
	public static $game_name = 'HCGame';
	public static $client_build = '251';
	public static $client_version = '1.8.4';
	public static $api_version = '1';
	
	// Hmac Authorization
	public static $hmac_key = '%j75jY6pnJPs#TFBMDJ26*6U5hE544&zcZm7^kTua8%@awSTyAMG&&Z#mg23zKMb';
}

// Known Player IDs, Devices:
// Main: 101013866213677, 66d2ea9883993b46e9f06fba6e652302 
// Droid3: 101013596288193, 8763af18eb4deace1840060a3bd9086b
// Fake: 101013849913487, b0b01655f403347cea151d87a45d2746

class WarOfNations {
	// Debug levels
	// >=40: Turn on Global Debugging
	// >=30: Debug
	// >=20: Informational
	// >=10: Warning
	private $debug_level;
	
	// Database
	private $db;
	
	// Device info
	private $device_id;
	private $mac_address;
	private $device_platform = 'android';
	private $device_version = '4.4.2';
	private $device_type = 'SCH-I545';
	
	// Player/session info
	private $world_id;
	private $player_id;
	private $session_id;
	
	// List of proxy servers to use for connections.
	private $proxies;
	
	function __construct($debug_level = 0) {
		$this->debug_level = $debug_level;
		if($debug_level >= 40) {
			global $debug;
			$debug = true;
		}
		
		$this->db = DatabaseFactory::getDatabase();
		$this->proxies = ProxyDAO::getActiveProxies($this->db);
	}
	
	private function do_curl_post_json($endpoint, $data_string, $retry_count = 0) {	
		$max_retries = 20;
			
		if($retry_count > 0)
			echo "Retry Attempt #$retry_count<br/>\r\n";
		$url = Constants::$url_base.$endpoint;
		
		if($this->debug_level >= 30) {
			echo 'Curling URL: '.$url."<br/>\r\n";
			echo 'Curling Data: '.$data_string."<br/>\r\n";
		}
		
		$time = substr(microtime(true), 0, 10);
		$hmac_secret = $time.$endpoint.$data_string;
		$headers = array(
		  	'Accept: application/json',
			'Content-type: application/json; charset=UTF-8;',
			'X-Signature: ' . hash_hmac('md5', $hmac_secret, Constants::$hmac_key, false),
			'X-Timestamp: ' . $time,
			'User-Agent: Dalvik/1.6.0 (Linux; U; Android 4.4.2; SCH-I545 Build/KOT49H)',
			'Connection: Keep-Alive',
			'Accept-Encoding: gzip'
		);
		
		if($this->debug_level >= 30) {
			echo '<pre>';
			print_r($headers);
			echo '</pre>';
		}
		
		$proxy = $this->proxies[array_rand($this->proxies)];
		$proxy_str = "{$proxy['ip_address']}:{$proxy['port']}";
		$ch = curl_init($url);         
		curl_setopt($ch, CURLOPT_PROXY, $proxy_str);                                                             
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);                                                                  
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                                                             
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); 
		curl_setopt($ch, CURLOPT_TIMEOUT, 10); //timeout in seconds  
		
		$start = microtime(true);
		$response_string = curl_exec($ch);
		$end = microtime(true);
		
		echo "Curl completed in ".($end - $start)." seconds<br/>\r\n";
		
		if(!$response_string) {
			echo "Error occurred while getting Curl response.  Request: <br/>\r\n";
			echo "Proxy: $proxy_str<br/>\r\n";
			echo "URL: $url<br/>\r\n";
			echo "Data: $data_string<br/>\r\n";
			
			ProxyDAO::countFailure($this->db, $proxy['id']);
			if($retry_count < $max_retries)
				return $this->do_curl_post_json($endpoint, $data_string, $retry_count + 1);
			else
				return false;
		}
		
		$decoded = gzdecode($response_string);
		
		// If we encountered an error in decoding, print the raw response.
		if(!$decoded) {
			if(mb_check_encoding($response_string, 'UTF-8')) {
				echo "Error occurred while decoding CURL response, but let's assume this is OK! <br/>\r\n";
				ProxyDAO::countSuccess($this->db, $proxy['id']);
				return $response_string;
			} else {
				echo "Error occurred while decoding CURL response, and we think this is a problem! <br/>\r\n";
				ProxyDAO::countFailure($this->db, $proxy['id']);
				
				if($retry_count < $max_retries)
					return $this->do_curl_post_json($endpoint, $data_string, $retry_count + 1);
				else
					return false;
			}
		} 
		
		ProxyDAO::countSuccess($this->db, $proxy['id']);
		
		return $decoded;
	}
	
	private static function get_transaction_time() {
		return substr(str_replace('.', '', microtime(true)), 0, 13);
	}
	
	private function generate_session_id() {
		return mt_rand(1000000,9999999);
	}
	
	private function generate_device_id() {
		return md5('this is a fake 3');
	}
	
	private function generate_mac_address() {
		return str_pad(dechex(mt_rand(0,255)), 2, '0').':'.str_pad(dechex(mt_rand(0,255)), 2, '0').':'.str_pad(dechex(mt_rand(0,255)), 2, '0').':'.str_pad(dechex(mt_rand(0,255)), 2, '0').':'.str_pad(dechex(mt_rand(0,255)), 2, '0').':'.str_pad(dechex(mt_rand(0,255)), 2, '0'); //40:0E:85:0D:77:29
		;
	}
	
	// Authenticate a device into the game
	public function Authenticate($new = false, $return_full_response = false) {
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
		$endpoint = Constants::$authenticate;
		$transaction_time = self::get_transaction_time();
		$session_id = $this->generate_session_id();
		
		if(!$new) {
			$device = DeviceDAO::getActiveDevice($this->db);
			//print_r($device);
			$this->device_id = $device['device_uuid'];
			$this->mac_address = $device['mac_address'];
			$this->device_platform = $device['platform'];
			$this->device_version = $device['version'];
			$this->device_type = $device['device_type'];
		} else {
			$this->device_id = $this->generate_device_id();
			$this->mac_address = $this->generate_mac_address();
		}
		
		$device_id = $this->device_id;
		$mac_address = $this->mac_address;
		$device_platform = $this->device_platform;
		$device_version = $this->device_version;
		$device_type = $this->device_type;
		$data_string = '[{"mac_address":"'.$mac_address.'","identifier_for_vendor":"'.$device_id.'","app_uuid":"'.$device_id.'","udid":"'.$device_id.'"},{"android_version":"'.$device_version.'","platform":"'.$device_platform.'","transaction_time":'.$transaction_time.',"session_id":"'.$session_id.'","data_connection_type":"WiFi","client_static_table_data":{"active":"'.Constants::$game_data_version.'","using":"'.Constants::$game_data_version.'"},"device_type":"'.$device_type.'","seconds_from_gmt":-18000,"game_data_version":"'.Constants::$game_data_version.'","client_build":"'.Constants::$client_build.'","game_name":"'.Constants::$game_name.'","client_version":"'.Constants::$client_version.'"},[{"service":"start.game","method":"load","_explicitType":"Command"}]]';
		//$data_string = '[{"mac_address":"c8:aa:21:40:0a:2a","identifier_for_vendor":"8763af18eb4deace1840060a3bd9086b","app_uuid":"8763af18eb4deace1840060a3bd9086b","udid":"8763af18eb4deace1840060a3bd9086b"},{"android_version":"2.3.4","platform":"android","transaction_time":1410325698780,"session_id":"8317325","data_connection_type":"WiFi","client_static_table_data":{"active":"hc_20140908_38941","using":"hc_20140908_38941"},"device_type":"DROID3","seconds_from_gmt":-18000,"game_data_version":"hc_20140908_38941","client_build":"251","game_name":"HCGame","client_version":"1.8.4"},[{"service":"start.game","method":"load","_explicitType":"Command"}]]';
		$data = json_decode($data_string);
		
		if($this->debug_level >= 30) {
			echo "<pre>";
			print_r($data);
			echo "</pre>";
		}
		
		echo "Authenticating...<br/>\r\n";
		
		//echo $response;
		$response = json_decode(self::do_curl_post_json($endpoint, $data_string), true);
		
		echo "Done!<br/>\r\n";
		
		if($this->debug_level >= 30) {
			echo '<pre>';
			print_r($response);
			echo '</pre>';
		}
		
		/*
		[metadata] => Array
		        (
		            [user] => Array
		                (
		                    [_explicitType] => user.User
		                    [id] => 103228105
		                    [iphone_udid] => 8763af18eb4deace1840060a3bd9086b
		                    [mac_address] => c8:aa:21:40:0a:2a
		                    [worlds] => Array
		                        (
		                            [101013] => 101013596288193
		                            [101001] => 101001401287397
		                        )

		                    [active_player_id] => 101001401287397
		                    [entry_source] => unknown
		                    [country_code] => US
		                    [time_created] => 2014-09-07 10:50:28
		                    [version] => 5
		                )

		            [user_info] => Array
		                (
		                    [_explicitType] => user.UserInfo
		                    [id] => 103228105
		                    [time_created] => 2014-09-07 10:50:28
		                    [time_updated] => 2014-09-07 12:45:34
		                    [version] => 98
		                    [hard_currency] => 500
		                    [usd_spent] => 0
		                    [is_tutorial_complete] => 1
		                    [is_banned] => 0
		                    [is_muted_from_chat] => 0
		                    [has_kinvite_reward] => 0
		                    [is_spender] => 0
		                    [server_sequence_num] => 4
		                    [ios_version] => 4.4.2
		                    [ban_timestamp] => 0
		                    [mute_timestamp] => 0
		                    [lifetime_currency_gained] => 500
		                    [lifetime_currency_spent] => 0
		                    [locale] => en-US
		                )

		            [server_time] => 1410119134
		            [player] => Array
		                (
		                    [_explicitType] => player.Player
		                    [player_name] => Player401287397
		                    [user_id] => 103228105
		                    [world_id] => 101001
		                    [is_spender] => 0
		                    [experience] => 23
		                    [level] => 2
		                    [ab_test] => []
		                    [is_test_account] => 0
		                    [did_player_level_up] => 0
		                    [last_game_load_time] => 2014-09-07 12:45:34
		                    [resources] => Array
		                        (
		                            [1] => 20000
		                            [2] => 20000
		                            [3] => 0
		                            [4] => 0
		                            [5] => 20000
		                            [6] => 19839.038888889
		                        )

		                    [is_npc] => 0
		                    [net_food_rate] => -140
		                    [unit_starvation_delta] => 510146
		                    [next_starvation_time] => 2014-09-13 10:28:00
		                    [food_update_time] => 2014-09-07 12:45:34
		                    [guild_id] => 
		                    [stats_number_of_towns] => 1
		                    [stats_total_unit_value_killed] => 0
		                    [immunity_end_time] => 2014-09-12 11:36:34
		                    [immunity_type] => 1
		                    [id] => 101001401287397
		                    [player_id] => 101001401287397
		                    [version] => 9
		                )
		
		
		[session] => Array
		        (
		            [_explicitType] => Session
		            [iphone_udid] => 8763af18eb4deace1840060a3bd9086b
		            [session_id] => 8765432
		            [api_version] => 1
		            [client_version] => 1.8.4
		            [client_build] => 251
		            [device_type] => SCH-I545
		            [game_name] => HCGame
		            [seconds_from_gmt] => -18000
		            [mac_address] => 48:16:7b:c2:34:0
		            [game_data_version] => hc_20140903_38604
		            [platform] => android
		            [player_id] => 101001401287397
		            [time_created] => 2014-09-07 11:37:48
		            [time_updated] => 2014-09-07 11:37:48
		            [version] => 0
		            [transaction_time] => 1410119134122
		            [start_sequence_num] => 1
		            [wd_player_id] => 0
		            [locale] => en-US
		            [end_sequence_num] => 1
		            [req_id] => 1
		            [language] => en
		            [client_ip] => 73.185.135.139
		            [country_code] => US
		            [guild_id] => 
		            [data_connection_type] => WiFi
		        )
		*/
		
		// Save the player ID and Session ID
		$this->player_id = $response['metadata']['user']['active_player_id'];
		$this->session_id = $response['session']['session_id'];
		
		if($new) {
			$device = new Device();
			$device->device_uuid = $device_id;
			$device->mac_address = $mac_address;
			$device->platform = $device_platform;
			$device->version = $device_version;
			$device->device_type = $device_type;

			DeviceDAO::insertDevice($this->db, $device);
		}
		
		// Get the local World ID from our database
		if(!$new)
			$this->world_id = WorldDAO::getLocalIdFromGameId($this->db, $response['metadata']['player']['world_id']);
	}
	
	// Tell the game that we've completed the tutorial
	public function FinishTutorial($return_full_response = false) {
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
		
		$endpoint = Constants::$call;
		$transaction_time = self::get_transaction_time();
		$session_id = $this->session_id;
		$device_id = $this->device_id;
		$mac_address = $this->mac_address;
		$tut_player_id = '67174';
		$device_platform = $this->device_platform;
		$device_version = $this->device_version;
		$device_type = $this->device_type;
		$data_string = '[{"transaction_time":"'.$transaction_time.'","platform":"'.$device_platform.'","session_id":"'.$session_id.'","start_sequence_num":3,"iphone_udid":"'.$device_id.'","wd_player_id":0,"locale":"en-US","_explicitType":"Session","client_build":"'.Constants::$client_build.'","game_name":"'.Constants::$game_name.'","api_version":"'.Constants::$api_version.'","mac_address":"'.$mac_address.'","end_sequence_num":3,"req_id":1,"player_id":'.$tut_player_id.',"language":"en","game_data_version":"'.Constants::$game_data_version.'","client_version":"'.Constants::$client_version.'"},[{"service":"profile.profile","method":"finish_tutorial","_explicitType":"Command","params":[]}]]';
		
		echo "Finishing Tutorial...<br/>\r\n";
		$result = json_decode(self::do_curl_post_json($endpoint, $data_string), true);
		echo "Done!<br/>\r\n";
		
		if($this->debug_level >= 30) {
			echo '<pre>';
			print_r($result);
			echo '</pre>';
		}
		
		$this->player_id = $result['metadata']['player']['player_id'];
		
		if($this->debug_level >= 20)
			echo "New Player ID: {$this->player_id}";
		
		return $result;
	}
	
	// In order to initialize a player into the world, you need to authenticate as a new device
	// and then tell the game that you've completed the tutorial.
	public function CreateNewPlayer() {
		echo "Authenticating as New Device...<br/>\r\n";
		$this->Authenticate(true);
		echo "Done!<br/>\r\n";	
		
		echo "Finishing Tutorial...<br/>\r\n";
		$this->FinishTutorial();
		echo "Done!<br/>\r\n";
		
		// TODO: Save information to database
	}
	
	private static function convertToMapCoordinate($x, $y) {
		return $x + floor($y/2);
	}
	
	private static function convertFromMapCoordinate($x, $y) {
		return $x - floor($y/2);
	}
	
	// Call the web service to get the world map and then parse and save it in the database
	public function GetWorldMap($x_start = 0, $y_start = 0, $x_range = 50, $y_range = 50) {
		/*
		POST /hc//index.php/json_gateway?svc=BatchController.call HTTP/1.1
		Accept: application/json
		Content-type: application/json; charset=UTF-8;
		X-Signature: b8b29f1b19e52ee5f2acb630440112f1
		X-Timestamp: 1409886446
		User-Agent: Dalvik/1.6.0 (Linux; U; Android 4.4.2; SCH-I545 Build/KOT49H)
		Host: gcand.gree-apps.net
		Connection: Keep-Alive
		Accept-Encoding: gzip
		Content-Length: 556

		[{"transaction_time":"1409886446625","platform":"android","session_id":"2913291","start_sequence_num":1,"iphone_udid":"66d2ea9883993b46e9f06fba6e652302","wd_player_id":0,"locale":"en-US","_explicitType":"Session","client_build":"251","game_name":"HCGame","api_version":"1","mac_address":"40:0E:85:0D:77:29","end_sequence_num":1,"req_id":1,"player_id":101013866213677,"language":"en","game_data_version":"hc_20140903_38604","client_version":"1.8.4"},[{"service":"world.world","method":"get_map_data","_explicitType":"Command","params":[[[-1250,0,50,50]]]}]]
		*/
		$endpoint = Constants::$call;
		$transaction_time = self::get_transaction_time();
		$session_id = $this->session_id;
		$device_id = $this->device_id;
		$mac_address = $this->mac_address;
		$player_id = $this->player_id;
		$device_platform = $this->device_platform;
		$device_version = $this->device_version;
		$device_type = $this->device_type;
		$x_start = self::convertFromMapCoordinate($x_start, $y_start);
		$data_string = '[{"transaction_time":"'.$transaction_time.'","platform":"'.$device_platform.'","session_id":"'.$session_id.'","start_sequence_num":1,"iphone_udid":"'.$device_id.'","wd_player_id":0,"locale":"en-US","_explicitType":"Session","client_build":"'.Constants::$client_build.'","game_name":"'.Constants::$game_name.'","api_version":"'.Constants::$api_version.'","mac_address":"'.$mac_address.'","end_sequence_num":1,"req_id":1,"player_id":'.$player_id.',"language":"en","game_data_version":"'.Constants::$game_data_version.'","client_version":"'.Constants::$client_version.'"},[{"service":"world.world","method":"get_map_data","_explicitType":"Command","params":[[['.$x_start.','.$y_start.','.$x_range.','.$y_range.']]]}]]';
		
		echo "Getting World Map...<br/>\r\n";
		$result_string = self::do_curl_post_json($endpoint, $data_string);
		if(!$result_string) return false;
		
		$result = json_decode($result_string, true);
		if($result == null) {
			echo "Error: Failed to decode JSON string: $result_string<br/>\r\n";
			return false;
		}
		echo "Done!<br/>\r\n";
		
		//echo $result_string;
		if($this->debug_level >= 30) {
			echo '<pre>';
			print_r($result);
			echo '</pre>';
		}
		
		$this->ParseAndSaveWorldMap($result);
		return true;
	}
	
	public function ParseAndSaveWorldMap($world_response) {
		// Get the Hexes section of the world response
		$world = $world_response['responses'][0]['return_value']['hexes'];
		
		$hex_count = 0;
		// Parse and store each Hex
		foreach($world as $key => $hex_arr) {
			$hex_count++;
			
			// Take the array and create the Hex object
			$hex = Hex::FromJson($hex_arr);
			
			// Set the world ID
			$hex->world_id = $this->world_id;
			
			// Recalculate the x coordinate.  Not sure why they store the data this way.
			$hex->hex_x = self::convertToMapCoordinate($hex->hex_x, $hex->hex_y);
			
			// Get the Hex ID if this Hex already exists
			$hex_id = WorldMapDAO::getLocalIdFromGameId($this->db, $hex->world_id, $hex->hex_x, $hex->hex_y);
			
			// If we found it, set it so that we can update the existing hex record
			if($hex_id)
				$hex->id = $hex_id;
			
			if($hex->building_id)
				$hex->building_id = BuildingDAO::getLocalIdFromGameId($this->db, $hex->building_id);
			
			// Determine whether we should use the player ID or NPC ID
			if(isset($hex->player_id))
				$game_player_id = $hex->player_id;
			else if(isset($hex->npc_player_id))
				$game_player_id = $hex->npc_player_id;
			
			// Get the local Player ID from our database
			$player_id = PlayerDAO::getLocalIdFromGameId($this->db, $game_player_id);
			
			// Start building a new player record
			$player = new Player();
			
			// Set the local Player ID if we found one
			if($player_id)
				$player->id = $player_id;
				
			// Set the world ID and game player ID
			$player->world_id = $hex->world_id;
			$player->game_player_id = $game_player_id;
			
			// If this is a town tile then we have additional information, so let's process it
			if(isset($hex->town_name)) {
				// Set the player's name and level
				$player->player_name = $hex->player_name;
				$player->level = $hex->player_level;
				
				// If this player is in a guild, process that information
				if(isset($hex->guild_id)) {
					// Get the local Guild ID from our database
					$guild_id = GuildDAO::getLocalIdFromGameId($this->db, $hex->guild_id);
					
					// If we didn't find this guild, then start building the record.
					// Otherwise, don't bother because this information won't change frequently.
					if(!$guild_id) {
						$guild = new Guild();
						$guild->world_id = $hex->world_id;
						$guild->game_guild_id = $hex->guild_id;
						$guild->guild_name = $hex->guild_name;
					
						// Insert the guild record into our database and keep the new local ID for later
						$guild_id = GuildDAO::insertGuild($this->db, $guild);
						
						if($this->db->hasError()) {
							echo 'Error inserting Guild: ';
							print_r($this->db->getError());
							echo "<br/>\r\n";
						}
					}
					// Set the player's guild
					$player->guild_id = $guild_id;
				}
			}
			
			// If this player didn't already exist in our database, create it.  Otherwise, update it.
			if(!$player_id) {
				if($game_player_id) {
					$player_id = PlayerDAO::insertPlayer($this->db, $player);
				
					if($this->db->hasError()) {
						echo 'Error inserting Player: ';
						print_r($this->db->getError());
						echo "<br/>\r\n";
					}
				}
			} else if ($player->player_name){
				$updateCount = PlayerDAO::updatePlayer($this->db, $player, array('battle_points', 'bases'));
				if($this->db->hasError()) {
					echo 'Error updating Player: ';
					print_r($this->db->getError());
					echo "<br/>\r\n";
				}
			}
			
			// Set the player ID on the hex tile to the local database ID
			if(isset($hex->player_id) || isset($hex->npc_player_id))
				$hex->player_id = $player_id;
			
			// Insert or update the hex record
			if($hex->id == null)
				$hex_id = WorldMapDAO::insertHex($this->db, $hex);
			else {
				$hex_id = WorldMapDAO::updateHex($this->db, $hex);
			}
			
			if($this->db->hasError()) {
				echo 'Error inserting Hex: ';
				print_r($this->db->getError());
				echo "<br/>\r\n";
			}
		}
		
		echo "Created $hex_count Hexes<br/>\r\n";
	}
	
	// Get the leaderboard and save the information found
	// Leaderboard IDs:
	//   1 = Players
	//   2 = Alliances
	function GetLeaderboard($leaderboard_id = 1, $start_index = 0) {
		/*
		POST /hc//index.php/json_gateway?svc=BatchController.call HTTP/1.1
		Accept: application/json
		Content-type: application/json; charset=UTF-8;
		X-Signature: fbf25ddcca6cae202309ada2d9e8e558
		X-Timestamp: 1410316979
		User-Agent: Dalvik/1.6.0 (Linux; U; Android 4.4.2; SCH-I545 Build/KOT49H)
		Host: gcand.gree-apps.net
		Connection: Keep-Alive
		Accept-Encoding: gzip
		Content-Length: 567

		[{"transaction_time":"1410316979106","platform":"android","session_id":"9389400","start_sequence_num":1,"iphone_udid":"66d2ea9883993b46e9f06fba6e652302","wd_player_id":0,"locale":"en-US","_explicitType":"Session","client_build":"251","game_name":"HCGame","api_version":"1","mac_address":"40:0E:85:0D:77:29","end_sequence_num":1,"req_id":1,"player_id":101013866213677,"language":"en","game_data_version":"hc_20140908_38941","client_version":"1.8.4"},[{"service":"leaderboard.leaderboard","method":"get_leaderboard_info","_explicitType":"Command","params":[1,0,null]}]]
		[{"transaction_time":"1410411615022","platform":"android","session_id":"9278399","start_sequence_num":1,"iphone_udid":"b0b01655f403347cea151d87a45d2746","wd_player_id":0,"locale":"en-US","_explicitType":"Session","client_build":"251","game_name":"HCGame","api_version":"1","mac_address":"48:16:7b:c2:34:0","end_sequence_num":1,"req_id":1,"player_id":101013849913487,"language":"en","game_data_version":"hc_20140908_38941","client_version":"1.8.4"},[{"service":"leaderboard.leaderboard","method":"get_leaderboard_info","_explicitType":"Command","params":[1,,null]}]]
		*/
		
		$endpoint = Constants::$call;
		$transaction_time = self::get_transaction_time();
		$session_id = $this->session_id;
		$device_id = $this->device_id;
		$mac_address = $this->mac_address;
		$player_id = $this->player_id;
		$device_platform = $this->device_platform;
		$device_version = $this->device_version;
		$device_type = $this->device_type;
		$data_string = '[{"transaction_time":"'.$transaction_time.'","platform":"'.$device_platform.'","session_id":"'.$session_id.'","start_sequence_num":1,"iphone_udid":"'.$device_id.'","wd_player_id":0,"locale":"en-US","_explicitType":"Session","client_build":"'.Constants::$client_build.'","game_name":"'.Constants::$game_name.'","api_version":"'.Constants::$api_version.'","mac_address":"'.$mac_address.'","end_sequence_num":1,"req_id":1,"player_id":'.$player_id.',"language":"en","game_data_version":"'.Constants::$game_data_version.'","client_version":"'.Constants::$client_version.'"},[{"service":"leaderboard.leaderboard","method":"get_leaderboard_info","_explicitType":"Command","params":['.$leaderboard_id.','.$start_index.',null]}]]';
		
		echo "Getting Leaderboards...<br/>\r\n";
		$result_string = self::do_curl_post_json($endpoint, $data_string);
		$result = json_decode($result_string, true);
		echo "Done!<br/>\r\n";
		
		if($this->debug_level >= 30) {
			echo '<pre>';
			print_r($result);
			echo '</pre>';
		}
		
		$leaderboard = $result['responses'][0]['return_value']['leaderboard_info']['leaderboard'];
		
		if($leaderboard_id == 1)
			$this->SavePlayerLeaderboard($leaderboard);
		else if($leaderboard_id == 2)
			$this->SaveGuildLeaderboard($leaderboard);
	}
	
	function SavePlayerLeaderboard($leader_data) {
		foreach($leader_data as $key => $leader) {
			$player = new Player();
			$player->world_id = $this->world_id;
			$player->game_player_id = $leader['player_id'];
			$player->player_name = $leader['player_name'];
			$player->battle_points = $leader['score'];
			$player->level = $leader['level'];
			$player->bases = $leader['bases'];
			
			$id = PlayerDAO::getLocalIdFromGameId($this->db, $player->game_player_id);
			if($id) {
				$player->id = $id;
				PlayerDAO::updatePlayer($this->db, $player, array('guild_id'));
			} else
				PlayerDAO::insertPlayer($this->db, $player);
				
			if($this->db->hasError()) {
				echo 'Error saving player: ';
				print_r($this->db->getError());
				echo "<br/>\r\n";
			}
		}
	}
		
	function SaveGuildLeaderboard($leader_data) {
		foreach($leader_data as $key => $leader) {
			$guild = new Guild();
			$guild->world_id = $this->world_id;
			$guild->game_guild_id = $leader['guild_id'];
			if($leader_id = PlayerDAO::getLocalIdFromGameId($this->db, $leader['owner_id']))
				$guild->leader_id = $leader_id;
			$guild->guild_name = $leader['guild_name'];
			$guild->battle_points = $leader['score'];
			$guild->members = $leader['member_count'];

			$id = GuildDAO::getLocalIdFromGameId($this->db, $guild->game_guild_id);
			if($id) {
				$guild->id = $id;
				GuildDAO::updateGuild($this->db, $guild);
			} else
				GuildDAO::insertGuild($this->db, $guild);

			if($this->db->hasError()) {
				echo 'Error saving guild: ';
				print_r($this->db->getError());
				echo "<br/>\r\n";
			}
		}
	}
}

?>