<?php
require_once __DIR__ . '/vendor/autoload.php';

use Autoframe\Core\Afr\Afr;


const AFR_BASE_DIR = __DIR__ . DIRECTORY_SEPARATOR . 'baseTest';


$aReport = Afr::makeApp()->run(); //print_r($aReport);

//print_r($aReport); // TODO lor or save the report

