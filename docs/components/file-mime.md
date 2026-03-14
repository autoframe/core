# File MIME Utilities (`Autoframe\\Core\\FileMime`)

> **Purpose**: document MIME lookup and MIME map generation utilities in `src/FileMime/`.

---

## 1) Component Snapshot

- **Namespace**: `Autoframe\\Core\\FileMime`
- **Source root**: `src/FileMime/`
- **Core runtime class**: `AfrFileMimeClass` (`AfrFileMimeInterface`)
- **Generator class**: `AfrFileMimeGeneratorClass`
- **Data source file**: `mime.types`

### 1.1 What it provides

- Extension -> MIME lookups
- MIME -> extension list lookups
- Fallback behavior for unknown extensions (`application/octet-stream`)
- Support for extensions with multiple MIME possibilities
- Trait generation from Apache `mime.types` source data

---

## 2) AI-Friendly Index (Machine-Readable)

```yaml
doc_id: file-mime
namespace: Autoframe\\Core\\FileMime
source_dir: src/FileMime
runtime:
  class: AfrFileMimeClass
  interface: AfrFileMimeInterface
generator:
  class: AfrFileMimeGeneratorClass
  input_file: src/FileMime/mime.types
  generated_traits:
    - AfrFileMimeExtensions
    - AfrFileMimeTypes
capabilities:
  - get_mime_from_filename
  - get_all_possible_mimes_from_filename
  - get_extensions_for_mime
  - get_extension_from_path
  - fallback_to_application_octet_stream
  - synchronize_mime_types_from_apache_source
```

---

## 3) Class map

| Class / Trait / Interface | Role |
|---|---|
| `AfrFileMimeInterface` | Public contract for MIME lookup methods. |
| `AfrFileMimeClass` | Singleton runtime service providing all MIME lookup operations. |
| `AfrFileMimeExtensions` | Generated extension => mime map trait. |
| `AfrFileMimeTypes` | Generated mime => [extensions] map trait. |
| `AfrFileMimeGeneratorClass` | Utility that parses `mime.types` and regenerates map traits. |
| `AfrFileSystemMimeException` | Exception type for generator/update failures. |

---

## 4) Runtime API (`AfrFileMimeClass`)

| Method | Purpose |
|---|---|
| `getFileMimeTypes(): array` | Full map: mime => extensions list. |
| `getFileMimeExtensions(): array` | Full map: extension => mime. |
| `getFileMimeFallback(): string` | Returns fallback mime (`application/octet-stream`). |
| `getAllMimesFromFileName(string $path): array` | All matching mimes for extension (handles multi-mime extensions). |
| `getMimeFromFileName(string $path): string` | Single best mime for extension (or fallback). |
| `getExtensionsForMime(string $mime): array` | Extensions list for a mime. |
| `getExtensionFromPath(string $path): string` | Extract extension from path. |

---

## 5) Generator workflow (`AfrFileMimeGeneratorClass`)

### 5.1 Intended usage

- Periodically synchronize local `mime.types` with Apache source.
- Regenerate `AfrFileMimeExtensions` and `AfrFileMimeTypes` traits when source updates.
- Keep this flow primarily in local development/maintenance tooling.

### 5.2 Main methods

| Method | Purpose |
|---|---|
| `synchronizeMimeTypesFromApache(...)` | Refresh source file and regenerate traits when stale. |
| `traitsAreUpToDate(int $deltaTs = 10)` | Check whether generated traits are fresh vs source file. |

---

## 6) Practical PHP examples

### 6.1 Resolve MIME from filename

```php
<?php

use Autoframe\Core\FileMime\AfrFileMimeClass;

$fileMime = AfrFileMimeClass::getInstance();

echo $fileMime->getMimeFromFileName('/tmp/image.jpg');
// image/jpeg
```

### 6.2 Get all possible mimes for extension

```php
<?php

use Autoframe\Core\FileMime\AfrFileMimeClass;

$fileMime = AfrFileMimeClass::getInstance();
$mimes = $fileMime->getAllMimesFromFileName('/tmp/vector.wmz');

print_r($mimes);
```

### 6.3 Get extensions for a given mime

```php
<?php

use Autoframe\Core\FileMime\AfrFileMimeClass;

$fileMime = AfrFileMimeClass::getInstance();
$extensions = $fileMime->getExtensionsForMime('image/jpeg');

// ['jpeg', 'jpg', 'jpe']
```

### 6.4 Regenerate MIME traits from Apache source

```php
<?php

use Autoframe\Core\FileMime\AfrFileMimeGeneratorClass;

$generator = AfrFileMimeGeneratorClass::getInstance();
$updated = $generator->synchronizeMimeTypesFromApache();

var_dump($updated);
```

---

## 7) Behavior notes and caveats

- Unknown extensions fall back to `application/octet-stream`.
- Some extensions can map to more than one mime; use `getAllMimesFromFileName` when ambiguity matters.
- Generator methods may access remote Apache source; handle network/environment constraints in CI.
- Runtime lookup is trait-backed map access (fast and deterministic once generated).

---

## 8) Contribution checklist

Before changing `src/FileMime/*`:

- [ ] Update method table if API changes.
- [ ] Keep fallback behavior documented.
- [ ] Document any changes in generation source/sync strategy.
- [ ] Add/update examples when new lookup behaviors are introduced.
