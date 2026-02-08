<?php

namespace Unit\ModuleBox;

use Autoframe\Core\ModuleBox\AfrModuleConstantsInterface;
use Autoframe\Core\ModuleBox\AfrModuleInterface;
use Autoframe\Core\ModuleBox\AfrModuleTrait;

class TestReplL1RepExt7A4A4B implements AfrModuleInterface
{
	use AfrModuleTrait;

	public static function getDefaultModuleConfig(): array
	{
		return [
			AfrModuleConstantsInterface::bDisabledModule => false,
			AfrModuleConstantsInterface::snModuleReplaces => TestBaseL0ExtRep4B::class,
			AfrModuleConstantsInterface::snModuleExtends => TestBaseL0ExtRep4A::class,
			AfrModuleConstantsInterface::aFunctionalities => static::getDefaultFunctionalitiesConfig(),
		];
	}

	public static function getDefaultFunctionalitiesConfig(): array
	{
		return [
			TestFniEatPie::class => [
				AfrModuleConstantsInterface::sFuncConcreteFQCN => TestFnxEatPie::class,
				AfrModuleConstantsInterface::anFunctionalitySettings => ['P1' => 'Blue'],
				AfrModuleConstantsInterface::onFunctionalityApplySettingsClosure => function (TestFniEatPie $oInstance, $anSettings) {
					if (is_array($anSettings)) sort($anSettings);
					if ($anSettings) $oInstance->eatPie(implode('~', (array)$anSettings));
				},
			],
			TestFniEat::class => [
				AfrModuleConstantsInterface::sFuncConcreteFQCN => TestFnxEatPie::class,
				AfrModuleConstantsInterface::anFunctionalitySettings => ['P2' => 'Red'],
				AfrModuleConstantsInterface::onFunctionalityApplySettingsClosure => function (TestFniEat $oInstance, $anSettings) {
					if (is_array($anSettings)) sort($anSettings);
					if ($anSettings) $oInstance->eatSome(implode('~', (array)$anSettings));
				},
			]
		];
	}
}