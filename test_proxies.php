<?php
require_once('classes/data/DatabaseFactory.class.php');
require_once('classes/data/Proxy.class.php');

$debug = false;

// This is the page that that we're going to request going through the proxy
$testpage = "http://gcand.gree-apps.net/hc//index.php/json_gateway";
$checktext = "Json_gateway?svc=";

// This loads all the proxies from the file into an array
$db = DatabaseFactory::getDatabase();
//$proxies = ProxyDAO::getActiveProxies($db);

// Test each of our proxies 10 times
for($i = 0; $i < 10; $i++) {
	// Reload the proxy list each time through in case we've deactivated something - this will speed up testing.
	$proxies = ProxyDAO::getActiveProxies($db);
	
	// Here we loop through each cell of the array with the proxies in them testing each one until we get to the end of the array
	foreach($proxies as $proxy) {
		// Concatenate the proxy string
		$proxy_str = "{$proxy['ip_address']}:{$proxy['port']}";
	
		// This script utilizes cURL which is library you can read more about
		//using curl in my intro tutorials
		// starting curl and setting the page to get
		$ch = curl_init($testpage);
	
		// sets the proxy to go through
		curl_setopt($ch, CURLOPT_PROXY, $proxy_str);
		if($proxy['type'] == 'SOCKS')
			curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); 
		curl_setopt($ch, CURLOPT_TIMEOUT, 10); //timeout in seconds
	
		// makes the curl call do it's work based on what we've set previously and
		//returns that fetched page to $page
		$page = curl_exec($ch);
	
		// cleans up the curl set
		curl_close($ch);
	
		// this will check that there was some html returned, now some sites might block some
		//proxies so you'd want to set for that specific site in the $testpage var and then
		//find something on that page to look for with the below function.
		$check = stripos($page, $checktext);
	
		// if there was a match in the stripos (string postion) function echo that the
		//proxy got the data and works
		if($check > 0)
		{
			echo $proxy_str." Works!\r\n";
			ProxyDAO::countSuccess($db, $proxy['id']);
			// or else echo it doesn't work
		}else{
			echo $proxy_str." Is Dead!\r\n";
			ProxyDAO::countFailure($db, $proxy['id']);
		}
	}
}
?>

?>