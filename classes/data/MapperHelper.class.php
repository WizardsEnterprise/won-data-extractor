<?php
class MapperHelper {
	public static function ColumnNames($mapping, $excludes = array()) {
		return array_diff(array_keys($mapping), $excludes);
	}

	public static function GetParamValues($obj, $operation, $mapping, $excludes = array(), $keys = false) {
		$arr = array();
		foreach(self::ColumnNames($mapping, $excludes) as $col) {
			$arr[] = $obj->{$mapping[$col]};
		}
	
		if($operation == 'Update') {
			if($keys === false)
				$arr[] = $obj->id;
			else {
				foreach($keys as $key)
					$arr[] = $obj->$key;
			}
		}
	
		return $arr;
	}
}
?>