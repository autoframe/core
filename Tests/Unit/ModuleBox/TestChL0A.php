<?php

namespace Unit\ModuleBox;

use Autoframe\Core\ModuleBox\AfrModuleConstantsInterface;
use Autoframe\Core\ModuleBox\AfrModuleInterface;
use Autoframe\Core\ModuleBox\AfrModuleTrait;

class TestChL0A implements AfrModuleInterface
{
	use AfrModuleTrait;

	public static function getDefaultModuleConfig(): array
	{
		return [
			'CH'=>'TestChL0A',
			'TestChL0A'=>'TestChL0A',
			'TestChL01'=>'TestChL0A',
			AfrModuleConstantsInterface::bDisabledModule => false,
			AfrModuleConstantsInterface::snModuleReplaces => null,
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
				AfrModuleConstantsInterface::anFunctionalitySettings  => ['CHL0A','cHloa'=>'loa'],
				AfrModuleConstantsInterface::onFunctionalityApplySettingsClosure  => function (TestFniBridgeStuff $oInstance,$anSettings) {
					if($anSettings) $oInstance->bridgeAction(implode(';',(array)$anSettings));
				},
			]
		];
	}
}