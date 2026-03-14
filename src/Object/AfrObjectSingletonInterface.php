<?php
declare(strict_types=1);

namespace Autoframe\Core\Object;

use Autoframe\Core\Exception\AfrException;
use ReflectionException;

interface AfrObjectSingletonInterface
{
    /**
     * Restore the instance after unserialization.
     * @throws AfrException
     */
    public function __wakeup();

    /**
     * The method you use to get the Singleton's instance.
     * @return object
     */
    public static function getInstance(): object;

    /**
     * The method you use to get a new Singleton's instance.
     * @return object
     * @throws ReflectionException
     */
    public static function renewInstance(): object;

    /**
     * The method you use to get a new Singleton's instance from array arguments.
     * @param array $arguments
     * @return object
     * @throws ReflectionException
     */
    public static function renewInstanceArrayOfArgs(array $arguments): object;

    /**
     * Has instance.
     * @return bool
     */
    public static function hasInstance(): bool;
}
