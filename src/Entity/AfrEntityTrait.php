<?php
declare(strict_types=1);

namespace Autoframe\Core\Entity;

use Autoframe\Core\Entity\Exception\AfrEntityException;

// https://www.php.net/manual/en/language.oop5.magic.php

trait AfrEntityTrait
{
    protected bool $_autoCastByPrefixNotation = true;
    protected bool $_dirty;
    protected array $_dirtyProperty;


    /**
     * Is public.
     * @param string $sProperty
     * @return bool
     */
    public function isPublic(string $sProperty): bool
    {
        //we can permit to add properties from outside without overwriting private or protected ones
        $sClass = get_class($this);
        return !AfrEntityMap::isPrivate($sClass, $sProperty) && !AfrEntityMap::isProtected($sClass, $sProperty);
    }

    /**
     * Get entity public vars.
     * @return array
     */
    public function getEntityPublicVars(): array
    {
        //fill default public initialized or uninitialized
        $aVars = array_fill_keys(array_keys(AfrEntityMap::getPublic(get_class($this))), null);
        foreach (get_object_vars($this) as $sProp => $mVal) {
            if ($this->isPublic($sProp)) {
                $aVars[$sProp] = $mVal;
            }
        }
        return $aVars;
    }

    /**
     * Set object properties from an associative array.
     * Ex: $aProperty = [
     *         'member_name_1' => 'value',
     *         'member_name_2' => array( 'value1', 'value2' )
     *     ];
     * @param $aProperty
     * @return int the number of matched properties
     * @throws AfrEntityException
     */
    public function setAssoc($aProperty): int
    {
        $i = 0;
        foreach ((array)$aProperty as $sProperty => $mValue) {
            if (is_integer($sProperty) || is_float($sProperty) || is_double($sProperty)) {
                $sProperty = '(' . gettype($sProperty) . ')' . (string)$sProperty;
            }
            $this->__set($sProperty, $mValue);
            $i += isset($this->$sProperty) ? 1 : 0;

        }
        return $i;
    }

    /**
     * Set an inaccessible property value.
     * @param string $sProperty
     * @param $mValue
     * @return void
     * @throws AfrEntityException
     */
    public function __set(string $sProperty, $mValue): void
    {
        if (!$this->isPublic($sProperty)) {
            throw new AfrEntityException('Protected or private members are not settable in class ' . get_class($this));
        }
        $this->castProperty($sProperty, $mValue);
    }


    /**
     * Determine if an inaccessible property is set.
     * @param string $sProperty
     * @return bool
     */
    public function __isset(string $sProperty)
    {
        return isset($this->$sProperty);
    }

    /**
     * Retrieve an inaccessible property value.
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        if (isset($this->$name) || property_exists($this, $name)) {
            return $this->$name;
        }
        return null;
    }

    /**
     * Return the string representation of the instance.
     * @return string
     */
    public function __toString(): string
    {
        return serialize($this);
    }

    /**
     * Get.
     * @param string $sProperty
     * @return mixed Entity value if exist or Null
     */
    public function get(string $sProperty)
    {
        if (isset($this->$sProperty) || property_exists($this, $sProperty)) {
            return $this->$sProperty;
        }
        return null;
    }

    /**
     * Get a property by reference.
     * @param string $sProperty
     * @return mixed Entity value if exist or Null
     */
    public function getReferenced(string $sProperty)
    {
        if (isset($this->$sProperty) || property_exists($this, $sProperty)) {
            $mData = &$this->$sProperty;
            return $mData;
        }
        return null;
    }

    /**
     * Is dirty.
     * @return bool
     */
    public function isDirty(): bool
    {
        return $this->_dirty;
    }

    /**
     * Not dirty.
     * @return void
     */
    public function notDirty(): void
    {
        $this->_dirty = false;
        $this->_dirtyProperty = [];
    }

    /**
     * Get dirty properties.
     * @return array
     */
    public function getDirtyProperties(): array
    {
        return $this->_dirtyProperty;
    }

    /**
     * It will be used when loading/creating a new entity from a database string record
     * @param string $sProperty
     * @param $mValue
     * @return void
     */
    public function castProperty(string $sProperty, $mValue): void
    {
        if ($this->_autoCastByPrefixNotation) {
            $mValue = $this->castToDataType($sProperty, $mValue);
        }
        if (!isset($this->$sProperty) || $this->$sProperty !== $mValue) {
            $this->$sProperty = $mValue;
            $this->_dirty = true;
            $this->_dirtyProperty[$sProperty] = true;
        }
    }

