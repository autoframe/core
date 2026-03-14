<?php

namespace Autoframe\Core\Validate;

class AfrValidateCNP {
	//TODO: TEST
	/**
	 * Validate.
	 */
	public static function validate(string $sCnp): bool
	{
		if(
			strlen($sCnp) !== 13 || //The CNP must have a minimum / maximum of 13 digits
			!preg_match('/^\d+$/', $sCnp) || //contains not only digits
			(int)substr($sCnp, 3, 2) > 12 || //a year has a maximum of 12 months
			(int)substr($sCnp, 5, 2) > 31  //the month has a maximum of 31 days
		){
			return false;
		}

		//we test according to the control number 279146358279
		$testKey = '279146358279'; // https://ortodoxinfo.ro/2018/02/25/cum-fost-ales-numarul-279146358279-pentru-calcularea-cnp/

		//we multiply (from left to right) each digit of the $testKey variable with its counterpart in the test variable
		$response = 0;
		for ($x = 0; $x < 12; $x++) {
			$response = $response + ((int)substr($testKey, $x, 1) * (int)substr($sCnp, $x, 1));
		}

		$response = (int)$response % 11;
		if ($response == 10) {
			$response = 1;
		}

		//we round the remainder of the division to 11 and compare with the last digit of the CNP code
		//if they are equal, then the CNP is valid
		return $response === (int)substr($sCnp, 12, 1);
	}



}
