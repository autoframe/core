# Container (`Autoframe\\Core\\Container`)

> **Purpose**: document the dependency container classes under `src/Container/`, including object resolution, singleton handling, and interface-to-concrete integration.

---

## 1) Component Snapshot

- **Namespace**: `Autoframe\\Core\\Container`
- **Source root**: `src/Container/`
- **Default implementation**: `AfrLiteContainer`
- **Facade**: `AfrContainerFacade`
- **Contract**: `AfrContainerInterface`
- **Default bindings helper**: `AfrDefaultBindings`

### 1.1 File map

| File | Role |
|---|---|
| `AfrContainerInterface.php` | Core container contract (`get`, `make`, `bind`, `has`, `registerInstance`). |
| `AfrLiteContainer.php` | Concrete lightweight container implementation. |
| `AfrContainerFacade.php` | Static facade and swappable container class entrypoint. |
| `AfrDefaultBindings.php` | Default and tenant-aware binding bootstrap helpers. |
| `Exception/AfrContainerException.php` | Container exception type. |

---

## 2) AI-Friendly Index

```yaml
doc_id: container
namespace: Autoframe\\Core\\Container
source_dir: src/Container
default_container: AfrLiteContainer
contract: AfrContainerInterface
features:
  - reflection_based_constructor_injection
  - alias_callable_instance_bindings
  - shared_binding_support
  - static_getInstance_singleton_resolution
  - interface_resolution_via_AfrInterfaceToConcreteInterface
  - swappable_container_class_via_facade
integrates_with:
  - Autoframe\\Core\\InterfaceToConcrete\\AfrInterfaceToConcreteInterface
  - Autoframe\\Core\\InterfaceToConcrete\\AfrInterfaceToConcreteClass
  - Autoframe\\Core\\InterfaceToConcrete\\AfrToConcreteStrategiesClass
```

---

## 3) Resolution behavior in `AfrLiteContainer`

### 3.1 Core flow

When resolving (`get` / `make`), the container supports:

1. **Bound entries**
   - alias string -> forwards to aliased service
   - object instance -> returns as-is
   - callable/closure -> executes with container context
2. **Reflection-based constructor injection**
   - resolves typed class dependencies recursively
   - throws for missing type hints / union-type constructor params / invalid scalar-only params
3. **Shared binding behavior**
   - entries marked shared are registered as instance on first resolution

### 3.2 Singleton resolution via static `getInstance`

A key behavior:

- If a target class is not directly instantiable by reflection, and it exposes a static `getInstance()` method, the container resolves it as a singleton-like service.
- The resolved object is registered into container instances for reuse.

This enables direct support for classes using classic singleton patterns without requiring custom closures.

### 3.3 Interface resolution via interface-to-concrete strategy

Another key behavior:

- If the abstract target is an interface, and the container has a binding for `AfrInterfaceToConcreteInterface`, it delegates resolution to:
  `AfrInterfaceToConcreteInterface::resolve($interfaceFqcn, ...)`.
- That resolver returns encoded result like:
  - `1|ConcreteFQCN` => instantiate concrete class
  - `2|ConcreteFQCN` => call concrete `::getInstance()`
  - `0|InterfaceFQCN` => fail / not resolved

So this container can automatically resolve interfaces by using:
- `AfrInterfaceToConcreteClass` (implementation of `AfrInterfaceToConcreteInterface`), and
- `AfrToConcreteStrategiesClass` (priority strategy selector),
as documented in `docs/components/interface-to-concrete.md`.

---

## 4) API Summary

### 4.1 `AfrContainerInterface`

| Method | Purpose |
|---|---|
| `get(string $id)` | Retrieve bound entry or build service by id. |
| `make(string $abstract, array $parameters = [])` | Build object using bindings/reflection/strategy paths. |
| `has(string $abstract)` | Check if abstract id has a registered binding/entry. |
| `bind(string $abstract, $concrete, bool $shared = false)` | Register alias/callable/class mapping and optional shared behavior. |
| `registerInstance(string $abstract, object $instance)` | Register concrete object instance. |
| `getInstance()` | Get singleton container instance. |

### 4.2 `AfrContainerFacade`

- Provides static access (`get`, `make`, `bind`, etc.) delegated to the configured container class.
- Allows swapping container implementation via `xetContainerClass($fqcn)` as long as it implements `AfrContainerInterface`.

### 4.3 `AfrDefaultBindings`

- Provides default binding maps for common framework contracts.
- Can apply tenant-specific binding files.
- Supports binding declaration forms:
  - alias string
  - interface => concrete FQCN
  - `[abstract, concrete, sharedFlag]`
  - closure factory

---

## 5) Practical integration use cases

### 5.1 Resolve singleton-style classes automatically

```php
<?php

use Autoframe\Core\Container\AfrContainerFacade;

$container = AfrContainerFacade::getContainer();
$service = $container->make(App\Legacy\SingletonService::class);
// If SingletonService has static getInstance(), container can resolve and reuse it.
```

### 5.2 Resolve interfaces through interface-to-concrete pipeline

```php
<?php

use Autoframe\Core\Container\AfrContainerFacade;
use Autoframe\Core\InterfaceToConcrete\AfrInterfaceToConcreteClass;
use Autoframe\Core\InterfaceToConcrete\AfrInterfaceToConcreteInterface;

$container = AfrContainerFacade::getContainer();

// Register resolver implementation
$container->bind(AfrInterfaceToConcreteInterface::class, fn() => new AfrInterfaceToConcreteClass(), true);

// Later: ask for any interface; container delegates to resolver strategy
$mailer = $container->make(App\Contracts\MailerInterface::class);
```

### 5.3 Bind custom shared services

```php
<?php

use Autoframe\Core\Container\AfrContainerFacade;

$container = AfrContainerFacade::getContainer();
$container->bind(App\Contracts\Clock::class, App\Infra\SystemClock::class, true);

$clock1 = $container->get(App\Contracts\Clock::class);
$clock2 = $container->get(App\Contracts\Clock::class);
// same shared instance after first resolution
```

---

## 6) Error/edge behavior

| Scenario | Behavior |
|---|---|
| Reflection class missing | Throws `AfrContainerException` wrapping reflection failure. |
| Constructor parameter has no type | Throws `AfrContainerException`. |
| Constructor parameter uses union type | Throws `AfrContainerException`. |
| Interface requested but no resolver bound | Falls back to non-instantiable error. |
| Resolver returns `0|...` | Container throws non-instantiable resolution error. |

---

## 7) Relationship with `interface-to-concrete.md`

This container documentation is intentionally connected to:

- `docs/components/interface-to-concrete.md`

Use that page for deep details on:
- class scanning,
- priority strategies,
- contextual rules,
- and how concrete implementation is selected when multiple candidates exist.

---

## 8) Contribution checklist

Before changing `src/Container/*`:

- [ ] Update singleton-resolution notes if `getInstance()` behavior changes.
- [ ] Update interface-resolution notes if `AfrInterfaceToConcreteInterface` delegation changes.
- [ ] Sync error matrix with thrown exceptions.
- [ ] Add/update examples for new binding/resolution patterns.
