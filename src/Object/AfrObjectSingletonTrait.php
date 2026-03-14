<?php
declare(strict_types=1);


namespace Autoframe\Core\Object;

use Autoframe\Core\Exception\AfrException;
use ReflectionClass;
use ReflectionException;
use function func_get_args;

trait AfrObjectSingletonTrait
{
    //https://refactoring.guru/design-patterns/singleton/php/example#example-1
    /**
     * The actual singleton's instance almost always resides inside a static
     * field. In this case, the static field is an array, where each subclass of
     * the Singleton stores its own instance.
     */
    private static array $instances = [];

    /**
     * Singleton's constructor should not be public. However, it can't be
     * private either if we want to allow subclassing.
     */
    protected function __construct()
    {
    }

    /**
     * Cloning and unserialization are not permitted for singletons.
     * @throws AfrException
     */
    protected function __clone()
    {
        throw new AfrException('Cannot clone a singleton');
    }

    /**
     * Restore the instance after unserialization.
     * @throws AfrException
     */
    public function __wakeup()
    {
        throw new AfrException("Cannot unserialize singleton");
    }


    /**
     * The method you use to get the Singleton's instance.
     * @return self
     */
    public static function getInstance(): object
    {
        $subclass = static::class;
        if (!self::hasInstance()) {
            // Note that here we use the "static" keyword instead of the actual
            // class name. In this context, the "static" keyword means "the name
            // of the current class". That detail is important because when the
            // method is called on the subclass, we want an instance of that
            // subclass to be created here.
            return self::renewInstance(...func_get_args());
//            return self::$instances[$subclass] = new static();
            // TODO adaugat aici apply afr config daca este instanta de interfata sau trait sau clasa configurabila
        }
        return self::$instances[$subclass];
    }

    /**
     * The method you use to get a new clean Singleton's instance.
     * @return object
     * @throws ReflectionException
     */
    public static function renewInstance(): object
    {
        $arguments = func_get_args();
        if (!empty($arguments)) {
            return self::renewInstanceArrayOfArgs($arguments);
        } else {
            return self::$instances[static::class] = new static();
        }
    }

    /**
     * The method you use to get a new Singleton's instance from an array of arguments.
     * @param array $arguments
     * @return self
     * @throws ReflectionException
     */
    public static function renewInstanceArrayOfArgs(array $arguments): object
    {
        $subclass = static::class;
        if(empty($arguments)){
            return self::renewInstance();
        }
        return self::$instances[$subclass] =
            (new ReflectionClass($subclass))->newInstanceArgs($arguments);
    }

    /**
     * Has instance.
     * @return bool
     */
    public static function hasInstance(): bool
    {
        return isset(self::$instances[static::class]);
    }

}
