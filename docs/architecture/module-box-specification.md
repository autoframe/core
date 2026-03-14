# Modular Box -- Conceptual Specification (PHP 7.4)

1.  Purpose and Scope

AfrModuleBoxClass is the central module manager for the Autoframe
ecosystem. It is responsible for:

-   Loading and wiring modules and their functionalities.
-   Allowing modules to be reused, personalized, extended, and replaced.
-   Cooperating with a DI container while adding module-specific
    semantics such as "replace", "extend", "disable", and "exclude
    functionality".
-   Doing all of this lazily and efficiently at runtime.

This specification targets PHP 7.4 and focuses on runtime behavior and
configuration semantics, not on routing or cross-project reuse concerns.

2.  Core Class and Namespace Rules

The module manager class is named "AfrModuleBoxClass" and uses the
namespace "Autoframe\\Core\\ModuleBox".

AfrModuleBoxClass is implemented as a singleton:

-   AfrModuleBoxClass extends
    "Autoframe\\Core\\DesignPatterns\\Singleton\\AfrSingletonAbstractClass".
-   AfrSingletonAbstractClass implements
    "Autoframe\\Core\\DesignPatterns\\Singleton\\AfrSingletonInterface".

All classes, interfaces, and traits related to the Module Box should:

-   Belong to the namespace "Autoframe\\Core\\ModuleBox".
-   Be prefixed with "Afr".
-   Be suffixed with "Class", "Interface", or "Trait", according to
    their type.

3.  Dependency Injection Integration

The Module Box integrates with a DI container exposed via:

-   Autoframe\\Core\\Afr\\Afr::app()-\>container()-\>get(\$sFQCN);

The DI container is responsible for resolving interfaces and concrete
fully qualified class names (FQCN) based on bindings from abstract types
(interfaces) to concrete implementations.

AfrModuleBoxClass has its own internal mechanism for resolving modules
and functionalities based on configuration and module manifests. If the
internal mechanism cannot resolve a requested functionality or module,
AfrModuleBoxClass falls back to:

-   Afr::app()-\>container()-\>get(\$sFQCN);

4.  Resolution Model and Disabled Modules

AfrModuleBoxClass resolves modules and functionalities using:

-   Interface FQCNs.
-   Concrete class FQCNs.
-   Optional context parameters (for example environment, tenant, or
    feature flags).
-   Optional fallback FQCNs when resolving interfaces.

The Module Box also provides debug and logging facilities related to
module loading, configuration merging, extension, replacement, and
resolution.

AfrModuleBoxClass instantiates module objects without eagerly
instantiating all nested functionalities. Functionalities are
instantiated lazily on first access.

The system uses a lazy per-module and per-functionality configuration
strategy:

-   Configuration is loaded only for modules and functionalities that
    are actually needed.
-   Only relevant module configurations are merged with the application
    configuration.

A module can be marked as "disabled" in the application configuration.
When a module is disabled:

-   It cannot be resolved directly via Module Box (unless it is replaced
    by another module).
-   Its functionalities are not visible to Module Box resolution.
-   Its default configuration is normally omitted from the global
    configuration merge.
-   Exception: if an enabled module extends this disabled module, the
    disabled module's configuration may still be loaded as an internal
    base for the extender; the base module itself remains non-resolvable
    through Module Box.
-   The DI container may still instantiate a disabled module if some
    other part of the application directly requests its FQCN. The
    "disabled" flag affects only Module Box wiring, not the entire
    application.

5.  Module Box Resolving Logic -- Priority and Order

AfrModuleBoxClass follows this conceptual resolution pipeline:

0.  Load the application-level configuration.
1.  Lazily load configuration only for modules and functionalities that
    are needed for the current resolution request. Exclude configuration
    from modules that are disabled and not used as bases for extenders.
2.  Process all module replacement and extension relationships based on
    the configuration loaded in step 1.
