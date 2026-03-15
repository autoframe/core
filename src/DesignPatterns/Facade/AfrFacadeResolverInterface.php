<?php
declare(strict_types=1);

namespace Autoframe\Core\DesignPatterns\Facade;

interface AfrFacadeResolverInterface
{
    public function resolve(string $accessor): object;
}
