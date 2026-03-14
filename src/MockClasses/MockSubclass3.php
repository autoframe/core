<?php

namespace Autoframe\Core\MockClasses;

class MockSubclass3 implements MockSubclass3Interface
{
    public int $iVal;
    /**
     * Create a new instance.
     */
    public function __construct()
    {
        echo __CLASS__.'->'.__FUNCTION__.PHP_EOL;
        $this->iVal = time();
    }

    /**
     * Get ival.
     * @return int
     */
    public function getIVal(): int
    {
        return $this->iVal;
    }

}
