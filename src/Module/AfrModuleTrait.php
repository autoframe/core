<?php

namespace Autoframe\Core\Module;

use Autoframe\Core\Afr\Afr;
use Autoframe\Core\Arr\Merge\AfrArrMergeProfileClass;
use Autoframe\Core\Container\AfrContainerFacade;
use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\DesignPatterns\ClosureBind\AfrBindAndCallClosureTrait;
use Autoframe\Core\Event\Exception\AfrEventException;
use Autoframe\Core\Module\Exception\AfrModuleException;

trait AfrModuleTrait
{
	use AfrBindAndCallClosureTrait;

	protected array $aModuleNaming = [];

	/**
	 * @param array|null $aImplementingInterfacesFilter
	 * @return array
	 * @throws \ReflectionException
	 */
	public function registerModule(array $aImplementingInterfacesFilter = null): array
	{
		$this->moduleNaming();
		$aMatched = [];
		$aDefaultRegisterMethods = [
			//	AfrModuleInterface::class => 'moduleNaming', //not deeded
			AfrModuleHTTPRoutesInterface::class => 'registerHTTPRoutes',
			AfrModuleCLIRoutesInterface::class => 'registerCLIRoutes',
		];
		$aImplementingInterfacesFilter ??= $aDefaultRegisterMethods;
		foreach ($aImplementingInterfacesFilter as $sInterface => $sMethod) {
			if(is_numeric($sInterface)) {
				$sInterface = $sMethod;
				$sMethod = $aDefaultRegisterMethods[$sInterface];
			}
			if ($this instanceof $sInterface) {
				$aMatched[$sInterface] = !empty($sMethod) ? $this->{$sMethod}() : 0;
			}
		}

		return $aMatched;
	}


	/**
	 * @throws \ReflectionException
	 */
	protected function moduleNaming(string $key = null, string $sParentClassName = null)
	{
		if (empty($this->aModuleNaming) || $sParentClassName) {
			$sFQCN = $sParentClassName ?? get_class($this);
			$sNamespace = substr($sFQCN, 0, (int)strrpos($sFQCN, '\\'));
			$aModuleParts = explode('\\', $sFQCN);
			krsort($aModuleParts);
			$sModuleName = implode('_', array_splice($aModuleParts, 0, 2));
			$oReflector = new \ReflectionClass($sParentClassName ?? $this);
			$sModuleClassFilePath = $oReflector->getFileName();
			$sModuleDirPath = dirname($sModuleClassFilePath);

			$aModuleNaming = [
				'sFQCN' => $sFQCN,
				'sNamespace' => $sNamespace,
				'sModuleName' => $sModuleName,
				'sModuleClassFilePath' => $sModuleClassFilePath,
				'sModuleDirPath' => $sModuleDirPath,
				'sModuleHTTPRoutesPath' => $sModuleDirPath . AfrModuleHTTPRoutesInterface::MODULE_HTTP_ROUTES_FILE,
				'sModuleCLIRoutesPath' => $sModuleDirPath . AfrModuleCLIRoutesInterface::MODULE_CLI_QA_ROUTES_FILE,
			];
			if ($sParentClassName) {
				return $aModuleNaming[$key] ?? null;
			}
			$this->aModuleNaming = $aModuleNaming;
		}
		return $this->aModuleNaming[$key] ?? null;
	}


	public function getModuleFQCN(): string
	{
		return $this->moduleNaming('sFQCN');
	}

	public function getModuleNameSpace(): string
	{
		return $this->moduleNaming('sNamespace');
	}

	public function getModuleName(): string
	{
		return $this->moduleNaming('sModuleName');
	}

	public function getModuleClassFilePath(): string
	{
		return $this->moduleNaming('sModuleClassFilePath');
	}

	public function getModuleDirPath(): string
	{
		return $this->moduleNaming('sModuleDirPath');
	}

	protected function getModuleParentImplementingInterface(string $sInterface): array
	{
		foreach ((array)class_parents($this) as $sParentClass) {
			if (!empty(class_implements($sParentClass)[$sInterface])) {
				$aParentsImplementing[] = $sParentClass;
			}
		}
		return $aParentsImplementing ?? [];
	}

	/**
	 * @param string $sPathKey
	 * @param string $sSampleFile
	 * @return array
	 * @throws AfrModuleException
	 * @throws \ReflectionException
	 */
	protected function loadConfigArrayOrInit(string $sPathKey, string $sSampleFile = ''): array
	{
		$aLoadedArray = include($sModuleConfigFilePath = $this->moduleNaming($sPathKey));
		if (!is_array($aLoadedArray)) {
			if (!file_exists($sModuleConfigFilePath) && $sSampleFile) {
				copy(__DIR__ . DIRECTORY_SEPARATOR . $sSampleFile, $sModuleConfigFilePath);
				throw new AfrModuleException('Initializing missing module config file: ' . $sModuleConfigFilePath);
			} else {
				throw new AfrModuleException('Miss formated module config file: ' . $sModuleConfigFilePath);
			}
		}
		return $aLoadedArray;
	}

	/**
	 * @param string $sTargetInterface
	 * @param string $sPathKey
	 * @param string $sSampleFile
	 * @param \Closure|null $oArrayMerge
	 * @param array|null $aConfig
	 * @return array
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 * @throws AfrModuleException
	 * @throws \ReflectionException
	 */
	protected function mergeConfigFileWithParentsConfig(
		string   $sTargetInterface,
		string   $sPathKey,
		string   $sSampleFile = '',
		\Closure $oArrayMerge = null,
		array    $aConfig = null
	): array
	{
		$aConfig ??= $this->loadConfigArrayOrInit($sPathKey, $sSampleFile);
		foreach ($this->getModuleParentImplementingInterface($sTargetInterface) as $sParentClass) {
			$sParentConfigFile = $this->moduleNaming($sPathKey, $sParentClass);
			$aConfigFromParent = include $sParentConfigFile;
			if (!is_array($aConfigFromParent)) {
				throw new AfrModuleException('Miss formated module config file: ' . $sParentConfigFile);
			}
			$aConfig = !empty($oArrayMerge) ?
				$oArrayMerge($aConfigFromParent, $aConfig) :
				AfrArrMergeProfileClass::getInstance()->arrayMergeProfile($aConfigFromParent, $aConfig);
		}
		return $aConfig;
	}


	/**
	 * @param string $sInterface
	 * @param string $sDependencyRegisterFunction
	 * @return void
	 * @throws AfrContainerException
	 * @throws AfrModuleException
	 */
	protected function loadRecursiveTypeDependencies(string $sInterface, string $sDependencyRegisterFunction): void
	{
		foreach ($this->aDependenciesCLIRoutesFQCN as $sDependencyRoute) {
			//a dependency has routes to register
			$oDependency = Afr::app() ?
				Afr::app()->container()->get($sDependencyRoute) :
				AfrContainerFacade::getContainer()->get($sDependencyRoute);
			if ($oDependency instanceof $sInterface) {
				//$oDependency->registerModule(); $oDependency->registerCLIRoutes();
				$sDependencyRegisterFunction ?
					$oDependency->$sDependencyRegisterFunction() :
					$oDependency->registerModule(); //TODO: TEST: load all module or only routes?
				continue;
			}
			throw new AfrModuleException(
				'The dependency class is not a Module and instance of ' . $sInterface
			);
		}
	}

}