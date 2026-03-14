<?php

namespace Autoframe\Core\MockClasses;


class MockSubclass1
{
    protected MockSubclass2 $oMockSubclass2;
    /**
     * Create a new instance.
     */
    public function __construct(MockSubclass2 $oMockSubclass2)
    {
        echo __CLASS__.'->'.__FUNCTION__.PHP_EOL;
        $this->oMockSubclass2 = $oMockSubclass2;

    }
}
