<?php

namespace Unit\ModuleBox;

class TestFnxSleep implements TestFniSleep
{
	use TestFnxTrait;

	function sleepMinutes(int $sX)
	{
		$this->setTested(static::class.'~'.__FUNCTION__ . ":$sX");
	}
}