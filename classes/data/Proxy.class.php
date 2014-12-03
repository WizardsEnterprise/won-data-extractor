<?php
class ProxyDAO {
	public static function getAllProxies($db) {
		return $db->Select("SELECT * FROM pgrm_proxies");
	}
	
	public static function getActiveProxies($db) {
		return $db->Select("SELECT * FROM pgrm_proxies WHERE active = 1");
	}
	
	public static function countSuccess($db, $proxy_id, $request_time) {
		$db->Update("UPDATE pgrm_proxies p SET success_count = success_count + 1 WHERE p.id = ?", array($proxy_id));
		$db->Insert("INSERT INTO pgrm_proxy_log (proxy_id, request_time, success) VALUES (?, ?, ?)", array($proxy_id, $request_time, 1));
	}
	
	public static function countFailure($db, $proxy_id, $request_time) {
		$db->Update("UPDATE pgrm_proxies p SET failed_count = failed_count + 1 WHERE p.id = ?", array($proxy_id));
		$db->Insert("INSERT INTO pgrm_proxy_log (proxy_id, request_time, success) VALUES (?, ?, ?)", array($proxy_id, $request_time, 0));
	}
	
	public static function disableProxy($db, $proxy_id, $reason) {
		$db->Update("UPDATE pgrm_proxies p SET active = 0, notes = ? WHERE p.id = ?", array($reason, $proxy_id));
	}
}
?>