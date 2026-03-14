<?php
declare(strict_types=1);

namespace Autoframe\Core\Date\Month\Language;

class AfrDateMonthLanguageEn implements AfrDateMonthLanguageInterface
{
    /**
     * Get month names.
     * @return string[]
     */
    public function getMonthNames(): array
    {
        return [
            'December',
            'January',
            'February',
            'March',
            'April',
            'May',
            'June',
            'July',
            'August',
            'September',
            'October',
            'November',
            'December'
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
            'Jan',
            'Feb',
            'Mar',
            'Apr',
            'May',
            'Jun',
            'Jul',
            'Aug',
            'Sep',
            'Oct',
            'Nov',
            'Dec'
        ];
    }
}
