<?php
declare(strict_types=1);

namespace Autoframe\Core\Date;

use Autoframe\Core\Config\AfrConfigurableInstanceInterface;
use Autoframe\Core\Config\AfrConfigurableInstanceTrait;

class AfrDate implements AfrConfigurableInstanceInterface
{
    use AfrConfigurableInstanceTrait;

    /**
     * Create a new instance.
     * @param $a
     */
    public function __construct($a)
    {
        print_r($a);
    }

    public static $staticPropx;

    private function test($sX){
        echo "\n  \n\n  $sX\n\n  \n\n  \n";
    }
    private static function testStatic($sX){
        echo "\n  \n\n  $sX\n\n  \n\n  \n";
    }
}
