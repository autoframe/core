<?php

declare(strict_types=1);

namespace Autoframe\Core\ModuleBox;

/**
 * default implementation of the common module interface
 */
trait AfrModuleTrait
{
	protected static array $aAfrModuleNaming = [];

	/**
	 * Register module instance.
	 */
	public function registerModuleInstance(): void {}


	/**
	 * Get default module config.
	 */
	public static function getDefaultModuleConfig(): array
	{
		return [
			AfrModuleConstantsInterface::bDisabledModule => false,
			AfrModuleConstantsInterface::snModuleReplaces => null,
			AfrModuleConstantsInterface::snModuleExtends => null,
			//	AfrModuleConstantsInterface::aFunctionalities => [],// static::getDefaultFunctionalitiesConfig(),
		];
	}


	/**
	 * Get module name space.
	 */
	public static function getModuleNameSpace(): string
	{
		return static::parseModuleNaming()['ns'];
	}

	/**
	 * Get module name.
	 */
	public static function getModuleName(): string
	{
		return static::parseModuleNaming()['name'];
	}

	protected static function parseModuleNaming(): array
	{
		if (empty(static::$aAfrModuleNaming[$class = static::class]['name'])) {
			$pos = strrpos($class, '\\');
			static::$aAfrModuleNaming[$class]['ns'] = (string)($pos === false ? '' : substr($class, 0, $pos));
			static::$aAfrModuleNaming[$class]['name'] = (string)($pos === false ? $class : substr($class, $pos + 1));
		}
		return static::$aAfrModuleNaming[$class];
	}

	/**
	 * This should be always implemented into the module class, or will return a blank array as default
	 * @return array
	 */
	public static function getDefaultFunctionalitiesConfig(): array
	{
		$aFnModel = [
			'SomeInterfaceDDDDD::class'=>[
				AfrModuleConstantsInterface::sFuncConcreteFQCN     => 'SomeConcreteImplementingDDDDD::class',
				AfrModuleConstantsInterface::bSingletonFunctionalityWithMergedSettings => false,
				AfrModuleConstantsInterface::bMergeFunctionalityIntKeys => false,
				AfrModuleConstantsInterface::sBridgeFunctionalityOnCommonInstanceKey => 'SomeCommonInstance',
				AfrModuleConstantsInterface::bMergeFunctionalityFlushOldConfig => false,
				AfrModuleConstantsInterface::bExcludedFunctionality  => false,
				AfrModuleConstantsInterface::anFuncSettings  => [], //array|null
				AfrModuleConstantsInterface::onFunctionalityApplySettingsClosure  => function ($anSettings) {},
			]
		];
		return rand(2, 3) > 1 ? $aFnModel : static::getDefaultFunctionalitiesConfigFromFile();
	}

	protected static function getDefaultFunctionalitiesConfigFromFile(): array
	{
		return (array)(include(
			static::getModuleDirPath() . DIRECTORY_SEPARATOR . static::getModuleName() . '-DefaultFunctionalitiesConfig.php'
		));
	}

	/**
	 * Get module fqcn.
	 */
	public static function getModuleFQCN(): string
	{
		return static::class;
	}


	/**
	 * Get module dir path.
	 */
	public static function getModuleDirPath(): string
	{
		// This assumes one module per file and __FILE__ corresponds to the main class.
		// In real projects you might inject this or compute it differently.
		return static::$aAfrModuleNaming[static::class][__FUNCTION__] ??= dirname(static::getModuleClassFilePath());
	}

	/**
	 * Get module class file path.
	 */
	public static function getModuleClassFilePath(): string
	{
		// In real usage, this could be hard-coded or injected.
		// Here we rely on debug_backtrace to find the declaring file on first call.
		return static::$aAfrModuleNaming[static::class][__FUNCTION__] ??=
			((new \ReflectionClass(static::class))->getFileName() ?: '');
	}


}
