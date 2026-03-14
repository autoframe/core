<?php

namespace Autoframe\Core\DesignPatterns\ClosureBind;

trait AfrBindAndCallClosureTrait {
	/**
	 * Bind and call closure.
	 */
	public function bindAndCallClosure(\Closure $oClosure,array $aParams = [])
	{
		return ($oClosure->bindTo($this))(...$aParams);
	}
	/**
	 * Bind and call closure static.
	 */
	public static function bindAndCallClosureStatic(\Closure $oClosure,array $aParams = [])
	{
		return ($oClosure->bindTo(null,static::class))(...$aParams);
	}
}
