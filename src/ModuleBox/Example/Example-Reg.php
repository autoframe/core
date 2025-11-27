<?php
// Example only – place in your bootstrap or test script.
// Example registration (illustrating disabled + replaced + extended):


use Autoframe\Core\ModuleBox\AfrModuleBoxClass;
use Autoframe\Core\ModuleBox\AfrModuleConstantsInterface;
use Autoframe\Core\ModuleBox\Example\AfrMailBaseModuleClass;
use Autoframe\Core\ModuleBox\Example\AfrMailReplacementModuleClass;
use Autoframe\Core\ModuleBox\Example\AfrMailExtenderModuleClass;
use Autoframe\Core\ModuleBox\Example\AfrEmailSenderInterface;

// Get the Module Box singleton
$box = AfrModuleBoxClass::getInstance();
/*
// Base mail module (could be disabled but still used as base for extension)
$box->registerModuleInstance(new AfrMailBaseModuleClass(), [
	AfrModuleConstantsInterface::bDisabledModule        => false,
	AfrModuleConstantsInterface::snModuleReplaces        => null,
	AfrModuleConstantsInterface::snModuleExtends         => null,
	AfrModuleConstantsInterface::aFunctionalities => [],
]);

// Replacement mail module (takes over the base module FQCN)
$box->registerModuleInstance(new AfrMailReplacementModuleClass(), [
	AfrModuleConstantsInterface::bDisabledModule        => false,
	AfrModuleConstantsInterface::snModuleReplaces        => AfrMailBaseModuleClass::class,
	AfrModuleConstantsInterface::snModuleExtends         => null,
	AfrModuleConstantsInterface::aFunctionalities => [],
]);

// Extender mail module (extends the base module config, not the replacement)
$box->registerModuleInstance(new AfrMailExtenderModuleClass(), [
	AfrModuleConstantsInterface::bDisabledModule        => false,
	AfrModuleConstantsInterface::snModuleReplaces        => null,
	AfrModuleConstantsInterface::snModuleExtends         => AfrMailBaseModuleClass::class,
	AfrModuleConstantsInterface::aFunctionalities => [],
]);
*/

// Base mail module (could be disabled but still used as base for extension)
$box->registerModuleFQCN(AfrMailBaseModuleClass::class);

// Replacement mail module (takes over the base module FQCN)
$box->registerModuleFQCN(AfrMailReplacementModuleClass::class, [
	AfrModuleConstantsInterface::snModuleReplaces => AfrMailBaseModuleClass::class,
]);

// Extender mail module (extends the base module config, not the replacement)
$box->registerModuleFQCN(AfrMailExtenderModuleClass::class, [
	AfrModuleConstantsInterface::snModuleExtends => AfrMailBaseModuleClass::class,
]);


// Resolving the base module returns the replacer:
$resolvedBaseModule = $box->resolveModule(AfrMailBaseModuleClass::class);

// Resolving the email sender as single instance:
$emailSender = $box->resolveFunctionality(
	AfrEmailSenderInterface::class,
	[],
	true
);
// $emailSender is either the module-provided implementation or a container fallback.
