# Environment Management (`Autoframe\\Core\\Env`)

> **Purpose**: document environment loading, parsing, validation, registration, and runtime access for classes under `src/Env/`.

---

## 1) Component Snapshot

- **Namespace**: `Autoframe\\Core\\Env`
- **Source root**: `src/Env/`
- **Primary class**: `AfrEnv`
- **Facade**: `AfrEnvFacade`
- **Parser**: `Parser\\AfrEnvParserClass`
- **Validator**: `Validator\\AfrEnvValidatorClass`

### 1.1 What this component does

- Reads `.env` files and PHP env-array files.
- Parses env key/value strings via parser service.
- Validates env data using reusable validation rules.
- Exposes runtime access helpers (`getEnv`, `isProduction`, `isStaging`, `isDev`, etc.).
- Optionally registers values into `$_ENV`, `$_SERVER`, and `getenv()`.
- Supports cache file generation for faster repeated loads.

---

## 2) AI-Friendly Index (Machine-Readable)

```yaml
doc_id: env-management
namespace: Autoframe\\Core\\Env
source_dir: src/Env
classes:
  - AfrEnv
  - AfrEnvFacade
  - Parser\\AfrEnvParserClass
  - Parser\\AfrEnvParserInterface
  - Validator\\AfrEnvValidatorClass
  - Validator\\AfrEnvValidatorInterface
  - Exception\\AfrEnvException
capabilities:
  - load_env_from_base_dir
  - load_env_from_extra_dirs_and_files
  - load_env_from_php_array_file
  - parse_env_strings_and_files
  - validate_required_and_optional_keys
  - register_env_to_superglobals_and_putenv
  - env_state_detection_production_staging_dev_debug
  - cache_env_snapshot_to_php
notes:
  - "isDev() returns true when APP_ENV is null"
  - "APP_ENV is recommended but not strictly mandatory"
```

---

## 3) Class map and responsibilities

| Class | Responsibility |
|---|---|
| `AfrEnv` | Main orchestrator for env read/merge/cache/register/get and validation pipeline. |
| `AfrEnvFacade` | Static proxy/facade wrapper over the env service instance/class. |
| `AfrEnvParserClass` | Parses env strings/files into structured arrays. |
| `AfrEnvValidatorClass` | Rule-based validator (required/ifPresent + type/value checks). |
| `AfrEnvException` | Domain exception for env parsing/validation/loading issues. |

---

## 4) Main workflow

### 4.1 Typical bootstrap flow

1. `AfrEnv::getInstance()->setBaseDir(<projectRoot>)`
2. `readEnv($cacheSeconds, $extraDirsFiles, $readBaseDir)`
3. Optional validation rules via `required(...)` / `ifPresent(...)`
4. `registerEnv($mutableOverwrite, $registerPutEnv)` if you want globals populated
5. Runtime access through `getEnv('KEY')`, `isProduction()`, etc.

### 4.2 Data sources

- `.env` files discovered from base dir and optional extra dirs/files.
- PHP files returning arrays via `readEnvPhpFile(...)`.
- Programmatic assignments via `setEnv(...)`.

### 4.3 Caching behavior

- `readEnv($iCacheSeconds > 0, ...)` can reuse a generated PHP cache snapshot.
- `0` means no cache reuse window.

---

## 5) API quick reference

### 5.1 `AfrEnv` core methods

| Method | Purpose |
|---|---|
| `setBaseDir(string $sDir)` | Set project/env discovery root directory. |
| `readEnv(int $iCacheSeconds, array $aEnvDirsFiles = [], bool $bReadEnvFromBaseDir = true)` | Read and merge env files with optional cache. |
| `readEnvPhpFile(string $sFilePath)` | Import env values from PHP file returning array. |
| `setEnv(string $sKey, $mData)` | Set/override a key in local env store. |
| `getEnv(string $sKey = '', $mFallback = null)` | Get one key or full env array. |
| `registerEnv(bool $bMutableOverwrite = false, bool $bRegisterPutEnv = false)` | Populate `$_ENV`, `$_SERVER`, and optionally `putenv`. |
| `flush()` | Reset internal env/validation/cache state. |
| `isProduction()` / `isStaging()` / `isDev()` / `isDebug()` / `isDevOrDebug()` | Runtime environment helpers. |

### 5.2 Validation methods (`AfrEnv` -> validator)

