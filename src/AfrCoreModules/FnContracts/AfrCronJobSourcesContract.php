<?php

namespace Autoframe\Core\AfrCoreModules\FnContracts;


use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\Event\Exception\AfrEventException;

interface AfrCronJobSourcesContract
{

	const FGC = 'file.get.contents';
	const URL_S = 'curl';
	const CLOSURE_FN = 'closure';

	const CLI_CRON_JOB_SOURCES_FILENAME = 'CRON_JOB_SOURCES.php';

	/**
	 * Automatically calls registerCronJobSources
	 * @return int
	 * @throws AfrContainerException|AfrEventException
	 */
	public function __invoke(): int;

	/**
	 * Registers the  sources inside AfrConJobSources
	 * @return int
	 * @throws AfrContainerException|AfrEventException
	 */
	public function registerCronJobSources(): int;

	/**
	 * Return functionality cron job source config
	 * @return array|null
	 */
	public function getCronJobSources(): ?array;


}
