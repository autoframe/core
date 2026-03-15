# Socket Cache (`Autoframe\\Core\\SocketCache`)

> **Purpose**: document the multi-adaptor cache system in `src/SocketCache/`, including repository management, adapter configuration, the proprietary `afrsock` client-server cache, and Laravel-port cache/redis internals.

---

## 1) Component Snapshot

- **Namespace**: `Autoframe\\Core\\SocketCache`
- **Source root**: `src/SocketCache/`
- **Primary role**: provide a unified cache manager that can run multiple cache channels for the same project.
- **Main architecture**:
  - app-level config container (`AfrCacheApp`),
  - manager/facade entrypoints (`AfrCacheManager`, `Facade\\AfrCache`),
  - adapter stores from Laravel-port cache layer,
  - optional local socket cache daemon (`afrsock`) via `AfrSocketClient` + `AfrSocketServer`.

### 1.1 Supported adapter families

This component is designed to work with these store types:
- `array`
- `null`
- `file`
- `apc` / `apcu`
- `memcached`
- `redis`
- `database` (through Laravel-port driver surface)
- `afrsock` (proprietary socket client/server cache channel)

---

## 2) AI-Friendly Index (Machine-Readable)

```yaml
doc_id: socket-cache
namespace: Autoframe\\Core\\SocketCache
source_dir: src/SocketCache
entrypoints:
  - class: AfrCacheManager
    method: store
  - class: Facade\\AfrCache
    method: __callStatic
  - class: App\\AfrCacheApp
    methods:
      - setNullConfig
      - setArrayConfig
      - setFileConfig
      - setApcConfig
      - setMemcachedConfig
      - setRedisConfig
      - setSockConfig
capabilities:
  - multi_channel_cache_repositories
  - per_driver_runtime_config
  - laravel_style_repository_contract
  - lock_and_rate_limiter_primitives
  - proprietary_socket_cache_client_server
  - redis_connector_abstraction_phpredis_and_predis
important_subsystems:
  - manager: AfrCacheManager
  - app_config: App\\AfrCacheApp
  - facade: Facade\\AfrCache
  - repo_selector: Facade\\AfrRepositoryAutoSelector
  - afrsock_client: Client\\AfrSocketClient
  - afrsock_store: Client\\AfrClientStore
  - afrsock_server: Server\\AfrSocketServer
  - redis_manager: LaravelPort\\Redis\\RedisManager
```

---

## 3) Core building blocks

### 3.1 `AfrCacheApp` (runtime config + driver wiring)

`App\\AfrCacheApp` is the configuration nucleus. It stores cache config, supported repository types, and helper methods to register each backend.

Key methods include:
- `setNullConfig(...)`
- `setArrayConfig(...)`
- `setFileConfig(...)`
- `setApcConfig(...)`
- `setMemcachedConfig(...)`
- `setRedisConfig(...)`
- `setSockConfig(...)`

`setSockConfig(...)` injects an `extend` closure that builds an `AfrClientStore` with `AfrCacheSocketConfig`, allowing afrsock repositories to be selected like any other cache store.

### 3.2 `AfrCacheManager` (store resolver)

`AfrCacheManager` extends Laravel-port `CacheManager` and customizes `resolve($name)`:
- obtains config for named store,
- checks native driver factory (`createXDriver`) or dynamic custom creators,
- supports per-store `extend` / `closure` custom creation paths,
- delegates back to parent resolver once wiring is prepared.

### 3.3 `Facade\\AfrCache` and `Facade\\AfrRepositoryAutoSelector`

- `AfrCache` is the static facade for manager/repository methods.
- `AfrRepositoryAutoSelector` maps namespace prefixes to selected repositories so callers can route cache usage by key namespace and priority.

### 3.4 afrsock channel (`Client`, `Server`, `Common`, `Integrity`)

- `Client\\AfrSocketClient`: low-level socket request/response client.
- `Client\\AfrClientStore`: cache store backed by afrsock protocol (put/get/many/flush/delete/increment/etc.).
- `Server\\AfrSocketServer` + `Server\\AfrServerStore`: in-memory socket server-side cache storage.
- `Common\\AfrCacheSocketStore` and traits: shared store behavior and client/server command helpers.
- `Integrity\\AfrSocketIntegrityClass`: payload integrity helper.

---

## 4) Redis LaravelPort status and fixes

The Redis layer in `src/SocketCache/LaravelPort/Redis/` is Laravel-inspired but adapted.

### 4.1 What was fixed in this update

1. **Removed hidden Laravel helper dependency in `PhpRedisConnection::hmset(...)`**
   - Replaced `collect(...)` usage with native array-pair normalization.
   - Prevents runtime failure in environments where global Laravel helpers are unavailable.

2. **Fixed `PhpRedisConnection::set(...)` argument handling**
   - No-expiry calls now execute as `set(key, value)` instead of passing a null options argument.
   - Expiry/options payload is built only when resolution/TTL is provided.