| Method | Purpose |
|---|---|
| `required(array $aKeys)` | Mark keys as required. |
| `ifPresent(array $aKeys)` | Apply rules only when key exists. |
| `unrequire(array $aKeys)` | Remove required/optional rules for keys. |

### 5.3 Swappable dependencies (`xet*` methods)

| Method | Purpose |
|---|---|
| `xetAfrEnvParser(...)` | Get/set parser implementation. |
| `xetAfrEnvValidator(...)` | Get/set validator implementation. |
| `xetFileList(...)` | Get/set file-list implementation for env discovery. |
| `xetOverWrite(...)` | Get/set file writer implementation for cache generation. |
| `xetExportArray(...)` | Get/set PHP array exporter implementation. |

---

## 6) Validation patterns (`AfrEnvValidatorClass`)

Supported chain-style validation helpers include:

- `required([...])`
- `ifPresent([...])`
- `allowedValues([...])`
- `customClosure(callable $fX)`
- type checks: `isInteger()`, `isFloat()`, `isBoolean()`, `isArray()`, `isString()`
- format/value checks: `isDateTime()`, `notEmpty()`
- lifecycle: `validateAll($dataset)`, `reset()`, `unrequire([...])`

---

## 7) Practical PHP examples

### 7.1 Load env from project root with cache

```php
<?php

use Autoframe\Core\Env\AfrEnv;

$env = AfrEnv::getInstance()
    ->setBaseDir(__DIR__)
    ->readEnv(60); // reuse cache up to 60 seconds

$appEnv = $env->getEnv('APP_ENV', 'DEV');
```

### 7.2 Merge extra files/dirs and register globals

```php
<?php

use Autoframe\Core\Env\AfrEnv;

$env = AfrEnv::getInstance()
    ->setBaseDir(__DIR__)
    ->readEnv(0, [
        __DIR__ . '/.env.local',
        __DIR__ . '/config/env',
    ])
    ->registerEnv(
        bMutableOverwrite: true,
        bRegisterPutEnv: true
    );

echo $_ENV['APP_ENV'] ?? 'unknown';
```

### 7.3 Load env values from a PHP array file

```php
<?php

use Autoframe\Core\Env\AfrEnv;

AfrEnv::getInstance()
    ->readEnvPhpFile(__DIR__ . '/env.custom.php')
    ->setEnv('FEATURE_FLAG_X', true);
```

### 7.4 Add validation rules before access

```php
<?php

use Autoframe\Core\Env\AfrEnv;

$env = AfrEnv::getInstance();

$env->required(['APP_ENV', 'SECRET_KEY']);
$env->required(['AFR_DEBUG'])->isInteger();
$env->ifPresent(['RATE_LIMIT'])->isFloat();
$env->ifPresent(['APP_ENV'])->allowedValues(['DEV', 'STAGING', 'PRODUCTION']);

$secret = $env->getEnv('SECRET_KEY');
```

### 7.5 Environment-state helpers

```php
<?php

use Autoframe\Core\Env\AfrEnv;

$env = AfrEnv::getInstance();

if ($env->isProduction()) {
    // production-only behavior
}

if ($env->isDevOrDebug()) {
    // verbose logging, diagnostics, etc.
}
```

---

## 8) Error and behavior notes

| Scenario | Expected behavior |
|---|---|
| Missing base dir in `setBaseDir` | Throws `AfrEnvException`. |
| Missing/invalid env PHP file | Throws `AfrEnvException`. |
| `registerEnv()` with no env data loaded | Throws `AfrEnvException`. |
| Accessing required key missing during validation | Throws validation exception (`AfrEnvException`). |
| `getEnv('AFR_ENV')` missing | Throws `AfrEnvException` with tenant-load guidance. |

---

## 9) `AfrEnvFacade` usage

Use `AfrEnvFacade` when static proxy style is preferred:

```php
<?php

use Autoframe\Core\Env\AfrEnvFacade;

AfrEnvFacade::setBaseDir(__DIR__);
AfrEnvFacade::readEnv(30);

$all = AfrEnvFacade::getEnv();
```

---

## 10) Contribution checklist

Before changing `src/Env/*`:

- [ ] Update this doc if env load order/caching behavior changes.
- [ ] Keep method tables synchronized with `AfrEnvInterface`.
- [ ] Add/update validation examples when new rule helpers are introduced.
- [ ] Document any changes to global registration behavior (`$_ENV`, `$_SERVER`, `putenv`).
