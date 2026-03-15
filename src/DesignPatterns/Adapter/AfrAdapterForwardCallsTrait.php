<?php
declare(strict_types=1);

namespace Autoframe\Core\DesignPatterns\Adapter;

trait AfrAdapterForwardCallsTrait
{
    abstract public function getAdaptee(): object;

    protected function forwardCallToAdaptee(string $method, array $arguments = [])
    {
        return $this->getAdaptee()->{$method}(...$arguments);
    }
}
