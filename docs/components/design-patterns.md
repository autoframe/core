# Design Patterns (`Autoframe\\Core\\DesignPatterns`)

> **Purpose**: document reusable design-pattern utilities under `src/DesignPatterns/`, with emphasis on the framework-specific singleton behavior.

---

## 1) Component Snapshot

- **Namespace**: `Autoframe\\Core\\DesignPatterns`
- **Source root**: `src/DesignPatterns/`
- **Pattern families**:
  - Singleton
  - SingletonArray
  - Facade
  - Factory
  - Adapter
  - ArrayAccess object wrappers
  - Closure bind/call helpers
  - Tap pattern helpers

### 1.1 File map

| Module | Main files |
|---|---|
| `Singleton` | `AfrSingletonInterface`, `AfrSingletonTrait`, `AfrSingletonResolveTrait`, `AfrSingletonClassicTrait`, `AfrSingletonAbstractClass` |
| `SingletonArray` | `AfrSingletonArrAbstractClass` |
| `Facade` | `AfrFacadeResolverInterface`, `AfrFacadeInterface`, `AfrFacadeTrait`, `AfrFacadeAbstractClass` |
| `Factory` | `AfrFactoryInterface`, `AfrFactoryMapTrait`, `AfrFactoryAbstractClass` |
| `Adapter` | `AfrAdapterInterface`, `AfrAdapterForwardCallsTrait`, `AfrAdapterAbstractClass` |
| `ArrayAccess` | `AfrObjectArrayAccessClass`, `AfrObjectArrayAccessTrait` |
| `ClosureBind` | `AfrBindAndCallClosureInterface`, `AfrBindAndCallClosureTrait` |
| `Tap` | `Tap`, `HigherOrderTapProxy` |

---

## 2) AI-Friendly Index (Machine-Readable)

```yaml
doc_id: design-patterns
namespace: Autoframe\\Core\\DesignPatterns
source_dir: src/DesignPatterns
modules:
  - Singleton
  - SingletonArray
  - Facade
  - Factory
  - Adapter
  - ArrayAccess
  - ClosureBind
  - Tap
singleton_model:
  - container_enriched_singleton
  - classic_singleton_fallback
  - singleton_as_service
principles:
  - liskov_substitution_principle
  - dependency_inversion_principle
```

---

## 3) Singleton model in this framework (key ideas)

### 3.1 Container-enriched singleton convention

As a general framework convention, the singleton pattern is **enriched by the container** (`AfrLiteContainer` / `AfrContainerFacade`) that implements `AfrContainerInterface`.

Meaning:
- Calling `static::getInstance()` on classes implementing `AfrSingletonInterface` (or extending `AfrSingletonAbstractClass`) is container-aware.
- The returned instance can come from container resolution/bindings, not only from direct `new static()`.

### 3.2 Why this matters conceptually

This design opens broader behavior compared to classic singleton:

- container-driven substitutions,
- contextual service resolution,
- integration with broader DI and binding strategies.

In practice, this supports SOLID-oriented architecture:
- **LSP (Liskov Substitution Principle)**: substituted implementations remain method-compatible with expected singleton consumers.
- **DIP (Dependency Inversion Principle)**: high-level code depends on abstractions while container decides concrete realization.

### 3.3 API contract guarantees

When `getInstance()` returns a container-provided object, the framework expectation is that the resolved instance preserves expected public behavior for the static class contract.

### 3.4 Classic singleton escape hatch

When you need pure/classic singleton behavior without container intervention, call:

- `getInstanceNoContainerBindings()`

This explicitly requests an instance created from `static::class` itself.

### 3.5 “Singleton as Service” interpretation

A class implementing `AfrSingletonInterface` can be viewed as:

- **Singleton as Service**, or
- **SOLID Singleton**

because it can remain singleton-friendly while still participating in container-based abstraction and substitution.

---

## 4) Singleton APIs and roles

| Class/Trait/Interface | Role |
|---|---|
| `AfrSingletonInterface` | Singleton contract (`getInstance`, `getInstanceNoContainerBindings`, lifecycle safety). |
| `AfrSingletonTrait` | Core singleton implementation + container-aware instance resolution path. |
| `AfrSingletonResolveTrait` | Resolution helpers used by singleton trait for static/container/class checks. |
| `AfrSingletonClassicTrait` | Utility methods for more classic/static singleton resolution variants. |
| `AfrSingletonAbstractClass` | Base abstract class for easy singleton adoption by inheritance. |
| `AfrSingletonArrAbstractClass` | Singleton-like array-access-oriented abstract helper class. |

---

## 5) Other design-pattern modules

### 5.1 ArrayAccess module

`AfrObjectArrayAccessTrait` and `AfrObjectArrayAccessClass` provide:
- `ArrayAccess`, `Iterator`, and `Countable` style behaviors over internal object data,
- dynamic property access bridging (`__get`, `__set`, etc.).

### 5.2 ClosureBind module

`AfrBindAndCallClosureTrait` provides helpers to:
- bind closures to an instance context (`$this`),
- bind closures to static class scope (`static::class`).

### 5.3 Tap module

