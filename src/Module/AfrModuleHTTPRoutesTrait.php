<?php

namespace Autoframe\Core\Module;

use Autoframe\Core\Afr\Afr;
use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\Event\AfrEvent;
use Autoframe\Core\Event\Exception\AfrEventException;
use Autoframe\Core\Module\Exception\AfrModuleException;
use Autoframe\Core\Router\Contracts\AfrRouterConstantsInterface;
use Autoframe\Core\Router\Exception\AfrRouterException;

trait AfrModuleHTTPRoutesTrait
{
	use AfrModuleTrait;

	protected ?string $sSubRoutingPath = ''; // eg: /administration
	protected array $aDependenciesHTTPRoutesFQCN = [];
	protected ?int $iRegisteredHTTPRoutes = null;

	public function getDependenciesHTTPRoutesFQCN(): array
	{
		//must be hardcoded :D so no setter!
		return $this->aDependenciesHTTPRoutesFQCN;
	}

	/**
	 * @param string|null $sSubRoutingPath
	 * @return string
	 * @throws AfrEventException
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
		return (string)$this->sSubRoutingPath;
	}




	/**
	 * @return int
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 * @throws AfrModuleException
	 * @throws \ReflectionException|AfrRouterException
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
	 * @return array
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 * @throws AfrModuleException
	 * @throws \ReflectionException
	 */
	public function getHTTPRoutesClosures(): array
	{
		AfrEvent::dispatchEvent();
		return $this->mergeConfigFileWithParentsConfig(
			AfrModuleHTTPRoutesInterface::class,
			'sModuleHTTPRoutesPath',
			'AfrModuleHTTPRoutes.sample.php'
		);
	}

	public function getModuleHTTPRoutesPath(): string
	{
		return $this->moduleNaming('sModuleHTTPRoutesPath');
	}



}