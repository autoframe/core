<?php
declare(strict_types=1);

namespace Autoframe\Core\DesignPatterns\Adapter;

interface AfrAdapterInterface
{
    public function getAdaptee(): object;
}
