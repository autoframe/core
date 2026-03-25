<?php
declare(strict_types=1);

namespace Autoframe\Core\Config;

use ReflectionMethod;
use ReflectionProperty;

trait AfrConfigurableInstanceTrait
{
    private bool $bAfrConfigurableInstanceExecuted = false;
    private int $iAfrConfigurableInstanceComponents = 0;
    private static bool $bAfrConfigurableInstanceExecutedStatic = false;
    private static int $iAfrConfigurableInstanceComponentsStatic = 0;


    /**
     * Apply afr instance config.
     * @param bool $bForce
     * @return int
     */
    public function applyAfrInstanceConfig(bool $bForce = false): int
    {
        if ($this->bAfrConfigurableInstanceExecuted && !$bForce) {
            return $this->iAfrConfigurableInstanceComponents;
        }
        self::applyAfrInstanceConfigStatic($bForce);

        $this->iAfrConfigurableInstanceComponents = 0;
        foreach (AfrConfigRegister::getInstance()->getConfig($this) as $oConfig) {
            $oConfig->defineConstants();
            $this->applyAfrInstanceConfigProperties($oConfig);
            $this->applyAfrInstanceConfigMethods($oConfig);
        }
        $this->bAfrConfigurableInstanceExecuted = true;

        return $this->iAfrConfigurableInstanceComponents;
    }


    /**
     * Apply afr instance config static.
     * @param bool $bForce
     * @return int
     */
    public static function applyAfrInstanceConfigStatic(bool $bForce = false): int
    {
        if (self::$bAfrConfigurableInstanceExecutedStatic && !$bForce) {
            return self::$iAfrConfigurableInstanceComponentsStatic;
        }

        self::$iAfrConfigurableInstanceComponentsStatic = 0;
        foreach (AfrConfigRegister::getInstance()->getStaticConfig(static::class) as $oConfig) {
            $oConfig->defineConstants();
            self::applyAfrInstanceConfigStaticProperties($oConfig);
            self::applyAfrInstanceConfigStaticMethods($oConfig);
        }
        self::$bAfrConfigurableInstanceExecutedStatic = true;

        return self::$iAfrConfigurableInstanceComponentsStatic;
    }

    /**
     * @param AfrConfig $oConfig
     * @return void
     */
    private static function applyAfrInstanceConfigStaticMethods(AfrConfig $oConfig): void
    {
        $aMethods = $oConfig->getStaticMethods();
        if (!empty($aMethods)) {
            self::$iAfrConfigurableInstanceComponentsStatic++;
            foreach ($aMethods as $aMethodAndArgs) {
                if ($oConfig->getPreventExistenceErrors() && (
                        !method_exists(static::class, $aMethodAndArgs[0]) ||
                        !((new ReflectionMethod(static::class, $aMethodAndArgs[0]))->isStatic())
                    )
                ) {
                    continue;
                }
                forward_static_call_array(
                    [static::class, $aMethodAndArgs[0]],
                    $aMethodAndArgs[1]
                );
            }
        }
    }

    /**
     * @param AfrConfig $oConfig
     * @return void
     */
    private static function applyAfrInstanceConfigStaticProperties(AfrConfig $oConfig): void
    {
        $aProps = $oConfig->getStaticProperties();
        if (!empty($aProps)) {
            self::$iAfrConfigurableInstanceComponentsStatic++;
            foreach ($aProps as $sProperty => $mValue) {
                if (
                    $oConfig->getPreventExistenceErrors() && (
                        !property_exists(static::class, $sProperty) ||
                        !((new ReflectionProperty(static::class, $sProperty))->isStatic())
                    )
                ) {
                    continue;
                }
                self::$$sProperty = $mValue;
            }
        }
    }

    /**
     * @param AfrConfig $oConfig
     * @return void
     */
    private function applyAfrInstanceConfigProperties(AfrConfig $oConfig): void
    {
        $aProps = $oConfig->getProperties();
        if (!empty($aProps)) {
            $this->iAfrConfigurableInstanceComponents++;
            foreach ($aProps as $sProperty => $mValue) {
                if (
                    $oConfig->getPreventExistenceErrors() &&
                    !property_exists($this, $sProperty)
                ) {
                    continue;
                }
                $this->$sProperty = $mValue;
            }
        }
    }

    /**
     * @param AfrConfig $oConfig
     * @return void
     */
    private function applyAfrInstanceConfigMethods(AfrConfig $oConfig): void
    {
        $aMethods = $oConfig->getMethods();
        if (!empty($aMethods)) {
            $this->iAfrConfigurableInstanceComponents++;
            foreach ($aMethods as $aMethodAndArgs) {
                if ($oConfig->getPreventExistenceErrors() && (
                        !method_exists($this, $aMethodAndArgs[0]) ||
                        (new ReflectionMethod($this, $aMethodAndArgs[0]))->isStatic()
                    )
                ) {
                    continue;
                }
                call_user_func_array([$this, $aMethodAndArgs[0]], $aMethodAndArgs[1]);
            }
        }
    }
}

