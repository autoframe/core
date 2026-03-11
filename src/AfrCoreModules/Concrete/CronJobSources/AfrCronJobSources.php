<?php

namespace Autoframe\Core\AfrCoreModules\Concrete\CronJobSources;

use Autoframe\Core\AfrCoreModules\FnContracts\AfrCronJobSourcesContract;
use Autoframe\Core\AfrCoreModules\Reusable\AfrCronJobSourcesHelper;

class AfrCronJobSources implements AfrCronJobSourcesContract
{
	use AfrCronJobSourcesHelper;
	const SELF_DIR = __DIR__;


}