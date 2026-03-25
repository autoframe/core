<?php

use Autoframe\Core\AfrCoreModules\FnContracts\AfrCronJobSourcesContract;

return [
	AfrCronJobSourcesContract::URL_S => [['alias1', 'url', []]], // ->addUrlSource
	AfrCronJobSourcesContract::FGC => [['alias2', 'path', null]], // ->addFileSource
	AfrCronJobSourcesContract::CLOSURE_FN => [['alias3', fn() => '#*/9 * * * * EXIT_DAEMON']], // ->addSourceFromClosure
];

//http://localhost:808/core/src/Cron/AfrCronJobDaemon.DemoCron.txt

/**
 * AfrCronJob Flag List:
 * Startup S;
 * TurnOffLog: O;
 * AllowParallelRun: P;
 * TimeLimitedSeconds: T(0.02)=20ms | T(60)=60 seconds;
 * AlwaysRunService: A;
 * AlwaysRunService with stop+start=restart trigger  : A(* * 5 * *);
 * TenantInsensitiveJob : I; The lock is tenant insensitive
 */

"#*/9 * * * * EXIT_DAEMON
32 * * * * EXIT_DAEMON
#*/7 * * * * RESPAWN_DAEMON
#* * * * * some.php args
*/2 * * * * REFRESH_JOBS
#* * * * * CLI:C:\Windows\System32\mspaint.exe
#* * * * * CLI:C:\Windows\System32\cmd.exe
<P>* * * * * http://localhost:808/core/src/multiexec.php
#<P>* * * * * http://localhost:808/core/
#<P>* * * * * http://localhost:808/
* * * * * https://ares.b-p-g.org/
<OA(*/3 * * * *)>* * * * * C:\xampp\htdocs\core\xEnd22Status.php
#* * * * * C:\xampp\htdocs\core\xSomeTxt.php
#* * * * * C:\xampp\htdocs\core\xNoFeedback.php
#* * * * * C:\xampp\htdocs\core\index.php AfrCronJobDaemon::demo
";