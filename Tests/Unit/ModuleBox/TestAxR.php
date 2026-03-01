<?php

namespace Unit\ModuleBox;

use Autoframe\Core\ModuleBox\AfrModuleConstantsInterface;
use Autoframe\Core\ModuleBox\AfrModuleInterface;
use Autoframe\Core\ModuleBox\AfrModuleTrait;

class TestAxR implements AfrModuleInterface
{
	use AfrModuleTrait;

	public static function getDefaultModuleConfig(): array
	{
		return [
			'AxR'=>'TestAxR',
			AfrModuleConstantsInterface::bDisabledModule => false,
			AfrModuleConstantsInterface::snModuleReplaces => TestAxEnd::class,
			AfrModuleConstantsInterface::snModuleExtends => null,
			AfrModuleConstantsInterface::aFunctionalities => static::getDefaultFunctionalitiesConfig(),
		];
	}

	public static function getDefaultFunctionalitiesConfig(): array
	{
		return [
		TestFniBridgeStuff::class=>[
			AfrModuleConstantsInterface::sFuncConcreteFQCN => TestFnxBridgeStuff::class,
			AfrModuleConstantsInterface::bSingletonFunctionalityWithMergedSettings => false,
		//	AfrModuleConstantsInterface::sBridgeFunctionalityOnCommonInstanceKey => 'BridgeStuffAx',
			AfrModuleConstantsInterface::bExcludedFunctionality  => false,
			AfrModuleConstantsInterface::anFuncSettings  => ['TestAxR','RL99'=>'ReplacerOfTestAxEnd'],
			AfrModuleConstantsInterface::onFunctionalityApplySettingsClosure  => function (TestFniBridgeStuff $oInstance,$anSettings) {
				if($anSettings) $oInstance->bridgeAction(implode(';',(array)$anSettings));
			},
		]
	];
	}
}