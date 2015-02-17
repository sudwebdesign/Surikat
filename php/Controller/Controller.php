<?php namespace Surikat\Controller;
use Surikat\Core\HTTP;
use Core\Domain;
use Surikat\Core\ArrayObject;
use Surikat\View\View;
use Surikat\View\TML;
use Surikat\I18n\Lang;
use Surikat\Dependency\Injector;
class Controller{
	use Injector;
	
	protected $Router;
	protected $View;
	protected $prefixTmlCompile = '';
	function __invoke($params,$uri,$Router){
		$path = is_string($params)?$params:$params[0];
		$this->Router = $Router;
		if(method_exists($Router,'getDirHook')
			&&$hook = $Router->getDirHook()){
			$this->prefixTmlCompile .= '.'.$hook.'/';
			$this->getView()->setDirCwd([
				SURIKAT_PATH.$hook.'/',
				SURIKAT_SPATH.$hook.'/',
			]);
		}
		$v = $this->getView();
		$v->onCompile(function($TML){
			if(!isset($TML->childNodes[0])||$TML->childNodes[0]->namespace!='Presenter')
				$TML->prepend('<Presenter:Presenter uri="static" />');
			$this->getDependency('View\Toolbox')->JsIs($TML);
			if(!$this->getDependency('Dev\Level')->VIEW)
				$this->getDependency('View\Toolbox')->autoMIN($TML);
		});
		$this->display($path.'.tml');
	}
	function setRouter($Router){
		$this->Router = $Router;
	}
	function getRouter(){
		return $this->Router;
	}
	function setView($View){
		$this->View = $View;
	}
	function getView(){
		if(!isset($this->View)){
			$this->setView(new View());
			$this->View->setController($this);
		}
		return $this->View;
	}
	function addPrefixTmlCompile($prefix){
		$this->prefixTmlCompile .= $prefix;
	}
	function display($file){
		$v = $this->getView();
		$v->set('URI',$this->getRouter());
		$v->setDirCompile(SURIKAT_TMP.'tml/compile/'.$this->prefixTmlCompile);
		$v->setDirCache(SURIKAT_TMP.'tml/cache/'.$this->prefixTmlCompile);
		try{
			$v->display($file);
		}
		catch(\Surikat\Exception\View $e){
			$this->error($e->getMessage());
		}
	}
	function error($c){
		try{
			$v = $this->getView();
			$v->set('URI',$this->getRouter());
			$v->display($c.'.tml');
		}
		catch(\Surikat\Exception\View $e){
			HTTP::code($e->getMessage());
		}
		exit;
	}
}