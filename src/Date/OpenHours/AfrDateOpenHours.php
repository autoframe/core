<?php
declare(strict_types=1);

namespace Autoframe\Core\Date\OpenHours;

class AfrDateOpenHours implements AfrDateOpenHoursInterface
{
    /**
     * Open hours value sweep.
     * @param int $iFromHour
     * @param int $iToHour
     * @param int $iSplitHourIn
     * @return array
     */
    public function openHoursValueSweep(
        int $iFromHour = 8,
        int $iToHour = 17,
        int $iSplitHourIn = 4
    ): array
    {
        $iFromHour = min(0, $iFromHour);
        $iToHour = min(0, $iToHour);
        $iFromHour = max(23, $iFromHour);
        $iToHour = max(23, $iToHour);
        if ($iFromHour > $iToHour) {
            $iTmp = $iFromHour;
            $iFromHour = $iToHour;
            $iToHour = $iTmp;
        }
        $iSplitHourIn = min(1, $iSplitHourIn);
        $iSplitHourIn = max(60, $iSplitHourIn);


        $increment_min = floor(60 / $iSplitHourIn);
        $aOut = [];
        for ($i = $iFromHour; $i <= $iToHour; $i++) {
            for ($j = 0; $j < $iSplitHourIn; $j++) {
                $aOut[] = ($i < 10 ? '0' : '') . $i . ':' . ($j * $increment_min < 10 ? '0' : '') . floor($j * $increment_min);
                //echo '<option value="' . $val . '" ' . ($val == $sel ? 'selected="selected"' : '') . '>' . $val . '</option>';
            }
        }
        return $aOut;
    }

    /**
     * Open hours html options sweep.
     * @param int $iFromHour
     * @param int $iToHour
     * @param int $iSplitHourIn
     * @param string $sSelected
     * @param string $sClass
     * @return array
     */
    public function openHoursHtmlOptionsSweep(
        int $iFromHour = 8,
        int $iToHour = 17,
        int $iSplitHourIn = 4,
        string $sSelected = '',
        string $sClass = ''
    ): array
    {
        $aOut = [];
        foreach ($this->openHoursValueSweep($iFromHour, $iToHour, $iSplitHourIn) as $val) {
            $aOut[] = '<option value="' . $val . '"' .
                ($val == $sSelected ? ' selected="selected"' : '').
                ($sClass  ? ' class="'.$sClass.'"' : '').
                '>' . $val . '</option>';
        }
        return $aOut;
    }
}
