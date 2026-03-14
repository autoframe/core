<?php
declare(strict_types=1);

namespace Autoframe\Core\Date\Day\Language;

class AfrDateDayLanguageEn implements AfrDateDayLanguageInterface
{
    /**
     * Get day names.
     * @return string[]
     */
    public function getDayNames(): array
    {
        return ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    }

    /**
     * Get day names short.
     * @return string[]
     */
    public function getDayNamesShort(): array
    {
        return ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    }
}
