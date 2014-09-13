<?php
require_once('MapperBase.class.php');

class DeviceDAO {
	public static function getAllDevices($db) {
		return $db->select("SELECT * FROM pgrm_devices");
	}
	
	public static function getActiveDevice($db) {
		return $db->select("SELECT * FROM pgrm_devices d WHERE d.id = (SELECT CAST(value1 AS UNSIGNED) FROM pgrm_config c WHERE c.key='CURRENT_DEVICE_ID');")[0];
	}
	
	public static function insertDevice($db, $Device) {
		global $debug;
		
		if($debug) {
			echo 'DeviceDAO::insertDevice'.PHP_EOL;
		}
		
		$cols = '`'.implode('`, `', DeviceMapper::ColumnNames('Insert')).'`';
		$paramValues = DeviceMapper::GetParamValues($Device, 'Insert');
		$params = rtrim(str_repeat('?, ', count($paramValues)), ', ');
		
		$sql = "INSERT INTO `pgrm_devices` ($cols) VALUES ($params)";
		
		if($debug) {
			echo 'SQL Query:'.PHP_EOL;
			echo $sql.PHP_EOL;
			echo 'Params Array:'.PHP_EOL;
			print_r($paramValues);
		}
		
		return $db->Insert($sql, $paramValues);
	}
}

class DeviceMapper {
	public static $mapping = array('id' => 'id', 'device_uuid' => 'device_uuid', 'mac_address' => 'mac_address',
								   'platform' => 'platform', 'version' => 'version', 'device_type' => 'device_type');
	
	public static $excludeFromInsert = array('id');
	public static $excludeFromUpdate = array('id', 'world_id', 'game_guild_id');
					
	public static function ColumnNames($operation, $customExcludes = array()) {
		$arr = "excludeFrom$operation";
		return MapperBase::ColumnNames(self::$mapping, array_merge(self::$$arr, $customExcludes));
	}

	public static function GetParamValues($obj, $operation, $customExcludes = array()) {
		$arr = "excludeFrom$operation";
		return MapperBase::GetParamValues($obj, $operation, self::$mapping, array_merge(self::$$arr, $customExcludes));
	}
}

class Device {
	public $id;
	public $device_uuid;
	public $mac_address;
	public $platform;
	public $version;
	public $device_type;
	
	public static function FromJson($json) {
		global $debug;
		
		if($debug) {
			echo 'Guild::FromJson'.PHP_EOL;
			echo 'JSON Array:'.PHP_EOL;
			print_r($json);
		}
		$obj = new Device();
		
		foreach ($json as $key => $value)
		{	
			if(property_exists($obj, $key)) {
				$obj->$key = $value;
			}
		}
		
		if($debug) {
			echo 'Guild Object:'.PHP_EOL;
			var_dump($obj);
		}
		
		return $obj;
	}
}
?>