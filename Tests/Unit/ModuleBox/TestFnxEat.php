<?php

namespace Unit\ModuleBox;

class TestFnxEat implements TestFniEat
{
	use TestFnxTrait;

	function eatSome(string $sX)
	{
		$this->setTested(static::class.'~'.__FUNCTION__ . ":$sX");
	}


}