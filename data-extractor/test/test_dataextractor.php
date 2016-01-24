<?php
require_once(dirname(__FILE__) . '/../classes/WarOfNationsDataExtractor.class.php');
require_once(dirname(__FILE__) . '/../classes/data/Device.class.php');

$de = new WarOfNationsDataExtractor();
$a = new WarOfNationsAuthentication();

$params = array('session_id' => mt_rand(1000000,9999999));

$device = DeviceDAO::getActiveDevice($de->db);
$de->AddCacheParam('device_id', $device['device_uuid']);
$de->AddCacheParam('mac_address', $device['mac_address']);
$de->AddCacheParam('device_platform', $device['platform']);
$de->AddCacheParam('device_version', $device['version']);
$de->AddCacheParam('device_type', $device['device_type']);

$de->BuildRequest('AUTHENTICATE', $params);

?>