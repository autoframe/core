<?php

namespace Autoframe\Core\AfrCoreModules\Reusable;

use Autoframe\Core\AfrCoreModules\FnContracts\AfrCronJobSourcesContract;
use Autoframe\Core\Cron\AfrConJobSources;

trait AfrCronJobSourcesHelper
{
	/** @inheritDoc */
	/**
	 * Invoke the instance as a callable.
	 */
	public function __invoke(): int
	{
		return $this->registerCronJobSources();
	}

	/** @inheritDoc */
	/**
	 * Register cron job sources.
	 */
	public function registerCronJobSources(): int
	{
		if (empty($aSources = $this->getCronJobSources())) return 0;

		$aSources = array_merge([
			AfrCronJobSourcesContract::URL_S => [],
			AfrCronJobSourcesContract::FGC => [],
			AfrCronJobSourcesContract::CLOSURE_FN => [],
		],(array)$aSources);

		$oConJobSources = AfrConJobSources::getInstance();
		$iCount = 0;

		foreach ($aSources as $sType => $aSourcesList) {
			foreach ($aSourcesList as $aSource) {
				if ($sType === AfrCronJobSourcesContract::URL_S) {
					$oConJobSources->addUrlSource($aSource[0], $aSource[1], $aSource[2] ?? null);
					$iCount++;
				} elseif ($sType === AfrCronJobSourcesContract::FGC) {
					$oConJobSources->addFileSource($aSource[0], $aSource[1], $aSource[2] ?? null);
					$iCount++;
				} elseif ($sType === AfrCronJobSourcesContract::CLOSURE_FN) {
					$oConJobSources->addSourceFromClosure($aSource[0], $aSource[1]);
					$iCount++;
				}
			}
		}

		return $iCount;
	}

	/** @inheritDoc */
	/**
	 * Get cron job sources.
	 */
	public function getCronJobSources(): ?array
	{
		return AfrLoadConfigHelper::loadConfigFile(self::SELF_DIR, self::CLI_CRON_JOB_SOURCES_FILENAME, false);
	}
}
