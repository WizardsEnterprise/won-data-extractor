<?php
require_once(dirname(__FILE__) . '/DataAccessManager.class.php');

class DatabaseFactory {
	private static $host = 'localhost';
	private static $user = 'root';
	private static $pass = 'steven';
	private static $dbname = 'war_of_nations';
	
	public static function getDatabase() {
		switch(gethostname()) {
			case 'WON-Data-Extractor': 
				return new DataAccessManager('localhost', 'won_data_extract', '', self::$dbname);
			default:
				return new DataAccessManager(self::$host, self::$user, self::$pass, self::$dbname);
		}
		
	}
}
?>