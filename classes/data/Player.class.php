<?php
require_once('MapperHelper.class.php');
require_once('ModelBase.class.php');

class PlayerDAO {
	public static function getAllPlayers($db) {
		return $db->select("SELECT * FROM players");
	}
	
	public static function getPlayerById($db, $player_id) {
		return $db->selectOne("SELECT * FROM players WHERE id=?", array($player_id));
	}
	
	public static function getLocalIdFromGameId($db, $game_id) {
		return $db->selectValue("SELECT id FROM players WHERE game_player_id=?", array($game_id));
	}
	
	public static function insertPlayer($db, $Player, $hist = false) {
		global $debug;
		
		if($debug) {
			echo 'PlayerDAO::insertPlayer'.PHP_EOL;
		}
		
		$cols = '`'.implode('`, `', PlayerMapper::ColumnNames('Insert')).'`';
		$paramValues = PlayerMapper::GetParamValues($Player, 'Insert');
		$params = rtrim(str_repeat('?, ', count($paramValues)), ', ');
		
		$hist_tbl = $hist ? '_hist' : '';
		$sql = "INSERT INTO `players$hist_tbl` ($cols) VALUES ($params)";
		
		if($debug) {
			echo 'SQL Query:'.PHP_EOL;
			echo $sql.PHP_EOL;
			echo 'Params Array:'.PHP_EOL;
			print_r($paramValues);
		}
		
		return $db->Insert($sql, $paramValues);
	}
	
	public static function updatePlayer($db, $Player, $customExcludes = array()) {
		global $debug;
		
		if($debug) {
			echo 'PlayerDAO::updatePlayer'.PHP_EOL;
		}
		
		// Write the existing player data to the player_hist table for safe keepings
		$existing_player = Player::FromDB(self::getPlayerById($db, $Player->id));
		
		// If there is no meaningful difference in this player, don't update the record
		if(!self::playerHasMeaningfulDifference($existing_player, $Player, $customExcludes)) {
			//echo "player {$Player->player_name} has no changes \r\n";
			return true;
		}
			
		self::insertPlayer($db, $existing_player, true);
		
		/* Update the existing record with the newly acquired information */
		$updateStr = '`'.implode('`=?, `', PlayerMapper::ColumnNames('Update', $customExcludes)).'`=?';
		$paramValues = PlayerMapper::GetParamValues($Player, 'Update', $customExcludes);
		$params = rtrim(str_repeat('?, ', count($paramValues)), ', ');
		
		$sql = "UPDATE `players` SET $updateStr WHERE id=?";
		
		if($debug) {
			echo 'SQL Query:'.PHP_EOL;
			echo $sql.PHP_EOL;
			echo 'Params Array:'.PHP_EOL;
			print_r($paramValues);
		}
		
		return $db->Update($sql, $paramValues);
	}
	
	public static function playerHasMeaningfulDifference($p1, $p2, $customExcludes = array()) {
		if(!in_array('player_name', $customExcludes) && $p1->player_name != $p2->player_name)
			return true;
		
		if(!in_array('battle_points', $customExcludes) && $p1->battle_points != $p2->battle_points)
			return true;
			
		if(!in_array('guild_id', $customExcludes) && $p1->guild_id != $p2->guild_id)
			return true;
		
		if(!in_array('level', $customExcludes) && $p1->level != $p2->level)
			return true;
		
		if(!in_array('bases', $customExcludes) && $p1->bases != $p2->bases)
			return true;
			
		return false;
	}
}

class PlayerMapper {
	public static $mapping = array('id' => 'id', 'world_id' => 'world_id', 'game_player_id' => 'game_player_id',
								   'player_name' => 'player_name', 'level' => 'level', 'battle_points' => 'battle_points', 
								   'bases' => 'bases', 'guild_id' => 'guild_id', 'data_load_id' => 'data_load_id');
								
	public static $excludeFromInsert = array('id');
	public static $excludeFromUpdate = array('id', 'world_id', 'game_player_id');
	
	public static function ColumnNames($operation, $customExcludes = array()) {
		$arr = "excludeFrom$operation";
		return MapperHelper::ColumnNames(self::$mapping, array_merge(self::$$arr, $customExcludes));
	}

	public static function GetParamValues($obj, $operation, $customExcludes = array()) {
		$arr = "excludeFrom$operation";
		return MapperHelper::GetParamValues($obj, $operation, self::$mapping, array_merge(self::$$arr, $customExcludes));
	}
}

class Player extends ModelBase {
	public $id;
	public $world_id;
	public $game_player_id;
	public $player_name;
	public $battle_points;
	public $level;
	public $bases;
	public $guild_id;
	public $data_load_id;
	public $mapper = 'PlayerMapper';
}
?>