<?php
declare(strict_types=1);

namespace Autoframe\Core\Config;

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

        //TODO de verificat daca am si config static si sa il aplic primul daca trebuie


        $iFoundInstanceComponents = 0;
        foreach ($aConfigs as $oConfig) {
            $aProps = $oConfig->getStaticProperties();
            if (!empty($aProps)) {
                $iFoundInstanceComponents++;
                foreach ($aProps as $sProperty => $mValue) {
                    self::$$sProperty = $mValue;
                }
            }
            $aMethods = $oConfig->getStaticMethods();
            if (!empty($aMethods)) {
                $iFoundInstanceComponents++;
                foreach ($aMethods as $sMethod => $aArgs) {
                    forward_static_call_array([get_called_class(), $sMethod], $aArgs);
                    // [$this, $sMethod]($aArgs); //TODO TEST
                    /*if(empty($aArgs)){
                        self::${$sMethod}();
                    }
                    else{

                    }*/

                }
            }
        }
        self::$countAfrStaticConfiguredComponents = $iFoundInstanceComponents;
        self::$applyAfrStaticConfigExecuted = true;

        return self::$countAfrStaticConfiguredComponents;
    }

}
