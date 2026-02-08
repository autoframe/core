<?php

namespace Unit\ModuleBox;

use Autoframe\Core\ModuleBox\AfrModuleConstantsInterface;
use Autoframe\Core\ModuleBox\AfrModuleInterface;
use Autoframe\Core\ModuleBox\AfrModuleTrait;

class TestBaseLoExt3A implements AfrModuleInterface
{
	use AfrModuleTrait;

	public static function getDefaultModuleConfig(): array
	{
		return [
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
				AfrModuleConstantsInterface::sFuncConcreteFQCN => TestFnxEatTwo::class,
				AfrModuleConstantsInterface::bExcludedFunctionality => false,
				AfrModuleConstantsInterface::anFunctionalitySettings => [6, 7, 8,9],
				AfrModuleConstantsInterface::onFunctionalityApplySettingsClosure => function (TestFniEat $oInstance, $anSettings) {
					if ($anSettings) $oInstance->eatSome(implode(';', (array)$anSettings));
				},
			],
		];
	}
}