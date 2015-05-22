<?php

class GameOperations {
	// Database
	public $db;
	
	// Data Extractor
	public $de;
	
	// Current Data Load ID
	public $data_load_id;
	
	// Authentication Information
	private $auth;

	// Unit Mapping
	private $unit_mapping = array();
	
	function __construct($db, $de, $dlid, $auth) {
		$this->db = $db;
		$this->de = $de;
		$this->data_load_id = $dlid;
		$this->auth = $auth;
	}
	
	public function setDataLoadId($dlid) {
		$this->data_load_id = $dlid;
	}

	private function BuildAttack($unit_list){
		// {"amount":1,"_explicitType":"units.PlayerUnit","unit_id":1011}
		$units = array();
		foreach($unit_list as $unit_name => $quantity) {
			$unit_struct[] = array("amount" => $quantity, 
								   "_explicitType" => "units.PlayerUnit",
								   "unit_id" => $this->unit_mapping[$unit_name]);
		}

		return $units;

	}

	public function sendCapture($target_player, $target_base, $target_building, $commander) {
		//POST /hc//index.php/json_gateway?svc=BatchController.call HTTP/1.1
		//x-newrelic-id: UgMDWFFADQYCUFFUBw==
		//Accept: application/json
		//Content-type: application/json; charset=UTF-8;
		//X-Signature: a5c386a0d52b613f929996f59c88522f
		//X-Timestamp: 1431832292
		//User-Agent: Dalvik/1.6.0 (Linux; U; Android 4.2.2; Droid4X-MAC Build/JDQ39E)
		//Host: gcand.gree-apps.net
		//Connection: Keep-Alive
		//Accept-Encoding: gzip
		//Content-Length: 913

		//1011: Jeep
		//1003: Helicopter
		//1017: Hailstorm
		//1004: Artillery
		//1002: Railgun Tank

		//[{"transaction_time":"$$transaction_time$$","platform":"$$device_platform$$","session_id":"$$session_id$$","start_sequence_num":1,"iphone_udid":"$$device_id$$","wd_player_id":0,"locale":"en-US","_explicitType":"Session","client_build":"$$client_build$$","game_name":"$$game_name$$","api_version":"$$api_version$$","mac_address":"$$mac_address$$","end_sequence_num":1,"req_id":1,"player_id":$$player_id$$,"language":"en","game_data_version":"$$game_data_version$$","client_version":"$$client_version$$"},[{"service":"army.army","method":"send_army_to_attack","_explicitType":"Command","params":[2,[{"amount":1,"_explicitType":"units.PlayerUnit","unit_id":1011},{"amount":50,"_explicitType":"units.PlayerUnit","unit_id":1003},{"amount":49,"_explicitType":"units.PlayerUnit","unit_id":1017},{"amount":850,"_explicitType":"units.PlayerUnit","unit_id":1004},{"amount":250,"_explicitType":"units.PlayerUnit","unit_id":1002}],"$$target_player_id$$",$$target_base_id$$,$$target_building_id$$,$$commander_id$$,0]}]]
		$log_seq = 0;
		DataLoadLogDAO::logEvent($this->db, $this->data_load_id, 'SEND_CAPTURE', $log_seq++, 'START', null, null);
		
		echo "Sending Capture...\r\n";

		$params = array();
		$params['target_player_id'] = $target_player;
		$params['target_base_id'] = $target_base;
		$params['target_building_id'] = $target_building;
		$params['commander_id'] = $commander;

		$result_string = $this->de->MakeRequest('SEND_CAPTURE', $params);
		if(!$result_string) return false;

		$result = json_decode($result_string, true);

		DataLoadLogDAO::logEvent($this->db, $this->data_load_id, 'SEND_CAPTURE', $log_seq++, 'RESPONSE', null, print_r($result, true));

		$success = $result['responses'][0]['return_value']['success'];

		if($success != 1){
			DataLoadLogDAO::logEvent($this->db, $this->data_load_id, 'SEND_CAPTURE', $log_seq++, 'FAILED', $result['responses'][0]['return_value']['reason'], null);
			echo "Failed to send capture.\r\n";
			return false;
		}

		$army = $result['responses'][0]['return_value']['player_army'];
		$time_to_dest = $army['delta_time_to_destination'];
		$army_id = $army['id'];

		$log_msg = "Capture en route.  Army $army_id occupation begins in $time_to_dest seconds.";
		echo "$log_msg\r\n";

		DataLoadLogDAO::logEvent($this->db, $this->data_load_id, 'SEND_CAPTURE', $log_seq++, 'COMPLETE', $log_msg, null);

		return $army;
	}

