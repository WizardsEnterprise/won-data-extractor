<?php
require_once('MapperHelper.class.php');
require_once('ModelBase.class.php');

class WorldMapDAO {
	public static function getAllHexes($db) {
		return $db->select("SELECT * FROM world_hexes");
	}
	
	public static function checkHexExists($db, $world_id, $x_coord, $y_coord) {
		$count = $db->selectValue("SELECT count(*) FROM world_hexes WHERE world_id=? AND x_coord=? AND y_coord=?", array($world_id, $x_coord, $y_coord));
	
		return ($count > 0);
	}
	
	public static function insertHex($db, $Hex) {
		global $debug;
		
		if($debug) {
			echo 'HexDAO::insertHex'.PHP_EOL;
		}
		
		$cols = '`'.implode('`, `', HexMapper::ColumnNames('Insert')).'`';
		$paramValues = HexMapper::GetParamValues($Hex, 'Insert');
		$params = rtrim(str_repeat('?, ', count($paramValues)), ', ');
		
		$sql = "INSERT INTO `world_hexes` ($cols) VALUES ($params)";
		
		if($debug) {
			echo 'SQL Query:'.PHP_EOL;
			echo $sql.PHP_EOL;
			echo 'Params Array:'.PHP_EOL;
			print_r($paramValues);
		}
		
		return $db->Insert($sql, $paramValues);
	}
	
	public static function updateHex($db, $Hex, $customExcludes = array()) {
		global $debug;
		
		if($debug) {
			echo 'HexDAO::updateHex'.PHP_EOL;
		}
		
		$updateStr = '`'.implode('`=?, `', HexMapper::ColumnNames('Update', $customExcludes)).'`=?';
		$paramValues = HexMapper::GetParamValues($Hex, 'Update', $customExcludes);
		$params = rtrim(str_repeat('?, ', count($paramValues)), ', ');
		
		$sql = "UPDATE `world_hexes` SET $updateStr WHERE `world_id`=? and `x_coord`=? and `y_coord`=?";
		
		if($debug) {
			echo 'SQL Query:'.PHP_EOL;
			echo $sql.PHP_EOL;
			echo 'Params Array:'.PHP_EOL;
			print_r($paramValues);
		}
		
		return $db->Update($sql, $paramValues);
	}
}

class HexMapper {
	public static $mapping = array('world_id' => 'world_id', 'x_coord' => 'hex_x', 'y_coord' => 'hex_y',
								   'type' => 'type', 'player_id' => 'player_id', 'town_id' => 'town_id',
								   'town_name' => 'town_name', 'building_id' => 'building_id', 
								   'building_unique_id' => 'building_unique_id', 'is_sb' => 'is_sb', 'is_npc' => 'is_npc',
								   'version' => 'version', 'resource_id' => 'resource_id', 'resource_level' => 'resource_level',
								   'destroyed' => 'destroyed', 'event_entity_type' => 'event_entity_type', 
								   'league_tier' => 'league_tier', 'data_load_id' => 'data_load_id');
	
	public static $excludeFromInsert = array();
	public static $excludeFromUpdate = array('world_id', 'x_coord', 'y_coord');
	
	public static function ColumnNames($operation, $customExcludes = array()) {
		$arr = "excludeFrom$operation";
		return MapperHelper::ColumnNames(self::$mapping, array_merge(self::$$arr, $customExcludes));
	}

	public static function GetParamValues($obj, $operation, $customExcludes = array()) {
		$arr = "excludeFrom$operation";
		
		$keys = array('world_id', 'hex_x', 'hex_y');
		return MapperHelper::GetParamValues($obj, $operation, self::$mapping, array_merge(self::$$arr, $customExcludes), $keys);
	}
}

class Hex extends ModelBase {
	public $world_id;
	public $hex_x;
	public $hex_y;
	public $type;
	public $player_id;
	public $npc_player_id;
	public $player_name;
	public $player_level;
	public $town_id;
	public $town_name;
	public $guild_id;
	public $guild_name;
	public $building_id;
	public $building_unique_id;
	public $is_sb;
	public $is_npc;
	public $version;
	public $resource_id;
	public $resource_level;
	public $destroyed;
	public $event_entity_type;
	public $league_tier;
	public $data_load_id;
	public $mapper = 'HexMapper';
	
	// Unstored Parameters
	public $town_radius;
	public $command_center = false;
}
?>