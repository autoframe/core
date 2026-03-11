<?php

namespace Autoframe\Core\AfrCoreModules\FnContracts;

use Autoframe\Core\Http\Request\AfrCliConstantsInterface;
use Autoframe\Core\Http\Request\AfrRequestInterface;

interface AfrCronJobSourcesContract // extends AfrCliConstantsInterface
{

	//TODO: 2026: AfrConJobSources::getSourcesFreshFromModules() //TODO!!!!!!

	const FGC = 'file.get.contents';
	const URL_S = 'curl';
	const CLOSURE_FN = 'closure';

	const CLI_CRON_JOB_SOURCES_FILENAME = 'CRON_JOB_SOURCES.php';


	public function registerCronJobSources():int;
	public function getCronJobSources():?array;


}