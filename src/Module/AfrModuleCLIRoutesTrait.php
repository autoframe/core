<?php

namespace Autoframe\Core\Module;

use Autoframe\Core\Afr\Afr;
use Autoframe\Core\Arr\Export\AfrArrExportArrayAsStringClass;
use Autoframe\Core\Arr\Merge\AfrArrMergeProfileClass;
use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\Env\Exception\AfrEnvException;
use Autoframe\Core\Event\AfrEvent;
use Autoframe\Core\Event\Exception\AfrEventException;
use Autoframe\Core\Module\Exception\AfrModuleException;
use Autoframe\Core\Router\Contracts\AfrRouterConstantsInterface;
use Autoframe\Core\Router\Exception\AfrRouterException;
use Autoframe\Core\String\Obj\AfrClosureToStr;

trait AfrModuleCLIRoutesTrait
{
	use AfrModuleTrait;

	protected array $aDependenciesCLIRoutesFQCN = [];
	protected ?int $iRegisteredCLIRoutes = null;

	public function getDependenciesCLIRoutesFQCN(): array
	{
		//must be hardcoded :D so no setter!
		return $this->aDependenciesCLIRoutesFQCN;
	}


	/**
	 * @param bool $bMergeQA
	 * @param bool $bMergeInline
	 * @param bool $bMergeCrons
	 * @return int
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 * @throws AfrModuleException
	 * @throws \ReflectionException|AfrEnvException
	 */
	public function registerCLIRoutes(
		bool $bMergeQA = true,
		bool $bMergeInline = true,
		bool $bMergeCrons = true
	): int
	{
		if ($this->iRegisteredCLIRoutes !== null) {
			return $this->iRegisteredCLIRoutes;
		}
		$this->iRegisteredCLIRoutes = 0; //prevent infinite loops when dependency load
		AfrEvent::dispatchEvent();
		$this->loadRecursiveTypeDependencies(
			AfrModuleCLIRoutesInterface::class,
			__FUNCTION__
		);
		$sEnvPrefix = strtoupper($this->getModuleName()) . '_';
		return $this->iRegisteredCLIRoutes = Afr::app()->router()->registerCLIRoutesFromModule(
			$this->getCLIRoutesClosures(),
			Afr::app()->env()->getEnv($sEnvPrefix . 'MERGE_QA', true),
			Afr::app()->env()->getEnv($sEnvPrefix . 'MERGE_INLINE', true),
			Afr::app()->env()->getEnv($sEnvPrefix . 'MERGE_CRON', true),
		);
	}

	/**
	 * @return array
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 * @throws AfrModuleException
	 * @throws \ReflectionException
	 */
	public function getCLIRoutesClosures(): array
	{
		AfrEvent::dispatchEvent();
		$aRoutes = $this->mergeConfigFileWithParentsConfig(
			AfrModuleCLIRoutesInterface::class,
			'sModuleCLIRoutesPath',
			'AfrModuleCLIRoutes.sample.php'
		);
		foreach ($aRoutes as $sType => $aRouteClusterInfo) {
			if (!is_array($aRouteClusterInfo) || !in_array($sType, [
					AfrRouterConstantsInterface::CLI_QA_REQUEST, //x.php --QA=initTenantFileSystem | QA | QA=
					AfrRouterConstantsInterface::CLI_INLINE,
					AfrRouterConstantsInterface::CLI_CRON_JOB_REQUEST,
				])) {
				throw new AfrModuleException('Invalid routes group!');
			}

			foreach ($aRouteClusterInfo as $sKeyCluster => $mRouteData) {
				if (!is_string($sKeyCluster)) {
					throw new AfrModuleException(
						'The CLI routes must be have a string key in order to respect SOLID structure!'
					);
				}
			}
		}
		return $aRoutes;
	}

	/**
	 * @return string
	 * @throws \ReflectionException
	 */
	public function getModuleCLIRoutesPath(): string
	{
		return $this->moduleNaming('sModuleCLIRoutesPath');
	}


}