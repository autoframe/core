<?php
declare(strict_types=1);

namespace Autoframe\Core\ModuleBox;

use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;


/**
 * Core Module Box implementation
 *
 * - Manages registration of modules and their configs.
 * - Applies disabled / replace / extend / excluded functionality rules.
 * - Resolves modules and functionalities.
 *
 * Modular box approach abstract concept definitions and constraints:
 * The module manager class is called "AfrModuleBoxClass" and uses the namespace "Autoframe\Core\ModuleBox";
 * The module manager class will load on demand or registers on demand functionalities provided by modules;
 * Dependency injection (auto wiring) is made using the container get method as follows: "Autoframe\Core\Afr\Afr::app()->container()->get($sFQCN);";
 * The Container will resolve interfaces and concrete FQCN based on class names bindings from abstract to concrete;
 * The module box should provide a internal mechanism to resolve abstract bindings originated from modules config or manifest files;
 * If the module box provided internal mechanism fails to resolve a concrete implementation, then we fallback on "Autoframe\Core\Afr\Afr::app()->container()->get($sFQCN);";
 * It is recommended that the module box class is "AfrModuleBoxClass" implemented as singleton;
 * "AfrModuleBoxClass" is a singleton because "class AfrModuleBoxClass extends Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass";
 * The module resolving can be done using full qualified class names of interfaces or concrete classes;
 * The module resolving can include context parameters;
 * The module resolving can include fallback full qualified class names when providing interfaces as parameter;
 * The module resolving should provide both debug and log methods;
 * The Module Box must instantiate the module without instantiating all the nested functionalities, because functionalities will be instantiated on demand at first access;
 * Lazy per-module/functionality states that ModuleBox loads config only for the modules/functionality types that are actually needed, and may incrementally merge these with app config;
 * A disabled module cannot be resolved directly via Module Box (unless replaced), its functionality is not visible, and its default configuration is normally omitted except when the module is extended by another module, in which case its default config may still be used only as a base for the extender. DI container may still instantiate a disabled module if requested directly by FQCN elsewhere in the application;
 *
 * Module Box Resolving Logic → Priority and order:
 * 0. Load Application config.
 * 1. Load needed config only for modules/functionality, excluding any config originating from modules that are disabled and not extended
 * 2. Process all Module replacement/extend config loaded in step 1
 * 3. Merge Step 2. (processed Module config) with Step 0.
 * 4. Use Module box internal resolver against the result from step 3.
 * 5. If module box cannot resolve the functionality → fallback to Afr::app()->container()->get()
 *
 * Module-level disable (Step 0) will precede Functionality-level “Excluded functionality”(Step 3)
 *
 * MODULE BASIC DEFINITION:
 * A module is a collection of functionalities that can be reused between php projects;
 * A module has one or more functionalities, nested as object properties;
 * A modules is composed from many files and it is not a monolith;
 * A module can provide one or more functionalities;
 * A module provides one ore more standard, common and advanced functionalities;
 * The module functionalities must be easily swapped or extended;
 * A module is driven by interfaces and concrete class implementation;
 * All public functionality classes (classes exposed through ModuleBox) must implement one or more interfaces.
 * In general, functionalities are resolved lazily at runtime via ModuleBox.
 * The application may also resolve certain configuration or routing functionalities using the same ModuleBox mechanism.
 * A module class is never a "final class", and it can always be extended by another php class;
 * A module is made up from one or more different functionalities, that are accessible on demand using Module Box.
 * The functionalities may do on demand actions like sending emails, generating pictures, register routes, etc;
 * It is recommended to keep all module classes, traits, interfaces, manifest or configuration files, or other files;
 * A module can interact with other modules or external interfaces, traits or classes and it is not limited inside it's own directory;
 * Each module provides interfaces that can be extended and reused by other modules;
 * A module can be published to packagist.org, once a composer.json file is defined;
 * A module can be included as a composer library;
 * A module can be specific to the current application, but build in such a way to allow reusability between other applications;
 * Installation and autoload dependencies between modules are handled by Composer (composer.json).
 * Runtime wiring (which module implements which interfaces, replaces or extends others) is handled by the Module Box configuration and the DI container.
 *
 * There should be a "common module interface" implemented by all modules;
 * The "common module interface" will provide the next methods: registerModule(); getModuleFQCN(): string; getModuleNameSpace(): string; getModuleDirPath(): string; getModuleClassFilePath(): string; getModuleName(): string;;
 * There should be a php trait that provides concrete implementation for the "common module interface";
 * The module box will provide a method that requires an interface::class parameter or a concrete class name, in order to access a functionality implemented by a module;
 * A module can contain functionalities that provide files like jpeg, css, js...;
 * When a module contains media files like jpeg, css or js, the files will be served using http routes;
 * Depending on the application implementation, a application specific routing class will call the ModuleBox that resolves just in time any specific routing functionalities;
 * In the case of a functionality, all public methods must be covered by one or more interfaces;
 * A module has a public method to resolve all the functionality interfaces;
 * A module has a public method that allows a custom configuration file to be applied;
 * A module can have one or more distinct configurations, resulting in a distinct module instance for each configuration;
 *
 * File and class naming conventions regarding modules, functionalities or the module box class:
 * All classes related to the module box, interfaces and traits should be a part of the namespace "Autoframe\Core\ModuleBox";
 * All classes, interfaces and traits should be be prefixed by "Afr";
 * All classes, interfaces and traits should be be suffixed by the php file content type: "Class", "Interface", "Trait";
 * Singleton instances will extend the abstract class: "Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass";
 * abstract class AfrSingletonAbstractClass implements Autoframe\Core\DesignPatterns\Singleton\AfrSingletonInterface;
 * The php code mandatory respects SOLID principles;
 * We are designing the Autoframe ecosystem for high extensibility (as your rule states: no final classes, open for extension, strong modularity), then using protected is a consistent architectural decision—even though it is not a SOLID rule.
 * The php code should avoid long or complicated methods;
 *
 * CONCEPT LIST REGARDING MODULE FUNCTIONALITIES CONFIGURATION EXTENSION,MERGING,REPLACING,EXCLUDING FUNCTIONALITIES:
 * A module will provide a default configuration that can be loaded by the Module Box when needed. If the module is disabled in the app config, its default configuration is normally omitted, except when it is used internally as a base by a module that extends it;
 * A module extender extends the original base module config not the replacement module config in the case a module is both extended and replaced by other modules.
 * The configuration manifest will be stored as a php file, so we can use PHP OPcache;
 * The configuration files will return an array, when included with php function include();
 * Modules and functionalities can be personalized from the current application configuration;
 * Inside the Module Box, the application configuration file is recursively merged only with the default settings of modules that are not disabled, but merge can occur with configuration of disabled modules when this configuration is used as base for extension in the case of the extender module.
 * After merging, any functionality marked as "Excluded functionality" in the final configuration will have its settings removed and will not be resolvable.
 * Based on the configuration files, certain interfaces can list as available functionalities or not list as available;
 * A module can be both replaced and extended at the same time in the final configuration. If such a situation occurs, the Module Box will prioritize module replace over module extend. Replacement represents complete override and therefore has higher precedence than extension, which is additive. Replacement has priority over the extender when resolving the base module itself. Extension still functions as a configuration merge mechanism;
 * A module extender extends the original base module config not the replacement module config in the case a module is both extended and replaced by other modules.
 *
 * A module can REPLACE or EXTEND another module, meaning all the functionalities and configuration files will be replaced or extended;
 * When a module replaces another module, all the functionalities and configuration files will be replaced;
 * When a module extends another module, all the functionalities and configuration files will be merged;
 *
 * When the configuration of "Module B" states that "Module B" FQCN replaces "Module A" FQCN, we know for a fact that "Module B" has replaced "Module A" for the purpose of resolving "Module A" using the Module Box
 * When "Module A" is replaced by "Module B", then "Module B" will not inherit anything(functionalities or configurations or anything else) from "Module A"
 * When "Module A" is replaced by "Module B", and we are resolving "Module A" inside the module box class, then we will get a instance of "Module B";
 * When "Module A" is replaced by "Module B", only instances of "Module B" can be generated using the module box class;
 * Even if "Module A" is disabled, any attempt to resolve "Module A" via Module Box will still return "Module B", because "Module B" replaces "Module A";
 *
 * When "Module D" extends "Module C", both "Module D" and "Module C" can be resolved independently. "Module C" and "Module D" can be resolved via Module Box and have instances of "Module C" and "Module D" in the same time is true only when "Module C" is not disabled;
 * When "Module D" extends "Module C", all the functionalities and configuration from "Module C" will be reflected in "Module D" configurations;
 * When "Module D" extends "Module C", "Module D" can add new functionalities,interfaces,concrete implementations and configuration files compared to "Module C";
 * When "Module D" extends "Module C", "Module D" can overwrite or replace default configuration php files located into "Module C";
 * When "Module D" extends "Module C", "Module D" can overwrite or replace functionalities that have been implemented using same interface in "Module C";
 * When "Module D" extends "Module C", "Module D" can exclude functionalities from "Module C", given a configuration file inside "Module D";
 * When "Module D" extends "Module C" and "Module C" is disabled, then "Module D" may still use "Module C" config as base;
 *
 * When "Module G" is disabled and "Module H" replaces "Module G", then in "Step 1" we will ignore any configuration originating from "Module G" and use the relevant configuration from "Module H";
 * When "Module E" is disabled and "Module F" extends "Module E", then in "Step 1" we will lazy-load the relevant default configuration of "Module E" and merge into it the relevant configuration from "Module F"
 * When "Module F" extends "Module E" and "Module E" is disabled, then "Module F" may still use "Module E" config as base;
 * When "Module E" is disabled, any attempt to resolve "Module E" will ask the Module Box to search for a replacement module, else fallback to "Step 5"
 *
 * If a module is simultaneously disabled, replaced, and extended, extension rules still use the original base module’s configuration as their merge base, while replacement rules control how the base module itself is resolved. The disabled state only prevents direct resolution of the base module; it does not prevent its configuration from being used as an internal base by extenders.
 *
 * What are the module functionalities:
 * A functionality is always defined and characterized by one or more interfaces;
 * A functionality always implements all the interfaces that define or characterizes it;
 * A functionality has a default php configuration/manifest file inside each module's directory;
 * A functionality instance is implemented by one or more concrete classes that provide methods to perform the required behavior.
 * A functionality can be singleton class or a class with a public constructor, and this aspect will be reflected into the configuration files;
 * A functionality instance will be nested as a object property inside each module;
 * A module can nest more than one functionality under the module instance;
 * A functionality can have one or more distinct configurations, resulting in a distinct functionality instance for each configuration;
 * A functionality can be a singleton or it can have multiple instances, depending on the configuration;
 * When multiple modules implement the same functionality and none of the modules extend or replace each other, then all the functionalities will be returned as an array of instances. As a conceptual example, imagine few modules that nest instances of the same functionality(interface) therefore defining distinct cron jobs;
 * When multiple modules implement the same functionality and none of the modules extend or replace each other, then in order to resolve the functionality correctly inside the module box class in the case of singleton functionality configuration, we can provide another parameter that contains the FQCN of the class;
 * Context or Priority rules may be used when resolving implementation order inside the module box class;
 * Functionalities live as object properties inside a module instance.
 * The functionalities will implement interfaces having granular methods, in order to maximize reusability;
 * Any settings or references to an excluded functionality must be removed immediately after the Step 3 merge, so that they never reach Step 4 (Module Box internal resolving).
 * A functionality that is “Excluded functionality” inside a module actually means that we can act like this functionality does not exist inside this module.
 * When resolving a functionality, any module where that functionality is marked as “Excluded functionality” is ignored for that functionality. If, after applying exclusions, no module provides it, then we cannot resolve via ModuleBox and we fallback to Step 5 (Afr::app()->container()->get());
 * So “Excluded functionality” means: “don’t use module wiring for this functionality; if the container has a binding, still use it.”
 * “Excluded functionality” means the application has intentionally removed this functionality from the module where it was marked as excluded, but the same functionality may be available in another module;
 *
 * PERFORMANCE CONCEPTS:
 * A module should prioritize server performance and speed;
 * The module box will be able to resolve the desired functionalities from inside modules, based on fully qualified class or interface name;
 * The module php configuration files will be accessed using php include();
 * Each module should have one ore more configuration/manifest files included as php files that return an array;
 * The module php configuration files will preferably return an array that contains the configuration;
 * Performance is important, so we should have a way to centralize what module implements what interfaces;
 * The server will surly use PHP OPcache, so we encourage the configuration or manifest files to be saved as php file, that will be included using the "include" function and that will return a array of data.;
 * When using OPcache, we discourage the usage of .env files for module configuration, because the usage of more computing power, when compared to the native "include" and return [];;
 * In the case of a module, the manifest or configuration files will be included on demand, and merged with any other settings of the same type (interface or FQCN) from the local app / project;
 * A module can be located into the vendor directory or into the local app / project directory;
 * A module must have at least one predefined configuration file;
 * A module must facilitate the loading of the configuration file or files using the module box class;
 * The module configuration files will be loaded on demand and merged with the app / project configuration, regarding this module, or regarding any particular functionality;
 * The module box can handle hundreds of modules, without sacrificing performance;
 * If it makes sense and it is possible, among Steps 0 to 3 from "Module Box Resolving Logic" caching may be provided;
 * The system temporary directory function sys_get_temp_dir() is replaced by Autoframe\Core\CliTools\AfrSysTempDir::sysGetTempDir();
 *
 */
class AfrModuleBoxClass extends AfrSingletonAbstractClass implements AfrModuleBoxInterface
{
	use AfrMbhTrait;
}
