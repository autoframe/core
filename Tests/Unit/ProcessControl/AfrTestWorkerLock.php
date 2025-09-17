<?php
declare(strict_types=1);

ignore_user_abort(true);

$insideProductionVendorDir = strpos(__DIR__, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false;
require_once(__DIR__ . ($insideProductionVendorDir ? '/../../../../../autoload.php' : '/../../../vendor/autoload.php'));

//TEST AfrLockFileTest
ob_start();

$oLock = new \Autoframe\Core\ProcessControl\Lock\AfrLockFileClass($_SERVER['argv'][1]);
echo 'obtainLock: ';
var_dump($oLock->obtainLock());

echo "\n\nLockPid: ";
var_dump($oLock->getLockPid());

$iMsSleep = max(intval($_SERVER['argv'][2]), 20);
if ($iMsSleep < 1000) {
    usleep($iMsSleep * 1000);//MS
} else {
    sleep((int)ceil($iMsSleep / 1000));//Sec
}

echo "\n\nreleaseLock: ";
var_dump($oLock->releaseLock());
ob_end_flush();



