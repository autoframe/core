# Interface To Concrete (`Autoframe\\Core\\InterfaceToConcrete`)

> **Purpose**: document how classes in `src/InterfaceToConcrete/` discover symbols and resolve a non-concrete type (interface/abstract/trait target) to a concrete class candidate, with strategy-based priority rules and container-friendly integration.

---

## 1) Component Snapshot

- **Namespace**: `Autoframe\\Core\\InterfaceToConcrete`
- **Source root**: `src/InterfaceToConcrete/`
- **Core objective**: provide an automatic **interface -> concrete** resolution pipeline.
- **Key dependency**: `Autoframe\\Core\\ClassDependency\\AfrClassDependency` for symbol type/dependency scanning and filtering.
- **Container relevance**: designed to support any implementation of `Autoframe\\Core\\Container\\AfrContainerInterface` with implicit resolution when multiple implementations exist.

### 1.1 Files and roles

| File/Class | Role |
|---|---|
| `AfrInterfaceToConcreteClass` | Main orchestrator: config, path collection, map building, and per-FQCN resolve API. |
| `AfrInterfaceToConcreteInterface` | Contract for map generation/settings/resolve operations. |
| `AfrMultiClassMapper` | Scans class sources, builds and caches interface->concrete maps. |
| `AfrToConcreteStrategiesClass` | Strategy engine: applies ordered priority rules to choose one concrete from many. |
| `AfrToConcreteStrategiesInterface` | Contract for context, strategy extension, and map resolution. |
| `AfrVendorPath` | Composer/vendor/autoload discovery and class-map helper. |
| `Exception/AfrInterfaceToConcreteException` | Domain-specific exception for this component. |

---

## 2) Reframed Intent (from product requirements)

Your requirements can be reframed into this operational statement:

1. Use `AfrClassDependency` as the underlying scanner/classifier for project and vendor symbols (classes, interfaces, traits, enums).
2. Build candidate mappings where one interface may have multiple valid implementations.
3. Resolve ambiguity through explicit **priority-rule strategies**, so one implementation is selected without mandatory manual pre-binding.
4. Expose this as a practical bridge for container flows (`AfrContainerInterface` implementations), enabling auto-selection of concrete dependencies at resolution time.

---

## 3) AI-Friendly Index (Machine-Readable)

```yaml
doc_id: interface-to-concrete
namespace: Autoframe\\Core\\InterfaceToConcrete
source_dir: src/InterfaceToConcrete
depends_on:
  - Autoframe\\Core\\ClassDependency\\AfrClassDependency
supports:
  - scan_project_and_vendor_symbols
  - build_interface_to_concrete_map
  - resolve_ambiguous_implementations_with_priority_rules
  - context_aware_resolution
  - composer_autoload_classmap_psr_discovery
container_alignment:
  - Autoframe\\Core\\Container\\AfrContainerInterface
result_encoding:
  - "1|FQCN  => instantiable concrete"
  - "2|FQCN  => singleton-like concrete"
  - "0|FQCN  => unresolved/fail"
```

---

## 4) How the pipeline works

### 4.1 Discovery inputs

- Extra explicit paths from caller configuration.
- Composer vendor paths and autoload metadata via `AfrVendorPath`.
- Project autoload namespaces/classmaps.

### 4.2 Map build stage

`AfrInterfaceToConcreteClass::getClassInterfaceToConcrete()` orchestrates:

1. Prepare scanner settings and path prefixes.
2. Configure/flush `AfrClassDependency` skip rules and cache state.
3. Pass wiring/config to `AfrMultiClassMapper`.
4. Build map: `not-concrete-FQCN => [candidate-concrete-FQCN => flag]`.
5. Optionally flush/restore scanner state based on config.

### 4.3 Resolve stage

`AfrInterfaceToConcreteClass::resolve()`:

1. Loads or reuses interface->concrete mappings.
2. Applies active strategy priority rule set from `AfrToConcreteStrategiesClass`.
3. Selects one candidate and returns encoded decision (`1|...`, `2|...`, or `0|...`).
4. Supports temporary context/priority overrides per call.

---

## 5) Relationship with `AfrClassDependency`

`AfrClassDependency` is fundamental for candidate validation and dependency metadata.

In practice it helps this component by enabling checks such as:
- symbol kind (`class`, `interface`, `trait`, `enum`, etc.)
- instantiability/singleton heuristics
- dependency relations used when matching implementations
- skip rules and scanner control for performance/noise reduction

This means interface-to-concrete selection quality depends on both:
- class discovery coverage (Composer + configured paths), and
- dependency scanning rules (skip classes/namespaces and cache lifecycle).

---

## 6) Strategy engine (priority-based resolution)

`AfrToConcreteStrategiesClass` defines built-in strategy names and priority rules (e.g. `neverFail`, `fail`, `onlyClosureFn`).

