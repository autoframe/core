<?php

use Autoframe\Core\ModuleBox\AfrModuleBoxClass;
use Autoframe\Core\ModuleBox\AfrModuleConstantsInterface;
use Autoframe\Core\ModuleBox\Example\AfrMailBaseModuleClass;
use Autoframe\Core\ModuleBox\Example\AfrMailReplacementModuleClass;

// Example registration snippet (not a separate file):
$box = AfrModuleBoxClass::getInstance();
if (rand(0, 1)) {
	$box->registerModuleFQCN(AfrMailBaseModuleClass::class);
	$box->registerModuleFQCN(AfrMailReplacementModuleClass::class, [
		AfrModuleConstantsInterface::snModuleReplaces => AfrMailBaseModuleClass::class,
	]);
} else {
	$box->registerModuleInstance(new AfrMailBaseModuleClass(), [
		AfrModuleConstantsInterface::bDisabledModule => false,
		AfrModuleConstantsInterface::snModuleReplaces => null,
		AfrModuleConstantsInterface::snModuleExtends => null,
		AfrModuleConstantsInterface::aFunctionalities => [], // can be overridden by app config
	]);
	$box->registerModuleInstance(new AfrMailReplacementModuleClass(), [
		AfrModuleConstantsInterface::bDisabledModule => false,
		AfrModuleConstantsInterface::snModuleReplaces => AfrMailBaseModuleClass::class,
		AfrModuleConstantsInterface::snModuleExtends => null,
		AfrModuleConstantsInterface::aFunctionalities => [],
	]);
}



