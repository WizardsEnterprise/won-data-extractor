<?php
require_once('data/DataLoadLog.class.php');
require_once('data/WorldMap.class.php');
require_once('data/Player.class.php');
require_once('data/Guild.class.php');
require_once('data/Building.class.php');

class WorldMapExtractor {
	// Database
	public $db;
	
	// Data Extractor
	public $de;
	
	// Current Data Load ID
	public $data_load_id;
	
	// Authentication Information
	private $auth;
	
	function __construct($db, $de, $dlid, $auth) {
		$this->db = $db;
		$this->de = $de;
		$this->data_load_id = $dlid;
		$this->auth = $auth;
	}
	
	public function setDataLoadId($dlid) {
		$this->data_load_id = $dlid;
	}
	
	private static function convertToMapCoordinate($x, $y) {
		return $x + floor($y/2);
	}
	
	private static function convertFromMapCoordinate($x, $y) {
		return $x - floor($y/2);
	}
	
	// Call the web service to get the world map and then parse and save it in the database
	public function GetWorldMap($x_start = 0, $y_start = 0, $x_range = 100, $y_range = 100) {
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
		
		$log_seq = 0;
		$descriptor = "World: {$this->auth->world_id}\r\n(Map Coordinates)\r\nX: $x_start, Y: $y_start \r\n(Range)\r\nX_RANGE: $x_range, Y_RANGE: $y_range";
		$func_args = func_get_args();
		$func_log_id = DataLoadLogDAO::startFunction($this->db, $this->data_load_id, __CLASS__,  __FUNCTION__, $func_args);

		DataLoadLogDAO::logEvent2($this->db, $func_log_id, $log_seq++, 'INFO', $descriptor);


		echo "Getting World Map...\r\n";
		
		$x_start = self::convertFromMapCoordinate($x_start, $y_start);
		$params = array();
		$params['x_start'] = $x_start;
		$params['y_start'] = $y_start;
		$params['x_range'] = $x_range;
		$params['y_range'] = $y_range;
		//$data_string = '[{"transaction_time":"'.$transaction_time.'","platform":"'.$device_platform.'","session_id":"'.$session_id.'","start_sequence_num":1,"iphone_udid":"'.$device_id.'","wd_player_id":0,"locale":"en-US","_explicitType":"Session","client_build":"'.Constants::$client_build.'","game_name":"'.Constants::$game_name.'","api_version":"'.Constants::$api_version.'","mac_address":"'.$mac_address.'","end_sequence_num":1,"req_id":1,"player_id":'.$player_id.',"language":"en","game_data_version":"'.Constants::$game_data_version.'","client_version":"'.Constants::$client_version.'"},[{"service":"world.world","method":"get_map_data","_explicitType":"Command","params":[[['.$x_start.','.$y_start.','.$x_range.','.$y_range.']]]}]]';
		
		DataLoadLogDAO::logEvent2($this->db, $func_log_id, $log_seq++, 'DEBUG', "Converted Database Coordinates X: $x_start, Y: $y_start");

		$retry_count = 0;
		
		$result = $this->de->MakeRequest('GET_MAP_DATA', $params);
		
		if($result === false) {
			DataLoadLogDAO::completeFunction($this->db, $func_log_id, 'Error getting map data.  See ws request log.', 1);
			return false;
		}
		
		// This will return false if there's no useable data in the response and we'll try again
		$status = $this->ParseAndSaveWorldMap($result, $descriptor);
		
		DataLoadLogDAO::completeFunction($this->db, $func_log_id, 'Map Extracted Successfully');
		return true;
	}
	
