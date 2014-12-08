<?php
require_once('data/DatabaseFactory.class.php');
require_once('WarOfNationsDataExtractor.class.php');
require_once('WarOfNationsAuthentication.class.php');
require_once('WorldMapExtractor.class.php');
require_once('LeaderboardExtractor.class.php');

class WarOfNations {
	// Database
	public $db;
	
	// Data Extractor
	public $de;
	
	// Current Data Load ID
	public $data_load_id;
	
	// Service Objects
	public $auth = false;
	public $map = false;
	public $leaders = false;
	
	function __construct() {
		$this->db = DatabaseFactory::getDatabase();
		$this->de = new WarOfNationsDataExtractor($this->db);
	}
	
	public function setDataLoadId($dlid) {
		$this->data_load_id = $dlid;
		$this->de->setDataLoadId($dlid);
		
		if ($this->auth !== false)
			$this->auth->setDataLoadId($dlid);
			
		if ($this->map !== false)
			$this->map->setDataLoadId($dlid);
			
		if ($this->leaders !== false)
			$this->leaders->setDataLoadId($dlid);
	}
	
	function Authenticate() {
		// If the authentication object hasn't been initialized yet, initialize it
		if ($this->auth === false)
			$this->auth = new WarOfNationsAuthentication($this->db, $this->de, $this->data_load_id);
		
		// If we aren't already authenticated, authenticate ourselves now
		if (!$this->auth->authenticated)
			$this->auth->Authenticate();
	}
	
	// Call the web service to get the world map and then parse and save it in the database
	public function GetWorldMap($x_start = 0, $y_start = 0, $x_range = 50, $y_range = 50) {
		// Ensure that we're authenticated already
		$this->Authenticate();
		
		// If the authentication object hasn't been initialized yet, initialize it
		if ($this->map === false)
			$this->map = new WorldMapExtractor($this->db, $this->de, $this->data_load_id, $this->auth);
		
		$this->map->GetWorldMap($x_start, $y_start, $x_range, $y_range);
		
		return true;
	}
	
	// Get the leaderboard and save the information found
	// Leaderboard IDs:
	//   1 = Players
	//   2 = Alliances
	function GetLeaderboard($leaderboard_id = 1, $start_index = 0) {
		// Ensure that we're authenticated already
		$this->Authenticate();
		
		// If the authentication object hasn't been initialized yet, initialize it
		if ($this->leaders === false)
			$this->leaders = new LeaderboardExtractor($this->db, $this->de, $this->data_load_id, $this->auth);
		
		$this->leaders->GetLeaderboard($leaderboard_id, $start_index);
	}
}

?>