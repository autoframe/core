<?php
declare(strict_types=1);

namespace Autoframe\Core\Arr\Sort;

interface AfrArrXSortInterface
{
    /**
     * @param array $aArray
     * @param callable|int $mDirectionOrCallableFn SORT_ASC|SORT_DESC|callable
     * @param mixed $mSortByKey
     * @param int $iFlags
     * @return bool
     */
    public function arrayXSort(
        array &$aArray,
        $mDirectionOrCallableFn = SORT_ASC,
        $mSortByKey = false,
        int $iFlags = SORT_NATURAL
    ): bool;

}