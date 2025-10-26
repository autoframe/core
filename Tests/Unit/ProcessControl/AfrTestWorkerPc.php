<?php
declare(strict_types=1);

ignore_user_abort(true);

//TEST AfrBackgroundWorkerClass
ob_start();
$file = __DIR__ . DIRECTORY_SEPARATOR . (!empty($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : rand(4242, 6146436) . '.test');
file_put_contents($file, print_r($_SERVER['argv'], true));

$iMsSleep = max(intval($_SERVER['argv'][2]), 20);
if ($iMsSleep < 1000) {
    usleep($iMsSleep * 1000);//100ms
} else {
    sleep((int)ceil($iMsSleep / 1000));
}
if (is_file($file)) {
    @unlink($file);
}
ob_end_flush();