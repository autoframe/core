<?php
declare(strict_types=1);

namespace Autoframe\Core\DesignPatterns\Singleton;

use Autoframe\Core\Exception\AfrException;

/**
 * There are 2 methods to use the Singleton pattern:
 * - target class extends AfrSingletonAbstractClass or
 * - use AfrSingletonTrait if the target class is not extendable
 */
interface AfrSingletonInterface
{
    /**
     * Cloning and un-serialization are not permitted for singletons.
     * @throws AfrException
     */
    public function __clone();

    /**
     * Restore the instance after unserialization.
     * @throws AfrException
     */
    public function __wakeup();

    /**
     * The method you use to get the Singleton's instance, of the container binding
     * or instance of the static class.
     * If the container binds to another class then make sure that the bound class
     * will implement the original all the methods of the original class like an interface does it!
     * The safest way is for the bound class to extend the original class similar to an interface!
     * You can use this way a singleton class as a SOLID service provider :)
     * @return self
     */
    public static function getInstance();

	/**
	 * Always get a singleton instance of the static class
	 * @return self
	 */
    public static function getInstanceNoContainerBindings(): self;

    /**
     * Has instance.
     * @return bool
     */
    public static function hasInstance(): bool;
}
