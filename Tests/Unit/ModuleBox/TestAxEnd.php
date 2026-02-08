<?php

namespace Unit\ModuleBox;

use Autoframe\Core\ModuleBox\AfrModuleConstantsInterface;
use Autoframe\Core\ModuleBox\AfrModuleInterface;
use Autoframe\Core\ModuleBox\AfrModuleTrait;

class TestAxEnd implements AfrModuleInterface
{
	use AfrModuleTrait;

	public static function getDefaultModuleConfig(): array
	{
		return [
			'ABX'=>'TestAxEnd--NOT VISIBLE BECAUSE THE MODULE IS REPALCED BY TestAxR',
			AfrModuleConstantsInterface::bDisabledModule => false,
			AfrModuleConstantsInterface::snModuleReplaces => TestRepL2Fin5A::class,
			AfrModuleConstantsInterface::snModuleExtends => TestChL3ReplChL2ExtChL0B::class,
			AfrModuleConstantsInterface::aFunctionalities => static::getDefaultFunctionalitiesConfig(),
		];
	}

	public static function getDefaultFunctionalitiesConfig(): array
	{
		return [
			TestFniBridgeStuff::class=>[
				AfrModuleConstantsInterface::sFuncConcreteFQCN => TestFnxBridgeStuff::class,
				AfrModuleConstantsInterface::bSingletonFunctionalityWithMergedSettings => false,
	//			AfrModuleConstantsInterface::sBridgeFunctionalityOnCommonInstanceKey => 'BridgeStuffAx',
				AfrModuleConstantsInterface::bExcludedFunctionality  => false,
				AfrModuleConstantsInterface::anFunctionalitySettings  =>  ['TestAx','Ax99'=>'TestAxEnd'],
				AfrModuleConstantsInterface::onFunctionalityApplySettingsClosure  => function (TestFniBridgeStuff $oInstance,$anSettings) {
					if($anSettings) $oInstance->bridgeAction(implode('&',(array)$anSettings));
				},
			]
		];
	}
}