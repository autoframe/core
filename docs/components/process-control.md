
*PHP worker instance create and inter process lock check Autoframe Framework*


```php
/** Concrete implementation that uses the php temporary folder to store the lock */
namespace Autoframe\Core\ProcessControl\Lock;
class AfrLockFileClass implements AfrLockInterface
...
interface AfrLockInterface
{
    /** Check if the lock is in place */
    public function isLocked(): bool;

    /** Creates a new lock or fails */
    public function obtainLock(): bool;

    /** Returns false if lock is in place and the lock file can't be closed
     *  Returns true if there is no lock in place or operation was successfully made */
    public function releaseLock(): bool;

    /** Returns Process ID for the lock thread or zero if other case */
    public function getLockPid(): int;
}
```

---

```php
/** Concrete implementation for create (spawn) worker process in background */
namespace Autoframe\Core\ProcessControl\Worker\Background;
class AfrBackgroundWorkerClass implements AfrBackgroundWorkerInterface
...
interface AfrBackgroundWorkerInterface
{
    /** Returns /usr/bin/php or C:\xampp\php\php.exe or php */
    public static function getPhpBin(): string;

    /** Calls: php $execFileArgs > /dev/null & or widows equivalent start /B */
    public static function execWithArgs(string $execFileArgs): void;
}
```