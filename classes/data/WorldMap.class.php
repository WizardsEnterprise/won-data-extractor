<?php
require_once(dirname(__FILE__) . '/MapperHelper.class.php');
require_once(dirname(__FILE__) . '/ModelBase.class.php');

class WorldMapDAO {
	private static $cache_init = false;
	private static $hex_cache = array();

	public static function getAllHexes($db) {
		return $db->select("SELECT * FROM world_hexes");
	}

	private static function build_hex_cache($db, $world_id) {
		$hexes = $db->select("SELECT x_coord, y_coord FROM world_hexes where world_id=?", array($world_id));

		foreach($hexes as $i => $hex) {
			self::$hex_cache[$hex['x_coord'].', '.$hex['y_coord']] = 1;
		}

		self::$cache_init = true;
	}
	
	public static function checkHexExists($db, $world_id, $x_coord, $y_coord) {
		if(self::$cache_init === false) 
			self::build_hex_cache($db, $world_id);

		if(isset(self::$hex_cache["$x_coord, $y_coord"]))
			return true;

		return false;
		//$count = $db->selectValue("SELECT count(*) FROM world_hexes WHERE world_id=? AND x_coord=? AND y_coord=?", array($world_id, $x_coord, $y_coord));
	
		//return ($count > 0);
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

	public static function setResourcePatches($db, $world_id) {
		/*
		update world_hexes as wh set wh.resource_patches = (select sum(case when wh2.resource_id is not null then 1 else 0 end)
		from (select world_id, player_id, town_id, resource_id from world_hexes where world_id in (13) and (resource_id is not null or building_id in (1, 2))) as wh2 
		where wh2.player_id = wh.player_id and wh2.town_id = wh.town_id and wh2.world_id = wh.world_id) 
		where wh.world_id in (13) and wh.player_id is not null and wh.town_id is not null and wh.town_name is not null;
		*/

		global $debug;

		$sql = "UPDATE world_hexes as wh set wh.resource_patches = (select sum(case when wh2.resource_id is not null then 1 else 0 end)
		from (select world_id, player_id, town_id, resource_id from world_hexes where world_id = ? and (resource_id is not null or building_id in (1, 2))) as wh2 
		where wh2.player_id = wh.player_id and wh2.town_id = wh.town_id and wh2.world_id = wh.world_id) 
		where wh.world_id = ? and wh.player_id is not null and wh.town_id is not null and wh.town_name is not null";
		
		$params = array($world_id, $world_id);

		if($debug) {
			echo 'SQL Query:'.PHP_EOL;
			echo $sql.PHP_EOL;
			echo 'Params Array:'.PHP_EOL;
			print_r($params);
		}

		return $db->Update($sql, $params);
	}

	public static function archiveOldBases($db, $world_id, $data_load_id) {
		/*
		insert into world_hexes_archive select * from world_hexes wh where wh.data_load_id is null or (wh.data_load_id < 744 and wh.world_id = 13) order by wh.data_load_id, wh.x_coord, wh.y_coord;
		delete from world_hexes where data_load_id is null or data_load_id < 744 and world_id = 13;
		*/

		global $debug;

		// Same params for both queries
		$params = array($data_load_id, $world_id);

		$sql = "INSERT into world_hexes_archive select * from world_hexes wh where (wh.data_load_id < ? and wh.world_id = ?) order by wh.data_load_id, wh.x_coord, wh.y_coord";

		if($debug) {
			echo 'SQL Query:'.PHP_EOL;
			echo $sql.PHP_EOL;
			echo 'Params Array:'.PHP_EOL;
			print_r($params);
		}

		$insert_count = $db->Update($sql, $params);

		$sql = "DELETE from world_hexes where data_load_id < ? and world_id = ?";

		if($debug) {
			echo 'SQL Query:'.PHP_EOL;
			echo $sql.PHP_EOL;
			echo 'Params Array:'.PHP_EOL;
			print_r($params);
		}

		$delete_count = $db->Update($sql, $params);

		if ($insert_count == $delete_count)
			return $insert_count;
		return false;
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

	// Player Info
	public $player_id;
	public $npc_player_id;
	public $player_name;
	public $player_level;
	public $immune_until_ts;

	// Base Info
	public $town_id;
	public $town_name;

	// Alliance Info
	public $guild_id;
	public $guild_name;

	// Building Info
	public $building_id;
	public $building_unique_id;

	// Flags
	public $is_sb;
	public $is_npc;

	// Resource Info
	public $resource_id;
	public $resource_level;

	// Holdout
	public $event_entity_type;
	public $league_tier;

	// Probably not needed
	public $version;
	public $destroyed;

	// Data Load Info
	public $data_load_id;
	public $mapper = 'HexMapper';
	
	// Unstored Parameters
	public $town_radius;
	public $is_guild_town_center;
	public $guild_town_phase;
	public $command_center = false;
}
?>