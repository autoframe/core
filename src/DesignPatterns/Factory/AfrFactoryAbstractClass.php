<?php
declare(strict_types=1);

namespace Autoframe\Core\DesignPatterns\Factory;

abstract class AfrFactoryAbstractClass implements AfrFactoryInterface
{
    use AfrFactoryMapTrait;

    public function make(string $type, array $arguments = []): object
    {
        $className = $this->resolveFactoryClassName($type);

        return new $className(...$arguments);
    }
}
