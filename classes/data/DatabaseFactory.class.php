<?php
require_once("DataAccess.class.php");

class DatabaseFactory {
	private static $host = 'localhost';
	private static $user = 'root';
	private static $pass = 'steven'; //rT-ieTu~6w.U
	private static $dbname = 'war_of_nations';
	
	public static function getDatabase() {
		switch(gethostname()) {
			case 'WON-Data-Extractor': 
				return new DataAccess('localhost', 'won_data_extract', 'nZSLuXDE5xFuVU7S', self::$dbname);
			default:
				return new DataAccess(self::$host, self::$user, self::$pass, self::$dbname);
		}
		
	}
}
?>