<?php
declare(strict_types=1);

namespace Autoframe\Core\DesignPatterns\Factory;

use InvalidArgumentException;

trait AfrFactoryMapTrait
{
    /** @var array<string, class-string> */
    protected array $factoryMap = [];

    /**
     * @param array<string, class-string> $map
     */
    public function setFactoryMap(array $map): void
    {
        $this->factoryMap = $map;
    }

    public function registerFactoryType(string $type, string $className): void
    {
        $this->factoryMap[$type] = $className;
    }

    public function canMake(string $type): bool
    {
        return isset($this->factoryMap[$type]);
    }

    protected function resolveFactoryClassName(string $type): string
    {
        if (!$this->canMake($type)) {
            throw new InvalidArgumentException(sprintf('Factory cannot build type: %s', $type));
        }

        return $this->factoryMap[$type];
    }
}
