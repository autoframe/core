<?php

namespace Autoframe\Core\Module;

use Autoframe\Core\Afr\Afr;
use Autoframe\Core\AfrCoreModule\AfrCore;
use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;
use Autoframe\Core\Exception\AfrException;
use Autoframe\Core\Module\Exception\AfrModuleException;
use Autoframe\Core\Tenant\AfrDefaultTenantConfigsInterface;
use Autoframe\Core\Tenant\AfrTenant;

class AfrModuleBox extends AfrSingletonAbstractClass implements AfrDefaultTenantConfigsInterface
{
	protected array $aModules = [
		AfrCore::class => [
			AfrModuleInterface::class,
			AfrModuleHTTPRoutesInterface::class,
			AfrModuleCLIRoutesInterface::class
		],
	];

	/**
	 * @param string $sModuleFQCN
	 * @param array $aImplementedInterfaces
	 * @return void
	 * @throws AfrModuleException
	 */
	public function addModuleToBox(string $sModuleFQCN, array $aImplementedInterfaces)
	{
		$aImplementedInterfaces = empty($aImplementedInterfaces) ? (array)class_implements($sModuleFQCN) : $aImplementedInterfaces;
		if (!in_array(AfrModuleInterface::class, $aImplementedInterfaces)) {
			throw new AfrModuleException("The module '$sModuleFQCN' does not implement " . AfrModuleInterface::class);
		}
		$this->aModules[$sModuleFQCN] = $aImplementedInterfaces;
		//TODO: parent check when extending
		//Todo: dependencies
		//TODO: interfata pentru clasa
	}

	public function getModulesThatImplementTheInterface(string $sTargetInterface): array
	{
		$this->applyDefaultTenantConfig();
		foreach ($this->aModules as $sModuleFQCN => $aInterfaces) {
			if (in_array($sTargetInterface, $aInterfaces)) {
				$aModulesThatImplement[] = $sModuleFQCN;
			}
		}
		return $aModulesThatImplement ?? [];
	}

	public function registerModulesThatImplementTheInterface(string $sTargetInterface): int
	{
		$iSum = 0;
		foreach ($this->getModulesThatImplementTheInterface($sTargetInterface) as $sModuleFQCN) {
			/** @var AfrModuleInterface $oModule */
			$oModule = Afr::app()->container()->get($sModuleFQCN);
			$iSum += $oModule->registerModule([$sTargetInterface])[$sTargetInterface] ?? 0;
		}
		return $iSum;
	}

	protected bool $bTenantModulesLoaded = false;


	/**
	 * @return void
	 * @throws AfrModuleException
	 * @throws AfrException
	 */
	public function applyDefaultTenantConfig():void
	{
		if (!$this->bTenantModulesLoaded) {
			$this->bTenantModulesLoaded = true;
			$aTenantModules = include AfrTenant::getAfrDefaultTenantConfigsForFqcn(static::class);
			if(empty($aTenantModules)) {
				return;
			}
			foreach ($aTenantModules as $sModuleFQCN =>$aImplementedInterfaces) {
				$this->addModuleToBox($sModuleFQCN, $aImplementedInterfaces);
			}
		}
	}

	public static function sampleTenantDefaultConfig(): ?string
	{
		return file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'config.sample.modules.php');

	}
}