	public function ParseAndSaveWorldMap($world_response, $descriptor) {
		$log_seq = 0;
		$func_args = func_get_args();
		$func_args[0] = 'Removed Hex Array. See WS Request Log.';
		$func_log_id = DataLoadLogDAO::startFunction($this->db, $this->data_load_id, __CLASS__,  __FUNCTION__, $func_args);

		DataLoadLogDAO::logEvent2($this->db, $func_log_id, $log_seq++, 'INFO', $descriptor);

		// Get the Hexes section of the world response
		$success = $world_response['responses'][0]['return_value']['success'];
		
		if($success != 1) {
			$reason = $world_response['responses'][0]['return_value']['reason'];			
			DataLoadLogDAO::completeFunction($this->db, $func_log_id, "Error Getting Map Data: $reason", 1);
			// Even though this is technically a failure, we return true because we don't want to retry this request
			return true;
		}
		
		$world = $world_response['responses'][0]['return_value']['hexes'];
		$hex_count = 0;
		
		// Sometimes this happens but I'm not sure why... so I created a return code to try again...
		if(empty($world)) {
			DataLoadLogDAO::completeFunction($this->db, $func_log_id, 'No Hexes Found', 1);

			echo "No hexes found.\r\n";
			return false;
		}

		// Parse and store each Hex
		foreach($world as $key => $hex_arr) {
			$hex_count++;
			
			// Take the array and create the Hex object
			$hex = Hex::FromJson($hex_arr);
			$hex->data_load_id = $this->data_load_id;
			
			// Set the world ID
			$hex->world_id = $this->auth->world_id;
			
			// Recalculate the x coordinate.  Not sure why they store the data this way.
			$hex->hex_x = (int)self::convertToMapCoordinate($hex->hex_x, $hex->hex_y);
			
			// Check if this hex already exists so we know whether we're inserting or updating
			$hex_exists = WorldMapDAO::checkHexExists($this->db, $hex->world_id, $hex->hex_x, $hex->hex_y);
			
			if($hex->building_id)
				$hex->building_id = BuildingDAO::getLocalIdFromGameId($this->db, $hex->building_id);
			
			// Determine whether we should use the player ID or NPC ID
			if(isset($hex->player_id))
				$game_player_id = $hex->player_id;
			else if(isset($hex->npc_player_id))
				$game_player_id = $hex->npc_player_id;
			else
				$game_player_id = null;
			
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

			// Initialize flags to NULL
			$hex->is_sb = null;
			$hex->is_npc = null;
			
			// If this is a town tile then we have additional information, so let's process it
			if(isset($hex->town_name) || in_array($hex->building_id, array(14))) {
				$hex->command_center = true;

				// Set base properties
				$hex->is_sb = ($hex->town_radius == 2 and $hex->building_id == 1) ? 1 : 0;
				$hex->is_npc = $hex->town_name === 'Renegade Outpost' ? 1 : 0;
				
				// Set the player's name and level
				$player->player_name = $hex->player_name;
				$player->level = $hex->player_level;
				$player->data_load_id = $this->data_load_id;
				
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
						$guild->data_load_id = $this->data_load_id;
					
						// Insert the guild record into our database and keep the new local ID for later
						$guild_id = GuildDAO::insertGuild($this->db, $guild);
						
						if($this->db->hasError()) {
							echo 'Error inserting Guild: ';
							print_r($this->db->getError());
							$log_msg = print_r($guild, true)."\r\n\r\n".print_r($this->db->getError(), true);
							DataLoadLogDAO::logEvent2($this->db, $func_log_id, $log_seq++, 'ERROR', "Error inserting Guild [{$guild->guild_name}] into World [{$guild->world_id}]", $log_msg, 1);
							echo "\r\n";
						}
					}
					// Set the player's guild
					$player->guild_id = $guild_id;
				}
			}
			
			// If this player didn't already exist in our database, create it.  Otherwise, update it.
			if(!$player_id) {
				if($game_player_id) {
					//DataLoadLogDAO::logEvent2($this->db, $func_log_id, $log_seq++, 'DEBUG', "Inserting Player [{$player->player_name}] into World [{$player->world_id}]", var_export($player, true));

					$player_id = PlayerDAO::insertPlayer($this->db, $player);
					
					if($this->db->hasError()) {
						echo 'Error inserting Player: ';
						print_r($this->db->getError());
						echo "\r\n";
						
						$log_msg = print_r($player, true)."\r\n\r\n".print_r($this->db->getError(), true);
						DataLoadLogDAO::logEvent2($this->db, $func_log_id, $log_seq++, 'ERROR', "Error inserting Player [{$player->player_name}] into World [{$player->world_id}]", $log_msg, 1);

					}
				}
			} else if ($player->player_name){
				//DataLoadLogDAO::logEvent2($this->db, $func_log_id, $log_seq++, 'DEBUG', "Updating Player [{$player->player_name}] in World [{$player->world_id}]", var_export($player, true));

				// Update the player, but exclude battle points, power, and the number of bases because these aren't available in this case
				$updateCount = PlayerDAO::updatePlayer($this->db, $player, array('battle_points', 'glory_points', 'bases'));
				
				if($this->db->hasError()) {
					echo 'Error updating Player: ';
					print_r($this->db->getError());
					echo "\r\n";
					
					$log_msg = var_export($player, true)."\r\n\r\n".print_r($this->db->getError(), true);
					DataLoadLogDAO::logEvent2($this->db, $func_log_id, $log_seq++, 'ERROR', "Error updating Player [{$player->player_name}] in World [{$player->world_id}]", $log_msg, 1);
				}
			}
			
			// Set the player ID on the hex tile to the local database ID
			if(isset($hex->player_id) || isset($hex->npc_player_id))
				$hex->player_id = $player_id;
			
			// Insert or update the hex record
			if($hex_exists == false) {
				$hex_id = WorldMapDAO::insertHex($this->db, $hex);
				if($this->db->hasError()) {
					echo 'Error inserting Hex: ';
					print_r($this->db->getError());
					echo "\r\n";
					
					$log_msg = var_export($hex, true)."\r\n\r\n".print_r($this->db->getError(), true);
					DataLoadLogDAO::logEvent2($this->db, $func_log_id, $log_seq++, 'ERROR', "Error inserting hex into World {$hex->world_id}, X: {$hex->hex_x}, Y: {$hex->hex_y}", $log_msg);

				}
			} else {
				$hex_id = WorldMapDAO::updateHex($this->db, $hex);
				if($this->db->hasError()) {
					echo 'Error updating Hex: ';
					print_r($this->db->getError());
					echo "\r\n";
					
					$log_msg = var_export($hex, true)."\r\n\r\n".print_r($this->db->getError(), true);
					DataLoadLogDAO::logEvent2($this->db, $func_log_id, $log_seq++, 'ERROR', "Error updating hex in World {$hex->world_id}, X: {$hex->hex_x}, Y: {$hex->hex_y}", $log_msg, 1);

				}
			}
		}
		
		DataLoadLogDAO::completeFunction($this->db, $func_log_id, "Created $hex_count Hexes");

		echo "Created $hex_count Hexes\r\n";
		return true;
	}
}
?>