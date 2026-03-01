<?php

namespace Unit\ModuleBox;

use Autoframe\Core\ModuleBox\AfrModuleConstantsInterface;
use Autoframe\Core\ModuleBox\AfrModuleInterface;
use Autoframe\Core\ModuleBox\AfrModuleTrait;

class TestExtL1Ext6A implements AfrModuleInterface
{
	use AfrModuleTrait;

	public static function getDefaultModuleConfig(): array
	{
		return [
			AfrModuleConstantsInterface::bDisabledModule => false,
			AfrModuleConstantsInterface::snModuleReplaces => null,
			AfrModuleConstantsInterface::snModuleExtends => TestBaseLoExt3A::class,
			AfrModuleConstantsInterface::aFunctionalities => static::getDefaultFunctionalitiesConfig(),
		];
	}

	public static function getDefaultFunctionalitiesConfig(): array
	{
		return [
			TestFniBridgeStuff::class => [
				AfrModuleConstantsInterface::sFuncConcreteFQCN => TestFnxBridgeStuffTwo::class,
				AfrModuleConstantsInterface::bSingletonFunctionalityWithMergedSettings => false,
				AfrModuleConstantsInterface::sBridgeFunctionalityOnCommonInstanceKey => 'ExtL16A-Unreferenced~~~~~',
				AfrModuleConstantsInterface::bExcludedFunctionality => false,
				AfrModuleConstantsInterface::anFuncSettings => ['ExtL16A' => 'ExtL16A','Ext-L16A'],
				AfrModuleConstantsInterface::onFunctionalityApplySettingsClosure => function (TestFniBridgeStuff $oInstance, $anSettings) {
					//if ($anSettings) $oInstance->bridgeAction(implode('*', (array)$anSettings));
					if (is_array($anSettings)) sort($anSettings);
					if ($anSettings) $oInstance->bridgeAction(implode('_', (array)$anSettings));
				},
			]
		];
	}
}