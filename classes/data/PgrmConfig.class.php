<?php
class PgrmConfigDAO {
	public static function getConfigProperties($db, $key, $dtl = false) {
		if ($dtl === false)
			return $db->SelectOne("SELECT c.key, c.key_dtl, c.value1, c.value2, c.value3 FROM pgrm_config c WHERE c.key = ?", array($key));
		else
			return $db->SelectOne("SELECT c.key, c.key_dtl, c.value1, c.value2, c.value3 FROM pgrm_config c WHERE c.key = ? and c.key_dtl = ?", array($key, $dtl));
	}
	
	public static function getConfigProperty($db, $property, $key, $dtl = false) {
		return self::getConfigProperties($db, $key, $dtl)[$property];
	}
}

?>