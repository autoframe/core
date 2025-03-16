<?php
declare(strict_types=1);

namespace Autoframe\Core\Config;
//TODO de instantiat clase la kil  => Voila Container

use ReflectionClass;
use ReflectionException;
use Autoframe\Core\Config\Exception\AfrConfigException;
use ReflectionMethod;

class AfrConfigFactory
{
    /**
     * @throws ReflectionException
     * @throws AfrConfigException
     */
    public static function makeInstanceFromNsClass(string $sNamespaceClass, bool $bForce = false)
    {
        if (!self::checkClassExists($sNamespaceClass)) {
            return false;
        }
        $aConstructorArgs = [];
        foreach (AfrConfigRegister::getInstance()->getStaticConfig($sNamespaceClass) as $oAfrConfig) {
            if ($oAfrConfig->getNamespaceAndClassOrTraitOrInterfaceOrKey() === $sNamespaceClass) {
                $aConstructorArgs = $oAfrConfig->getConstructorArgs();
                break;
            }
        }
        if (empty($aConstructorArgs)) {
            $oNewObject = new $sNamespaceClass();
        } else {
            $oNewObject = (new ReflectionClass($sNamespaceClass))->newInstanceArgs($aConstructorArgs);
        }
        if (
            $oNewObject instanceof AfrConfigurableInstanceInterface ||
            is_callable([$oNewObject, 'applyAfrInstanceConfig'], true)
        ) {
            $oNewObject->applyAfrInstanceConfig($bForce);
        } else {
            throw new AfrConfigException('Class ' . $sNamespaceClass . ' does not implement method: applyAfrInstanceConfig');
        }
        return $oNewObject;
    }

    /**
     * @param string $sNamespaceClass
     * @param bool $bForce
     * @return AfrConfigurableInstanceInterface|false|mixed
     * @throws AfrConfigException
     */
    public static function getConfiguredSingletonFromNsClass(string $sNamespaceClass, bool $bForce = false)
    {
        if (!self::checkClassExists($sNamespaceClass)) {
            return false;
        }
        if (
            !method_exists($sNamespaceClass, 'getInstance') ||
            !((new ReflectionMethod($sNamespaceClass, 'getInstance'))->isStatic())
        ) {
            throw new AfrConfigException('Class ' . $sNamespaceClass . ' does not implement static method: getInstance');
        }
        $oSingleton = forward_static_call_array(
            [$sNamespaceClass, 'getInstance'],
            [$bForce]
        );
        if (
            $oSingleton instanceof AfrConfigurableInstanceInterface ||
            is_callable([$oSingleton, 'applyAfrInstanceConfig'], true)
        ) {
            $oSingleton->applyAfrInstanceConfig($bForce);
        } else {
            throw new AfrConfigException('Class ' . $sNamespaceClass . ' does not implement method: applyAfrInstanceConfig');
        }
        return $oSingleton;
    }

    /**
     * @param string $sNamespaceClass
     * @param bool $bForce
     * @return bool|int
     * @throws AfrConfigException
     */
    public static function applyStaticConfigToNsClass(string $sNamespaceClass, bool $bForce = false)
    {
        if (!self::checkClassExists($sNamespaceClass)) {
            return false;
        }
        if (
            !method_exists($sNamespaceClass, 'applyAfrInstanceConfigStatic') ||
            !((new ReflectionMethod($sNamespaceClass, 'applyAfrInstanceConfigStatic'))->isStatic())
        ) {
            throw new AfrConfigException('Class ' . $sNamespaceClass . ' does not implement static method: applyAfrInstanceConfigStatic');
        }
        return forward_static_call_array(
            [$sNamespaceClass, 'applyAfrInstanceConfigStatic'],
            [$bForce]
        );
    }

    /**
     * @param string $sNamespaceClass
     * @return bool
     */
    private static function checkClassExists(string $sNamespaceClass): bool
    {
        return class_exists($sNamespaceClass);
    }
}