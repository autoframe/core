<?php
declare(strict_types=1);

namespace Autoframe\Core\DesignPatterns\Adapter;

abstract class AfrAdapterAbstractClass implements AfrAdapterInterface
{
    use AfrAdapterForwardCallsTrait;

    protected object $adaptee;

    public function __construct(object $adaptee)
    {
        $this->adaptee = $adaptee;
    }

    public function getAdaptee(): object
    {
        return $this->adaptee;
    }
}