`Tap::tap(...)` and `HigherOrderTapProxy` provide tap-style fluent side-effect patterns:
- execute callback on value,
- keep fluent expression flow while returning proxied target.

### 5.4 Facade module

The facade helpers provide a reusable static entry point layer:
- `AfrFacadeResolverInterface` defines how facades resolve service objects.
- `AfrFacadeTrait` implements root resolution cache + `__callStatic` forwarding.
- `AfrFacadeAbstractClass` is a ready-to-extend base for concrete facades.

### 5.5 Factory module

The factory helpers provide map-based object creation:
- `AfrFactoryInterface` defines `canMake()` and `make()` contract.
- `AfrFactoryMapTrait` provides registration + type-map checks.
- `AfrFactoryAbstractClass` builds registered types with variadic constructor arguments.

### 5.6 Adapter module

The adapter helpers provide adaptee wrapping + call forwarding:
- `AfrAdapterInterface` exposes the wrapped adaptee.
- `AfrAdapterForwardCallsTrait` forwards method calls to adaptee.
- `AfrAdapterAbstractClass` is a base implementation for object adapters.

---

## 6) PHP examples

### 6.1 Container-aware singleton (`getInstance`)

```php
<?php

use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;

class MyService extends AfrSingletonAbstractClass
{
    public function hello(): string
    {
        return 'hello';
    }
}

$service = MyService::getInstance();
// can be container-provided according to framework bindings/resolution
```

### 6.2 Classic singleton fallback (`getInstanceNoContainerBindings`)

```php
<?php

$service = MyService::getInstanceNoContainerBindings();
// always attempts classic static::class singleton instance creation
```

### 6.3 Trait-based singleton for non-extendable hierarchy

```php
<?php

use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonInterface;
use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonTrait;

final class MyFinalService implements AfrSingletonInterface
{
    use AfrSingletonTrait;

    public function ping(): string
    {
        return 'pong';
    }
}

echo MyFinalService::getInstance()->ping();
```

### 6.4 Closure bind helper

```php
<?php

use Autoframe\Core\DesignPatterns\ClosureBind\AfrBindAndCallClosureTrait;

class Runner
{
    use AfrBindAndCallClosureTrait;

    private string $name = 'Runner';
}

$r = new Runner();
$result = $r->bindAndCallClosure(function () {
    return $this->name;
});
```

### 6.5 Tap helper

```php
<?php

use Autoframe\Core\DesignPatterns\Tap\Tap;

$object = (object)['count' => 0];
Tap::tap($object, function ($o) {
    $o->count++;
});
```

### 6.6 Generic facade helper

```php
<?php

use Autoframe\Core\DesignPatterns\Facade\AfrFacadeAbstractClass;
use Autoframe\Core\DesignPatterns\Facade\AfrFacadeResolverInterface;

final class ArrayResolver implements AfrFacadeResolverInterface
{
    /** @var array<string, object> */
    private array $services;

    /**
     * @param array<string, object> $services
     */
    public function __construct(array $services)
    {
        $this->services = $services;
    }

    public function resolve(string $accessor): object
    {
        return $this->services[$accessor];
    }
}

final class LoggerFacade extends AfrFacadeAbstractClass
{
    protected static function getFacadeAccessor(): string
    {
        return 'logger';
    }
}

LoggerFacade::setFacadeResolver(new ArrayResolver([
    'logger' => new class {
        public function info(string $message): string
        {
            return $message;
        }
    },
]));

echo LoggerFacade::info('hello');
```

### 6.7 Generic factory helper

```php
<?php

use Autoframe\Core\DesignPatterns\Factory\AfrFactoryAbstractClass;

final class NotificationFactory extends AfrFactoryAbstractClass
{
    public function __construct()
    {
        $this->setFactoryMap([
            'email' => EmailNotification::class,
            'sms' => SmsNotification::class,
        ]);
    }
}

$factory = new NotificationFactory();
$notification = $factory->make('email', ['subject', 'body']);
```

### 6.8 Generic adapter helper

```php
<?php

use Autoframe\Core\DesignPatterns\Adapter\AfrAdapterAbstractClass;

final class LegacyMailerAdapter extends AfrAdapterAbstractClass
{
    public function send(string $to, string $message): bool
    {
        return (bool) $this->forwardCallToAdaptee('dispatch', [$to, $message]);
    }
}
```

---

## 7) Integration notes

- Prefer `getInstance()` for framework-integrated singleton behavior.
- Prefer `getInstanceNoContainerBindings()` only when you explicitly want to bypass container substitution.
- If container bindings substitute singleton implementations, ensure compatibility of public API to honor substitution expectations.

---

## 8) Contribution checklist

Before changing `src/DesignPatterns/*`:

- [ ] Update singleton section if container-resolution behavior changes.
- [ ] Keep `getInstance` vs `getInstanceNoContainerBindings` semantics clearly documented.
- [ ] Add/update examples for any new helper trait or pattern class.
- [ ] Keep Facade resolver and static call-forwarding behavior documented.
- [ ] Keep Factory type-map registration and exception behavior documented.
- [ ] Keep Adapter forwarding approach and adaptee contract documented.
- [ ] Document behavioral impacts on SOLID/substitution assumptions.
