<?php
class DataLoadDAO {
	public static function getAllDataLoads($db) {
		return $db->Select("SELECT * FROM data_loads");
	}
	
	public static function initNewLoad($db, $load_type, $total_operations, $desc = null) {
		$data_load_id = $db->Insert("INSERT INTO data_loads (load_type, load_desc, total_operations) VALUES (?, ?, ?)", array($load_type, $desc, $total_operations));
		
		if($db->hasError()) {
			echo 'Error inserting log: ';
			print_r($db->getError());
			echo "\r\n";
		}
		
		return $data_load_id;
	}
	
	public static function startLoad($db, $data_load_id) {
		$rows_updated = $db->Update("UPDATE data_loads d SET status = 'STARTED' WHERE d.id = ?", array($data_load_id));
		
		if($db->hasError()) {
			echo 'Error updating log: ';
			print_r($db->getError());
			echo "\r\n";
		}
		
		return $rows_updated;
	}
	
	public static function operationComplete($db, $data_load_id) {
		$rows_updated = $db->Update("UPDATE data_loads d SET current_operation = current_operation + 1 WHERE d.id = ?", array($data_load_id));
	
		if($db->hasError()) {
			echo 'Error updating log: ';
			print_r($db->getError());
			echo "\r\n";
		}
		
		return $rows_updated;
	}
	
	public static function loadComplete($db, $data_load_id) {
		$rows_updated = $db->Update("UPDATE data_loads d SET status = 'COMPLETED', completed = NOW() WHERE d.id = ?", array($data_load_id));
		
		if($db->hasError()) {
			echo 'Error updating log: ';
			print_r($db->getError());
			echo "\r\n";
		}
		
		return $rows_updated;
	}

	public static function loadFailed($db, $data_load_id) {
		$rows_updated = $db->Update("UPDATE data_loads d SET status = 'FAILED' WHERE d.id = ?", array($data_load_id));
		
		if($db->hasError()) {
			echo 'Error updating log: ';
			print_r($db->getError());
			echo "\r\n";
		}
		
		return $rows_updated;
	}

	public static function cleanupWebserviceLogs($db, $data_load_id = false) {
		if($data_load_id === false)
			$rows_updated = $db->Update("UPDATE data_load_ws_log wl SET request_data = NULL, response_data = NULL, response_array = NULL where wl.status_error = 0");
		else	
			$rows_updated = $db->Update("UPDATE data_load_ws_log wl SET request_data = NULL, response_data = NULL, response_array = NULL where wl.status_error = 0 and wl.data_load_func_id in (select id from data_load_func_log fl where fl.data_load_id = ?)", array($data_load_id));
		
		if($db->hasError()) {
			echo 'Error updating log: ';
			print_r($db->getError());
			echo "\r\n";
		}
		
		return $rows_updated;
	}
}
?>