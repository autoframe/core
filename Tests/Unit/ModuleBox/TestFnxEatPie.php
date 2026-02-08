<?php

namespace Unit\ModuleBox;


class TestFnxEatPie  implements TestFniEatPie {
	use TestFnxTrait;

	function eatSome(string $sX)
	{
		$this->setTested(static::class . '~' . __FUNCTION__ . ":$sX");
	}

	function eatPie(string $sX)
	{
		$this->eatSome(__FUNCTION__.':'.$sX);
	}
}