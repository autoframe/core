<?php
declare(strict_types=1);

namespace Autoframe\Core\Date\Month\Language;

class AfrDateMonthLanguageRo implements AfrDateMonthLanguageInterface
{
    /**
     * Get month names.
     * @return string[]
     */
    public function getMonthNames(): array
    {
        return [
            'Decembrie',
            'Ianuarie',
            'Februarie',
            'Martie',
            'Aprilie',
            'Mai',
            'Iunie',
            'Iulie',
            'August',
            'Septembrie',
            'Octombrie',
            'Noiembrie',
            'Decembrie'
        ];
    }

    /**
     * Get month names short.
     * @return string[]
     */
    public function getMonthNamesShort(): array
    {
        return [
            'Dec',
            'Ian',
            'Feb',
            'Mar',
            'Apr',
            'Mai',
            'Iun',
            'Iul',
            'Aug',
            'Sep',
            'Oct',
            'Nov',
            'Dec'
        ];
    }
}
