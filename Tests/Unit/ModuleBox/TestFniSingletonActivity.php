<?php

namespace Unit\ModuleBox;

use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonInterface;

interface TestFniSingletonActivity extends AfrSingletonInterface {
	function doSingletonActivity(string $sX);
	function getTested();

}