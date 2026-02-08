<?php

namespace Unit\ModuleBox;

class TestFnxBridgeStuff implements TestFniBridgeStuff
{
	use TestFnxTrait;

	function bridgeAction(string $sX)
	{
		$this->setTested(static::class.'~'.__FUNCTION__ . ":$sX");
	}
}