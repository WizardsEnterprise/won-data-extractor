<?php

class DataAccess {
	protected $conn = false;
	
	protected $errno = false;
	protected $errorMsg = false;
	
	// Constructor - 
	// Parameters: Host, Username, Password, Database
	// Returns: Nothing
	function DataAccess($in_dbhost, $in_dbuser, $in_dbpass, $in_dbname) {
		try {
			$this->conn = new PDO("mysql:host=$in_dbhost;dbname=$in_dbname", $in_dbuser, $in_dbpass);
			$this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch (PDOException $e) {
		    $this->errno = $e->getCode();
			$this->errorMsg = $e->getMessage();			
		}
	}
	
	function hasError() {
		return !($this->errno === false && $this->errorMsg === false);
	}
	
	function getError() {
		return array('ErrorCode' => $this->errno, 'ErrorMessage' => $this->errorMsg);
	}
	
	private function PrepareAndExecuteQuery($query, $params) {
		$this->errno = false;
		$this->errorMsg = false;
		
		try {
			$stmt = $this->conn->prepare($query);
		} catch (PDOException $e) {
			$this->errno = $e->getCode();
			$this->errorMsg = $e->getMessage();
			return false;
		}
		
		try {
			$stmt->execute($params);
		} catch (PDOException $e) {
		    $this->errno = $e->getCode();
			$this->errorMsg = $e->getMessage();
			return false;
		}
		
		return $stmt;
	}
	
	// Insert - Executes the passed insert statement
	// Parameters: Query, Array of Parameters
	// Returns: The new ID number on success, false otherwise
	function Insert($query, $params) {
		foreach($params as $key => $param) {
			if(!isset($params[$key])) {
				$params[$key] = null;
			}
				
		}
		$this->PrepareAndExecuteQuery($query, $params);
		
		$id = $this->conn->lastInsertID();
		
		return $id;
	}
	
	// Update - Executes the passed insert statement
	// Parameters: Query, Array of Parameters
	// Returns: The number of rows updated
	function Update($query, $params) {
		foreach($params as $key => $param) {
			if(!isset($params[$key])) {
				$params[$key] = null;
			}
				
		}
		$stmt = $this->PrepareAndExecuteQuery($query, $params);
		
		$count = $stmt->rowCount();
		
		return $count;
	}
	
	// SelectValue - Executes the passed Select statement, returns 1 value
	// Parameters: Query, Array of Parameters
	// Returns: The value from the select statement on success, FALSE otherwise
	function SelectValue($query, $params) {
		$stmt = $this->PrepareAndExecuteQuery($query, $params);
		
		// If the statement failed to execute, return false
		if (!$stmt) return false;
		
		try {
			$retVal = $stmt->fetchColumn();
		} catch (PDOException $e) {
		    echo "Fetching selected column failed: ". $e->getMessage();
			return false;
		}
		
		return $retVal;
	}
	
	// Select - Executes the passed Select statement, returns array of all results
	// Parameters: Query, Array of Parameters
	// Returns: The array of results from the select statement on success, FALSE otherwise
	function Select($query, $params = array()) {
		$stmt = $this->PrepareAndExecuteQuery($query, $params);
		
		// If the statement failed to execute, return false
		if (!$stmt) return false;
		
		try {
			$retVal = $stmt->fetchAll(PDO::FETCH_ASSOC);
		} catch (PDOException $e) {
		    echo "Fetching results failed: ". $e->getMessage();
			return false;
		}
		
		return $retVal;
	}
	
	function SelectOne($query, $params = array()) {
		return $this->Select($query, $params)[0];
	}
}

?>