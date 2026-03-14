# Arr Utilities (`Autoframe\\Core\\Arr`)

> **Purpose**: documentation blueprint for all classes and interfaces under `src/Arr/`.
> 
> **Audience**: humans and AI agents (LLMs, code assistants, indexers).

---

## 1) Component Snapshot

- **Namespace**: `Autoframe\\Core\\Arr`
- **Source root**: `src/Arr/`
- **What it provides**:
  - Array comparison utilities
  - Profile-style recursive merge helpers
  - Sorting helpers (including sub-key sort)
  - Array export/stringification helpers

### 1.1 Module map

| Module | Class | Interface |
|---|---|---|
| Compare | `AfrArrCompareClass` | `AfrArrCompareInterface` |
| Merge | `AfrArrMergeProfileClass` | `AfrArrMergeProfileInterface` |
| Sort | `AfrArrSortBySubKeyClass` | `AfrArrSortBySubKeyInterface` |
| Sort | `AfrArrXSortClass` | `AfrArrXSortInterface` |
| Export | `AfrArrExportArrayAsStringClass` | `AfrArrExportArrayAsStringInterface` |

---

## 2) AI-Friendly Index (Machine-Readable Summary)

Use this block as a stable, structured index for retrieval systems.

```yaml
doc_id: arr-utilities
namespace: Autoframe\\Core\\Arr
source_dir: src/Arr
packages:
  - name: Compare
    class: AfrArrCompareClass
    interface: AfrArrCompareInterface
  - name: Merge
    class: AfrArrMergeProfileClass
    interface: AfrArrMergeProfileInterface
  - name: SortBySubKey
    class: AfrArrSortBySubKeyClass
    interface: AfrArrSortBySubKeyInterface
  - name: XSort
    class: AfrArrXSortClass
    interface: AfrArrXSortInterface
  - name: ExportArrayAsString
    class: AfrArrExportArrayAsStringClass
    interface: AfrArrExportArrayAsStringInterface
conventions:
  input_type: array
  strict_types: php
  error_mode: "describe per method"
  mutability: "mark whether input array is mutated"
```

---

## 3) Quick Start

```php
<?php

use Autoframe\\Core\\Arr\\Merge\\AfrArrMergeProfileClass;

$base = ['db' => ['host' => 'localhost', 'port' => 3306]];
$override = ['db' => ['port' => 3307]];

$result = AfrArrMergeProfileClass::singleton()->arrMergeProfile($base, $override);
```

> Add one short runnable snippet per module once behavior is documented.

---

## 4) Conceptual Model

### 4.1 Design goals
- Deterministic array operations
- Reusable utility methods behind focused interfaces
- Compatibility with nested associative arrays and list arrays

### 4.2 Terminology
- **Profile merge**: merge strategy where override arrays replace/merge default config branches.
- **Sub-key sort**: sorting parent rows using values of a nested key.
- **Export-as-string**: converting PHP arrays into reproducible string form.

---

## 5) API Reference Template (Repeat per Class/Interface Pair)

> Copy this section for each module and fill all fields.

### 5.X `<Module Name>`

#### Interface
- **Name**: `<InterfaceName>`
- **File**: `src/Arr/<Module>/<InterfaceName>.php`
- **Responsibility**: `<single-sentence contract>`

#### Class
- **Name**: `<ClassName>`
- **File**: `src/Arr/<Module>/<ClassName>.php`
- **Pattern**: `<singleton/static/instance>`
- **Depends on**: `<none or list>`

#### Methods

| Method | Signature | Returns | Mutates input? | Throws | Notes |
|---|---|---|---|---|---|
| `<methodName>` | ``<php signature>`` | `<type>` | Yes/No | `<exceptions>` | `<edge-case behavior>` |

#### Behavior details
- **Input expectations**: `<shape + constraints>`
- **Ordering guarantees**: `<stable/unstable/not applicable>`
- **Type handling**: `<scalar/null/object behavior>`
- **Complexity**: `<time/space big-O if relevant>`

#### Example
```php
<?php
// minimal runnable example
```

#### Gotchas
- `<important caveat 1>`
- `<important caveat 2>`

---

## 6) Module-Specific Sections to Fill

### 6.1 Compare (`AfrArrCompare*`)
- Equality semantics (strict vs loose)
- Key-order sensitivity
- Nested-array comparison rules

### 6.2 Merge (`AfrArrMergeProfile*`)
- Conflict resolution policy (left/right precedence)
- Numeric-key behavior (append/replace)
- Deep merge recursion behavior

### 6.3 Sort (`AfrArrSortBySubKey*`, `AfrArrXSort*`)
- Sorting direction flags
- Comparator behavior when keys are missing
- Stable vs unstable sorting behavior

### 6.4 Export (`AfrArrExportArrayAsString*`)
- Output format guarantees
- Supported value types and fallbacks
- Round-trip safety notes

---

## 7) Cross-Cutting: Contracts, Errors, and Compatibility

### 7.1 Error handling matrix

| Scenario | Method(s) | Expected behavior |
|---|---|---|
| Invalid input type | `<...>` | `<throw / cast / ignore>` |
| Missing key | `<...>` | `<default / skip / error>` |
| Unsupported value type | `<...>` | `<serialize / fail>` |

### 7.2 PHP compatibility
- Minimum supported PHP version
- Any version-specific behavior differences

### 7.3 Backward compatibility policy
- What is considered a breaking change for this component

---

## 8) Testing & Validation Mapping

| Concern | Suggested test file | Cases |
|---|---|---|
| Compare semantics | `tests/Arr/Compare/...` | strict/loose, nested, key order |
| Merge policy | `tests/Arr/Merge/...` | deep merge, numeric keys, null override |
| Sorting behavior | `tests/Arr/Sort/...` | asc/desc, missing keys, mixed values |
| Export output | `tests/Arr/Export/...` | scalar/null/array/object handling |

---

## 9) Changelog Notes for this Component

Keep a short, component-local changelog:

- `YYYY-MM-DD`: `<change summary>`
- `YYYY-MM-DD`: `<change summary>`

---

## 10) Contribution Checklist (for maintainers and AI agents)

Before changing `src/Arr/*`, ensure docs are updated:

- [ ] Updated API table for affected class/interface
- [ ] Added/updated at least one runnable example
- [ ] Documented edge cases and error behavior
- [ ] Synced testing matrix with new behavior
- [ ] Added changelog note in Section 9

---

## 11) Suggested Writing Rules (AI-Friendly)

- Keep headings stable; avoid renaming section numbers casually.
- One behavior statement per bullet.
- Prefer explicit input/output types over prose.
- For each method, include at least one edge-case note.
- Keep YAML index (Section 2) synchronized with source files.
