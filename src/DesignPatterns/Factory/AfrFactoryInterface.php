<?php
declare(strict_types=1);

namespace Autoframe\Core\DesignPatterns\Factory;

interface AfrFactoryInterface
{
    public function canMake(string $type): bool;

    public function make(string $type, array $arguments = []): object;
}
