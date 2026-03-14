# File System Utilities (`Autoframe\\Core\\FileSystem`)

> **Purpose**: document path, write, traversal, versioning, split/merge, and cache helpers in `src/FileSystem/`.

---

## 1) Component Snapshot

- **Namespace**: `Autoframe\\Core\\FileSystem`
- **Source root**: `src/FileSystem/`
- **Architecture style**:
  - singleton service classes,
  - interface + trait-based reusable logic,
  - focused submodules for path, encoding, traversal, versioning, split/merge, and cache.

### 1.1 Submodule map

| Submodule | Main class/interface |
|---|---|
| `DirPath` | `AfrDirPathClass` / `AfrDirPathInterface` |
| `Encode` | `AfrBase64InlineDataClass` / `AfrBase64InlineDataInterface` |
| `OverWrite` | `AfrOverWriteClass` / `AfrOverWriteInterface` |
| `Traversing` | `AfrDirTraversingCollectionClass` and family interfaces/classes |
| `Versioning` | `AfrDirMaxFileMtimeClass`, `AfrFileVersioningMtimeHashClass` |
| `SplitMerge` | `AfrSplitMergeClass` / `AfrSplitMergeInterface` |
| `SplitMergeCopyDir` | `AfrSplitMergeCopyDirClass` / `AfrSplitMergeCopyDirInterface` |
| `CacheToPhpFile` | `AfrCachePhpFileToArray` + trait helpers |
| `Recursive` | `AfrRecursiveRmDir` helper |

---

## 2) AI-Friendly Index (Machine-Readable)

```yaml
doc_id: file-system
namespace: Autoframe\\Core\\FileSystem
source_dir: src/FileSystem
modules:
  - DirPath
  - Encode
  - OverWrite
  - Traversing
  - Versioning
  - SplitMerge
  - SplitMergeCopyDir
  - CacheToPhpFile
  - Recursive
capabilities:
  - normalize_and_validate_paths
  - base64_inline_encoding
  - safe_retry_file_overwrite
  - recursive_directory_traversal
  - max_mtime_and_version_hashing
  - split_large_files_and_merge_parts
  - split_copy_and_merge_directory_workflows
  - php_file_array_cache_read_write
```

---

## 3) Key modules and responsibilities

### 3.1 `DirPath`

Main concerns:
- path normalization and separator conversion,
- absolute path simplification,
- realpath wrappers,
- writable directory checks + creation.

Common methods include:
- `isDir`, `openDir`, `removeFinalSlash`, `addFinalSlash`,
- `makeUniformSlashStyle`, `correctDirPathFormat`, `simplifyAbsolutePath`, `fixDs`,
- `dirExistAndWritable`, `getRelativePath`.

### 3.2 `Encode`

`AfrBase64InlineDataClass` provides:
- `getBase64InlineData($path)` for data-uri style encoding,
- `getBase64InlineOnePx()` helper for inline one-pixel placeholders.

### 3.3 `OverWrite`

`AfrOverWriteClass` provides retry-based overwrite behavior for files,
helpful in scenarios with transient locks/races.

### 3.4 `Traversing`

Provides directory traversal variants:
- file listing (`getDirFileList`),
- all-children directory collection,
- recursive child-dir count,
- aggregated traversal collection service.

### 3.5 `Versioning`

Provides change/version helpers:
- `getDirMaxFileMtime(...)` for max timestamp scan,
- `fileVersioningMtimeHash(...)` for deterministic mtime-based version hash.

### 3.6 `SplitMerge`

Provides large-file split/merge helpers:
- `split(...)`
- `merge(...)`
- `blindMerge(...)`
- part-list validation helpers.

### 3.7 `SplitMergeCopyDir`

Provides directory-level split-copy and merge-copy workflows:
- `splitCopyDir(...)`
- `mergeCopyDir(...)`

### 3.8 `CacheToPhpFile`

Provides PHP-array cache helpers:
- `setToCache(...)` / `setToCacheInline(...)`
- `getFromCache(...)` / `getFromCacheInline(...)`
- temp/cache path derivation and conversion utilities.

---

## 4) Practical PHP examples

### 4.1 Normalize and validate a directory path

```php
<?php

use Autoframe\Core\FileSystem\DirPath\AfrDirPathClass;

$dirPath = AfrDirPathClass::getInstance();
$normalized = $dirPath->correctDirPathFormat('/var//www/app/', true);
$isWritable = $dirPath->dirExistAndWritable('/var/www/app/cache', true);
```

### 4.2 Build an inline base64 data string

```php
<?php

use Autoframe\Core\FileSystem\Encode\AfrBase64InlineDataClass;

$enc = AfrBase64InlineDataClass::getInstance();
$dataUri = $enc->getBase64InlineData(__DIR__ . '/logo.png');
```

### 4.3 Overwrite a file with retry behavior

```php
<?php

use Autoframe\Core\FileSystem\OverWrite\AfrOverWriteClass;

$ow = AfrOverWriteClass::getInstance();
$ok = $ow->overWriteFile(__DIR__ . '/config.runtime.php', "<?php return ['ok' => true];");
```

### 4.4 Traverse files by extension

```php
<?php

use Autoframe\Core\FileSystem\Traversing\AfrDirTraversingFileListClass;

$tr = AfrDirTraversingFileListClass::getInstance();
$phpFiles = $tr->getDirFileList(__DIR__ . '/src', ['php']);
```

### 4.5 Generate mtime-based version hash

```php
<?php

use Autoframe\Core\FileSystem\Versioning\AfrFileVersioningMtimeHashClass;

$vh = AfrFileVersioningMtimeHashClass::getInstance();
$version = $vh->fileVersioningMtimeHash(__DIR__ . '/public/app.js');
```

### 4.6 Split and merge a large file

```php
<?php

use Autoframe\Core\FileSystem\SplitMerge\AfrSplitMergeClass;

$sm = AfrSplitMergeClass::getInstance();
$sm->split(__DIR__ . '/archive.tar', 5 * 1024 * 1024);
$sm->merge(__DIR__ . '/archive.tar.001');
```

### 4.7 Cache array data to PHP file

```php
<?php

use Autoframe\Core\FileSystem\CacheToPhpFile\AfrCachePhpFileToArray;

$cache = AfrCachePhpFileToArray::getInstance();
$cache->setToCache(['feature' => 'on'], 'MyContext', 60, 'demo_cache');
$data = $cache->getFromCache('MyContext', 'demo_cache', 60);
```

---

## 5) Error and behavior notes

| Scenario | Expected behavior |
|---|---|
| Path formatting mismatch | Path helpers normalize style and separators. |
| Directory missing but creatable | `dirExistAndWritable(..., true)` can create target dir. |
| Overwrite races/locks | overwrite helper retries based on internal strategy. |
| Missing traversal target | traversing methods can throw/return empty based on implementation and context. |
| Invalid split/merge part sets | split/merge utilities validate part sequence and may fail safely. |

---

## 6) Contribution checklist

Before changing `src/FileSystem/*`:

- [ ] Keep submodule map synchronized with current folders/classes.
- [ ] Update examples if signatures/return contracts change.
- [ ] Document behavior changes for retry/caching/versioning logic.
- [ ] Add module-specific caveats when introducing filesystem-side effects.
