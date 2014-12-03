<?php namespace Surikat\Tool;
class ArrayTools{
	static function array_merge_recursive(){
		$args = func_get_args();
		$merged = array_shift($args);
		foreach($args as $array2){
			if(!is_array($array2)){
				continue;
			}
			foreach($array2 as $key => &$value){
				if(is_array($value)&&isset($merged [$key])&&is_array($merged[$key])){
					$merged[$key] = self::array_merge_recursive($merged[$key],$value);
				}
				else{
					$merged[$key] = $value;
				}
			}
		}
		return $merged;
	}
	static function array_values_recursive($key,$arr){
		$val = [];
		array_walk_recursive($arr, function($v, $k) use($key, &$val){
			if($k == $key) array_push($val, $v);
		});
		return count($val) > 1 ? $val : array_pop($val);
	}
}