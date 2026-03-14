<?php
declare(strict_types=1);

namespace Autoframe\Core\Date\Gmt;

class AfrDateGmt
{
    /**
     * Time to gmt.
     * @param int $iTime
     * @return string
     */
    public function timeToGmt(int $iTime = -1): string
    {
        if ($iTime === -1) {
            $iTime = time();
        }
        return gmdate('D, d M Y H:i:s', $iTime) . ' GMT';
    }
}
