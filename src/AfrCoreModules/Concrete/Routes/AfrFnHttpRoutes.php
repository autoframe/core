<?php

namespace Autoframe\Core\AfrCoreModules\Concrete\Routes;

use Autoframe\Core\Afr\Afr;
use Autoframe\Core\AfrCoreModules\Reusable\AfrHttpRoutesHelper;
use Autoframe\Core\AfrCoreModules\FnContracts\AfrHttpRoutesContract;

class AfrFnHttpRoutes implements AfrHttpRoutesContract
{
	use AfrHttpRoutesHelper;
	const SELF_DIR = __DIR__;


/*
	//TODO:  implements AfrDefaultTenantConfigsInterface
	public function applyDefaultTenantConfig(): void
	{
		if (!$this->bTenantModulesLoaded) {
			$this->bTenantModulesLoaded = true;
			$aTenantModules = include Afr::getAfrDefaultTenantConfigsForFqcn(static::class);
			if (empty($aTenantModules)) return;

			foreach ($aTenantModules as $sModuleFQCN => $aImplementedInterfaces) {
				$this->addModuleToBox($sModuleFQCN, $aImplementedInterfaces);
			}
		}
	}
	//TODO: se pare ca este in AfrTenant::initFileSystem()
	//TODO: se pare ca este in AfrTenant::initFileSystem()
	//TODO: se pare ca este in AfrTenant::initFileSystem()
	// TODO AfrTenant:: $aAfrDefaultTenantConfigs are delievery static. DECE? EMBEDDED?
	public static function sampleTenantDefaultConfig(): ?string
	{
		return file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'config.sample.modules.php');
	}
	*/
}