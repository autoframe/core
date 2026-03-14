# Process Control (`Autoframe\\Core\\ProcessControl`)

> **Purpose**: document process-locking and background-worker helpers in `src/ProcessControl/`.

---

## 1) Component Snapshot

- **Namespace**: `Autoframe\\Core\\ProcessControl`
- **Source root**: `src/ProcessControl/`
- **Primary domains**:
  - lock coordination across processes (`Lock` module)
  - background command/worker spawning (`Worker\\Background` module)

### 1.1 Module map

| Module | Class | Interface |
|---|---|---|
| `Lock` | `AfrLockFileClass` | `AfrLockInterface` |
| `Worker\\Background` | `AfrBackgroundWorkerClass` | `AfrBackgroundWorkerInterface` |
| `Worker\\Background` | `AfrBackgroundWorkerTrait` | (trait implementation) |

---

## 2) AI-Friendly Index (Machine-Readable)

```yaml
doc_id: process-control
namespace: Autoframe\\Core\\ProcessControl
source_dir: src/ProcessControl
modules:
  - Lock
  - Worker.Background
capabilities:
  - file_based_inter_process_locking
  - lock_pid_tracking
  - lock_acquire_release_checks
  - background_php_process_spawning
  - cross_platform_dispatch_windows_unix
  - php_binary_auto_detection
runtime_dependencies:
  - Autoframe\\Core\\CliTools\\AfrSysTempDir
  - Autoframe\\Core\\CliTools\\AfrCheckExec
  - flock_fopen_exec_popen
```

---

## 3) `Lock` module

### 3.1 `AfrLockInterface` contract

| Method | Purpose |
|---|---|
| `isLocked(): bool` | Check whether lock is currently held. |
| `obtainLock(): bool` | Attempt to acquire lock. |
| `releaseLock(): bool` | Release lock and cleanup resources. |
| `getLockPid(): int` | Get process ID associated with lock. |
| `getMyPid(): int` (static) | Cached current process PID helper. |

### 3.2 `AfrLockFileClass` behavior

- Uses filesystem-based lock files in temp directory (`AfrSysTempDir::sysGetTempDir()`).
- Lock path is deterministic hash of lock name + optional context data.
- Uses `flock(LOCK_EX | LOCK_NB)` to attempt non-blocking exclusive lock.
- Writes PID to both lock file stream and `.pid` side file.
- Registers shutdown cleanup when lock resource is still active.

### 3.3 When to use

Use `AfrLockFileClass` to guard singleton jobs/cron/worker entrypoints against parallel execution.

---

## 4) `Worker\\Background` module

### 4.1 `AfrBackgroundWorkerInterface` contract

| Method | Purpose |
|---|---|
| `getPhpBin(): string` | Detect and return PHP executable path. |
| `execWithArgs(string $execFileArgs): void` | Spawn a PHP script with args in background (or platform equivalent). |

### 4.2 `AfrBackgroundWorkerTrait` behavior

- Detects PHP binary from `PHP_BINARY`, then platform fallbacks (`/usr/bin/php`, windows php.exe near ini, etc.).
- Supports background launch style differences:
  - Unix: appends `> /dev/null &`
  - Windows: can use `start /B`
- Dispatches via:
  - Unix: `exec(...)` (requires `AfrCheckExec::isExecAvailable()`)
  - Windows: `popen/pclose` (requires `AfrCheckExec::isPOpenCloseAvailable()`)
- Throws `AfrException` when required exec functions are disabled.

### 4.3 Operational note

Scripts intended for long-running detached execution should manage lifecycle explicitly (for example `ignore_user_abort(true)` where applicable).

---

## 5) Practical PHP examples

### 5.1 Acquire lock for a cron job

```php
<?php

use Autoframe\Core\ProcessControl\Lock\AfrLockFileClass;

$lock = new AfrLockFileClass('my-cron-job', ['tenant' => 'default']);

if (!$lock->obtainLock()) {
    // another process is running this job
    exit(0);
}

try {
    // protected critical section
    // run task...
} finally {
    $lock->releaseLock();
}
```

### 5.2 Check lock owner process

```php
<?php

use Autoframe\Core\ProcessControl\Lock\AfrLockFileClass;

$lock = new AfrLockFileClass('queue-worker');

if ($lock->isLocked()) {
    $pid = $lock->getLockPid();
    echo "Worker already running with PID: {$pid}";
}
```

### 5.3 Start PHP worker in background

```php
<?php

use Autoframe\Core\ProcessControl\Worker\Background\AfrBackgroundWorkerClass;

AfrBackgroundWorkerClass::execWithArgs('bin/worker.php --queue=emails --sleep=2');
```

### 5.4 Run custom CLI command dispatch

```php
<?php

use Autoframe\Core\ProcessControl\Worker\Background\AfrBackgroundWorkerClass;

AfrBackgroundWorkerClass::execCli('php bin/sync.php --target=cache > /tmp/sync.log 2>&1 &');
```

---

## 6) Behavior and edge cases

| Scenario | Behavior |
|---|---|
| Lock file missing | `isLocked()` returns false. |
| Lock held by current process | `isLocked()` returns true while pointer active. |
| Unable to open lock file | `obtainLock()` returns false. |
| Disabled `exec`/`popen` functions | background dispatch throws `AfrException`. |
| Unknown php binary | falls back to `php` command string if auto-detection cannot locate concrete path. |

---

## 7) Contribution checklist

Before changing `src/ProcessControl/*`:

- [ ] Keep lock lifecycle semantics (`obtain`/`release`/shutdown cleanup) documented.
- [ ] Keep platform-specific background execution notes synchronized.
- [ ] Update examples when command-dispatch signatures change.
- [ ] Document any new modules (e.g., additional lock backends or worker strategies).
