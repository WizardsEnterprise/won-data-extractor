<?php
require_once('data/DataLoadLog.class.php');
require_once('data/Player.class.php');
require_once('data/Guild.class.php');

class LeaderboardExtractor {
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
		
		$log_seq = 0;
		DataLoadLogDAO::logEvent($this->db, $this->data_load_id, 'GET_LEADERBOARD', $log_seq++, 'START', "Board ID: $leaderboard_id, Start Index: $start_index", null);
		
		//$data_string = '[{"transaction_time":"'.$transaction_time.'","platform":"'.$device_platform.'","session_id":"'.$session_id.'","start_sequence_num":1,"iphone_udid":"'.$device_id.'","wd_player_id":0,"locale":"en-US","_explicitType":"Session","client_build":"'.Constants::$client_build.'","game_name":"'.Constants::$game_name.'","api_version":"'.Constants::$api_version.'","mac_address":"'.$mac_address.'","end_sequence_num":1,"req_id":1,"player_id":'.$player_id.',"language":"en","game_data_version":"'.Constants::$game_data_version.'","client_version":"'.Constants::$client_version.'"},[{"service":"leaderboard.leaderboard","method":"get_leaderboard_info","_explicitType":"Command","params":['.$leaderboard_id.','.$start_index.',null]}]]';
		
		echo "Getting Leaderboards...\r\n";
		$params = array();
		$params['leaderboard_id'] = $leaderboard_id;
		$params['start_index'] = $start_index;
		
		$result_string = $this->de->MakeRequest('GET_LEADERBOARD_DATA', $params);
		$result = json_decode($result_string, true);
		echo "Done!\r\n";
		
		DataLoadLogDAO::logEvent($this->db, $this->data_load_id, 'GET_LEADERBOARD', $log_seq++, 'RESPONSE', null, print_r($result, true));
		
		$leaderboard = $result['responses'][0]['return_value']['leaderboard_info']['leaderboard'];
		
		if($leaderboard_id == 1)
			$this->SavePlayerLeaderboard($leaderboard);
		else if($leaderboard_id == 2)
			$this->SaveGuildLeaderboard($leaderboard);
			
		DataLoadLogDAO::logEvent($this->db, $this->data_load_id, 'GET_LEADERBOARD', $log_seq++, 'COMPLETE', null, null);
	}
	
	function SavePlayerLeaderboard($leader_data) {
		$log_seq = 0;
		DataLoadLogDAO::logEvent($this->db, $this->data_load_id, 'SAVE_PLAYER_LEADERBOARD', $log_seq++, 'START', null, null);
		
		$count = 0;
		foreach($leader_data as $key => $leader) {
			$player = new Player();
			$player->world_id = $this->auth->world_id;
			$player->data_load_id = $this->data_load_id;
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
				echo "\r\n";
				
				$log_msg = var_dump($player)."\r\n\r\n".print_r($this->db->getError(), true);
				DataLoadLogDAO::logEvent($this->db, $this->data_load_id, 'SAVE_PLAYER_LEADERBOARD', $log_seq++, 'ERROR_SAVING_PLAYER', "World: {$player->world_id}, Player: {$player->player_name}", $log_msg, 1);
			} else {
				$count++;
			}
		}
		
		DataLoadLogDAO::logEvent($this->db, $this->data_load_id, 'SAVE_PLAYER_LEADERBOARD', $log_seq++, 'COMPLETE', "Saved $count Players", null);
		
	}
		
	function SaveGuildLeaderboard($leader_data) {
		$log_seq = 0;
		DataLoadLogDAO::logEvent($this->db, $this->data_load_id, 'SAVE_GUILD_LEADERBOARD', $log_seq++, 'START', null, null);
		
		$count = 0;
		foreach($leader_data as $key => $leader) {
			$guild = new Guild();
			$guild->world_id = $this->auth->world_id;
			$guild->data_load_id = $this->data_load_id;
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
				echo "\r\n";
				
				$log_msg = var_dump($player)."\r\n\r\n".print_r($this->db->getError(), true);
				DataLoadLogDAO::logEvent($this->db, $this->data_load_id, 'SAVE_GUILD_LEADERBOARD', $log_seq++, 'ERROR_SAVING_GUILD', "World: {$player->world_id}, Guild: {$guild->guild_name}", $log_msg, 1);
			} else {
				$count++;
			}
		}
		
		DataLoadLogDAO::logEvent($this->db, $this->data_load_id, 'SAVE_GUILD_LEADERBOARD', $log_seq++, 'COMPLETE', "Saved $count Guilds", null);
		
	}
}
?>