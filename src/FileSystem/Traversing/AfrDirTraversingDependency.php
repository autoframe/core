<?php
declare(strict_types=1);

namespace Autoframe\Core\FileSystem\Traversing;

use Autoframe\Core\FileSystem\DirPath\AfrDirPathClass;
use Autoframe\Core\FileSystem\DirPath\AfrDirPathInterface;

trait AfrDirTraversingDependency
{
    /** @var AfrDirPathInterface */
    protected static AfrDirPathInterface $AfrDirPathInstance;

    /**
     * Set afr dir path interface.
     * @param AfrDirPathInterface $AfrDirPathInstance
     * @return void
     */
    public function setAfrDirPathInterface(AfrDirPathInterface $AfrDirPathInstance): void
    {
        self::$AfrDirPathInstance = $AfrDirPathInstance;
    }

    /**
     * @return void
     */
    protected function checkAfrDirPathInstance():void
    {
        if (empty(self::$AfrDirPathInstance)) {
            self::$AfrDirPathInstance = AfrDirPathClass::getInstance();
        }
    }


}
