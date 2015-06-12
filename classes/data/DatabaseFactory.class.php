<?php
require_once("DataAccess.class.php");

class DatabaseFactory {
	private static $host = 'localhost';
	private static $user = 'root';
	private static $pass = 'steven';
	private static $dbname = 'war_of_nations';
	
	public static function getDatabase() {
		switch(gethostname()) {
			case 'WON-Data-Extractor': 
				return new DataAccess('localhost', 'war_of_nations', 'SVxE3FXYHuEm5cxM', self::$dbname);
			default:
				return new DataAccess(self::$host, self::$user, self::$pass, self::$dbname);
		}
		
	}
}
?>