### 6.1 Built-in strategy groups

- **Closure/custom hooks**: user-provided filtering/selection.
- **Context-bound strategies**: resolve by context name or request URI patterns.
- **Declared/namespace heuristics**: narrow/choose from discovered candidate sets.
- **Fallback strategies**: first-found (with or without warning), shuffle, or fail.

### 6.2 Customization model

- Add custom strategy via `addStrategy(name, callable)`.
- Compose ordered rule chains via `addPriorityRules(ruleName, [strategies...])`.
- Activate a chain via `setPriorityRule(ruleName)`.
- Optionally set runtime context via `setContext(contextName)`.

This is the core mechanism for resolving “multiple implementations for one interface” without hardcoded container bindings.

---

## 7) Test-derived use cases (real scenarios)

The following patterns are extracted from the PHPUnit suites in:
- `Tests/Unit/InterfaceToConcrete/AfrToConcreteStrategiesClassTest.php`
- `Tests/Unit/InterfaceToConcrete/B_AfrInterfaceToConcreteClassTest.php`

### 7.1 `AfrToConcreteStrategiesClass`: closure-based filtering

```php
<?php

use Autoframe\Core\InterfaceToConcrete\AfrToConcreteStrategiesClass;
use Autoframe\Core\InterfaceToConcrete\AfrToConcreteStrategiesInterface;

$obj = AfrToConcreteStrategiesClass::getLatestInstance();
$obj->addPriorityRules('closureOnly', [AfrToConcreteStrategiesClass::StrategyClosureFn]);
$obj->setPriorityRule('closureOnly');

$obj->extendStrategyClosureFn(function (AfrToConcreteStrategiesInterface $ctx, array $map) {
    if ($ctx->getNotConcreteFQCN() === 'extendClosureFn') {
        unset($map['clFake1']);
        return $map;
    }
    return [];
});

$result = $obj->resolveMap(['clFake1' => true, 'clFake2' => true], 'extendClosureFn');
// expected: "1|clFake2"
```

### 7.2 `AfrToConcreteStrategiesClass`: context-bound explicit mapping

```php
<?php

use Autoframe\Core\InterfaceToConcrete\AfrToConcreteStrategiesClass;

$obj = AfrToConcreteStrategiesClass::getLatestInstance();
$obj->addPriorityRules('ctxBound', [AfrToConcreteStrategiesClass::StrategyContextBound]);
$obj->setPriorityRule('ctxBound');

$obj->extendStrategyContextBound('X\NotConcrete', 'X\ConcreteDefault', '');
$obj->extendStrategyContextBound('X\NotConcrete', 'X\ConcreteForCtx1', 'Context1');

$obj->setContext('Context1');
$r1 = $obj->resolveMap(['X\\ConcreteForCtx1' => true, 'X\\Other' => true], 'X\NotConcrete');
// expected: "1|X\\ConcreteForCtx1"

$obj->setContext('');
$r2 = $obj->resolveMap(['X\\ConcreteDefault' => true, 'X\\Other' => true], 'X\NotConcrete');
// expected: "1|X\\ConcreteDefault"
```

### 7.3 `AfrToConcreteStrategiesClass`: URI regex + context selection

```php
<?php

use Autoframe\Core\InterfaceToConcrete\AfrToConcreteStrategiesClass;

$_SERVER['REQUEST_URI'] = '/PHPUnit/XxX/Event/';

$obj = AfrToConcreteStrategiesClass::getLatestInstance();
$obj->addPriorityRules('uriRegex', [AfrToConcreteStrategiesClass::StrategyContextHttpRequestUriRegex])
    ->setPriorityRule('uriRegex');

$obj->extendStrategyContextHttpRequestUriRegex('NotConcreteRgx', 'ConcreteBlank', '@PHPUnit.{1,}Event@', '');
$obj->extendStrategyContextHttpRequestUriRegex('NotConcreteRgx', 'ConcreteCtx', '@PHPUnit.{1,}Event@', 'customContextRegex');

$obj->setContext('customContextRegex');
$result = $obj->resolveMap(['ConcreteBlank' => true, 'ConcreteCtx' => true], 'NotConcreteRgx');
// expected: "1|ConcreteCtx"
```

### 7.4 `AfrToConcreteStrategiesClass`: namespace filter per context

```php
<?php

use Autoframe\Core\InterfaceToConcrete\AfrToConcreteStrategiesClass;

$obj = AfrToConcreteStrategiesClass::getLatestInstance();
$obj->addPriorityRules('nsFilter', [AfrToConcreteStrategiesClass::StrategyContextNamespaceFilterArr])
    ->setPriorityRule('nsFilter');

$obj->extendStrategyStrategyContextNamespaceFilterArr('ns1', '');
$obj->extendStrategyStrategyContextNamespaceFilterArr('ns2', 'customContextNs');

$obj->setContext('customContextNs');
$result = $obj->resolveMap([
    'ns2\\ConcreteForContext' => true,
    'nsOther\\Other' => true,
], 'nsx\\NotConcreteNs', false);
// expected: "1|ns2\\ConcreteForContext"
```

