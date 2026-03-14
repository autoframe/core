<?php
declare(strict_types=1);

namespace Autoframe\Core\Date\Day\Language;

interface AfrDateDayLanguageInterface
{
    /**
     * Get day names.
     * @return string[]
     */
    public function getDayNames(): array;

    /**
     * Get day names short.
     * @return string[]
     */
    public function getDayNamesShort(): array;
}
