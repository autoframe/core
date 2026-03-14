<?php
declare(strict_types=1);

namespace Autoframe\Core\FtpTransfer\Log;

interface AfrFtpLogInterface
{
    public const FATAL_ERR = 1;
    public const MESSAGE = 2;

    /**
     * New log.
     * @return $this
     */
    public function newLog(): self;

    /**
     * Log message.
     * @param string $sMessage
     * @param int $iType
     * @return $this
     */
    public function logMessage(string $sMessage, int $iType): self;

    /**
     * Close log.
     * @return $this
     */
    public function closeLog(): self;
}