3. **Hardened `RedisManager` connector behavior**
   - Initializes `$connections` as an array.
   - Throws explicit `InvalidArgumentException` for unsupported Redis drivers.
   - `connections()` now safely returns an array.

4. **Improved `PhpRedisConnector` compatibility paths**
   - Added safer Redis extension version checks.
   - Added auth credential resolver supporting username+password form on newer phpredis versions.

---

## 5) Adapter usage examples (from tests)

Below examples are adapted from:
- `Tests/Unit/SocketCacheTest/AfrCacheManagerTest.php`
- `Tests/Unit/SocketCacheTest/AfrClientStoreTest.php`

### 5.1 Null store

```php
<?php

use Autoframe\Core\SocketCache\App\AfrCacheApp;
use Autoframe\Core\SocketCache\Facade\AfrCache;

AfrCacheApp::getInstance()->setNullConfig(true);
$repo = AfrCache::getManager()->store();

$repo->put('ff', 4, 5);   // bool
$value = $repo->get('ff'); // null (NullStore behavior)
```

### 5.2 Array store

```php
<?php

use Autoframe\Core\SocketCache\App\AfrCacheApp;
use Autoframe\Core\SocketCache\Facade\AfrCache;

AfrCacheApp::getInstance()->setArrayConfig(
    true,  // serialize
    true,  // default store
    ['driver' => 'array']
);

$repo = AfrCache::getManager()->store();
$repo->put('k', ['a' => 1], 5);
$data = $repo->get('k');
```

### 5.3 File store

```php
<?php

use Autoframe\Core\SocketCache\App\AfrCacheApp;
use Autoframe\Core\SocketCache\Facade\AfrCache;

AfrCacheApp::getInstance()->setFileConfig(true, [
    'driver' => 'file',
    'path' => __DIR__ . '/fileCache',
]);

$repo = AfrCache::getManager()->store();
$repo->put('file:key', 'payload', 50);
```

### 5.4 afrsock store through manager

```php
<?php

use Autoframe\Core\SocketCache\App\AfrCacheApp;
use Autoframe\Core\SocketCache\Facade\AfrCache;

$app = AfrCacheApp::getInstance();

if ($app->testSock()) {
    $app->setSockConfig([
        'driver' => 'afrsock',
        'iAutoShutdownServerAfterXSeconds' => 40,
        'bServerAutoPowerOnByConfigViaCliOnLocal' => true,
        'iServerMemoryMb' => 16,
    ], true);

    $repo = AfrCache::getManager()->store();
    $repo->put('sock:key', 'value', 5);
}
```

### 5.5 afrsock direct client store operations

```php
<?php

use Autoframe\Core\SocketCache\AfrCacheSocketConfig;
use Autoframe\Core\SocketCache\Client\AfrClientStore;

$config = new AfrCacheSocketConfig('afrsock');
$config->iAutoShutdownServerAfterXSeconds = 60;
$config->bServerAutoPowerOnByConfigViaCliOnLocal = true;
$config->iServerMemoryMb = 16;
$config->socketPort = 27499;

AfrCacheSocketConfig::serverUp($config);
$store = new AfrClientStore($config);

$store->putMany(['a' => 1, 'b' => 'x'], 1);
$items = $store->many(['a', 'b']);
$store->increment('counter', 5);
$store->delete('a');
$store->flush();
```

### 5.6 Repository auto-selector

```php
<?php

use Autoframe\Core\SocketCache\Facade\AfrRepositoryAutoSelector;

AfrRepositoryAutoSelector::setToUseRepositories(
    AfrRepositoryAutoSelector::SECONDARY_LOAD,
    ['file']
);

$key = AfrRepositoryAutoSelector::prefixKeyForRepo(
    'sKeyName',
    AfrRepositoryAutoSelector::SECONDARY_LOAD
);

$repo = AfrRepositoryAutoSelector::selectRepoByKeyNs($key);
$repo->set('sKeyName', 'sKeyVal', 2);
```

---

## 6) Operational notes

| Scenario | Behavior |
|---|---|
| Missing PHP extension for a driver | `AfrCacheApp::test*()` helpers gate setup and may throw `AfrException` on forced setup. |
| Redis driver unavailable (`ext-redis` and `predis/predis` both missing) | `setRedisConfig(...)` throws with guidance text. |
| afrsock on environments without sockets extension | `setSockConfig(...)` throws; tests skip through `testSock()`. |
| Memcached service down | tests probe port 11211 before running memcached assertions. |
| APC differences | tests normalize `false` payload expectations because APC can map false-like values differently. |

---

## 7) Practical guidance

- Use `AfrCacheApp` for explicit per-driver setup before calling the manager facade.
- Treat `afrsock` as a local high-performance cache channel when you control both client and server process lifecycle.
- Keep multiple named stores configured and route usage with `AfrRepositoryAutoSelector` where workload classes differ.
- For Redis usage, prefer explicit client config (`phpredis` or `predis`) and verify connection options in `database.redis`.
- Validate adapter availability at runtime (`testSock`, `testMemcached`, `testApc`, `testRedis`) before promoting a driver to default.
