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
	private static $unit_mapping = array('Jeep' => 1011, 
										 'Helicopter' => 1003, 
										 'Hailstorm' => 1017, 
										 'Artillery' => 1004, 
										 'Railgun Tank' => 1002,
										 'Transport' => 1008,
										 'Bomber' => 1010);
	
	// Attack Type Mapping
	private static $attack_type_mapping = array('Capture' => 0, 'Attack' => 1);

	function __construct($db, $de, $dlid, $auth) {
		$this->db = $db;
		$this->de = $de;
		$this->data_load_id = $dlid;
		$this->auth = $auth;
	}
	
	public function setDataLoadId($dlid) {
		$this->data_load_id = $dlid;
	}

	public static function BuildAttackUnits($unit_list){
		// {"amount":1,"_explicitType":"units.PlayerUnit","unit_id":1011}
		$units = array();
		foreach($unit_list as $unit_name => $quantity) {
			if(!array_key_exists($unit_name, self::$unit_mapping)) {
				die("ERROR: $unit_name does not exist in unit mapping\r\n");
			}

			$units[] = array("amount" => $quantity, 
								   "_explicitType" => "units.PlayerUnit",
								   "unit_id" => self::$unit_mapping[$unit_name]);
		}

		return $units;
	}

	private function sendGenericAttack($target_player, $target_base, $target_building, $commander, $units, $attack_type) {
		$log_seq = 0;
		$func_args = func_get_args();
		$func_log_id = DataLoadLogDAO::startFunction($this->db, $this->data_load_id, __CLASS__,  __FUNCTION__, $func_args);

		$params = array();
		$params['target_player_id'] = $target_player;
		$params['target_base_id'] = $target_base;
		$params['target_building_id'] = $target_building;
		$params['commander_id'] = $commander;
		$params['units_json'] = json_encode(self::BuildAttackUnits($units), true);
		$params['attack_type'] = self::$attack_type_mapping[$attack_type];

		$result = $this->de->MakeRequest('SEND_GENERIC_ATTACK', $params);
		if(!$result) return false;

		$success = $result['responses'][0]['return_value']['success'];

		if($success != 1){
			echo "Failed to send generic attack.\r\n";

			DataLoadLogDAO::completeFunction($this->db, $func_log_id, 'Failed to send generic attack', 1);
			return false;
		}

		$army = $result['responses'][0]['return_value']['player_army'];

		DataLoadLogDAO::completeFunction($this->db, $func_log_id, "Complete");

		return $army;
	}

	public function sendCapture($target_player, $target_base, $target_building, $commander, $units) {
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
		//[{"amount":1,"_explicitType":"units.PlayerUnit","unit_id":1011},{"amount":50,"_explicitType":"units.PlayerUnit","unit_id":1003},{"amount":50,"_explicitType":"units.PlayerUnit","unit_id":1017},{"amount":849,"_explicitType":"units.PlayerUnit","unit_id":1004},{"amount":250,"_explicitType":"units.PlayerUnit","unit_id":1002}]

		$log_seq = 0;
		$func_args = func_get_args();
		$func_log_id = DataLoadLogDAO::startFunction($this->db, $this->data_load_id, __CLASS__,  __FUNCTION__, $func_args);

		echo "Sending Capture...\r\n";

		$army = $this->sendGenericAttack($target_player, $target_base, $target_building, $commander, $units, 'Capture');

		$time_to_dest = $army['delta_time_to_destination'];
		$army_id = $army['id'];

		$log_msg = "Capture en route.  Army $army_id occupation begins in $time_to_dest seconds.";
		echo "$log_msg\r\n";

		DataLoadLogDAO::completeFunction($this->db, $func_log_id, $log_msg);
		
		return $army;
	}

	public function sendAttack($target_player, $target_base, $target_building, $commander, $units) {
		//[{"transaction_time":"$$transaction_time$$","platform":"$$device_platform$$","session_id":"$$session_id$$","start_sequence_num":1,"iphone_udid":"$$device_id$$","wd_player_id":0,"locale":"en-US","_explicitType":"Session","client_build":"$$client_build$$","game_name":"$$game_name$$","api_version":"$$api_version$$","mac_address":"$$mac_address$$","end_sequence_num":1,"req_id":1,"player_id":$$player_id$$,"language":"en","game_data_version":"$$game_data_version$$","client_version":"$$client_version$$"},[{"service":"army.army","method":"send_army_to_attack","_explicitType":"Command","params":[2,[{"amount":1,"_explicitType":"units.PlayerUnit","unit_id":1011}],"$$target_player_id$$",$$target_base_id$$,$$target_building_id$$,$$commander_id$$,1]}]]
		//[{"transaction_time":"1431832773590","platform":"android","session_id":"1720258","start_sequence_num":1,"iphone_udid":"3934026952a3bb2473aee251bf29722b","wd_player_id":0,"locale":"en-US","_explicitType":"Session","client_build":"408","game_name":"HCGame","api_version":"1","mac_address":"14:10:9F:D6:7B:33","end_sequence_num":1,"req_id":1,"player_id":101013866213677,"language":"en","game_data_version":"hc_NA_20150515_52100","client_version":"2.0.2"},[{"service":"army.army","method":"send_army_to_attack","_explicitType":"Command","params":[2,[{"amount":1,"_explicitType":"units.PlayerUnit","unit_id":1011}],"101013100928452",1,1,430,1]
	
		$log_seq = 0;
		$func_args = func_get_args();
		$func_log_id = DataLoadLogDAO::startFunction($this->db, $this->data_load_id, __CLASS__,  __FUNCTION__, $func_args);

		echo "Sending Attack...\r\n";

		$army = $this->sendGenericAttack($target_player, $target_base, $target_building, $commander, $units, 'Attack');

		$time_to_dest = $army['delta_time_to_destination'];
		$army_id = $army['id'];

		$log_msg = "Attack en route.  Army $army_id lands in $time_to_dest seconds.";
		echo "$log_msg\r\n";

		DataLoadLogDAO::completeFunction($this->db, $func_log_id, $log_msg);
		return $army;
	}

	public function recallArmy($army_id) {
		//[{"transaction_time":"$$transaction_time$$","platform":"$$device_platform$$","session_id":"$$session_id$$","start_sequence_num":1,"iphone_udid":"$$device_id$$","wd_player_id":0,"locale":"en-US","_explicitType":"Session","client_build":"$$client_build$$","game_name":"$$game_name$$","api_version":"$$api_version$$","mac_address":"$$mac_address$$","end_sequence_num":1,"req_id":1,"player_id":$$player_id$$,"language":"en","game_data_version":"$$game_data_version$$","client_version":"$$client_version$$"},[{"service":"army.army","method":"send_army_to_attack","_explicitType":"Command","params":[$$army_id$$]}]]

		//[{"transaction_time":"1431832983218","platform":"android","session_id":"1720258","start_sequence_num":1,"iphone_udid":"3934026952a3bb2473aee251bf29722b","wd_player_id":0,"locale":"en-US","_explicitType":"Session","client_build":"408","game_name":"HCGame","api_version":"1","mac_address":"14:10:9F:D6:7B:33","end_sequence_num":1,"req_id":1,"player_id":101013866213677,"language":"en","game_data_version":"hc_NA_20150515_52100","client_version":"2.0.2"},[{"service":"army.army","method":"send_army_home","_explicitType":"Command","params":[1]}]
		
		$log_seq = 0;
		$func_args = func_get_args();
		$func_log_id = DataLoadLogDAO::startFunction($this->db, $this->data_load_id, __CLASS__,  __FUNCTION__, $func_args);

		echo "Recalling Army...\r\n";

		$params = array(); 
		$params['army_id'] = $army_id;
		$result = $this->de->MakeRequest('RECALL_ARMY', $params);
		if(!$result) return false;

		$success = $result['responses'][0]['return_value']['success'];

		if($success != 1){
			$reason = $result['responses'][0]['return_value']['reason'];
			echo "Failed to recall army. [$reason]\r\n";

			DataLoadLogDAO::completeFunction($this->db, $func_log_id, 'Failed to recall army: $reason', 1);

			return $reason;
		}

		$army = $result['responses'][0]['return_value']['player_deployed_army'];
		$time_to_dest = $army['delta_time_to_destination'];
		$army_id = $army['id'];

		echo "Army recalled.  Army $army_id returns in $time_to_dest seconds.\r\n";

		DataLoadLogDAO::completeFunction($this->db, $func_log_id, "Attack recalled.  Army $army_id returns in $time_to_dest seconds.");
		
		return $army;
	}

	public function SyncPlayer() {
		//[{"transaction_time":"$$transaction_time$$","platform":"$$device_platform$$","session_id":"$$session_id$$","start_sequence_num":1,"iphone_udid":"$$device_id$$","wd_player_id":0,"locale":"en-US","_explicitType":"Session","client_build":"$$client_build$$","game_name":"$$game_name$$","api_version":"$$api_version$$","mac_address":"$$mac_address$$","end_sequence_num":1,"req_id":1,"player_id":$$player_id$$,"language":"en","game_data_version":"$$game_data_version$$","client_version":"$$client_version$$"},[{"service":"players.players","method":"sync","_explicitType":"Command","params":[]}]]

		//[{"transaction_time":"1431832617484","platform":"android","session_id":"1720258","start_sequence_num":1,"iphone_udid":"3934026952a3bb2473aee251bf29722b","wd_player_id":110002973568008,"locale":"en-US","_explicitType":"Session","client_build":"408","game_name":"HCGame","api_version":"1","mac_address":"14:10:9F:D6:7B:33","end_sequence_num":1,"req_id":1,"player_id":101013866213677,"language":"en","game_data_version":"hc_NA_20150515_52100","client_version":"2.0.2"},[{"service":"players.players","method":"sync","_explicitType":"Command","params":[]}]]
	
		$log_seq = 0;
		$func_args = func_get_args();
		$func_log_id = DataLoadLogDAO::startFunction($this->db, $this->data_load_id, __CLASS__,  __FUNCTION__, $func_args);

		echo "Syncing Player...\r\n";

		$result = $this->de->MakeRequest('SYNC_PLAYER');
		if(!$result) return false;

		echo "Player Synced\r\n";

		DataLoadLogDAO::completeFunction($this->db, $func_log_id, 'Player Synced');

		return $result;
	}

	public function PurchaseItem($item_id, $item_quantity) {
		// [{"transaction_time":"1448653971391","platform":"android","session_id":"1040817","start_sequence_num":0,"iphone_udid":"41dae559cd8e4c057fe757a2f3057d38","wd_player_id":0,"locale":"en-US","_explicitType":"Session","client_build":"489","game_name":"HCGame","api_version":"1","mac_address":"14:10:9F:D6:7B:33","end_sequence_num":0,"req_id":1,"player_id":101004817631728,"language":"en","game_data_version":"hc_NA_20151126_60629","client_version":"2.5.3.1"},[{"service":"items.items","method":"buy","_explicitType":"Command","params":["180000",2]}]]
		// [{"transaction_time":"$$transaction_time$$","platform":"$$device_platform$$","session_id":"$$session_id$$","start_sequence_num":1,"iphone_udid":"$$device_id$$","wd_player_id":0,"locale":"en-US","_explicitType":"Session","client_build":"$$client_build$$","game_name":"$$game_name$$","api_version":"$$api_version$$","mac_address":"$$mac_address$$","end_sequence_num":0,"req_id":1,"player_id":$$player_id$$,"language":"en","game_data_version":"$$game_data_version$$","client_version":"$$client_version$$"},[[{"service":"items.items","method":"buy","_explicitType":"Command","params":["$$item_id$$",$$item_quantity$$]}]]
	
		$log_seq = 0;
		$func_args = func_get_args();
		$func_log_id = DataLoadLogDAO::startFunction($this->db, $this->data_load_id, __CLASS__,  __FUNCTION__, $func_args);

		echo "Buying item [$item_id]...\r\n";

		$params = array();
		$params['item_id'] = $item_id;
		$params['item_quantity'] = $item_quantity;
		
		$result = $this->de->MakeRequest('PURCHASE_ITEM', $params);
		if(!$result) return false;
		
		echo "Purchased!\r\n\r\n";

		DataLoadLogDAO::completeFunction($this->db, $func_log_id, "Successfully Purchased [$item_quantity] of Item [$item_id]");

		return $result;
	}

	public function ChangeName($new_name) {
		// [{"transaction_time":"1448668562530","platform":"android","session_id":"8888851","start_sequence_num":0,"iphone_udid":"41dae559cd8e4c057fe757a2f3057d38","wd_player_id":0,"locale":"en-US","_explicitType":"Session","client_build":"489","game_name":"HCGame","api_version":"1","mac_address":"14:10:9F:D6:7B:33","end_sequence_num":0,"req_id":1,"player_id":101004817631728,"language":"en","game_data_version":"hc_NA_20151126_60629","client_version":"2.5.3.1"},[{"service":"players.players","method":"update_name","_explicitType":"Command","params":["test001"]}]]
	
		$log_seq = 0;
		$func_args = func_get_args();
		$func_log_id = DataLoadLogDAO::startFunction($this->db, $this->data_load_id, __CLASS__,  __FUNCTION__, $func_args);

		echo "Changing Player {$this->player_id} to $new_name...\r\n";

		$params = array();
		$params['new_name'] = $new_name;
		$result = $this->de->MakeRequest('CHANGE_NAME', $params);
		if(!$result) return false;

		echo "Changed!\r\n\r\n";

		DataLoadLogDAO::completeFunction($this->db, $func_log_id, "Successfully changed player name to [$new_name]");

		return $result;
	}

	public function RequestAlliance($alliance_id) {
		// [{"transaction_time":"1448668572831","platform":"android","session_id":"8888851","start_sequence_num":0,"iphone_udid":"41dae559cd8e4c057fe757a2f3057d38","wd_player_id":0,"locale":"en-US","_explicitType":"Session","client_build":"489","game_name":"HCGame","api_version":"1","mac_address":"14:10:9F:D6:7B:33","end_sequence_num":0,"req_id":1,"player_id":101004817631728,"language":"en","game_data_version":"hc_NA_20151126_60629","client_version":"2.5.3.1"},[{"service":"guilds.guilds","method":"send_join_request","_explicitType":"Command","params":["101004319067916"]}]]
	
		$log_seq = 0;
		$func_args = func_get_args();
		$func_log_id = DataLoadLogDAO::startFunction($this->db, $this->data_load_id, __CLASS__,  __FUNCTION__, $func_args);

		echo "Requesting to Join Alliance [$alliance_id]...\r\n";

		$params = array();
		$params['guild_id'] = $alliance_id;
		$result = $this->de->MakeRequest('REQUEST_ALLIANCE', $params);
		if(!$result) return false;

		echo "Requested!\r\n\r\n";

		DataLoadLogDAO::completeFunction($this->db, $func_log_id, "Successfully requested Alliance [$alliance_id]");

		return $result;
	}

	public function AcceptAllianceRequest($requesting_player_id) {
		// [{"transaction_time":"1448670738260","platform":"android","session_id":"3991521","start_sequence_num":1,"iphone_udid":"90c64c2d561d472f3610073f87b091bb","wd_player_id":110001058551003,"locale":"en-US","_explicitType":"Session","client_build":"489","game_name":"HCGame","api_version":"1","mac_address":"14:10:9F:D6:7B:33","end_sequence_num":1,"req_id":1,"player_id":101013866213677,"language":"en","game_data_version":"hc_NA_20151126_60629","client_version":"2.5.3.1"},[{"service":"guilds.guilds","method":"accept_join_request","_explicitType":"Command","params":["101013624609676"]}]]
		// [{"transaction_time":"1448670908805","platform":"android","session_id":"3991521","start_sequence_num":1,"iphone_udid":"90c64c2d561d472f3610073f87b091bb","wd_player_id":110001058551003,"locale":"en-US","_explicitType":"Session","client_build":"489","game_name":"HCGame","api_version":"1","mac_address":"14:10:9F:D6:7B:33","end_sequence_num":1,"req_id":1,"player_id":101013866213677,"language":"en","game_data_version":"hc_NA_20151126_60629","client_version":"2.5.3.1"},[{"service":"guilds.guilds","method":"accept_join_request","_explicitType":"Command","params":["101013624609676"]}]]
	
		$log_seq = 0;
		$func_args = func_get_args();
		$func_log_id = DataLoadLogDAO::startFunction($this->db, $this->data_load_id, __CLASS__,  __FUNCTION__, $func_args);

		echo "Accepting [$requesting_player_id] into Alliance...\r\n";

		$params = array();
		$params['requesting_player_id'] = $requesting_player_id;
		$result = $this->de->MakeRequest('ACCEPT_ALLIANCE_REQUEST', $params);
		if(!$result) return false;

		echo "Accepted!\r\n\r\n";

		DataLoadLogDAO::completeFunction($this->db, $func_log_id, "Successfully accepted Player [$requesting_player_id] into Alliance");

		return $result;
	}

	public function DonateToAlliance($item_type, $item_id, $donate_qty) {
		// [{"transaction_time":"1448670125830","platform":"android","session_id":"3339135","start_sequence_num":1,"iphone_udid":"90c64c2d561d472f3610073f87b091bb","wd_player_id":110001058551003,"locale":"en-US","_explicitType":"Session","client_build":"489","game_name":"HCGame","api_version":"1","mac_address":"14:10:9F:D6:7B:33","end_sequence_num":1,"req_id":1,"player_id":101013866213677,"language":"en","game_data_version":"hc_NA_20151126_60629","client_version":"2.5.3.1"},[{"service":"guilds.guilds","method":"donate_to_guild","_explicitType":"Command","params":["resource",1,115625]}]]
		// [{"transaction_time":"1448671048102","platform":"android","session_id":"7968688","start_sequence_num":0,"iphone_udid":"8c9c72c38515837f4957843075bcac39","wd_player_id":0,"locale":"en-US","_explicitType":"Session","client_build":"489","game_name":"HCGame","api_version":"1","mac_address":"14:10:9F:D6:7B:33","end_sequence_num":0,"req_id":1,"player_id":101013624609676,"language":"en","game_data_version":"hc_NA_20151126_60629","client_version":"2.5.3.1"},[{"service":"guilds.guilds","method":"donate_to_guild","_explicitType":"Command","params":["item",180000,2]}]]
	
		$log_seq = 0;
		$func_args = func_get_args();
		$func_log_id = DataLoadLogDAO::startFunction($this->db, $this->data_load_id, __CLASS__,  __FUNCTION__, $func_args);

		echo "Donating [$donate_qty] of $item_type [$item_id]...\r\n";

		$params = array();
		$params['item_type'] = $item_type;
		$params['item_id'] = $item_id;
		$params['donate_qty'] = $donate_qty;
		$result = $this->de->MakeRequest('DONATE_TO_ALLIANCE', $params);
		if(!$result) return false;

		echo "Donated!\r\n\r\n";

		DataLoadLogDAO::completeFunction($this->db, $func_log_id, "Successfully donated [$donate_qty] of [$item_type] [$item_id] to Alliance");

		return $result;
	}

	public function LeaveAlliance() {
		// [{"transaction_time":"1448671223098","platform":"android","session_id":"7968688","start_sequence_num":0,"iphone_udid":"8c9c72c38515837f4957843075bcac39","wd_player_id":0,"locale":"en-US","_explicitType":"Session","client_build":"489","game_name":"HCGame","api_version":"1","mac_address":"14:10:9F:D6:7B:33","end_sequence_num":0,"req_id":1,"player_id":101013624609676,"language":"en","game_data_version":"hc_NA_20151126_60629","client_version":"2.5.3.1"},[{"service":"guilds.guilds","method":"leave_guild","_explicitType":"Command","params":[]}]]
	
		$log_seq = 0;
		$func_args = func_get_args();
		$func_log_id = DataLoadLogDAO::startFunction($this->db, $this->data_load_id, __CLASS__,  __FUNCTION__, $func_args);

		echo "Leaving Alliance...\r\n";

		$result = $this->de->MakeRequest('LEAVE_ALLIANCE');
		if(!$result) return false;

		echo "Left!\r\n\r\n";

		DataLoadLogDAO::completeFunction($this->db, $func_log_id, "Successfully left alliance");

		return $result;
	}

	public function SendChatMessage($chat_stream, $message) {
		// [{"transaction_time":"1448671061854","platform":"android","session_id":"7968688","start_sequence_num":0,"iphone_udid":"8c9c72c38515837f4957843075bcac39","wd_player_id":0,"locale":"en-US","_explicitType":"Session","client_build":"489","game_name":"HCGame","api_version":"1","mac_address":"14:10:9F:D6:7B:33","end_sequence_num":0,"req_id":1,"player_id":101013624609676,"language":"en","game_data_version":"hc_NA_20151126_60629","client_version":"2.5.3.1"},[{"service":"chatservice.chatservice","method":"channels","_explicitType":"Command","params":["\/guild_101013232844480","2 concrete donated"]}]]
	
		$log_seq = 0;
		$func_args = func_get_args();
		$func_log_id = DataLoadLogDAO::startFunction($this->db, $this->data_load_id, __CLASS__,  __FUNCTION__, $func_args);

		echo "Sending '$message' to [$chat_stream]...\r\n";

		$params = array();
		$params['chat_stream'] = $chat_stream;
		$params['message'] = $message;
		$result = $this->de->MakeRequest('SEND_CHAT_MESSAGE', $params);
		if(!$result) return false;

		echo "Sent!\r\n\r\n";

		DataLoadLogDAO::completeFunction($this->db, $func_log_id, "Successfully sent [$message] to [$chat_stream]");

		return $result;
	}

}



?>