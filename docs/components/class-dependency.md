# Class Dependency Utilities (`Autoframe\\Core\\ClassDependency`)

> **Purpose**: document how `AfrClassDependency` builds and exposes dependency relationships between PHP symbols (classes, interfaces, traits, enums), and how that mapping can support interface-to-concrete resolution in DI workflows.

---

## 1) Reframed Intent (from product requirements)

The goals for this component can be restated as follows:

1. `AfrClassDependency` should be documented as a **dependency graph helper** that maps links between objects and types (parents, traits, interfaces).
2. The docs should explain how to use this graph to **choose one concrete implementation for an interface** from a discovered list.
3. The interface-to-concrete mapping built with this class should be presented as a **pre-step for dependency injection containers**, so a container can select an implementation from a curated candidate set.
4. The docs must mention that input candidates are expected to come from **Composer-driven class discovery/scanning**, so mapping quality depends on scanner coverage.

---

## 2) Component Snapshot

- **Namespace**: `Autoframe\\Core\\ClassDependency`
- **Source folder**: `src/ClassDependency`
- **Primary class**: `AfrClassDependency`
- **Related exception**: `AfrClassDependencyException`

### 2.1 What this component does

- Inspects a symbol (FQCN or object) and classifies its type (`class`, `trait`, `interface`, `enum`, `unknown`, `skip`, `fatal-error`).
- Builds dependency links:
  - parent classes
  - used traits (including recursive trait dependencies)
  - implemented interfaces
- Caches discovery results for repeated lookup.
- Provides skip rules (by FQCN and by namespace) to ignore selected symbols.
- Tracks failures encountered during reflection/discovery.

---

## 3) AI-Friendly Index

```yaml
doc_id: class-dependency
namespace: Autoframe\\Core\\ClassDependency
source_dir: src/ClassDependency
main_class: AfrClassDependency
exception_class: AfrClassDependencyException
capabilities:
  - classify_symbol_type
  - gather_parents_traits_interfaces
  - recursive_trait_detection
  - skip_rules_by_class
  - skip_rules_by_namespace
  - cache_and_flush
  - fatal_error_tracking
di_relevance:
  - build_interface_to_concrete_candidates
  - precompute_dependency_links_from_composer_scan
  - feed_candidate_lists_to_container_selection_logic
```

---

## 4) Static API Reference (`AfrClassDependency`)

| Method | Purpose |
|---|---|
| `getClassInfo(mixed $obj_sFQCN): AfrClassDependency` | Get (or compute + cache) dependency info for an object/FQCN. |
| `clearClassInfo(mixed $obj_sFQCN): bool` | Remove one cached entry. |
| `isSkipped(mixed $obj_sFQCN): bool` | Check if class/namespace skip rules apply. |
| `getDependencyInfo(): array` | Return all cached dependency objects. |
| `clearDependencyInfo(): void` | Clear all cached dependency objects. |
| `getDebugFatalError(): array` | Return discovery failures/fatal capture info. |
| `clearDebugFatalError(): void` | Clear failure debug list. |
| `flush(): void` | Reset all static state (cache + skip rules + fatal debug). |
| `setSkipClassInfo(array $aFQCN, bool $bMergeWithExisting=false): array` | Define skipped classes. |
| `setSkipNamespaceInfo(array $aNamespaces, bool $bMergeWithExisting=false): array` | Define skipped namespaces. |
| `getSkipClassInfo(): array` | Get skip-class debug/output list. |
| `getSkipNamespaceInfo(): array` | Get skip-namespace list. |

---

## 5) Instance API Reference (`AfrClassDependency` object)

| Method | Purpose |
|---|---|
| `getType(): string` | Return detected symbol type. |
| `getAllDependencies(): array` | Return merged map of parents + traits + interfaces. |
| `getClassName(): string` | Return FQCN represented by this object. |
| `__toString(): string` | String representation (FQCN). |
| `getParents(): array` | Parent classes map. |
| `getTraits(): array` | Traits map (includes recursively detected trait dependencies). |
| `getInterfaces(): array` | Implemented interfaces map. |
| `isClass(): bool` | Type check helper. |
| `isTrait(): bool` | Type check helper. |
| `isInterface(): bool` | Type check helper. |
| `isEnum(): bool` | Type check helper. |
| `isAbstract(): bool` | Reflection-based abstract check. |
| `isInstantiable(): bool` | Reflection-based instantiability check. |
| `isSingleton(): bool` | Heuristic check for static `getInstance()` singleton style. |
| `doIDependOn($mClass): bool` | Check whether symbol depends on a target class/interface/trait. |

