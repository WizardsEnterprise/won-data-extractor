<?php
require_once(dirname(__FILE__) . '/DataAccess.class.php');

class DataAccessManager extends DataAccess{
	// In order to make this work, we need to store the connection details, even though I don't want to
	private $host;
	private $user;
	private $pass;
	private $dbname;

	// Constructor - 
	// Parameters: Host, Username, Password, Database
	// Returns: Nothing
	function DataAccessManager($in_dbhost, $in_dbuser, $in_dbpass, $in_dbname) {
		$this->host = $in_dbhost;
		$this->user = $in_dbuser;
		$this->pass = $in_dbpass;
		$this->dbname = $in_dbname;

		parent::__construct($this->host, $this->user, $this->pass, $this->dbname);

		if($this->hasError()) {
			print_r($this->getError());
		}
	}

	protected function PrepareAndExecuteQuery($query, $params) {
		$stmt = parent::PrepareAndExecuteQuery($query, $params);

		if(!$stmt) {
			// If this was a general error, attempt to reconnect and retry the query
			if($this->errno == 'HY000') {
				echo "Database Error.  Waiting 1 minute before attempting to Reconnect.\n";
				sleep(60);
				echo "Attempting to reconnect...\n";
				
				$this->conn = null;
				$this->connect($this->host, $this->user, $this->pass, $this->dbname);

				if(!$this->hasError())
					return parent::PrepareAndExecuteQuery($query, $params);

				echo "Reconnect Failed\n";
				return false;
			}
		}

		return $stmt;
	}
}

?>
