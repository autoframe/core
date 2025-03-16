<?php

namespace Autoframe\Core\Tenant;

interface AfrDefaultTenantConfigsInterface
{
	/**
	 * The file must return an array with data or a closure, as a .php file contents called with INCLUDE()
	 *
	 * This method returns the default configuration settings for a specific tenant. These settings
	 * can be used as the base configuration for the tenant in order to apply any customizations are applied.
	 *
	 * !!! IMPORTANT: the implementing class must call AfrTenant::getAfrDefaultTenantConfigsForFqcn(static::class) to include the config and use it!
	 * @return string
	 */
	public static function sampleTenantDefaultConfig(): ?string;

	//	return file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'config.sample.CLASS_NAME.php');

	/*
	protected static array $aSet = [];
	public static function applyDefaultTenantConfig(bool $bForce = false): void
	{
		$sBindingsFile = AfrTenant::getAfrDefaultTenantConfigsForFqcn(static::class);
		if (empty($sBindingsFile) || (!empty(self::$aSet[__FUNCTION__]) && !$bForce)) {
			return;
		}
		//	static::default();
		self::$aSet[static::class][__FUNCTION__] = true;
		if(!file_exists($sBindingsFile)){
			return;
		}
		static::bind(include $sBindingsFile);
	}

	public static function applyDefaultTenantConfig(): void
	{
		if (!empty(self::$aSet[static::class][__FUNCTION__])) {
			return;
		}
		if (!empty($sConfigFilePath = AfrTenant::getAfrDefaultTenantConfigsForFqcn(static::class))) {
			self::$aSet[static::class][__FUNCTION__] = true;
			if (file_exists($sConfigFilePath)) {
				static::extendConfigFlagsFromArray((include $sConfigFilePath));
			}
		}
	}

	public function applyDefaultTenantConfig(): void
	{
		if(!empty($this->bApplyDefaultTenantConfig)){
			return;
		}

		$this->bApplyDefaultTenantConfig = true;

		if (!empty($sConfigFile = AfrTenant::getAfrDefaultTenantConfigsForFqcn($this)) && file_exists($sConfigFile)) {
			(include $sConfigFile)($this);
		}
	}
	*/


}
