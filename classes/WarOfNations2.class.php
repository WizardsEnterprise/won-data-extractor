<?php
require_once('data/DatabaseFactory.class.php');
require_once('WarOfNationsDataExtractor.class.php');
require_once('WarOfNationsAuthentication.class.php');
require_once('WorldMapExtractor.class.php');
require_once('LeaderboardExtractor.class.php');
require_once('GameOperations.class.php');
require_once('UplinkService.class.php');
require_once('../PHPMailer/PHPMailerAutoload.php');

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
	public $gameops = false;
	public $uplink = false;
	
	function __construct() {
		$this->db = DatabaseFactory::getDatabase();
		$this->de = new WarOfNationsDataExtractor($this->db);

		// We always need authentication, so just initialize it now
		$this->auth = new WarOfNationsAuthentication($this->db, $this->de, $this->data_load_id);
	}
	
	function setDataLoadId($dlid) {
		$this->data_load_id = $dlid;
		$this->de->setDataLoadId($dlid);
		
		if ($this->auth !== false)
			$this->auth->setDataLoadId($dlid);
			
		if ($this->map !== false)
			$this->map->setDataLoadId($dlid);
			
		if ($this->leaders !== false)
			$this->leaders->setDataLoadId($dlid);

		if ($this->gameops !== false)
			$this->gameops->setDataLoadId($dlid);

		if ($this->uplink !== false)
			$this->uplink->setDataLoadId($dlid);
	}

	function Authenticate($new = false, $device_id = false, $return_full_response = false) {
		// If the authentication object hasn't been initialized yet, initialize it
		if ($this->auth === false)
			$this->auth = new WarOfNationsAuthentication($this->db, $this->de, $this->data_load_id);
		
		// If we aren't already authenticated, authenticate ourselves now
		if (!$this->auth->authenticated)
			return $this->auth->Authenticate($new, $device_id, $return_full_response);
		return true;
	}
	
	// Call the web service to get the world map and then parse and save it in the database
	function GetWorldMap($x_start = 0, $y_start = 0, $x_range = 50, $y_range = 50) {
		// Ensure that we're authenticated already
		$this->Authenticate();
		
		// If the extraction object hasn't been initialized yet, initialize it
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
		
		// If the extraction object hasn't been initialized yet, initialize it
		if ($this->leaders === false)
			$this->leaders = new LeaderboardExtractor($this->db, $this->de, $this->data_load_id, $this->auth);
		
		$this->leaders->GetLeaderboard($leaderboard_id, $start_index);
	}

	// Join a new world
	function JoinNewWorld($world_id) {
		// Ensure that we're authenticated already
		$this->Authenticate();
		
		$this->auth->JoinNewWorld($world_id);
	}

	function SwitchWorld($world_id) {
		// Ensure that we're authenticated already
		$this->Authenticate();
		
		$this->auth->SwitchWorld($world_id);
	}

	function GetGameOperations() {
		$this->Authenticate();

		if ($this->gameops === false)
			return $this->gameops = new GameOperations($this->db, $this->de, $this->data_load_id, $this->auth);
	
		return $this->gameops;
	}

	function GetUplinkService() {
		$this->Authenticate();

		if ($this->uplink === false)
			return $this->uplink = new UplinkService($this->db, $this->de, $this->data_load_id, $this->auth);

		return $this->uplink;
	}

	function SendWarningText($message, $ringer = false, $debug = 0) {
		// If we need to get the alert immediately, turn on the ringer
		if($ringer)
			$message = PgrmConfigDAO::getConfigProperty($this->db, 'SMTP', 'SERVER', 'value1').' | '.$message;

		$log_seq = 0;
		$func_args = func_get_args();
		$func_log_id = DataLoadLogDAO::startFunction($this->db, $this->data_load_id, __CLASS__,  __FUNCTION__, $func_args);

		//Create a new PHPMailer instance
		$mail = new PHPMailer;
		//Tell PHPMailer to use SMTP
		$mail->isSMTP();
		//Enable SMTP debugging
		// 0 = off (for production use)
		// 1 = client messages
		// 2 = client and server messages
		$mail->SMTPDebug = $debug;
		//Ask for HTML-friendly debug output
		$mail->Debugoutput = 'html';
		//Set the hostname of the mail server
		$server = PgrmConfigDAO::getConfigProperties($this->db, 'SMTP', 'SERVER');
		$mail->Host = $server['value1'];
		//Set the SMTP port number - likely to be 25, 465 or 587
		$mail->Port = $server['value2'];
		//Whether to use SMTP authentication
		$mail->SMTPAuth = true;
		//Username to use for SMTP authentication
		$credentials = PgrmConfigDAO::getConfigProperties($this->db, 'SMTP', 'CREDENTIALS');
		$mail->Username = $credentials['value1'];
		//Password to use for SMTP authentication
		$mail->Password = $credentials['value2'];
		//Set who the message is to be sent from
		$from_email = PgrmConfigDAO::getConfigProperties($this->db, 'SMTP', 'FROM_EMAIL');
		$mail->setFrom($from_email['value1'], $from_email['value2']);
		//Set who the message is to be sent to
		$text_to = PgrmConfigDAO::getConfigProperties($this->db, 'SMTP', 'WARNING_TEXT_TO');
		$mail->addAddress($text_to['value1'], $text_to['value2']);
		//Set the subject line
		$mail->Subject = '';
		//Set the body of the message
		$mail->isHTML(false);
		$mail->Body = $message;

		//send the message, check for errors
		if (!$mail->send()) {
		    echo "Mailer Error: " . $mail->ErrorInfo . "\r\n";
			DataLoadLogDAO::logEvent2($this->db, $func_log_id, $log_seq++, 'ERROR', 'Error Sending Message', $mail->ErrorInfo, 1);	

			DataLoadLogDAO::completeFunction($this->db, $func_log_id, 'Error Sending Message', 1);

		    return false;
		} else {
		    //echo "Message sent!";
		    DataLoadLogDAO::completeFunction($this->db, $func_log_id, 'Message Sent');
		    return true;
		}
	}
}

?>