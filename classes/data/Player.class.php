<?php
require_once('MapperBase.class.php');

class PlayerDAO {
	public static function getAllPlayers($db) {
		return $db->select("SELECT * FROM players");
	}
	
	public static function getLocalIdFromGameId($db, $game_id) {
		return $db->selectValue("SELECT id FROM players WHERE game_player_id=?", array($game_id));
	}
	
	public static function insertPlayer($db, $Player) {
		global $debug;
		
		if($debug) {
			echo 'PlayerDAO::insertPlayer'.PHP_EOL;
		}
		
		$cols = '`'.implode('`, `', PlayerMapper::ColumnNames('Insert')).'`';
		$paramValues = PlayerMapper::GetParamValues($Player, 'Insert');
		$params = rtrim(str_repeat('?, ', count($paramValues)), ', ');
		
		$sql = "INSERT INTO `players` ($cols) VALUES ($params)";
		
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
}

class PlayerMapper extends MapperBase {
	public static $mapping = array('id' => 'id', 'world_id' => 'world_id', 'game_player_id' => 'game_player_id',
								   'player_name' => 'player_name', 'level' => 'level', 'battle_points' => 'battle_points', 
								   'bases' => 'bases', 'guild_id' => 'guild_id');
	
	public static $excludeFromInsert = array('id');
	public static $excludeFromUpdate = array('id', 'world_id', 'game_player_id');
	
	public static function ColumnNames($operation, $customExcludes = array()) {
		$arr = "excludeFrom$operation";
		return MapperBase::ColumnNames(self::$mapping, array_merge(self::$$arr, $customExcludes));
	}

	public static function GetParamValues($obj, $operation, $customExcludes = array()) {
		$arr = "excludeFrom$operation";
		return MapperBase::GetParamValues($obj, $operation, self::$mapping, array_merge(self::$$arr, $customExcludes));
	}
}

class Player {
	public $id;
	public $world_id;
	public $game_player_id;
	public $player_name;
	public $battle_points;
	public $level;
	public $bases;
	public $guild_id;
	
	public static function FromJson($json) {
		global $debug;
		
		if($debug) {
			echo 'Player::FromJson'.PHP_EOL;
			echo 'JSON Array:'.PHP_EOL;
			print_r($json);
		}
		$obj = new Player();
		
		foreach ($json as $key => $value)
		{	
			if(property_exists($obj, $key)) {
				$obj->$key = $value;
			}
		}
		
		if($debug) {
			echo 'Player Object:'.PHP_EOL;
			var_dump($obj);
		}
		
		return $obj;
	}
}
?>