3.  Merge the processed module-level configuration from step 2 with the
    application-level configuration from step 0.
4.  Use the internal Module Box resolver against the result of step 3 to
    resolve the requested module or functionality.
5.  If the Module Box cannot resolve the requested functionality or
    module, it falls back to Afr::app()-\>container()-\>get().

Module-level "disabled" is considered before functionality-level
"excluded functionality". A disabled module is never returned directly
by Module Box, even if particular functionalities are not explicitly
excluded.

6.  Modules -- Concept and Structure

A module is a reusable unit that groups functionalities. Conceptually:

-   A module is a collection of functionalities that can be reused
    between PHP projects.
-   A module is composed of multiple files (PHP classes, configuration,
    manifests, assets) and is not a monolith.
-   A module exposes one or more functionalities, typically as
    properties of the main module class or through Module Box
    resolution.
-   Modules are driven by interfaces and concrete class implementations.
    All public functionality classes exposed through Module Box must
    implement one or more interfaces.
-   Functionalities are resolved lazily at runtime via Module Box.
-   The application can also resolve configuration or routing-related
    concerns via the same Module Box mechanism.
-   The main module class is never declared as "final" and is designed
    to be extendable.
-   Functionalities can perform on-demand actions such as sending
    emails, generating images, or registering routes.
-   A module can interact with other modules or external interfaces,
    traits, or classes and is not limited to its own directory.
-   A module can be published as a Composer package and included in
    other projects via composer.json.
-   A module can be application-specific but should be structured to
    permit reuse.

Installation and autoloading of modules are handled by Composer. Runtime
wiring---deciding which module implements which interfaces, and which
modules replace or extend others---is handled by AfrModuleBoxClass and
the DI container configuration.

7.  Common Module Interface and Trait

All modules implement a common module interface. This interface provides
at least the following methods:

-   registerModule()
-   getModuleFQCN(): string
-   getModuleNameSpace(): string
-   getModuleDirPath(): string
-   getModuleClassFilePath(): string
-   getModuleName(): string

A PHP trait provides a default concrete implementation for the common
module interface, so module authors can minimize boilerplate.

8.  Exposing and Resolving Functionalities from Modules

AfrModuleBoxClass exposes module functionalities with an API that:

-   Accepts either an interface FQCN (Interface::class) or a concrete
    class FQCN.
-   Optionally accepts context parameters or fallback class names.
-   Resolves the request according to configuration, including replace,
    extend, excluded functionality, and disabled modules.

Further constraints:

-   For each functionality, all public methods must be covered by one or
    more interfaces.
-   Modules can contain functionalities that produce or serve assets
    such as JPEG, CSS, or JS files. Those assets are typically exposed
    via HTTP routes, with the routing layer calling into Module Box to
    obtain the appropriate functionality.
-   A module defines a public method that resolves all functionality
    interfaces it provides.
-   A module defines a public method that allows a custom configuration
    file or overrides to be applied.
-   A module can have one or more distinct configurations, each
    resulting in a distinct module instance or variant.

9.  Naming Conventions and Design Principles

For Module Box-related code:

-   Namespace: Autoframe\\Core\\ModuleBox.
-   Class/Interface/Trait naming: prefixed with "Afr" and suffixed with
    "Class", "Interface", or "Trait".
-   Singleton instances extend AfrSingletonAbstractClass and thus
    implement AfrSingletonInterface.

Code in this subsystem adheres to SOLID principles:

-   Classes are open for extension and closed for modification; no
    "final" classes in Module Box-related code.
-   Protected members are preferred over private members where extension
    is expected.
-   Methods should remain short and focused; complex logic should be
    decomposed into smaller methods.

10. Configuration, Extension, Replacement and Excluded Functionalities

10.1 Configuration Manifest Files

Each module provides one or more configuration or manifest files:

-   Configuration manifests are PHP files that return an array when
    included.
