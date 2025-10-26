<?php
declare(strict_types=1);

namespace Autoframe\Core\Arr\Sort;

use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;

use function array_flip;
use function arsort;
use function asort;
use function gettype;
use function is_callable;
use function krsort;
use function ksort;
use function natcasesort;
use function natsort;
use function shuffle;
use function uasort;
use function uksort;

class AfrArrXSortClass extends AfrSingletonAbstractClass implements AfrArrXSortInterface
{

	/**
	 * @param array $aArray
	 * @param callable|int $mDirectionOrCallableFn ; SORT_ASC|SORT_DESC|callable
	 * @param mixed $mSortByKey
	 * @param int $iFlags
	 * @return bool
	 */

	public function arrayXSort(
		array &$aArray,
		$mDirectionOrCallableFn = SORT_ASC,
		$mSortByKey = false,
		int   $iFlags = SORT_NATURAL
	): bool
	{
		//detect array data type...
		if ($mSortByKey === '' || $mSortByKey === null || $mSortByKey === -1) {
			$aSortableTypes = array_flip(['boolean', 'integer', 'double', 'string', 'NULL']);
			$bSortByKey = false;
			foreach ($aArray as $mVal) {
				if (!isset($aSortableTypes[gettype($mVal)])) {
					$bSortByKey = true; //unsortable because of unsupported sort data
					break;
				}
			}
		} else {
			$bSortByKey = (bool)$mSortByKey;
		}

		if ($mDirectionOrCallableFn === SORT_DESC) {
			return $bSortByKey ? krsort($aArray, $iFlags) : arsort($aArray, $iFlags);
		} elseif ($mDirectionOrCallableFn === SORT_ASC) {
			return $bSortByKey ? ksort($aArray, $iFlags) : asort($aArray, $iFlags);
		} elseif ($mDirectionOrCallableFn === 'natsort') {
			return natsort($aArray);
		} elseif ($mDirectionOrCallableFn === 'natcasesort') {
			return natcasesort($aArray);
		} elseif ($mDirectionOrCallableFn === 'shuffle') {
			return shuffle($aArray);
		} elseif (is_callable($mDirectionOrCallableFn)) {
			return $bSortByKey ? uksort($aArray, $mDirectionOrCallableFn) : uasort($aArray, $mDirectionOrCallableFn);
		}
		return false;
	}
}