> Instance methods are obtained via `AfrClassDependency::getClassInfo(<object-or-fqcn>)`.

---

## 6) How dependency links are mapped

### 6.1 Discovery flow

1. Normalize input to FQCN.
2. If cached, return cached instance.
3. If skipped by class or namespace rule, return a lightweight `skip` node.
4. Otherwise create a dependency node and detect:
   - trait usage via `class_uses`
   - implemented interfaces via `class_implements`
   - parent chain via `class_parents`
5. For traits and parents, recursively collect nested trait dependencies.

### 6.2 Output model

- Dependencies are returned as associative maps where keys are FQCNs.
- `getAllDependencies()` merges parents, traits, and interfaces.
- Dependency checks use `doIDependOn(<fqcn|object>)`.

### 6.3 Operational controls

- Use skip rules to avoid scanning vendor/internal namespaces you do not want in the graph.
- Use `flush()` for clean state between scans/builds.
- Use `getDebugFatalError()` to inspect problematic symbols.

---

## 7) Interface-to-concrete resolution (DI pre-step)

`AfrClassDependency` is not a full DI container, but it can act as a **selection engine input**.

### 7.1 Practical pattern

1. Build a class candidate list from Composer class discovery.
2. For each candidate class:
   - call `getClassInfo($candidate)`
   - keep only those where `isClass() && isInstantiable()`
3. For each target interface:
   - test candidates with `$candidateInfo->doIDependOn(TargetInterface::class)` or inspect `getInterfaces()`
4. Store the resulting `interface => [concrete candidates...]` map.
5. Apply project policy to choose one concrete implementation (priority, env, tags, config, tenant, etc.).
6. Feed the selected mapping into your container wiring.

### 7.2 Why this matters

- Enables deterministic interface implementation selection from a discovered set.
- Moves expensive graph discovery before container boot/runtime resolution.
- Creates a maintainable bridge between Composer metadata and DI configuration.

---

## 8) Composer scanner integration notes

To maximize mapping quality, feed `AfrClassDependency` with symbols discovered by Composer tooling (autoload/classmap/scan pipeline).

Recommended inputs:
- classes from Composer classmap/autoload registry
- project namespaces and selected vendor namespaces
- filtered list excluding ignored namespaces via `setSkipNamespaceInfo()`

Recommended workflow:
- collect symbols -> analyze via `AfrClassDependency` -> persist dependency index -> use index during DI bootstrap.

---

## 9) Example snippets

### 9.1 Basic inspection

```php
<?php

use Autoframe\\Core\\ClassDependency\\AfrClassDependency;

$info = AfrClassDependency::getClassInfo(App\\Service\\MyService::class);

$type = $info->getType();
$deps = $info->getAllDependencies();
$isInstantiable = $info->isInstantiable();
```

### 9.2 Build interface candidates

```php
<?php

use Autoframe\\Core\\ClassDependency\\AfrClassDependency;

$targetInterface = App\\Contracts\\MailerInterface::class;
$candidates = [
    App\\Infra\\Mailer\\SmtpMailer::class,
    App\\Infra\\Mailer\\NullMailer::class,
    App\\Infra\\Mailer\\ApiMailer::class,
];

$matches = [];
foreach ($candidates as $fqcn) {
    $i = AfrClassDependency::getClassInfo($fqcn);
    if ($i->isClass() && $i->isInstantiable() && $i->doIDependOn($targetInterface)) {
        $matches[] = $fqcn;
    }
}

// $matches is now your interface->concrete candidate list input.
```

---

## 10) Caveats & best practices

- `isSingleton()` is convention-based (checks static `getInstance()`), not a universal singleton detector.
- Invalid/non-loadable FQCN values can produce `unknown`/`fatal-error` flows; inspect debug data.
- For large scans, apply skip rules early and periodically clear cache to manage memory.
- Keep DI selection policy separate from dependency discovery logic.
