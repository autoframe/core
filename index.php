<?php
require_once __DIR__ . '/vendor/autoload.php';

use Autoframe\Core\Afr\Afr;

$aReport = Afr::makeApp(__DIR__ . DIRECTORY_SEPARATOR . 'baseApp')->run();

//print_r($aReport); // TODO save the report

