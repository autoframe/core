<?php
declare(strict_types=1);

namespace Autoframe\Core\Config;

use Autoframe\Core\Object\AfrObjectSingletonAbstractClass;

final class AfrConfigRegister extends AfrObjectSingletonAbstractClass
{
	private array $aRegistry = [];
	private array $aInternalCache = [];  //TODO make private

	/**
	 * Get instance.
	 * @return AfrConfigRegister
	 */
	public static function getInstance(): AfrConfigRegister
	{
		return parent::getInstance();
	}

	/**
	 * Register config.
	 * @param AfrConfig $oConfigObject
	 * @return bool
	 */
	public function registerConfig(AfrConfig $oConfigObject): bool
	{
		if ($oConfigObject->getNamespaceAndClassOrTraitOrInterfaceOrKey()) {
			$this->aRegistry[$oConfigObject->getNamespaceAndClassOrTraitOrInterfaceOrKey()] = $oConfigObject;
			return true;
		}
		return false;
	}

	/**
	 * Get config.
	 * @param object $oClassInstance
	 * @return AfrConfig[]
	 */
	public function getConfig(object $oClassInstance): array
	{
		return $this->getExistentConfig($this->checkConfig($this->getClassComponents($oClassInstance)));

	}

	/**
	 * Get static config.
	 * @param string $sClassName
	 * @return AfrConfig[]
	 */
	public function getStaticConfig(string $sClassName): array
	{
		return $this->getExistentConfig($this->checkConfig($this->getStaticClassComponents($sClassName)));
	}

	/**
	 * Get data config.
	 * @param string $sRegKey
	 * @return array
	 */
	public function getDataConfig(string $sRegKey): array
	{
		return (isset($this->aRegistry[$sRegKey]) && $this->aRegistry[$sRegKey] instanceof AfrConfig) ?
			$this->aRegistry[$sRegKey]->getData() :
			[];
	}

	/**
	 * Get config by key.
	 * @param string $sRegKey
	 * @return AfrConfig|null
	 */
	public function getConfigByKey(string $sRegKey): ?AfrConfig
	{
		return (isset($this->aRegistry[$sRegKey]) && $this->aRegistry[$sRegKey] instanceof AfrConfig) ?
			$this->aRegistry[$sRegKey] :
			null;
	}

	/**
	 * @param object $oClassInstance
	 * @return array
	 */
	private function getClassComponents(object $oClassInstance): array
	{
		$aOut = $this->getComponents($oClassInstance);
		$aOut[] = get_class($oClassInstance);
		return array_values(array_unique($aOut));
	}


	/**
	 * @param string $sClassName
	 * @return array
	 */
	private function getStaticClassComponents(string $sClassName): array
	{
		$aOut = $this->getComponents($sClassName);
		$aOut[] = $sClassName;
		return array_values(array_unique($aOut));
	}

	/**
	 * @param $mClass
	 * @param array $aOut
	 * @return array
	 */
	private function getComponents($mClass, array $aOut = []): array
	{
		if (is_object($mClass)) {
			$sCacheKey = get_class($mClass);
		} else {
			$sCacheKey = (string)$mClass;
		}
		if (isset($this->aInternalCache[$sCacheKey])) {
			return $this->aInternalCache[$sCacheKey];
		}

		if (
			!is_object($mClass) &&
			!class_exists($mClass) &&
			!interface_exists($mClass) &&
			!trait_exists($mClass)
		) {
			return $this->aInternalCache[$sCacheKey] = array_unique($aOut);
		}
		$aThisClass = [];

		foreach ((array)class_uses($mClass) as $sTrait) {
			$aThisClass[] = $sTrait;
		}
		foreach ((array)class_implements($mClass) as $sInterface) {
			$aThisClass[] = $sInterface;
		}
		foreach ((array)class_parents($mClass) as $sParent) {
			$aThisClass[] = $sParent;
		}
		foreach ($aThisClass as $sParentClass) {
			$aOut = $this->getComponents($sParentClass, $aOut);
		}
		foreach ($aThisClass as $sParentClass) {
			$aOut[] = $sParentClass;
		}
		$aOut[] = $sCacheKey;

		return $this->aInternalCache[$sCacheKey] = array_unique($aOut);
	}

	/**
	 * @param array $aCheck
	 * @return array
	 */
	private function checkConfig(array $aCheck): array
	{
		$aOut = [];
		foreach ($aCheck as $sRegKey) {
			$aOut[$sRegKey] =
				isset($this->aRegistry[$sRegKey]) &&
				$this->aRegistry[$sRegKey] instanceof AfrConfig;
		}
		return $aOut;
	}

	/**
	 * @param array $aExistentConfig
	 * @return array
	 */
	private function getExistentConfig(array $aExistentConfig): array
	{
		$aConfigs = [];
		foreach ($aExistentConfig as $sClassOrInterfaceName => $bExists) {
			if ($bExists) {
				$aConfigs[] = $this->aRegistry[$sClassOrInterfaceName];
			}
		}
		return $aConfigs;
	}

}
