# FTP Transfer (`Autoframe\\Core\\FtpTransfer`)

> **Purpose**: document resumable local-to-FTP backup workflows, transfer business logic, connection abstractions, logging, and optional reporting from `src/FtpTransfer/`.

---

## 1) Component Snapshot

- **Namespace**: `Autoframe\\Core\\FtpTransfer`
- **Source root**: `src/FtpTransfer/`
- **Package**: `autoframe/components-ftp-transfer`
- **Primary use case**: upload local folders/files to FTP destinations with support for:
  - resumable transfer state,
  - pluggable business logic,
  - lock-based single-instance execution,
  - inline progress/action logging,
  - optional delivery report hooks.

### 1.1 Submodule map

| Submodule | Main class/interface | Responsibility |
|---|---|---|
| Root config | `AfrFtpBackupConfig` | Runtime transfer settings, credentials, paths, report config, class wiring |
| Business logic facade | `AfrFtpPutBigDataFacade` | Orchestrates run/resume, dependency defaults, lock/log setup |
| Business logic implementations | `AfrFtpPutBigData`, `AfrFtpNbrCopiesDms` | Concrete backup/copy behaviors implementing `AfrFtpBusinessLogicInterface` |
| FTP connection | `AfrFtpConnectionClass`, `AfrFtpConnectionInterface` | FTP connect/login/passive mode/reconnect/error lifecycle |
| Logging | `AfrFtpLogInline`, `AfrFtpLogInterface` | In-memory + inline stdout logging with severity tags |
| Reporting | `AfrFtpReportInterface`, `AfrFtpReportBpg` | Optional POST-based report dispatch after backup completion |
| Shared internals | `FtpBusinessLogic/SharedMethods/*` traits | Path normalization, queueing, FTP/local file operations, action audit |

---

## 2) AI-Friendly Index (Machine-Readable)

```yaml
doc_id: ftp-transfer
namespace: Autoframe\\Core\\FtpTransfer
source_dir: src/FtpTransfer
package: autoframe/components-ftp-transfer
requires:
  php: ">=7.4"
  extensions:
    - ftp
  packages:
    - autoframe/components-filesystem
    - autoframe/process-control
entrypoints:
  - class: AfrFtpPutBigDataFacade
    method: makeBackup
  - class: AfrFtpPutBigData
    method: makeBackup
  - class: AfrFtpNbrCopiesDms
    method: makeBackup
core_objects:
  - AfrFtpBackupConfig
  - Connection/AfrFtpConnectionInterface
  - Log/AfrFtpLogInterface
  - Report/AfrFtpReportInterface
capabilities:
  - resumable_ftp_upload
  - local_to_ftp_folder_mirroring
  - single_instance_locking
  - transfer_action_logging
  - upload_progress_reporting
  - pluggable_business_logic_and_reporter
```

---

## 3) Configuration model (`AfrFtpBackupConfig`)

`AfrFtpBackupConfig` is the central DTO-style runtime config.

### 3.1 Required fields (typical)

- `aFromToPaths`: map of local source dir => FTP destination dir.
- `ConServer`, `ConUsername`, `ConPassword`: FTP credentials.

### 3.2 Important optional fields

- `ConPort` (default `21`), `ConTimeout` (default `90`), `ConPassive` (default `true`).
- `iDirPermissions` (default `0775`) for created directories.
- `sTodayFolderName`, `sLatestFolderName` folder naming convention.
- `sResumeDump` file path used for serialized resume state.
- Report fields: `sReportTarget`, `sReportTo`, `sReportToSecond`, `sReportSubject`, `sReportBody`, mixed payload/header fields.
- `iLogUploadProgressEveryXSeconds` controls progress log cadence.

### 3.3 Strategy hooks

- `setBusinessLogic(string $class)` requires `AfrFtpBusinessLogicInterface`.
- `setReportClass(string $class)` requires `AfrFtpReportInterface`.

---

## 4) Runtime flow (high level)

1. Build and populate `AfrFtpBackupConfig`.
2. Instantiate `AfrFtpPutBigDataFacade` with the config.
3. Optionally inject a custom log implementation via `xetAfrFtpLog(...)`.
4. Call `makeBackup()`.
5. Facade checks resume dump:
   - if present: unserializes and resumes upload object state,
   - else: validates source paths and creates configured business-logic class.
6. Business logic manages FTP ops, queueing, retries/reconnects, and action logging.
7. Optional report class is invoked to publish transfer report payload.

---

## 5) Core interfaces

### 5.1 `FtpBusinessLogic/AfrFtpBusinessLogicInterface`

```php
<?php

namespace Autoframe\Core\FtpTransfer\FtpBusinessLogic;

interface AfrFtpBusinessLogicInterface
{
    public function makeBackup(): void;
}
```

### 5.2 `Connection/AfrFtpConnectionInterface`

