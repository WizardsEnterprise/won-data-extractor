<?php
class DataLoadDAO {
	public static function getAllDataLoads($db) {
		return $db->Select("SELECT * FROM data_loads");
	}
	
	public static function initNewLoad($db, $load_type) {
		return $db->Insert("INSERT INTO data_loads (load_type) VALUES (?)", array($load_type));
	}
	
	public static function startLoad($db, $data_load_id) {
		return $db->Update("UPDATE data_loads d SET status = 'STARTED' WHERE d.id = ?", array($data_load_id));
	}
	
	public static function loadComplete($db, $data_load_id) {
		return $db->Update("UPDATE data_loads d SET status = 'COMPLETED' WHERE d.id = ?", array($data_load_id));
	}
}
?>