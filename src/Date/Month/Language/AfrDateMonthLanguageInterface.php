<?php
declare(strict_types=1);

namespace Autoframe\Core\Date\Month\Language;

interface AfrDateMonthLanguageInterface
{
    /**
     * Get month names.
     * @return string[]
     */
    public function getMonthNames(): array;

    /**
     * Get month names short.
     * @return string[]
     */
    public function getMonthNamesShort(): array;
}