```php
<?php

namespace Autoframe\Core\FtpTransfer\Connection;

interface AfrFtpConnectionInterface
{
    public function connect();
    public function disconnect(): void;
    public function reconnect(int $iTimeoutMs = 10);
    public function getConnection();
    public function getLoginResult(): bool;
    public function getError(): string;
    public function getDirPerms(): int;
}
```

### 5.3 `Log/AfrFtpLogInterface`

```php
<?php

namespace Autoframe\Core\FtpTransfer\Log;

interface AfrFtpLogInterface
{
    public const FATAL_ERR = 1;
    public const MESSAGE = 2;

    public function newLog(): self;
    public function logMessage(string $sMessage, int $iType): self;
    public function closeLog(): self;
}
```

### 5.4 `Report/AfrFtpReportInterface`

```php
<?php

namespace Autoframe\Core\FtpTransfer\Report;

use Autoframe\Core\FtpTransfer\AfrFtpBackupConfig;

interface AfrFtpReportInterface
{
    public function ftpReport(AfrFtpBackupConfig $oFtpConfig): array;
}
```

---

## 6) Practical PHP examples

### 6.1 Minimal resumable FTP upload with facade

```php
<?php

declare(strict_types=1);

use Autoframe\Core\FtpTransfer\AfrFtpBackupConfig;
use Autoframe\Core\FtpTransfer\FtpBusinessLogic\AfrFtpPutBigDataFacade;

$config = new AfrFtpBackupConfig();
$config->ConServer = 'ftp.example.com';
$config->ConUsername = 'backup_user';
$config->ConPassword = 'secret';
$config->ConPassive = true;
$config->aFromToPaths = [
    '/var/backups/app' => '/offsite/app',
];
$config->sResumeDump = __DIR__ . '/runtime/ftp.resume.php';

$facade = new AfrFtpPutBigDataFacade($config);
$facade->makeBackup();
```

### 6.2 Inject inline logger for console visibility

```php
<?php

declare(strict_types=1);

use Autoframe\Core\FtpTransfer\AfrFtpBackupConfig;
use Autoframe\Core\FtpTransfer\FtpBusinessLogic\AfrFtpPutBigDataFacade;
use Autoframe\Core\FtpTransfer\Log\AfrFtpLogInline;

$config = new AfrFtpBackupConfig(date('Ymd'));
$config->ConServer = 'ftp.example.com';
$config->ConUsername = 'backup_user';
$config->ConPassword = 'secret';
$config->aFromToPaths = ['/data/export' => '/remote/export'];

$facade = new AfrFtpPutBigDataFacade($config);
$facade->xetAfrFtpLog(new AfrFtpLogInline());
$facade->makeBackup();
```

### 6.3 Use an alternate business-logic implementation

```php
<?php

declare(strict_types=1);

use Autoframe\Core\FtpTransfer\AfrFtpBackupConfig;
use Autoframe\Core\FtpTransfer\FtpBusinessLogic\AfrFtpNbrCopiesDms;
use Autoframe\Core\FtpTransfer\FtpBusinessLogic\AfrFtpPutBigDataFacade;

$config = new AfrFtpBackupConfig();
$config->setBusinessLogic(AfrFtpNbrCopiesDms::class);
$config->ConServer = 'ftp.example.com';
$config->ConUsername = 'user';
$config->ConPassword = 'pass';
$config->aFromToPaths = ['/src' => '/dst'];

(new AfrFtpPutBigDataFacade($config))->makeBackup();
```

### 6.4 Configure report delivery endpoint

```php
<?php

declare(strict_types=1);

use Autoframe\Core\FtpTransfer\AfrFtpBackupConfig;
use Autoframe\Core\FtpTransfer\Report\AfrFtpReportBpg;

$config = new AfrFtpBackupConfig();
$config->setReportClass(AfrFtpReportBpg::class);
$config->sReportTarget = 'https://ops.example.com/ftp-report';
$config->sReportTo = 'ops@example.com';
$config->sReportToSecond = 'security@example.com';
$config->sReportSubject = 'Nightly FTP backup report';
$config->sReportBody = 'Upload completed. See attached metadata.';
$config->mReportMixedA = ['environment' => 'production'];
```

---

## 7) Operational notes and caveats

| Scenario | Behavior |
|---|---|
| Source path missing | Facade removes invalid entry and logs fatal message. |
| All source paths invalid | Backup exits early after fatal log. |
| FTP connection interruption | Connection class supports reconnect; business logic retries and logs state. |
| Resume dump exists | Facade resumes via serialized uploader state from `sResumeDump`. |
| Reporter cannot open endpoint | `AfrFtpReportBpg` throws exception on stream open/read failure. |
| CLI vs web output | `AfrFtpLogInline` prints newline in CLI and `<br />` for non-CLI output. |

---

## 8) Suggested best practices

- Use dedicated FTP credentials with restricted directory scope.
- Keep `sResumeDump` on durable local storage and rotate stale files.
- Pair uploads with process lock (`AfrLockFileClass`) when scheduling cron jobs.
- Keep `ConPassive = true` unless network policy requires active mode.
- Provide a custom report class if your environment uses non-HTTP transport.
