<?php
require_once(dirname(__FILE__) . '/MapperHelper.class.php');
require_once(dirname(__FILE__) . '/ModelBase.class.php');

class DeviceDAO {
	public static function getAllDevices($db) {
		return $db->select("SELECT * FROM pgrm_devices");
	}
	
	public static function getActiveDevice($db) {
		return $db->select("SELECT * FROM pgrm_devices d WHERE d.id = (SELECT CAST(value1 AS UNSIGNED) FROM pgrm_config c WHERE c.key='CURRENT_DEVICE_ID');")[0];
	}

	public static function getDeviceById($db, $device_id) {
		return $db->select("SELECT * FROM pgrm_devices d WHERE d.id = ?", array($device_id))[0];
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
	public static $mapping = array('id' => 'id', 'device_uuid' => 'device_uuid', 'vendor_id' => 'device_uuid', 'mac_address' => 'mac_address',
								   'platform' => 'platform', 'version' => 'version', 'device_type' => 'device_type',
								   'use_proxy' => 'use_proxy');
	
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

class Device extends ModelBase{
	public $id;
	public $device_uuid;
	public $mac_address;
	public $platform;
	public $version;
	public $device_type;
	public $use_proxy;
	public $mapper = 'DeviceMapper';
}
?>