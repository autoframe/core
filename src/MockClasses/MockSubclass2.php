<?php

namespace Autoframe\Core\MockClasses;

class MockSubclass2
{
    protected MockSubclass3Interface $oMockSubclass3;

    /**
     * Create a new instance.
     */
    public function __construct(MockSubclass3Interface $oMockSubclass3)
    {
        echo __CLASS__.'->'.__FUNCTION__.PHP_EOL;
        $this->oMockSubclass3 = $oMockSubclass3;
    }
}
