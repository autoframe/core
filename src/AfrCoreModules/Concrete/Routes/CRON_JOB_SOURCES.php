<?php

use Autoframe\Core\AfrCoreModules\FnContracts\AfrCronJobSourcesContract;

return [
	AfrCronJobSourcesContract::URL_S => [['alias', 'url', []]], // ->addUrlSource
	AfrCronJobSourcesContract::FGC => [['alias', 'url', null]], // ->addFileSource
	AfrCronJobSourcesContract::CLOSURE_FN => [['alias', fn() => '#*/9 * * * * EXIT_DAEMON']], // ->addSourceFromClosure
];