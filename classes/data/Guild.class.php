<?php
require_once('MapperHelper.class.php');
require_once('ModelBase.class.php');

class GuildDAO {
	public static function getAllGuilds($db) {
		return $db->select("SELECT * FROM guilds");
	}
	
	public static function getLocalIdFromGameId($db, $game_id) {
		return $db->selectValue("SELECT id FROM guilds WHERE game_guild_id=?", array($game_id));
	}
	
	public static function insertGuild($db, $Guild) {
		global $debug;
		
		if($debug) {
			echo 'GuildDAO::insertGuild'.PHP_EOL;
		}
		
		$cols = '`'.implode('`, `', GuildMapper::ColumnNames('Insert')).'`';
		$paramValues = GuildMapper::GetParamValues($Guild, 'Insert');
		$params = rtrim(str_repeat('?, ', count($paramValues)), ', ');
		
		$sql = "INSERT INTO `guilds` ($cols) VALUES ($params)";
		
		if($debug) {
			echo 'SQL Query:'.PHP_EOL;
			echo $sql.PHP_EOL;
			echo 'Params Array:'.PHP_EOL;
			print_r($paramValues);
		}
		
		return $db->Insert($sql, $paramValues);
	}
	
	public static function updateGuild($db, $Guild, $customExcludes = array()) {
		global $debug;
		
		if($debug) {
			echo 'GuildDAO::updateGuild'.PHP_EOL;
		}
		
		$updateStr = '`'.implode('`=?, `', GuildMapper::ColumnNames('Update', $customExcludes)).'`=?';
		$paramValues = GuildMapper::GetParamValues($Guild, 'Update', $customExcludes);
		$params = rtrim(str_repeat('?, ', count($paramValues)), ', ');
		
		$sql = "UPDATE `guilds` SET $updateStr WHERE id=?";
		
		if($debug) {
			echo 'SQL Query:'.PHP_EOL;
			echo $sql.PHP_EOL;
			echo 'Params Array:'.PHP_EOL;
			print_r($paramValues);
		}
		
		return $db->Update($sql, $paramValues);
	}
}

class GuildMapper {
	public static $mapping = array('id' => 'id', 'world_id' => 'world_id', 'game_guild_id' => 'game_guild_id', 'leader_id' => 'leader_id',
								   'guild_name' => 'guild_name', 'battle_points' => 'battle_points', 'members' => 'members',
								   'data_load_id' => 'data_load_id');
	
	public static $excludeFromInsert = array('id');
	public static $excludeFromUpdate = array('id', 'world_id', 'game_guild_id');
	
	public static function ColumnNames($operation, $customExcludes = array()) {
		$arr = "excludeFrom$operation";
		return MapperHelper::ColumnNames(self::$mapping, array_merge(self::$$arr, $customExcludes));
	}

	public static function GetParamValues($obj, $operation, $customExcludes = array()) {
		$arr = "excludeFrom$operation";
		return MapperHelper::GetParamValues($obj, $operation, self::$mapping, array_merge(self::$$arr, $customExcludes));
	}
}

class Guild extends ModelBase {
	public $id;
	public $world_id;
	public $game_guild_id;
	public $owner_id;
	public $guild_name;
	public $battle_points;
	public $members;
	public $data_load_id;
	public $mapper = 'GuildMapper';
}
?>