	public function sendAttack($target_player, $target_base, $target_building, $commander) {
		//[{"transaction_time":"$$transaction_time$$","platform":"$$device_platform$$","session_id":"$$session_id$$","start_sequence_num":1,"iphone_udid":"$$device_id$$","wd_player_id":0,"locale":"en-US","_explicitType":"Session","client_build":"$$client_build$$","game_name":"$$game_name$$","api_version":"$$api_version$$","mac_address":"$$mac_address$$","end_sequence_num":1,"req_id":1,"player_id":$$player_id$$,"language":"en","game_data_version":"$$game_data_version$$","client_version":"$$client_version$$"},[{"service":"army.army","method":"send_army_to_attack","_explicitType":"Command","params":[2,[{"amount":1,"_explicitType":"units.PlayerUnit","unit_id":1011}],"$$target_player_id$$",$$target_base_id$$,$$target_building_id$$,$$commander_id$$,1]}]]
		//[{"transaction_time":"1431832773590","platform":"android","session_id":"1720258","start_sequence_num":1,"iphone_udid":"3934026952a3bb2473aee251bf29722b","wd_player_id":0,"locale":"en-US","_explicitType":"Session","client_build":"408","game_name":"HCGame","api_version":"1","mac_address":"14:10:9F:D6:7B:33","end_sequence_num":1,"req_id":1,"player_id":101013866213677,"language":"en","game_data_version":"hc_NA_20150515_52100","client_version":"2.0.2"},[{"service":"army.army","method":"send_army_to_attack","_explicitType":"Command","params":[2,[{"amount":1,"_explicitType":"units.PlayerUnit","unit_id":1011}],"101013100928452",1,1,430,1]
	
		$log_seq = 0;
		DataLoadLogDAO::logEvent($this->db, $this->data_load_id, 'SEND_ATTACK', $log_seq++, 'START', null, null);
		
		echo "Sending Attack...\r\n";

		$params = array(); 
		$params['target_player_id'] = $target_player;
		$params['target_base_id'] = $target_base;
		$params['target_building_id'] = $target_building;
		$params['commander_id'] = $commander;

		$result_string = $this->de->MakeRequest('SEND_ATTACK', $params);
		if(!$result_string) return false;

		$result = json_decode($result_string, true);

		DataLoadLogDAO::logEvent($this->db, $this->data_load_id, 'SEND_ATTACK', $log_seq++, 'RESPONSE', null, print_r($result, true));

		$success = $result['responses'][0]['return_value']['success'];

		if($success != 1){
			DataLoadLogDAO::logEvent($this->db, $this->data_load_id, 'SEND_ATTACK', $log_seq++, 'FAILED', $result['responses'][0]['return_value']['reason'], null);
			echo "Failed to send attack.\r\n";
			return false;
		}

		$army = $result['responses'][0]['return_value']['player_army'];
		$time_to_dest = $army['delta_time_to_destination'];
		$army_id = $army['id'];

		$log_msg = "Attack en route.  Army $army_id lands in $time_to_dest seconds.";
		echo "$log_msg\r\n";

		DataLoadLogDAO::logEvent($this->db, $this->data_load_id, 'SEND_ATTACK', $log_seq++, 'COMPLETE', $log_msg, null);

		return $army;
	}

