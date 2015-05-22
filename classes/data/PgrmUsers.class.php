<?php
require_once('MapperHelper.class.php');
require_once('ModelBase.class.php');

class PgrmUserDAO {
	public static function getLocalIdFromGameId($db, $game_user_id) {
		return $db->selectValue("SELECT id FROM pgrm_users WHERE game_user_id=?", array($game_user_id));
	}
}

?>