<?php

namespace Autoframe\Core\Module;

interface AfrModuleCronJobsSourcesListsInterface  extends AfrModuleInterface{
	//TODO: de verificat / probat merge cu parinte / dependinte!!!
	const MODULE_CRON_JOBS_ROUTES_FILE = DIRECTORY_SEPARATOR . 'CronJobsSourcesLists.php';
	public function registerCronJobs(): int;

	public function getModuleCronJobsSourcesList(): string;

	public function getDependenciesCronJobsSourcesListFQCN(): array;


}