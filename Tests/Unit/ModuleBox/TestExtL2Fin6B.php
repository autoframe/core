<?php

namespace Unit\ModuleBox;

use Autoframe\Core\ModuleBox\AfrModuleConstantsInterface;
use Autoframe\Core\ModuleBox\AfrModuleInterface;
use Autoframe\Core\ModuleBox\AfrModuleTrait;

class TestExtL2Fin6B implements AfrModuleInterface
{
	use AfrModuleTrait;

	public static function getDefaultModuleConfig(): array
	{
		return [
			AfrModuleConstantsInterface::bDisabledModule => false,
			AfrModuleConstantsInterface::snModuleReplaces => null,
			AfrModuleConstantsInterface::snModuleExtends => TestExtL1Ext6A::class,
			AfrModuleConstantsInterface::aFunctionalities => static::getDefaultFunctionalitiesConfig(),
		];
	}

	public static function getDefaultFunctionalitiesConfig(): array
	{
		return [
			TestFniBridgeStuff::class => [
				AfrModuleConstantsInterface::sFuncConcreteFQCN => TestFnxBridgeStuffTwo::class,
				AfrModuleConstantsInterface::sBridgeFunctionalityOnCommonInstanceKey => 'x',
				AfrModuleConstantsInterface::anFuncSettings => ['ExtL26B' => 'ExtL26B','Ext-L26B','2.Ext-L26B'],
			],
			TestFniEat::class => [
				AfrModuleConstantsInterface::sFuncConcreteFQCN => TestFnxEatTwo::class,
				AfrModuleConstantsInterface::bExcludedFunctionality => false,
				AfrModuleConstantsInterface::anFuncSettings => [0=>"A", 3=>'D',4=>'E'],
				AfrModuleConstantsInterface::onFunctionalityApplySettingsClosure => function (TestFniEat $oInstance, $anSettings) {
					if ($anSettings) $oInstance->eatSome(implode(';', (array)$anSettings));
				},
			],
		];
	}
}

