<?php

namespace Autoframe\Core\Module;

use Autoframe\Core\Afr\Afr;
use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\Env\Exception\AfrEnvException;
use Autoframe\Core\Event\AfrEvent;
use Autoframe\Core\Event\Exception\AfrEventException;
use Autoframe\Core\Module\Exception\AfrModuleException;
use Autoframe\Core\Router\Contracts\AfrRouterConstantsInterface;
use Autoframe\Core\Router\Exception\AfrRouterException;

trait AfrModuleHTTPRoutesTrait
{
	use AfrModuleTrait;

	protected ?string $sSubRoutingPath = null; // eg: /administration
	protected array $aDependenciesHTTPRoutesFQCN = [];
	protected ?int $iRegisteredHTTPRoutes = null;

	public function getDependenciesHTTPRoutesFQCN(): array
	{
		//must be hardcoded :D so no setter!
		//TODO: test?? aici fac numai listare cu get sau ce?
		return $this->aDependenciesHTTPRoutesFQCN;
	}
	/**
	 * @return int
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 * @throws AfrModuleException
	 * @throws \ReflectionException|AfrRouterException
	 * @throws AfrEnvException
	 */
	public function registerHTTPRoutes(): int
	{
		if ($this->iRegisteredHTTPRoutes !== null) {
			return $this->iRegisteredHTTPRoutes;
		}
		$this->iRegisteredHTTPRoutes = 0; //prevent infinite loops when dependency load
		AfrEvent::dispatchEvent();
		$this->loadRecursiveTypeDependencies(
			AfrModuleHTTPRoutesInterface::class,
			__FUNCTION__
		);

		return $this->iRegisteredHTTPRoutes = Afr::app()->router()->registerHTTPRoutesFromModule(
			$this->getHTTPRoutesClosures(),
			$this->xetHTTPSubRoutingPath()
		);
	}
	/**
	 * @param string|null $sSubRoutingPath
	 * @return string
	 * @throws AfrEventException|AfrEnvException
	 */
	public function xetHTTPSubRoutingPath(string $sSubRoutingPath = null): string
	{
		if ($sSubRoutingPath !== null) { //TODO: test la setat ca si ''
			AfrEvent::dispatchEvent();
			if (strlen($sSubRoutingPath) > 0) {
				$sSubRoutingPath = '/' . trim($sSubRoutingPath, '/');
			}
			$this->sSubRoutingPath = $sSubRoutingPath;
		}
		if($this->sSubRoutingPath === null){
			$this->sSubRoutingPath = (string)Afr::app()->env()->getEnv(strtoupper($this->getModuleName()) . '_' . 'SUB_ROUTING_PATH');
		}
		return (string)$this->sSubRoutingPath;
	}

	public function getModuleHTTPRoutesPath(): string
	{
		return $this->getModuleDirPath().AfrModuleHTTPRoutesInterface::MODULE_HTTP_ROUTES_FILE;
		return $this->moduleNaming('sModuleHTTPRoutesPath');
	}


	/**
	 * @return array
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 * @throws AfrModuleException
	 * @throws \ReflectionException
	 */
	public function getHTTPRoutesClosures(): array //TODO
	{
		AfrEvent::dispatchEvent();
		return $this->mergeConfigFileWithParentsConfig(
			AfrModuleHTTPRoutesInterface::class,
			'sModuleHTTPRoutesPath',
			'AfrModuleHTTPRoutes.sample.php'  //TODO remove???? sau misca in framework
		);
	}





}