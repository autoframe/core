<?php

namespace Autoframe\Core\DesignPatterns\ClosureBind;

interface AfrBindAndCallClosureInterface {
	/**
	 * @param \Closure $oClosure
	 * @param array $aParams
	 * @return mixed
	 */
	public function bindAndCallClosure(\Closure $oClosure,array $aParams = []);

	/**
	 * @param \Closure $oClosure
	 * @param array $aParams
	 * @return mixed
	 */
	public static function bindAndCallClosureStatic(\Closure $oClosure,array $aParams = []);

}