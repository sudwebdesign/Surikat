<?php namespace Surikat\DependencyInjection;
use ReflectionClass;
use Surikat\DependencyInjection\MutatorMagic;
use Surikat\DependencyInjection\Facade;
class Container{
	use MutatorMagic,Facade{
		Facade::__call insteadof MutatorMagic;
		MutatorMagic::__call as ___call;
	}
	static function get(){
		$args = func_get_args();
		if(empty($args))
			return static::getStatic();
		$key = array_shift($args);
		return static::getStatic()->getDependency($key,$args);
	}
	static function set($key,$value){
		return static::getStatic()->setDependency($key,$value);
	}
	static function factory($value){
		$value = Convention::toClassMixed($value);
		if($value&&!is_object($value)){
			if(is_array($value)&&!empty($value)){
				$value = (new ReflectionClass(array_shift($value)))->newInstanceArgs($value);
			}
			else{
				$value = new $value();
			}
		}
		return $value;
	}
	function defaultDependency($key,$args=null){
		return $this->defaultNew($key,$args);
	}
}