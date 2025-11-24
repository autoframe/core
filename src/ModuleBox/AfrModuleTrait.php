<?php

declare(strict_types=1);

namespace Autoframe\Core\ModuleBox;

/**
 * default implementation of the common module interface
 */
trait AfrModuleTrait
{
	protected static array $aAfrModuleNaming = [];

	public function registerModuleInstance(): void {}


	public static function getDefaultModuleConfig(): array
	{
		return [
			AfrModuleConstantsInterface::bDisabledModule => false,
			AfrModuleConstantsInterface::snModuleReplaces => null,
			AfrModuleConstantsInterface::snModuleExtends => null,
		//	AfrModuleConstantsInterface::aFunctionalities => [],// static::getDefaultFunctionalitiesConfig(),
		];
	}


	public static function getModuleNameSpace(): string
	{
		return static::parseModuleNaming()['ns'];
	}

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
		return rand(2, 3) > 1 ? [] : static::getDefaultFunctionalitiesConfigFromFile();
	}

	protected static function getDefaultFunctionalitiesConfigFromFile(): array
	{
		return (array)(include(
			static::getModuleDirPath() . DIRECTORY_SEPARATOR . static::getModuleName() . '-DefaultFunctionalitiesConfig.php'
		));
	}

	public static function getModuleFQCN(): string
	{
		return static::class;
	}


	public static function getModuleDirPath(): string
	{
		// This assumes one module per file and __FILE__ corresponds to the main class.
		// In real projects you might inject this or compute it differently.
		return static::$aAfrModuleNaming [static::class][__FUNCTION__] ??= dirname(static::getModuleClassFilePath());
	}

	public static function getModuleClassFilePath(): string
	{
		// In real usage, this could be hard-coded or injected.
		// Here we rely on debug_backtrace to find the declaring file on first call.
		return static::$aAfrModuleNaming [static::class][__FUNCTION__] ??=
			((new \ReflectionClass(static::class))->getFileName() ?: '');
	}


}
