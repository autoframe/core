<?php
declare(strict_types=1);

namespace Autoframe\Core\Http\Cookie\Manager;

use Autoframe\Core\Object\AfrObjectSingletonAbstractClass;

class AfrHttpCookieManagerClass extends AfrObjectSingletonAbstractClass  implements AfrHttpCookieManagerInterface
{
    use AfrHttpCookieManagerTrait;
    /**
     * Get instance.
     * @return AfrHttpCookieManagerClass
     */
    public static function getInstance(): AfrHttpCookieManagerClass
    {
        return parent::getInstance();
    }
}
