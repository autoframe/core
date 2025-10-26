<?php

use Autoframe\Core\AfrCoreModule\AfrCore;
use Autoframe\Core\Module\AfrModuleInterface;
use Autoframe\Core\Module\AfrModuleCLIRoutesInterface;
use Autoframe\Core\Module\AfrModuleHTTPRoutesInterface;
return [];
return [
	AfrCore::class => function () {
		// custom registration procedures... must return array of implementing interfaces or empty for auto-detection
		return [AfrModuleInterface::class, AfrModuleHTTPRoutesInterface::class, AfrModuleCLIRoutesInterface::class];
	},


];