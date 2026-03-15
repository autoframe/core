<?php
declare(strict_types=1);

namespace Autoframe\Core\DesignPatterns\Facade;

use RuntimeException;

trait AfrFacadeTrait
{
    protected static ?AfrFacadeResolverInterface $facadeResolver = null;

    /** @var array<string, object> */
    protected static array $facadeResolvedInstances = [];

    public static function setFacadeResolver(AfrFacadeResolverInterface $resolver): void
    {
        static::$facadeResolver = $resolver;
    }

    public static function clearFacadeResolvedInstances(): void
    {
        static::$facadeResolvedInstances = [];
    }

    protected static function resolveFacadeRoot(): object
    {
        $accessor = static::getFacadeAccessor();

        if (isset(static::$facadeResolvedInstances[$accessor])) {
            return static::$facadeResolvedInstances[$accessor];
        }

        if (null === static::$facadeResolver) {
            throw new RuntimeException('Facade resolver was not configured.');
        }

        return static::$facadeResolvedInstances[$accessor] = static::$facadeResolver->resolve($accessor);
    }

    public static function __callStatic(string $method, array $arguments)
    {
        $instance = static::resolveFacadeRoot();

        return $instance->{$method}(...$arguments);
    }

    abstract protected static function getFacadeAccessor(): string;
}
