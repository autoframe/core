<?php

namespace Autoframe\Core\FtpTransfer\Connection;

interface AfrFtpConnectionInterface
{
    /**
     * Connect.
     * @return false|mixed|null
     */
    public function connect();

    /**
     * Disconnect.
     * @return void
     */
    public function disconnect(): void;

    /**
     * Reconnect.
     * @param int $iTimeoutMs
     * @return false|mixed|null
     */
    public function reconnect(int $iTimeoutMs = 10);

    /**
     * Get connection.
     * @return false|resource
     */
    public function getConnection();

    /**
     * Get login result.
     * @return bool
     */
    public function getLoginResult(): bool;

    /**
     * Get error.
     * @return string
     */
    public function getError(): string;

    /**
     * Clean up resources before the instance is destroyed.
     */
    public function __destruct();

    /**
     * Get dir perms.
     */
    public function getDirPerms(): int;
}
