<?php
class Constants {
	// Base URL
	public static $url_base = 'http://gcand.gree-apps.net/hc//index.php/json_gateway?svc=';
	
	// Service Endpoints
	public static $authenticate = 'BatchController.authenticate_iphone';
	public static $call = 'BatchController.call';
	
	// Game Information
	public static $game_data_version = 'hc_20141001_40364'; //hc_20140908_38941
	public static $game_name = 'HCGame';
	public static $client_build = '277'; //251
	public static $client_version = '1.8.6'; //1.8.4
	public static $api_version = '1';
	
	// Hmac Authorization
	public static $hmac_key = '%j75jY6pnJPs#TFBMDJ26*6U5hE544&zcZm7^kTua8%@awSTyAMG&&Z#mg23zKMb';
}
?>