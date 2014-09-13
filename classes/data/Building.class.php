<?php
class BuildingDAO {
	public static function getAllBuildings($db) {
		return $db->select("SELECT * FROM building_ref");
	}
	
	public static function getLocalIdFromGameId($db, $game_building_id) {
		return $db->selectValue("SELECT id FROM building_ref WHERE game_building_id=?", array($game_building_id));
	}
}
?>