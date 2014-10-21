<?php
class DataLoadLogDAO {
	public static function getAllDataLoadLogs($db) {
		return $db->Select("SELECT * FROM data_load_log");
	}
	
	public static function logEvent($db, $load_id, $event_func, $sequence_id, $event_type, $event_desc, $event_txt) {
		//echo "data_load_log: $load_id, $event_func, $sequence_id, $event_type, $event_desc, $event_txt";
		$id = $db->Insert("INSERT INTO data_load_log (data_load_id, event_func, sequence_id, event_type, event_desc, event_txt) VALUES (?, ?, ?, ?, ?, ?)", array($load_id, $event_func, $sequence_id, $event_type, $event_desc, $event_txt));
		if($db->hasError()) {
			echo 'Error inserting log: ';
			print_r($db->getError());
			echo "<br/>\r\n";
		}
		return $id;
	}
}
?>