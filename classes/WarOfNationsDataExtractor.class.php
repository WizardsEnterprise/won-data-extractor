<?php
require_once('data/Proxy.class.php');
require_once('data/ServiceRequest.class.php');
require_once('data/PgrmConfig.class.php');
require_once('WarOfNationsWS.class.php');

// Known Player IDs, Devices:
// Main: 101013866213677, 66d2ea9883993b46e9f06fba6e652302 
// Droid3: 101013596288193, 8763af18eb4deace1840060a3bd9086b
// Fake: 101013849913487, b0b01655f403347cea151d87a45d2746

class WarOfNationsDataExtractor {
	// Database
	public $db;
	
	// Current Data Load ID
	public $data_load_id;
	
	// Cache Params from Database
	private $params_cache;
	private $request_cache;
	
	function __construct($db) {
		$this->db = $db;
		
		$this->request_cache = array();
		$this->params_cache = array();
		$this->params_cache['game_data_version'] = PgrmConfigDAO::getConfigProperty($this->db, 'value1', 'GAME_INFO', 'DATA_VERSION');
		$this->params_cache['game_name'] = PgrmConfigDAO::getConfigProperty($this->db, 'value1', 'GAME_INFO', 'NAME');
		$this->params_cache['client_build'] = PgrmConfigDAO::getConfigProperty($this->db, 'value1', 'CLIENT', 'BUILD');
		$this->params_cache['client_version'] = PgrmConfigDAO::getConfigProperty($this->db, 'value1', 'CLIENT', 'VERSION');
		$this->params_cache['api_version'] = PgrmConfigDAO::getConfigProperty($this->db, 'value1', 'API', 'VERSION');
		
		$url_base = PgrmConfigDAO::getConfigProperty($this->db, 'value1', 'URL_BASE');
		$hmac_key = PgrmConfigDAO::getConfigProperty($this->db, 'value1', 'TOKENS', 'HMAC_KEY');
		$proxies = ProxyDAO::getActiveProxies($this->db);
		
		$this->ws = new WarOfnationsWS($this->db, $proxies, $url_base, $hmac_key);
	}

	public function DisableProxy() {
		$this->ws->DisableProxy();
	}
	
	public function setDataLoadId($dlid) {
		$this->data_load_id = $dlid;
		$this->ws->data_load_id = $dlid;
	}
	
	private static function get_transaction_time() {
		return substr(str_replace('.', '', microtime(true)), 0, 13);
	}
	
	public function AddCacheParam($param, $value) {
		$this->params_cache[$param] = $value;
	}
	
	public function BuildRequest($method, $request_string, $params = array()) {
		// Merge our request specific parameters with the cached parameters
		$params = array_merge($params, $this->params_cache);
		
		// Replace placeholders in the request string with values from our parameters array
		$request_data = $request_string;
		foreach($params as $param => $value) {
			$request_data = str_replace('$$'.$param.'$$', $value, $request_data);
		}
		
		// Add Transaction Time
		$request_data = str_replace('$$transaction_time$$', self::get_transaction_time(), $request_data);

		return $request_data;
	}
	
	public function MakeRequest($method, $params = array()) {
		// Get our request properties from the database
		if (!in_array($method, $this->request_cache)) {
			$request = ServiceRequestDAO::getServiceRequestByMethod($this->db, $method);
			$this->request_cache[$method] = $request;
		} else {
			$request = $this->request_cache[$method];
		}
		
		// Build the data string
		$data_string = $this->BuildRequest($method, $request['request_string'], $params);
		
		// Call the webservice
		return $this->ws->MakeRequest($request['endpoint'], $data_string);
	}
	
	
}

?>