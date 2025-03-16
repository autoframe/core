<?php

require_once __DIR__ . '/vendor/autoload.php';


if (strpos($_SERVER['REQUEST_URI'] ?? '', 'opcache')) {
	echo '<pre>';
	print_r(opcache_get_status());
	echo '</pre>';
} elseif (strpos($_SERVER['REQUEST_URI'] ?? '', 'pi~')) {
	phpinfo();
}


use Autoframe\Core\AfrCoreModule\AfrCore;
use Autoframe\Core\Afr\Afr;
use Autoframe\Core\CliTools\AfrCliHttpDetect;
use Autoframe\Core\String\AfrStr;
use Autoframe\Core\Tenant\AfrTenant;
use Autoframe\Core\Event\AfrEvent;


/*
AfrEvent::addEventClosure('*afr*',function ($aData) {
	echo "\n~~addEventClosure Class: ";
	if(!empty($this)){
		if($this instanceof Afr){
		//	$this->dick = '9999';
		}
	}
	debug_print_backtrace();
	echo (!empty($this) ?  (get_class($this).": ") : '~~~~-php~~~~:').print_r($aData,true);
	echo "\n\n";
},true);

AfrEvent::addEventClosure('*',function (&$aData) {
	$aData['AfrEvent::addEventClosure(*'] = 'rand(112,998)';
	return rand(112,998).count($aData);
});
AfrEvent::addEventClosure('Connection\AfrDbConnectionManagerClass::getInstance',function ($aData) {
	return 'Connection\AfrDbConnectionManagerClass::getInstance--'.rand(112,998).count($aData);
});

AfrEvent::dispatchEvent('',['s1',2,['a3']]);
AfrEvent::dispatchEvent('SomeEvD');
*/


//var_dump(AfrIncPhpCache::getInstance()->get('\Composer\Autoload\ClassLoader'));
//$oc->put($k, get_declared_classes(),15); die;
define('AFR_BASE_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'base1');

new Afr();

$aReport = Afr::app()->run(); //print_r($aReport);
AfrCore::getInstance()->registerModule();

AfrEvent::dispatchEvent('Some.Last.Eventx', ['SomeEvDArg1']);
if (0) {
	echo PHP_EOL .
		'E.time: ' .
		number_format(
			(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000,
			3
		) . ' MS @END' . PHP_EOL;
	print_r(AfrEvent::getTriggeredEventsLog());
	die;
}



/*
echo '<br><pre>';
//print_r(AfrTenant::getProtocolHost());
//print_r(AfrTenant::getHost());
//$l = print_r(array_merge(get_declared_classes(),get_declared_interfaces(),get_declared_traits()),true);
//echo str_repeat($l,20);
//var_dump($_ENV);
//AfrTenant::initFileSystem();
//print_r($_SERVER);
print_r($_SESSION);
echo '</pre>';
*/

