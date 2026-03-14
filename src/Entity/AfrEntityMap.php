<?php
declare(strict_types=1);

namespace Autoframe\Core\Entity;

use Exception;
use ReflectionClass;
use ReflectionProperty;

class AfrEntityMap
{
    private static array $objReflections = [];
    private static array $obj = [];
    private static array $objProps = [];
    private static string $private = 'private';
    private static string $protected = 'protected';
    private static string $public = 'public';

    /**
     * Get.
     * @param string $sEntityClassName
     * @return ReflectionClass
     */
    public static function get(string $sEntityClassName): ReflectionClass
    {
        self::checkInit($sEntityClassName);
        return self::$objReflections[$sEntityClassName];
    }

    /**
     * Get public.
     * @param string $sEntityClassName
     * @return array
     */
    public static function getPublic(string $sEntityClassName): array
    {
        self::checkInit($sEntityClassName);
        return self::$objProps[$sEntityClassName][self::$public];
    }

    /**
     * Get protected.
     * @param string $sEntityClassName
     * @return array
     */
    public static function getProtected(string $sEntityClassName): array
    {
        self::checkInit($sEntityClassName);
        return self::$objProps[$sEntityClassName][self::$protected];
    }

    /**
     * Get private.
     * @param string $sEntityClassName
     * @return array
     */
    public static function getPrivate(string $sEntityClassName): array
    {
        self::checkInit($sEntityClassName);
        return self::$objProps[$sEntityClassName][self::$private];
    }

    /**
     * Is public.
     * @param string $sEntityClassName
     * @param string $sPropertyName
     * @return bool
     */
    public static function isPublic(string $sEntityClassName, string $sPropertyName): bool
    {
        self::checkInit($sEntityClassName);
        return isset(self::$objProps[$sEntityClassName][self::$public][$sPropertyName]);
    }

    /**
     * Is protected.
     * @param string $sEntityClassName
     * @param string $sPropertyName
     * @return bool
     */
    public static function isProtected(string $sEntityClassName, string $sPropertyName): bool
    {
        self::checkInit($sEntityClassName);
        return isset(self::$objProps[$sEntityClassName][self::$protected][$sPropertyName]);
    }

    /**
     * Is private.
     * @param string $sEntityClassName
     * @param string $sPropertyName
     * @return bool
     */
    public static function isPrivate(string $sEntityClassName, string $sPropertyName): bool
    {
        self::checkInit($sEntityClassName);
        return isset(self::$objProps[$sEntityClassName][self::$private][$sPropertyName]);
    }


    /**
     * @param string $sEntityClassName
     * @return void
     */
    private static function set(string $sEntityClassName): void
    {
        try {
            $oRc = new ReflectionClass($sEntityClassName);
            self::$objReflections[$sEntityClassName] = $oRc;
            /** @var ReflectionProperty $oProp */
            foreach (self::$objReflections[$sEntityClassName]->getProperties() as $oProp) {
                $sPropName = $oProp->getName();
                $sVisibility = $oProp->isPrivate() ? self::$private : ($oProp->isProtected() ? self::$protected : self::$public);
                $aProps[$sVisibility][$sPropName] = $oProp;
            }
        } catch (Exception $e) {

        }
        foreach ([self::$private, self::$protected, self::$public] as $sVisibility) {
            self::$objProps[$sEntityClassName][$sVisibility] = !empty($aProps[$sVisibility]) ? $aProps[$sVisibility] : [];
        }

    }

    /**
     * Get default property value.
     * @param string $sEntityClassName
     * @param string $sProp
     * @return array
     */
    public static function getDefaultPropertyValue(string $sEntityClassName, string $sProp): array
    {
        self::checkInit($sEntityClassName);
        if (!isset(self::$obj[$sEntityClassName])) {
            self::$obj[$sEntityClassName] = new $sEntityClassName();
        }

        if(isset(self::$obj[$sEntityClassName]->$sProp)){
            return [self::$obj[$sEntityClassName]->$sProp, true];
        }
        return [null, false];
    }

    /**
     * @param string $sEntityClassName
     * @return void
     */
    private static function checkInit(string $sEntityClassName): void
    {
        if (!isset(self::$objProps[$sEntityClassName]) || !isset(self::$objReflections[$sEntityClassName])) {
            self::set($sEntityClassName);
        }
    }


}
