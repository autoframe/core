<?php
declare(strict_types=1);

namespace Autoframe\Core\Arr\Merge;

use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;

/**
 * Recursive array merging for config profiles
 * $aMergedProfile = $this->arrayMergeProfile(array $aOriginal, array $aNew);
 */
class AfrArrMergeProfileClass extends AfrSingletonAbstractClass implements AfrArrMergeProfileInterface
{
	/*
	When to use arrayMergeProfile(): Use it when you need:
	-	deep merge with predictable rules
	-	preserve original array structure
	-	append numeric keys, not overwrite
	-	only merge arrays if both sides are arrays
	-	config/profile merging, inheritance, or module overrides

	When to use array_replace_recursive(): Use it when you want:
	-	strict replacement
	-	no merging logic
	-	no numeric key preservation
	-	simple override behavior
	 */

	/**
	 * Recursive array merging for config profiles
	 * @param array $aOriginal
	 * @param array $aNew
	 * @return array
	 */
	public function arrayMergeProfile(array $aOriginal, array $aNew): array
	{
		foreach ($aNew as $sNewKey => $mNewProfile) {
			if (!isset($aOriginal[$sNewKey])) {
				$aOriginal[$sNewKey] = $mNewProfile;
			} elseif (is_array($aOriginal[$sNewKey]) && is_array($mNewProfile)) {
				$aOriginal[$sNewKey] = $this->arrayMergeProfile($aOriginal[$sNewKey], $mNewProfile);
			} elseif (is_integer($sNewKey)) {
				$aOriginal[] = $mNewProfile;
			} else {
				$aOriginal[$sNewKey] = $mNewProfile;
			}
		}
		return $aOriginal;
	}
}