-   These files are stored as PHP to take advantage of PHP OPcache.
-   Modules and functionalities can be personalized or overridden by the
    application configuration.

10.2 Default Configuration and Disabled Modules

A module always has a default configuration that can be loaded when
needed by Module Box.

If a module is disabled in the application configuration:

-   Its default configuration is not merged into the global
    configuration for direct module use.
-   However, if another module extends this disabled base module, the
    disabled module's configuration may be loaded and used as a merge
    base for the extender.
-   The disabled base module itself remains non-resolvable via Module
    Box.

10.3 Global Configuration Merge

Inside AfrModuleBoxClass:

-   The application-level configuration is recursively merged with the
    default settings of all modules that are not disabled.
-   For disabled modules that are used as bases for extenders, their
    configuration may be loaded solely for internal merge operations
    with their extenders; they are not treated as active modules.
-   After the merge, any functionality flagged as "excluded
    functionality" in the final configuration is removed from the
    resolved configuration and is not available to Module Box
    resolution.

Configuration can also control which interfaces are advertised as
available functionalities.

10.4 Replace and Extend -- General Rules

A module can replace or extend another module.

-   Replace: the replacing module takes over the role of the base module
    in Module Box resolution. Resolving the base module via Module Box
    yields the replacer.
-   Extend: the extender module's configuration and behavior are derived
    from the base module's configuration, with additions and overrides.

Rules and constraints:

-   For a given base module, there may be at most one replacer module.
    If more than one module declares that it replaces the same base
    module, this is a configuration error and should be treated as
    invalid.

-   For a given base module, there may be zero, one, or multiple
    extenders.

-   When both a replacement and one or more extenders exist for the same
    base module:

    -   Resolving the base module via Module Box returns the replacer
        (not the base and not any extender).
    -   Extenders still use the original base module configuration as
        their merge base, not the replacer's configuration.

Extenders of the same base module are processed in deterministic
configuration order. When their configuration settings conflict, later
extenders override earlier extenders.

10.5 Replacement -- Detailed Semantics

When the configuration states that ModuleB replaces ModuleA:

-   ModuleB does not automatically inherit functionalities or
    configuration from ModuleA. Replacement is a resolution-level alias,
    not inheritance.
