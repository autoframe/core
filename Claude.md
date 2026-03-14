# Autoframe is a PHP 7.4 - 8.x framework that implements the SOLID principles and much more

## Project Context & Documentation

For comprehensive framework documentation and specifications, refer to:

### Architecture & System Design
- @docs/architecture/module-box-specification.md - Complete Module Box system specification and design patterns
- @docs/architecture/app-overview.md - Application overview, deployment, and performance optimization tips

### Core Components Documentation
Components are stand-alone classes and interfaces that solve specific problems independently.
- @docs/components/arr-utilities.md - Array manipulation utilities
- @docs/components/class-dependency.md - Class dependency analysis tools
- @docs/components/cli-tools.md - CLI utilities and text formatting
- @docs/components/design-patterns-tap.md - Tap design pattern implementation
- @docs/components/env-management.md - Environment and configuration management
- @docs/components/exception-handling.md - Exception handling framework
- @docs/components/file-mime.md - MIME type detection and management
- @docs/components/file-system.md - File system operations
- @docs/components/ftp-transfer.md - FTP transfer utilities
- @docs/components/git-exec-hook.md - Git execution hooks
- @docs/components/interface-to-concrete.md - Interface to concrete implementation resolution
- @docs/components/lightquery-js.md - LightQuery JavaScript library documentation
- @docs/components/process-control.md - Process control and management
- @docs/components/socket-cache.md - Socket-based caching layer

### Database & ORM Documentation
- @docs/database/orm-requirements.md - ORM implementation requirements and roadmap

---

- The framework can be installed and configured with demo / sample config files using bash to the file "afr-deploy"
  - composer exec afr-deploy
  -
- The framework is designed to host multiple tenants, each having its own environment and configuration.
- The framework will execute inside a base directory, where it will detect the right tenant, load the constants, environment and other settings, dispatch the correct route and do something.
- The framework also handles CLI requests
- The framework will be delivered as a composer package to the end users.
- The framework is in not released and under development
- In the current local dev that runs under windows, http requests can be tested on http://localhost:808/core/
- The preferred database type is MariaDB / MySql or SqLite
- The resulting php code is primarily designed for using WHM / Cpanel hosting environments.
- Locally we will have an unorganized file system to start with.
- The framework is missing documentation files as Mark Down and is incomplete.
- The goal is to continue with the development of missing components.
- The SOLID principles are recommended as an implementation way.
- The framework booting is done using Afr::makeApp(__DIR__.'/path...') from the entry point file.
- The framework should be truly modular and should have minimum dependencies.

- The php unit tests can be run on Windows like: C:\xampp\php\php.exe C:/xampp/htdocs/core/vendor/phpunit/phpunit/phpunit --configuration C:\xampp\htdocs\core\phpunit.xml --teamcity
- Before running the php tests the php unit result cache file must be cleared: core/.phpunit.result.cache
- When creating a new function or class method, always add a one-sentence description for what it does.
## AfrCoreModules - New Module Architecture

The framework uses a modern module system under `Autoframe\Core\AfrCoreModules` (replaces deprecated `Autoframe\Core\Module` namespace).

### Key Components

**Module Box Pattern:**
- `Afr::app()->box()` returns `AfrModuleBoxInterface` instance
- Used to resolve functionality groups via: `Afr::app()->box()->resolveFunctionalityGroup(ContractClass::class)`
- Returns array of functionality implementations for a given contract interface

**Functionality Contracts:**
- Located in `src/AfrCoreModules/FnContracts/`
- Each contract implements a specific functionality (e.g., `AfrCronJobSourcesContract`)
- **Must include `__invoke()` magic method** to allow direct invocation instead of calling methods
- Pattern: `foreach ($aOFunctionalities as $oFunctionality) $oFunctionality();` // calls __invoke()

**Reusable Helpers (Traits):**
- Located in `src/AfrCoreModules/Reusable/`
- `AfrCronJobSourcesHelper` - manages cron job source registration
- `AfrLoadConfigHelper` - handles configuration loading
- Use `use TraitName;` in classes that need the functionality

### Implementation Example: AfrConJobSources

**File:** `src/Cron/AfrConJobSources.php`

**Method:** `registerCronJobSourcesFromModules()`
- Uses `Afr::app()->box()->resolveFunctionalityGroup(AfrCronJobSourcesContract::class)` to get all cron sources
- Loops through each functionality group and calls `__invoke()` to register sources
- Uses `AfrCronJobSourcesHelper` trait for registration logic

**Pattern for Similar Functionality:**
This pattern can be replicated for other contracts (HTTP routes, middleware, etc.):
```php
$aOFunctionalities = Afr::app()->box()->resolveFunctionalityGroup(YourContract::class);
foreach ($aOFunctionalities as $oFunctionality) {
    $oFunctionality(); // Calls __invoke() which handles registration
}
```

### Related Files
- `src/AfrCoreModules/FnContracts/AfrCronJobSourcesContract.php` - Contract with __invoke() method
- `src/AfrCoreModules/Reusable/AfrCronJobSourcesHelper.php` - Trait with registration logic
- `src/AfrCoreModules/Reusable/AfrLoadConfigHelper.php` - Configuration helper trait