<?php
declare(strict_types=1);

namespace Autoframe\Core\Object;


use Autoframe\Core\Exception\AfrException;
use function constant;
use function defined;


abstract class AfrObjectAbstractSingletonFactory
{
    /*    protected static string $sDefaultNamespace = __NAMESPACE__;
        const DEFAULT_NAMESPACE = 'AFR_SESSION_CLASS_DEFAULT_NAMESPACE';
        protected static string $sDefaultClassName = 'AfrSessionPhp';
        const DEFAULT_CLASS_NAME = 'AFR_SESSION_CLASS_DEFAULT_CLASS_NAME';*/

    protected static string $sDefaultNamespace = '';
    const DEFAULT_NAMESPACE = '';
    protected static string $sDefaultClassName = '';
    const DEFAULT_CLASS_NAME = '';

    protected static string $sClassName = '';
    protected static string $sNamespace = '';

    abstract static protected function customConstruct();

    /**
     * @throws AfrException
     */
    static private function factoryConstruct():bool
    {
        // assign only once on the first run the static::$sClassName
        if (static::getClassName()) {
            return true;
        }

        //load defined namespace constants as defaults
        if (
            static::DEFAULT_NAMESPACE &&
            defined(static::DEFAULT_NAMESPACE) &&
            constant(static::DEFAULT_NAMESPACE) &&
            constant(static::DEFAULT_NAMESPACE) !== static::$sDefaultNamespace
        ) {
            static::$sDefaultNamespace = constant(static::DEFAULT_NAMESPACE);
        }

        if (!static::getNamespace()) {
            static::setNamespace(static::$sDefaultNamespace);
        }

        //load defined class name constants as defaults
        if (
            static::DEFAULT_CLASS_NAME &&
            defined(static::DEFAULT_CLASS_NAME) &&
            constant(static::DEFAULT_CLASS_NAME) &&
            constant(static::DEFAULT_CLASS_NAME) !== static::$sDefaultClassName
        ) {
            static::$sDefaultClassName = constant(static::DEFAULT_CLASS_NAME);
        }

        if (!static::getClassName()) {
            static::setClassName(static::$sDefaultClassName);
        }
        if(!static::getClassName() && !static::getNamespace()){
            return false;
        }
        static::customConstruct();
        return true;
    }


	/**
	 * @throws AfrException
	 */
	public static function getInstance(): object
    {
        if(!self::factoryConstruct()){
            throw new AfrException('General exception for ' . __CLASS__ . '::factoryConstruct()');
        }
        /** @var object $sClassName */
        $sClassName = static::getClassName();
        $sNamespace = static::getNamespace();

        $sNsClass = $sNamespace . '\\' . $sClassName;
        return $sNsClass::getInstance();
    }

    /**
     * @param string $sClassName
     * @return string
     * @throws AfrException
     */
    final public static function setClassName(string $sClassName): string
    {
        if (!$sClassName) {
            throw new AfrException('Expected valid class name parameter for ' . __CLASS__ . '::setClassName');
        }

        return static::$sClassName = $sClassName;
    }

    final public static function getClassName(): string
    {
        return static::$sClassName;
    }

    final public static function getNamespace(): string
    {
        return static::$sNamespace;
    }

    final public static function setNamespace(string $sNamespace): string
    {
        return static::$sNamespace = $sNamespace;
    }
}