<?php
class DataLoadLogDAO {
	public static function logEvent($db, $load_id, $event_func, $sequence_id, $event_type, $event_desc, $event_txt, $is_error = 0) {
		//echo "data_load_log: $load_id, $event_func, $sequence_id, $event_type, $event_desc, $event_txt";
		$id = $db->Insert("INSERT INTO data_load_log (data_load_id, event_func, sequence_id, event_type, event_desc, event_txt, is_error) VALUES (?, ?, ?, ?, ?, ?, ?)", array($load_id, $event_func, $sequence_id, $event_type, $event_desc, $event_txt, $is_error));
		if($db->hasError()) {
			echo 'Error inserting log: ';
			print_r($db->getError());
			echo "\r\n";
		}
		return $id;
	}

	public static function startFunction($db, $load_id, $class_name, $func_name, $parms_array = array()) {
		$params = array();
		$params[] = $load_id;
		$params[] = $class_name;
		$params[] = $func_name;
		$params[] = print_r($parms_array, true);
		
		$id = $db->Insert("INSERT INTO data_load_func_log (data_load_id, class_name, func_name, func_parms) VALUES (?, ?, ?, ?)", $params);
	
		if($db->hasError()) {
			echo 'Error STARTING function log: ';
			print_r($db->getError());
			echo "\r\n";
		}

		return $id;
	}

	public static function completeFunction($db, $func_id, $func_result, $errored = 0) {
		$params = array();
		$params[] = $func_result;
		$params[] = $errored;
		$params[] = $func_id;
		
		$rows_updated = $db->Update("UPDATE data_load_func_log SET func_result = ?, errored = ? WHERE id = ?", $params);
	
		if($db->hasError()) {
			echo 'Error COMPLETING function log: ';
			print_r($db->getError());
			echo "\r\n";
		}

		if($rows_updated > 1) 
			echo "WARNING: More than 1 function log row updated! id = [$func_id]\r\n";

		if($rows_updated == 0) 
			echo "WARNING: No function log rows updated! id = [$func_id]\r\n";

		return $rows_updated;
	}

	public static function logEvent2($db, $func_id, $seq, $level, $text, $long_text = null, $errored = 0) {
		$params = array();
		$params[] = $func_id;
		$params[] = $seq;
		$params[] = $level;
		$params[] = $text;
		$params[] = $long_text === null ? 0 : 1;
		$params[] = $errored;

		$log_id = $db->Insert("INSERT INTO data_load_log2 (data_load_func_id, log_seq, log_level, log_text, has_long_text, errored) VALUES (?, ?, ?, ?, ?, ?)", $params);
		if($db->hasError()) {
			echo 'Error INSERTING log: ';
			print_r($db->getError());
			echo "\r\n";
		}

		if($long_text !== null) {
			$params = array();
			$params[] = $log_id;
			$params[] = $long_text;

			$text_id = $db->Insert("INSERT INTO data_load_log_txt (data_load_log_id, long_text) VALUES (?, ?)", $params);
			if($db->hasError()) {
				echo 'Error INSERTING long text: ';
				print_r($db->getError());
				echo "\r\n";
			}
		}

		return $log_id;
	}

	public static function LogWebserviceRequest($db, $func_id, $url, $method = null, $proxy_array = false, $header_array = false, $data = null) {
		$params = array();
		$params[] = $func_id;
		$params[] = $url;
		$params[] = $method;
		$params[] = $proxy_array === false ? null : "{$proxy_array['ip_address']}:{$proxy_array['port']}";
		$params[] = $header_array === false ? null : print_r($header_array, true);
		$params[] = $data;

		$id = $db->Insert("INSERT INTO data_load_ws_log (data_load_func_id, url, request_method, proxy, request_headers, request_data) VALUES (?, ?, ?, ?, ?, ?)", $params);
		
		if($db->hasError()) {
			echo 'Error INSERTING web service request: ';
			print_r($db->getError());
			echo "\r\n";
		}

		return $id;
	}

	public static function LogWebserviceResponse($db, $request_id, $raw_response, $complete_seconds, $response_array = false) {
		$params = array();
		$params[] = $raw_response;
		$params[] = $complete_seconds;
		$params[] = $response_array === false ? null : print_r($response_array, true);
		$params[] = $request_id;

		$rows_updated = $db->Update("UPDATE data_load_ws_log SET response_data = ?, complete_seconds = ?, response_array = ? WHERE id = ?", $params);
		
		if($db->hasError()) {
			echo 'Error COMPLETING function log: ';
			print_r($db->getError());
			echo "\r\n";
		}

		if($rows_updated > 1) 
			echo "WARNING: More than 1 web service log row updated! id = [$request_id]\r\n";

		if($rows_updated == 0) 
			echo "WARNING: No web service log rows updated! id = [$request_id]\r\n";

		return $rows_updated;
	}
}
?>