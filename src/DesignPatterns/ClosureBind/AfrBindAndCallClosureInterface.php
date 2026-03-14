<?php

namespace Autoframe\Core\DesignPatterns\ClosureBind;

interface AfrBindAndCallClosureInterface {
	/**
	 * Bind and call closure.
	 * @param \Closure $oClosure
	 * @param array $aParams
	 * @return mixed
	 */
	public function bindAndCallClosure(\Closure $oClosure,array $aParams = []);

	/**
	 * Bind and call closure static.
	 * @param \Closure $oClosure
	 * @param array $aParams
	 * @return mixed
	 */
	public static function bindAndCallClosureStatic(\Closure $oClosure,array $aParams = []);

}
