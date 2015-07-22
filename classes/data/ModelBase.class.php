<?php

class ModelBase {
	public static function FromJson($json) {
		global $debug;
		
		if($debug) {
			echo 'ModelBase::FromJson'.PHP_EOL;
			echo 'JSON Array:'.PHP_EOL;
			print_r($json);
		}
		$obj = new static();
		
		foreach ($json as $key => $value)
		{	
			if(property_exists($obj, $key)) {
				$obj->$key = $value;
			}
		}
		
		if($debug) {
			echo "Object:".PHP_EOL;
			var_dump($obj);
		}
		
		return $obj;
	}
	
	public static function FromDB($array) {
		global $debug;
		
		if($debug) {
			echo 'ModelBase::FromDB'.PHP_EOL;
			echo 'DB Array:'.PHP_EOL;
			print_r($array);
		}
		$obj = new static();
		
		$mapping = get_class_vars($obj->mapper)['mapping'];
		$db_mapping = array_flip($mapping);
		
		foreach ($array as $key => $value)
		{	
			if(!array_key_exists($key, $db_mapping))
				continue;
			
			$obj_key = $db_mapping[$key];

			if(property_exists($obj, $obj_key)) {
				$obj->$obj_key = $value;
			}
		}
		
		if($debug) {
			echo 'Object:'.PHP_EOL;
			var_dump($obj);
		}
		
		return $obj;
	}
}

?>