-   When ModuleA is requested via Module Box, the result is an instance
    of ModuleB (or ModuleB's functionality), according to wiring.
-   Only instances of ModuleB are generated by Module Box when resolving
    ModuleA. ModuleA is not instantiated by Module Box for that
    resolution.
-   These rules apply even if ModuleA is disabled: resolving ModuleA via
    Module Box still yields ModuleB if the replacement relationship is
    defined.

10.6 Extension -- Detailed Semantics

When ModuleD extends ModuleC:

-   Both ModuleC and ModuleD can be independently resolved via Module
    Box, and instances of both can exist simultaneously, as long as
    ModuleC is not disabled.
-   ModuleD's effective configuration is formed by merging ModuleC's
    configuration with ModuleD's own configuration.
-   ModuleD can add new functionalities, interfaces, concrete
    implementations, and configuration entries compared to ModuleC.
-   ModuleD can override or replace configuration originally defined in
    ModuleC, including using the same interface but a different
    implementation.
-   ModuleD can mark inherited functionalities as "excluded
    functionality" in its own configuration, effectively removing them
    from ModuleD's view.
-   If ModuleC is disabled but extended by ModuleD, ModuleD may still
    use ModuleC's configuration as its merge base. ModuleC itself
    remains non-resolvable via Module Box.

10.7 Disabled + Replaced + Extended

If a base module is simultaneously disabled, replaced, and extended:

-   The disabled flag prevents direct resolution of the base module.
-   Replacement rules dictate resolution: resolving the base module's
    FQCN via Module Box returns the replacer module, not the base and
    not the extenders.
-   Extension rules still use the original base module configuration as
    the merge base for extenders. Extenders do not merge against the
    replacer's configuration.
-   The base module's configuration can still be loaded internally as a
    base for extenders, even though the base module itself is disabled
    and replaced.

10.8 Excluded Functionalities

"Excluded functionality" is a configuration directive at the
functionality level:

-   After the configuration merge (step 3 of the pipeline), any
    functionality marked as "excluded functionality" is removed from the
    final configuration and is not available to Module Box resolution.
-   For a module where a functionality is excluded, Module Box behaves
    as though that functionality does not exist in that module.
-   When resolving a functionality, any module where that functionality
    is excluded is ignored for that functionality.
-   If, after applying exclusions, no module provides a given
    functionality, Module Box cannot resolve it and falls back to the DI
    container (step 5).
-   Exclusion affects only Module Box wiring. If the DI container has an
    independent binding for the same interface, that binding remains
    available to the container.

11. Functionality -- Definition and Resolution

A functionality is a unit of behavior provided by a module. It has the
following properties:

-   A functionality is defined and characterized by one or more
    interfaces.
-   A functionality always implements all interfaces that characterize
    it.
-   Each functionality has a default configuration/manifest PHP file
    within each module that implements it.
-   A functionality instance is implemented by one or more concrete
    classes.
-   A functionality implementation can be a singleton or a class with a
    public constructor; this is specified in configuration.
-   A functionality instance typically appears as an object property
    inside a module instance or is obtained via Module Box resolution.

Modules can nest multiple functionalities under a single module
instance.

A functionality can have multiple distinct configurations, with each
configuration producing a distinct functionality instance. Depending on
configuration, a functionality may be treated as singleton or
multi-instance.

When multiple modules implement the same functionality interface and
none of them extend or replace each other:

-   All implementations can be returned as an array of instances. A
    typical example is multiple modules each contributing different cron
    jobs for the same "cron job" interface.
-   For semantically "singleton" functionalities where a single
    implementation is desired, additional parameters (such as a concrete
    FQCN or explicit priority rules) may be needed so Module Box can
    select the appropriate implementation.

Context or priority rules may be used to determine which implementation
is chosen when a single instance is required.

Functionalities are treated as object properties inside module instances
and implement granular interfaces to maximize reusability.

12. Performance Considerations

Performance and scalability are primary concerns of AfrModuleBoxClass:

-   Module configuration manifests are stored as PHP files returning
    arrays and are accessed via include(). This allows OPcache to
    optimize loading.
-   Each module should have at least one configuration/manifest PHP
    file.
-   Configuration files are loaded on demand (lazy loading) and merged
    only when needed, minimizing memory usage and startup time.
-   The Module Box is designed to handle hundreds of modules without
    sacrificing performance.

When using OPcache:

-   PHP-based configuration manifests are preferred over .env files for
    module configuration to reduce overhead and to benefit fully from
    OPcache.
-   It is recommended to centralize and cache information about which
    module implements which interfaces, to avoid repeated discovery.
-   Caching may apply to steps 0--3 of the resolution pipeline
    (application configuration loading, module configuration loading,
    and merging), when it is safe and beneficial.

For temporary directory usage, the system uses:

-   Autoframe\\Core\\CliTools\\AfrSysTempDir::sysGetTempDir()

instead of the native sys_get_temp_dir(), so that temporary directory
behavior can be customized at framework level.

**Actionable Steps**

1.  Replace your existing prose with the refactored specification above
    as the "source of truth".

2.  When you implement AfrModuleBoxClass, follow the explicit rules for:

    -   Disabled + replaced + extended modules.
    -   Single replacer per base module (configuration error otherwise).
    -   Multiple extenders merged in configuration order.
    -   Functionality exclusion and fallback to the DI container.

3.  As you start coding, if you add new behaviors (e.g., priority
    attributes, error-handling strategies), you can extend this text by
    adding small, focused sections without changing the core semantics.
