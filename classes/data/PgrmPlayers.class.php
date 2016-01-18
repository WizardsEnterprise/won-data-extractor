<?php
require_once(dirname(__FILE__) . '/MapperHelper.class.php');
require_once(dirname(__FILE__) . '/ModelBase.class.php');

class PgrmPlayerDAO {
	public static function joinNewWorld($db, $game_player_id, $user_id, $world_id) {
		$sql = "INSERT INTO pgrm_players (world_id, user_id, game_player_id) VALUES (?, ?, ?)";
		return $db->Insert($sql, array($world_id, $user_id, $game_player_id));
	}
}

?>