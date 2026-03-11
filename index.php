<?php
require_once __DIR__ . '/vendor/autoload.php';

use Autoframe\Core\Afr\Afr;


define('AFR_BASE_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'baseTest');


Afr::makeApp(); //print_r($aReport);
$aReport = Afr::app()->run();

//print_r($aReport); // TODO lor or save the report

