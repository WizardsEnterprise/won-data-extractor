<?php
class ProxyDAO {
	public static function getAllProxies($db) {
		return $db->Select("SELECT * FROM pgrm_proxies");
	}
	
	public static function getActiveProxies($db) {
		return $db->Select("SELECT * FROM pgrm_proxies WHERE active = 1");
	}
	
	public static function countSuccess($db, $proxy_id) {
		$db->Update("UPDATE pgrm_proxies p SET success_count = success_count + 1 WHERE p.id = ?", array($proxy_id));
	}
	
	public static function countFailure($db, $proxy_id) {
		$db->Update("UPDATE pgrm_proxies p SET failed_count = failed_count + 1 WHERE p.id = ?", array($proxy_id));
	}
}
?>