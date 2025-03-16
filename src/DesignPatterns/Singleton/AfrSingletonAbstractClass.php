<?php
declare(strict_types=1);

namespace Autoframe\Core\DesignPatterns\Singleton;

/**
 * There are 2 methods to use the Singleton pattern:
 * - child class extends AfrSingletonAbstractClass or
 * - or use AfrSingletonTrait and implement AfrSingletonInterface
 */
abstract class AfrSingletonAbstractClass implements AfrSingletonInterface
{
    use AfrSingletonTrait;
}