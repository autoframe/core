<?php

namespace Autoframe\Core\AfrCoreModules;

use Autoframe\Core\AfrCoreModules\Concrete\Routes\AfrFnCliRoutes;
use Autoframe\Core\AfrCoreModules\Concrete\Routes\AfrFnHttpRoutes;
use Autoframe\Core\AfrCoreModules\FnContracts\AfrHttpRoutesContract;
use Autoframe\Core\ModuleBox\AfrModuleInterface;
use Autoframe\Core\ModuleBox\AfrModuleTrait;
use Autoframe\Core\AfrCoreModules\FnContracts\AfrCliRoutesContract;

class AfrCoreAll implements AfrModuleInterface{
	use AfrModuleTrait;

	public static function getDefaultModuleConfig(): array
	{
		return [
			self::bDisabledModule => false, // self::snModuleReplaces => null, self::snModuleExtends => null,
			self::aFunctionalities => static::getDefaultFunctionalitiesConfig(),
		];
	}

	public static function getDefaultFunctionalitiesConfig(): array
	{
		return [
			AfrCliRoutesContract::class=>[
				self::sFuncConcreteFQCN => AfrFnCliRoutes::class,
			//	self::bSingletonFunctionalityWithMergedSettings => false,
				//			self::sBridgeFunctionalityOnCommonInstanceKey => 'BridgeStuffAx',
			//	self::bExcludedFunctionality  => false,
				self::anFuncSettings  =>  [],
			//	self::onFunctionalityApplySettingsClosure  => function () {	},
			],

			AfrHttpRoutesContract::class=>[
				self::sFuncConcreteFQCN => AfrFnHttpRoutes::class,
				self::anFuncSettings  =>  [],
			]
		];
	}
}