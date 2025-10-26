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

}
