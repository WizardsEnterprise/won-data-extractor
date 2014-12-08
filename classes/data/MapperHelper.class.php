<?php
class MapperHelper {
	public static function ColumnNames($mapping, $excludes = array()) {
		return array_diff(array_keys($mapping), $excludes);
	}

	public static function GetParamValues($obj, $operation, $mapping, $excludes = array()) {
		$arr = array();
		foreach(self::ColumnNames($mapping, $excludes) as $col) {
			$arr[] = $obj->{$mapping[$col]};
		}
	
		if($operation == 'Update')
			$arr[] = $obj->id;
	
		return $arr;
	}
}
?>