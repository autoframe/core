<?php

namespace Unit\ModuleBox;

trait TestFnxTrait {
	protected function setTested(string $sX)
	{
		$this->tested = $sX; //static::class.'~'.__FUNCTION__ . ":$sX"
	}

	function getTested()
	{
		return $this->tested?? self::class. ":NULL";
	}
}