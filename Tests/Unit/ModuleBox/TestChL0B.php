<?php

namespace Unit\ModuleBox;

use Autoframe\Core\ModuleBox\AfrModuleConstantsInterface;
use Autoframe\Core\ModuleBox\AfrModuleInterface;
use Autoframe\Core\ModuleBox\AfrModuleTrait;

class TestChL0B implements AfrModuleInterface
{
	use AfrModuleTrait;

	public static function getDefaultModuleConfig(): array
	{
		return [
			'CH'=>'TestChL0B',
			'CHL2'=>'TestChL0B',
			'TestChL0B'=>'TestChL0B',
			'TestChL01'=>'TestChL0B',
			AfrModuleConstantsInterface::bDisabledModule => false,
			AfrModuleConstantsInterface::snModuleReplaces => null,
			AfrModuleConstantsInterface::snModuleExtends => null,
			AfrModuleConstantsInterface::aFunctionalities => static::getDefaultFunctionalitiesConfig(),
		];
	}

	public static function getDefaultFunctionalitiesConfig(): array
	{
		return [
			TestFniSleep::class => [
				AfrModuleConstantsInterface::sFuncConcreteFQCN => TestFnxSleepTwo::class,
				AfrModuleConstantsInterface::anFuncSettings => [27],
				AfrModuleConstantsInterface::onFunctionalityApplySettingsClosure => function (TestFniSleep $oInstance, $anSettings) {
					if ($anSettings) $oInstance->sleepMinutes(array_sum($anSettings));
				},
			]
		];
	}
}