<?php
require_once __DIR__ . '/vendor/autoload.php';

use Autoframe\Core\Afr\Afr;

define('AFR_BASE_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'apps');


$aReport = Afr::makeApp()->run(); //print_r($aReport);
//AfrCore::getInstance()->registerModule();
