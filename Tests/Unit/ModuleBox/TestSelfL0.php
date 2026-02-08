<?php

namespace Unit\ModuleBox;

use Autoframe\Core\ModuleBox\AfrModuleConstantsInterface;
use Autoframe\Core\ModuleBox\AfrModuleInterface;
use Autoframe\Core\ModuleBox\AfrModuleTrait;

class TestSelfL0 implements AfrModuleInterface
{
	use AfrModuleTrait;

	public static function getDefaultModuleConfig(): array
	{
		return [
			'shouldPreserveInReplacer'=>'YES in '.TestSelfL1RepExt::class,
			AfrModuleConstantsInterface::bDisabledModule => false,
			AfrModuleConstantsInterface::snModuleReplaces => null,
			AfrModuleConstantsInterface::snModuleExtends => null,
			AfrModuleConstantsInterface::aFunctionalities => static::getDefaultFunctionalitiesConfig(),
		];
	}

	public static function getDefaultFunctionalitiesConfig(): array
	{
		return [
			TestFniSleep::class => [  // @ TestRepL2Fin5N
				AfrModuleConstantsInterface::sFuncConcreteFQCN => TestFnxSleep::class,
				AfrModuleConstantsInterface::bSingletonFunctionalityWithMergedSettings => true,
//				AfrModuleConstantsInterface::sBridgeFunctionalityOnCommonInstanceKey => '',
//				AfrModuleConstantsInterface::bExcludedFunctionality => false,
				AfrModuleConstantsInterface::anFunctionalitySettings => [22],
				AfrModuleConstantsInterface::onFunctionalityApplySettingsClosure => function (TestFniSleep $oInstance, $anSettings) {
					if ($anSettings) $oInstance->sleepMinutes(array_sum($anSettings));
				},
			]
		];
	}
}