    /**
     * Cast to data type.
     * @param string $sProperty
     * @param $mValue
     * @return array|bool|float|int|string|object|resource|mixed
     */
    public function castToDataType(string $sProperty, $mValue)
    {
        $sDataType = substr($sProperty, 0, 1);
        if ($sDataType === 'i') { //integer
            if (PHP_INT_SIZE === 4) { // PHP_INT_SIZE will return 4 for x86 version
                $dProcess = (double)$mValue;
                $mValue = ($dProcess < -1 - PHP_INT_MAX || $dProcess > PHP_INT_MAX) ? $dProcess : (int)$mValue;
            } else {//PHP_INT_SIZE will return 8 for x64 version
                $mValue = (int)$mValue;
            }
        } elseif ($sDataType === 'd') {//double
            $mValue = (double)$mValue;
        } elseif ($sDataType === 'f') {//float
            $mValue = (float)$mValue;
        } elseif ($sDataType === 'b') { //boolean
            $mValue = (
                $mValue === '1' ||
                $mValue === 1 ||
                $mValue === 'yes' ||
                $mValue === 'Yes' ||
                !empty($mValue)
            );
        } elseif ($sDataType === 't') { //Y-m-d H:i:s
            $mValue = (string)$mValue;
        } elseif ($sDataType === 's') { //string
            $mValue = (string)$mValue;
        } elseif ($sDataType === 'a') { //array
            if (is_string($mValue)) {
                if (substr($mValue, 0, 2) === '{"' || substr($mValue, 0, 1) === '[') {
                    $aTest = @json_decode($mValue);
                    if (is_array($aTest)) {
                        return $aTest;
                    }
                }
                if (substr($mValue, 0, 2) === 'a:') {
                    $aTest = @unserialize($mValue);
                    if (is_array($aTest)) {
                        return $aTest;
                    }
                }

            }
            $mValue = (array)$mValue;
        } elseif ($sDataType === 'o') { //object
            if (is_string($mValue) && substr($mValue, 0, 2) === 'O:') {
                $oTest = @unserialize($mValue);
                if (is_object($oTest)) {
                    return (object)$oTest;
                }
            }
            $mValue = (object)$mValue;

        } elseif ($sDataType === 'r' || $sDataType === 'm') { //mixed | resource | resource (closed) as of PHP 7.2.0
        }
        return $mValue;
    }


    /**
     * Get default value.
     * @param string $sProperty
     * @return array|false|float|int|object|string|null
     */
    public function getDefaultValue(string $sProperty)
    {
        $aDefaultPropertyValue = AfrEntityMap::getDefaultPropertyValue(get_class($this), $sProperty);
        if ($aDefaultPropertyValue[1]) {
            return $aDefaultPropertyValue[0];
        } elseif (!$this->_autoCastByPrefixNotation) {
            return null;
        }
        $sDataType = substr($sProperty, 0, 1);
        $mValue = null;
        if ($sDataType === 'i') {
            $mValue = 0;
        } elseif ($sDataType === 'd') {//double
            $mValue = 0.0;
        } elseif ($sDataType === 'f') {//float
            $mValue = 0.0;
        } elseif ($sDataType === 'b') { //boolean
            $mValue = false;
        } elseif ($sDataType === 't') { //Y-m-d H:i:s
            $sDataSubType = substr($sProperty, 0, 2);
            if ($sDataSubType === 'th') {
                $mValue = '00:00:00';
            } elseif ($sDataSubType === 'ty') {
                $mValue = '0000-00-00';
            } else {
                $mValue = '0000-00-00 00:00:00';
            }
        } elseif ($sDataType === 's') { //string
            $mValue = '';
        } elseif ($sDataType === 'a') { //array
            $mValue = [];
        } elseif ($sDataType === 'o') { //object
            $mValue = (object)null;
        } elseif ($sDataType === 'r' || $sDataType === 'm') { //mixed | resource | resource (closed) as of PHP 7.2.0
        }
        return $mValue;
    }

    /**
     * Reset defaults.
     * @return int
     */
    public function resetDefaults(): int
    {
        $i = 0;
        foreach ($this->getEntityPublicVars() as $sProp => $mVal) {
            $i++;
            $this->__set($sProp, $this->getDefaultValue($sProp));
        }
        return $i;
    }

    /**
     * Copy public properties.
     * @param object $oSourceObject
     * @return bool
     * @throws AfrEntityException
     */
    public function copyPublicProperties(object $oSourceObject): bool
    {
        if (!is_object($oSourceObject)) {
            return false;
        }
        $aSourceMembers = [];
        $sClass = get_class($oSourceObject);
        foreach (get_object_vars($oSourceObject) as $sSourceProp => $mVar) {
            if (!AfrEntityMap::isPrivate($sClass, $sSourceProp) && !AfrEntityMap::isProtected($sClass, $sSourceProp)) {
                $aSourceMembers[] = $sSourceProp;
            }
        }
        $aSelfMembers = array_keys($this->getEntityPublicVars());
        if (!count($aSourceMembers) || !count($aSelfMembers)) {
            return false;
        }
        $i = 0;
        foreach ($aSourceMembers as $sSourceProp) {
            $i++;
            $this->__set($sSourceProp, $oSourceObject->$sSourceProp);
        }
        return (bool)$i;
    }

    /**
     * Cast for database.
     * @param bool $bOnlyDirty
     * @return array
     */
    public function castForDatabase(bool $bOnlyDirty = false): array
    {
        $aOut = [];
        foreach ($this->getEntityPublicVars() as $sProp => $mVal) {
            if ($bOnlyDirty && !isset($this->_dirtyProperty[$mVal])) {
                return $aOut;
            }
            $sDataType = gettype($mVal);
            if ($sDataType === 'boolean') {
                $mVal = $mVal ? '1' : '0';
            } elseif ($sDataType === 'object') {
                $mVal = serialize($mVal);
            } elseif ($sDataType === 'array') {
                $mVal = json_encode($mVal); //json has a lower size than serialize
            } elseif (
                $mVal === null ||
                $sDataType === 'integer' ||
                $sDataType === 'double' ||
                $sDataType === 'float' ||
                $sDataType === 'string'
            ) {
                $mVal = (string)$mVal;
            } else {
                $mVal = (string)$mVal; //resource | resource (closed) | unknown type
            }
            $aOut[$sProp] = $mVal;
        }
        return $aOut;
    }


}
