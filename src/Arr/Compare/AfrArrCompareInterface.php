<?php

namespace Autoframe\Core\Arr\Compare;

interface AfrArrCompareInterface {
	public function assertSameContents($a, $b, bool $bIgnoreOrder = true, bool $bStrictObjectIdentity = true, bool $bStrictClosureIdentity = true, bool $bStrictScalars = true, int $iMaxDepth = 20): bool;
}