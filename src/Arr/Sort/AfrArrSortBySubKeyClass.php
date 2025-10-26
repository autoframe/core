<?php
declare(strict_types=1);

namespace Autoframe\Core\Arr\Sort;

use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;

use function asort;
use function arsort;
use function is_array;
use function is_object;
use function property_exists;

/**
 * Sort by a key or property owned by a first level array element
 * AfrArrSortBySubKeyClass->arraySortBySubKey(array $aMultiLevelToSort, string $sSubArrayKey, int $iOrder = SORT_ASC, int $iFlag = SORT_REGULAR): array;
 */
class AfrArrSortBySubKeyClass extends AfrSingletonAbstractClass implements AfrArrSortBySubKeyInterface
{
	/**
	 * Sort by a key or property owned by a first level array element
	 * @param array $aMultiLevelToSort
	 * @param string $sSubArrayKey
	 * @param int $iOrder
	 * @param int $iFlag
	 * @return array
	 */
	public function arraySortBySubKey(
		array  $aMultiLevelToSort,
		string $sSubArrayKey,
		int    $iOrder = SORT_ASC,
		int    $iFlag = SORT_REGULAR
	): array
	{
		if (!$aMultiLevelToSort) {
			return $aMultiLevelToSort;
		}
		$aNew = $aSortable = [];

		foreach ($aMultiLevelToSort as $k => $v) {
			if (is_array($v) && isset($v[$sSubArrayKey])) {
				$aSortable[$k] = $v[$sSubArrayKey];
			} elseif (is_object($v) && property_exists($v, $sSubArrayKey)) {
				$aSortable[$k] = $v->$sSubArrayKey;
			} else {
				$aSortable[$k] = $v;
			}
		}

		switch ($iOrder) {
			case SORT_ASC:
				asort($aSortable, $iFlag);
				break;
			case SORT_DESC:
				arsort($aSortable, $iFlag);
				break;
		}

		foreach ($aSortable as $k => &$v) {
			$aNew[$k] = $aMultiLevelToSort[$k];
			unset($aMultiLevelToSort[$k], $v);
		}
		return $aNew;
	}
}