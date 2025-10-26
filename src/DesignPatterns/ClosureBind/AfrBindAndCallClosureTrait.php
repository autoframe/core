<?php

namespace Autoframe\Core\DesignPatterns\ClosureBind;

trait AfrBindAndCallClosureTrait {
	public function bindAndCallClosure(\Closure $oClosure,array $aParams = [])
	{
		return ($oClosure->bindTo($this))(...$aParams);
	}
	public static function bindAndCallClosureStatic(\Closure $oClosure,array $aParams = [])
	{
		return ($oClosure->bindTo(null,static::class))(...$aParams);
	}
}