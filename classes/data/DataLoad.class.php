<?php
class DataLoadDAO {
	public static function getAllDataLoads($db) {
		return $db->Select("SELECT * FROM data_loads");
	}
	
	public static function initNewLoad($db, $load_type, $total_operations) {
		$data_load_id = $db->Insert("INSERT INTO data_loads (load_type, total_operations) VALUES (?, ?)", array($load_type, $total_operations));
		
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
}
?>