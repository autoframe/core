<?php
declare(strict_types=1);


namespace Autoframe\Core\Object;


use Autoframe\Core\Exception\AfrException;
use Autoframe\Core\String\AfrStr;

use function constant;
use function defined;
use function strpos;


abstract class AfrObjectAbstractConstructorSingletonFactory
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

    abstract protected function customConstruct();

    /**
     * @throws AfrException
     */
    final function __construct(string $sClassName = '', string $sNamespace = '', bool $forceClassReinitialisation = false)
    {
        // assign only once on the first run the static::$sClassName
        if ($this->getClassName() && !$forceClassReinitialisation) {
            return $this;
        }

        //extract namespace from full class name
        if ($sClassName && strpos($sClassName, '\\') !== false) {
            $sNamespace = AfrStr::getClassNamespace($sClassName);
            $sClassName = AfrStr::getClassBasename($sClassName);
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
        if ($sNamespace || !$this->getNamespace()) {
            $this->setNamespace($sNamespace);
        }

        if (!$this->getNamespace()) {
            $this->setNamespace(static::$sDefaultNamespace);
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

        if ($sClassName) {
            $this->setClassName($sClassName);
        }
        if (!$this->getClassName()) {
            $this->setClassName(static::$sDefaultClassName);
        }
        $this->customConstruct();
    return $this;
    }


    /**
     * Get instance.
     */
    public function getInstance(): object
    {
        /** @var object $sClassName */
        $sClassName = $this->getClassName();
        $sNamespace = $this->getNamespace();

        $sNsClass = $sNamespace.'\\'.$sClassName;
        return $sNsClass::getInstance();
    }

    /**
     * @param string $sClassName
     * @return string
     * @throws AfrException
     */
    final public function setClassName(string $sClassName): string
    {
        if (!$sClassName) {
            throw new AfrException('Expected valid class name parameter for ' . __CLASS__ . '->setClassName');
        }

        return static::$sClassName = $sClassName;
    }

    final public function getClassName(): string
    {
        return static::$sClassName;
    }

    final public function getNamespace(): string
    {
        return static::$sNamespace;
    }

    final public function setNamespace(string $sNamespace): string
    {
        return static::$sNamespace = $sNamespace;
    }
}
