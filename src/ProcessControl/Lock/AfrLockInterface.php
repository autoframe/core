<?php
declare(strict_types=1);

namespace Autoframe\Core\ProcessControl\Lock;

interface AfrLockInterface
{
    /**
     * Check if the lock is in place
     * @return bool
     */
    public function isLocked(): bool;

    /**
     * Creates a new lock or fails
     * @return bool
     */
    public function obtainLock(): bool;

    /**
     * Returns false if lock is in place and the lock file can't be closed
     * Returns true if there is no lock in place or operation was successfully made.
     * @return bool
     */
    public function releaseLock(): bool;

    /**
     * Returns Process ID for the lock thread or zero if other case
     * @return int
     */
    public function getLockPid(): int;

	/**
	 * Returns current process id in order to prevent overload system calls with getmypid()
	 * @return int
	 */
	public static function getMyPid(): int;
}