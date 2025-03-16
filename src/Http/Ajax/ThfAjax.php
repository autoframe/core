<?php

namespace Autoframe\Core\Http\Ajax;

/*
ThfAjax::status(bool,'message to show');
//ThfAjax::msg('info message'); //ThfAjax::$nl="\r\n";
//ThfAjax::redirect('/'); //ThfAjax::$callback='alert'; ThfAjax::$callback_params='MSG!';
ThfAjax::json();
//ThfAjax::prea();
*/


class ThfAjax{
	public static $out=array();
	public static $nl='<hr style="margin:5px;">'; //br |   \r\n
	public static function init(){
		self::$out=array(
			'redirect'=>NULL, //link for redirect after mesessage
			'status'=>-1, // true; false; info =-1;
			'msg'=>NULL, //str | array
			'callback'=>NULL, //js function name
			'callback_params'=>NULL,
			'click'=>false,//call
			'show_time'=>3000,//call
			'class'=>NULL,
			'data'=>array(),
		);
	}

	public static function redirect($link){self::$out['redirect']=$link;}
	public static function msg($msg){
		if(!$msg){return;}
		if(is_array(self::$out['msg'])){self::$out['msg'][]=$msg;}
		elseif(self::$out['msg']){self::$out['msg']=array(self::$out['msg'],$msg);}
		else{self::$out['msg']=array($msg);}
	}
	public static function status($status,$msg=''){
		if(!$status && !$msg){$msg='Generic state process ERROR!';}
		self::msg($msg);
		if(floor(self::$out['status'])==-1 || self::$out['status']){ self::$out['status']=$status;	}
	}

	public static function json(){self::process();	header('Content-Type:application/json');	die(json_encode(self::$out)); }
	public static function prea(){self::process();	prea(self::$out); die;	}
	public static function process(){
		if(is_array(self::$out['msg'])){
			self::$out['msg']=(count(self::$out['msg'])>1?implode(self::$nl,self::$out['msg']):self::$out['msg'][0]);
		}
		if(!self::$out['msg'] && !self::$out['status']){self::$out['msg']='Error!';}
		elseif(!self::$out['msg'] && floor(self::$out['status'])==-1){self::$out['msg']='Info!';}
		elseif(!self::$out['msg'] && self::$out['status'] && floor(self::$out['status'])==1){self::$out['msg']='Success!';}
	}
}//end of class
ThfAjax::init();