### 7.5 `AfrInterfaceToConcreteClass`: env/settings/path-driven map build + resolve

```php
<?php

use Autoframe\Core\ClassDependency\AfrClassDependency;
use Autoframe\Core\CliTools\AfrSysTempDir;
use Autoframe\Core\InterfaceToConcrete\AfrInterfaceToConcreteClass;
use Autoframe\Core\InterfaceToConcrete\AfrInterfaceToConcreteInterface;
use Autoframe\Core\InterfaceToConcrete\AfrMultiClassMapper;

AfrClassDependency::flush();

$settings = [
    AfrMultiClassMapper::CacheExpireSeconds => 60 * 30,
    AfrMultiClassMapper::ForceRegenerateAllButVendor => false,
    AfrMultiClassMapper::SilenceErrors => false,
    AfrMultiClassMapper::DumpPhpFilePathAndMtime => true,
    AfrMultiClassMapper::ClassDependencySetSkipClassInfo => [],
    AfrMultiClassMapper::ClassDependencySetSkipNamespaceInfo => [],
    AfrMultiClassMapper::RegexExcludeFqcnsAndPaths => ['@src.{1,}Exception@', '@PHPUnit.{1,}Telemetry@'],
    AfrMultiClassMapper::CacheDir => (ini_get('sys_temp_dir') ?: AfrSysTempDir::sysGetTempDir()) . '/cache_B_AfrInterfaceToConcreteClassTest',
];

$mapper = new AfrInterfaceToConcreteClass($settings, [__DIR__]);
$map = $mapper->getClassInterfaceToConcrete();
$result = $mapper->resolve(AfrInterfaceToConcreteInterface::class, false);
// expected in test: "1|" . AfrInterfaceToConcreteClass::class
```

---

## 8) Container integration perspective

Although this component is not itself a container, it can serve container implementations by:

1. precomputing interface->candidate maps,
2. resolving one concrete class through strategies,
3. returning an encoded resolution decision that container logic can interpret.

This allows `AfrContainerInterface` implementations to rely on deterministic auto-resolution policies instead of requiring every interface binding to be manually declared in advance.

---

## 9) Public API summary

### 9.1 `AfrInterfaceToConcreteInterface`

| Method | Purpose |
|---|---|
| `getClassInterfaceToConcrete(?string $filter)` | Build or fetch mappings, optionally for one FQCN. |
| `getLatestInstance()` | Access latest orchestrator instance. |
| `getSettings(?string $type)` | Read component settings/profile values. |
| `getPaths()` | Return effective scan/wiring paths. |
| `getAfrToConcreteStrategies()` / `setAfrToConcreteStrategies()` | Read/replace strategy engine implementation. |
| `resolve(...)` | Resolve not-concrete FQCN to a concrete result code string. |

### 9.2 `AfrToConcreteStrategiesInterface`

| Method family | Purpose |
|---|---|
| Context methods (`setContext/getContext`) | Scope resolution decisions. |
| Extenders (`extendStrategy...`, `addStrategy`) | Add/override matching logic. |
| Priority controls (`setPriorityRule`, `addPriorityRules`) | Determine strategy execution order. |
| Resolution methods (`resolveInterfaceToConcrete`, `resolveMap`) | Produce final encoded concrete decision. |

---

## 10) Decision encoding contract

Resolution methods use string-encoded outcomes:

- `1|Concrete\\ClassName` -> choose normal instantiable class
- `2|Concrete\\ClassName` -> choose singleton-style class
- `0|NotConcrete\\TypeName` -> unresolved/fail path

When integrating with container code, parse this prefix before construction.

---

## 11) Composer/vendor scanning notes

`AfrVendorPath` is used to discover:
- vendor root path,
- composer.json metadata,
- autoload classmap/PSR-4/PSR-0 maps,
- install timestamp hints for cache invalidation.

This provides the broad symbol inventory used by `AfrMultiClassMapper` and, transitively, by `AfrClassDependency`-based analysis.

---

## 12) Contribution checklist

Before modifying `src/InterfaceToConcrete/*`:

- [ ] Update strategy/rule docs if constants or defaults changed.
- [ ] Update decision encoding section if resolve return contract changes.
- [ ] Document any new scanner inputs/filters/caching behavior.
- [ ] Keep `AfrClassDependency` interaction notes synchronized.
- [ ] Add/update runnable snippet for new integration behavior.
