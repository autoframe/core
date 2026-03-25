<?php
declare(strict_types=1);

namespace Autoframe\Core\Config;

use ReflectionMethod;
use ReflectionProperty;

trait AfrConfigurableStaticTrait
{
    private static bool $applyAfrStaticConfigExecuted = false;
    private static int $countAfrStaticConfiguredComponents = 0;

    /**
     * Apply afr static config.
     * @param bool $bForce
     * @return int
     */
    public static function applyAfrStaticConfig(bool $bForce = false): int
    {
        if (self::$applyAfrStaticConfigExecuted && !$bForce) {
            return self::$countAfrStaticConfiguredComponents;
        }
        $aConfigs = AfrConfigRegister::getInstance()->getStaticConfig(static::class);

        $iFoundInstanceComponents = 0;
        foreach ($aConfigs as $oConfig) {
            $aProps = $oConfig->getStaticProperties();
            if (!empty($aProps)) {
                $iFoundInstanceComponents++;
                foreach ($aProps as $sProperty => $mValue) {
                    if (
                        $oConfig->getPreventExistenceErrors() && (
                            !property_exists(static::class, $sProperty) ||
                            !(new ReflectionProperty(static::class, $sProperty))->isStatic()
                        )
                    ) {
                        continue;
                    }
                    self::$$sProperty = $mValue;
                }
            }

            $aMethods = $oConfig->getStaticMethods();
            if (!empty($aMethods)) {
                $iFoundInstanceComponents++;
                foreach ($aMethods as $aMethodAndArgs) {
                    if ($oConfig->getPreventExistenceErrors() && (
                            !method_exists(static::class, $aMethodAndArgs[0]) ||
                            !(new ReflectionMethod(static::class, $aMethodAndArgs[0]))->isStatic()
                        )
                    ) {
                        continue;
                    }
                    forward_static_call_array([static::class, $aMethodAndArgs[0]], $aMethodAndArgs[1]);
                }
            }
        }
        self::$countAfrStaticConfiguredComponents = $iFoundInstanceComponents;
        self::$applyAfrStaticConfigExecuted = true;

        return self::$countAfrStaticConfiguredComponents;
    }

}