	public function recallArmy($army_id) {
		//[{"transaction_time":"$$transaction_time$$","platform":"$$device_platform$$","session_id":"$$session_id$$","start_sequence_num":1,"iphone_udid":"$$device_id$$","wd_player_id":0,"locale":"en-US","_explicitType":"Session","client_build":"$$client_build$$","game_name":"$$game_name$$","api_version":"$$api_version$$","mac_address":"$$mac_address$$","end_sequence_num":1,"req_id":1,"player_id":$$player_id$$,"language":"en","game_data_version":"$$game_data_version$$","client_version":"$$client_version$$"},[{"service":"army.army","method":"send_army_to_attack","_explicitType":"Command","params":[$$army_id$$]}]]

		//[{"transaction_time":"1431832983218","platform":"android","session_id":"1720258","start_sequence_num":1,"iphone_udid":"3934026952a3bb2473aee251bf29722b","wd_player_id":0,"locale":"en-US","_explicitType":"Session","client_build":"408","game_name":"HCGame","api_version":"1","mac_address":"14:10:9F:D6:7B:33","end_sequence_num":1,"req_id":1,"player_id":101013866213677,"language":"en","game_data_version":"hc_NA_20150515_52100","client_version":"2.0.2"},[{"service":"army.army","method":"send_army_home","_explicitType":"Command","params":[1]}]
		
		$log_seq = 0;
		DataLoadLogDAO::logEvent($this->db, $this->data_load_id, 'RECALL_ARMY', $log_seq++, 'START', null, null);
		
		echo "Recalling Army...\r\n";

		$params = array(); 
		$params['army_id'] = $army_id;

		$result_string = $this->de->MakeRequest('RECALL_ARMY', $params);
		if(!$result_string) return false;

		$result = json_decode($result_string, true);

		DataLoadLogDAO::logEvent($this->db, $this->data_load_id, 'RECALL_ARMY', $log_seq++, 'RESPONSE', null, print_r($result, true));

		$success = $result['responses'][0]['return_value']['success'];

		if($success != 1){
			DataLoadLogDAO::logEvent($this->db, $this->data_load_id, 'RECALL_ARMY', $log_seq++, 'FAILED', $result['responses'][0]['return_value']['reason'], null);
			echo "Failed to recall army.\r\n";
			return false;
		}

		$army = $result['responses'][0]['return_value']['player_deployed_army'];
		$time_to_dest = $army['delta_time_to_destination'];
		$army_id = $army['id'];

		echo "Army recalled.  Army $army_id returns in $time_to_dest seconds.\r\n";

		DataLoadLogDAO::logEvent($this->db, $this->data_load_id, 'RECALL_ARMY', $log_seq++, 'COMPLETE', "Attack recalled.  Army $army_id returns in $time_to_dest seconds.", null);

		return $army;
	}

	public function SyncPlayer() {
		//[{"transaction_time":"$$transaction_time$$","platform":"$$device_platform$$","session_id":"$$session_id$$","start_sequence_num":1,"iphone_udid":"$$device_id$$","wd_player_id":0,"locale":"en-US","_explicitType":"Session","client_build":"$$client_build$$","game_name":"$$game_name$$","api_version":"$$api_version$$","mac_address":"$$mac_address$$","end_sequence_num":1,"req_id":1,"player_id":$$player_id$$,"language":"en","game_data_version":"$$game_data_version$$","client_version":"$$client_version$$"},[{"service":"players.players","method":"sync","_explicitType":"Command","params":[]}]]

		//[{"transaction_time":"1431832617484","platform":"android","session_id":"1720258","start_sequence_num":1,"iphone_udid":"3934026952a3bb2473aee251bf29722b","wd_player_id":110002973568008,"locale":"en-US","_explicitType":"Session","client_build":"408","game_name":"HCGame","api_version":"1","mac_address":"14:10:9F:D6:7B:33","end_sequence_num":1,"req_id":1,"player_id":101013866213677,"language":"en","game_data_version":"hc_NA_20150515_52100","client_version":"2.0.2"},[{"service":"players.players","method":"sync","_explicitType":"Command","params":[]}]]
	
		$log_seq = 0;
		DataLoadLogDAO::logEvent($this->db, $this->data_load_id, 'SYNC_PLAYER', $log_seq++, 'START', null, null);
		
		echo "Syncing Player...\r\n";

		$result_string = $this->de->MakeRequest('SYNC_PLAYER');
		if(!$result_string) return false;

		$result = json_decode($result_string, true);

		DataLoadLogDAO::logEvent($this->db, $this->data_load_id, 'SYNC_PLAYER', $log_seq++, 'RESPONSE', null, print_r($result, true));

		echo "Player Synced\r\n";

		DataLoadLogDAO::logEvent($this->db, $this->data_load_id, 'SYNC_PLAYER', $log_seq++, 'COMPLETE', "Player Synced", null);

		return $result;
	}

}



?>