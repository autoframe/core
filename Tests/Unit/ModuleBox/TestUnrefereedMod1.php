<?php

namespace Unit\ModuleBox;

use Autoframe\Core\ModuleBox\AfrModuleConstantsInterface;
use Autoframe\Core\ModuleBox\AfrModuleInterface;
use Autoframe\Core\ModuleBox\AfrModuleTrait;

class TestUnrefereedMod1 implements AfrModuleInterface
{
	use AfrModuleTrait;

	public static function getDefaultModuleConfig(): array
	{
		return [
			'SomeData' => 'TestUnrefereedMod1',
			AfrModuleConstantsInterface::bDisabledModule => false,
			AfrModuleConstantsInterface::snModuleReplaces => null,
			AfrModuleConstantsInterface::snModuleExtends => null,
			AfrModuleConstantsInterface::aFunctionalities => static::getDefaultFunctionalitiesConfig(),
		];
	}

	public static function getDefaultFunctionalitiesConfig(): array
	{

		return [
			TestFniEat::class => [
				AfrModuleConstantsInterface::sFuncConcreteFQCN => TestFnxEat::class,
				AfrModuleConstantsInterface::bSingletonFunctionalityWithMergedSettings => false,
				AfrModuleConstantsInterface::sBridgeFunctionalityOnCommonInstanceKey => '',
				AfrModuleConstantsInterface::bExcludedFunctionality => false,
				AfrModuleConstantsInterface::anFuncSettings => [2, 5, 9],
				AfrModuleConstantsInterface::onFunctionalityApplySettingsClosure => function (TestFniEat $oInstance, $anSettings) {
					if ($anSettings) $oInstance->eatSome(implode(';', (array)$anSettings));
				},
			],
			TestFniBridgeStuff::class => [
				AfrModuleConstantsInterface::sFuncConcreteFQCN => TestFnxBridgeStuffTwo::class,
				AfrModuleConstantsInterface::sBridgeFunctionalityOnCommonInstanceKey => 'ExtL16A-Unreferenced~~~~~',
				AfrModuleConstantsInterface::anFuncSettings => ['UnrefereedMod1' => 'UnrefereedMod1'],
				AfrModuleConstantsInterface::onFunctionalityApplySettingsClosure => function (TestFniBridgeStuff $oInstance, $anSettings) {
					if (is_array($anSettings)) sort($anSettings);
					if ($anSettings) $oInstance->bridgeAction(implode('_', (array)$anSettings));
				},
			],
			TestFniSleep::class => [
				AfrModuleConstantsInterface::sFuncConcreteFQCN => TestFnxSleep::class,
			]
		];
	}
}