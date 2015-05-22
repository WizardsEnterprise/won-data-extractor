<?php
class WorldDAO {
	public static function getAllWorlds($db) {
		return $db->select("SELECT * FROM worlds");
	}
	
	public static function getLocalIdFromGameId($db, $game_world_id) {
		return $db->selectValue("SELECT id FROM worlds WHERE game_world_id=?", array($game_world_id));
	}

	public static function getGameIdFromLocalId($db, $id) {
		return $db->selectValue("SELECT game_world_id FROM worlds WHERE id=?", array($id));
	}
}
?>