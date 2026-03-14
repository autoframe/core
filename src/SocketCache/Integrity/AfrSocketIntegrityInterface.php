<?php
declare(strict_types=1);

namespace Autoframe\Core\SocketCache\Integrity;

use Autoframe\Core\SocketCache\AfrCacheSocketConfig;

interface AfrSocketIntegrityInterface
{
    /**
     * Create a new instance.
     * @param AfrCacheSocketConfig $oConfig
     */
    public function __construct(AfrCacheSocketConfig $oConfig);

    /**
     * Sv decode read.
     * @param string $sRawRead
     * @return array
     */
    public function svDecodeRead(string $sRawRead): array;

    /**
     * Sv code write.
     * @param string $sWrite
     * @return string
     */
    public function svCodeWrite(string $sWrite): string;

    /**
     * Cl decode read.
     * @param string $sRawRead
     * @return array
     */
    public function clDecodeRead(string $sRawRead): array;

    /**
     * Cl code write.
     * @param string $sWrite
     * @return string
     */
    public function clCodeWrite(string $sWrite): string;
}
