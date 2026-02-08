<?php

namespace Unit\ModuleBox;

use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;

class TestFnxSingletonActivity extends AfrSingletonAbstractClass implements TestFniSingletonActivity
{
	use TestFnxTrait;

	function doSingletonActivity(string $sX)
	{
		$this->setTested(static::class.'~'.__FUNCTION__ . ":$sX");
	}


}