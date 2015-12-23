<?php
require_once('MapperHelper.class.php');
require_once('ModelBase.class.php');

class PgrmUserDAO {
	public static function getLocalIdFromGameId($db, $game_user_id) {
		return $db->selectValue("SELECT id FROM pgrm_users WHERE game_user_id=?", array($game_user_id));
	}

	public static function createUser($db, $game_user_id, $local_device_id) {
		$sql = "INSERT INTO pgrm_users (game_user_id, device_id) VALUES (?, ?)";
		return $db->Insert($sql, array($game_user_id, $local_device_id));
	}
}

?>