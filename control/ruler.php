<?php namespace surikat\control; 
class ruler {
	// static function __callStatic($func,array $args=array()){return true;} //court-circuit
	static function unique($v){
		return $this->model->id||!R::findOne($this->model->tableName,' WHERE '.$this->col.'=?',array($v));
	}
	static function required($v){
		return !empty($v);
	}
	static function contains($v,$arg){
		return in_array(trim(strtolower($v)), explode(chr(32), trim(strtolower($arg))));
	}
	static function tel($v){
		return preg_match("/^((\+\d{1,3}(-| )?\(?\d\)?(-| )?\d{1,5})|(\(?\d{2,6}\)?))(-| )?(\d{3,4})(-| )?(\d{4})(( x| ext)\d{1,5}){0,1}$/",$v);
	}
	static function email($v){
		return filter_var($v, FILTER_VALIDATE_EMAIL);
	}
	static function maxlength($v,$arg){
		return mb_strlen($v)<=(int)$arg;
	}
	static function minlength($v,$arg){
		return mb_strlen($v)>=(int)$arg;
	}
	static function exactlength($v,$arg){
		return mb_strlen($v)==(int)$arg;
	}
	static function alpha($v){
		return preg_match("/^([a-zÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÒÓÔÕÖÙÚÛÜÝàáâãäåçèéêëìíîïðòóôõöùúûüýÿ])+$/i",$v)!==FALSE;
	}
	static function alpha_numeric($v){
		return preg_match("/^([a-z0-9ÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÒÓÔÕÖÙÚÛÜÝàáâãäåçèéêëìíîïðòóôõöùúûüýÿ])+$/i",$v)!==FALSE;
	}
	static function alpha_dash($v){
		return preg_match("/^([a-z0-9ÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÒÓÔÕÖÙÚÛÜÝàáâãäåçèéêëìíîïðòóôõöùúûüýÿ_-])+$/i",$v)!==FALSE;
	}
	static function numeric($v){
		return is_numeric($v);
	}
	static function integer($v){
		return is_integer(filter_var($v, FILTER_VALIDATE_INT));
	}
	static function boolean($v){
		return is_bool(filter_var($v, FILTER_VALIDATE_BOOLEAN));
	}
	static function float($v){
		return filter_var($v, FILTER_VALIDATE_FLOAT);
	}
	static function url($v){
		return filter_var($v, FILTER_VALIDATE_URL);
	}
	static function url_exists($v){
		$v = str_replace(array('http://','https://','ftp://'),'',strtolower($v)); 
		return function_exists('checkdnsrr')?checkdnsrr($v):gethostbyname($v)!=$v;
	}
	static function ip($v){
		return filter_var($v,FILTER_VALIDATE_IP)!==FALSE;
	}
	static function name($v){
		return preg_match("/^([a-zÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÒÓÔÕÖÙÚÛÜÝàáâãäåçèéêëìíîïñðòóôõöùúûüýÿ '-])+$/i", $v)!==FALSE;
	}
	static function cc($c){
		$number = preg_replace('/\D/', '', $v);
		$number_length = mb_strlen($v);
	  	$parity = $number_length % 2;
	 	$total = 0;
	  	for($i=0;$i<$number_length;$i++){
	    	$digit = $number[$i];
	    	if($i%2==$parity) {
	      		$digit *= 2;
	      		if($digit>9) $digit -= 9;
	    	}
	    	$total += $digit;
	  	}
		return $total%10==0;
	}
	static function date($v){
		return C_Filter::validate_date($v);
	}
	static function time($v){
		return C_Filter::validate_time($v);
	}
}
?>