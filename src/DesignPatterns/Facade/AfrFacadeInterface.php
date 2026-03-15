<?php
declare(strict_types=1);

namespace Autoframe\Core\DesignPatterns\Facade;

interface AfrFacadeInterface
{
    public static function setFacadeResolver(AfrFacadeResolverInterface $resolver): void;

    public static function clearFacadeResolvedInstances(): void;
}
