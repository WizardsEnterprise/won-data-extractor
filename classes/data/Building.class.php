<?php
class BuildingDAO {
	// Cache these for performance during world map extractor
	private static $building_cache = array();

	public static function getAllBuildings($db) {
		return $db->select("SELECT * FROM building_ref");
	}
	
	public static function getLocalIdFromGameId($db, $game_building_id) {
		// Try to find the building in cache
		if(isset($game_building_id, self::$building_cache))
			return $building_cache[$game_building_id];

		// If it's not there... find it, save it, and return in
		return $building_cache[$game_building_id] = $db->selectValue("SELECT id FROM building_ref WHERE game_building_id=?", array($game_building_id));
	}
}
?>