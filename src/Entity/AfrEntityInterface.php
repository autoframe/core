<?php

namespace Autoframe\Core\Entity;

use Autoframe\Core\Entity\Exception\AfrEntityException;

interface AfrEntityInterface
{
    /**
     * Create a new instance.
     * @param $mProperties
     */
    public function __construct($mProperties = []);

    /**
     * Is public.
     * @param string $sProperty
     * @return bool
     */
    public function isPublic(string $sProperty): bool;

    /**
     * Get entity public vars.
     * @return array
     */
    public function getEntityPublicVars(): array;

    /**
     * Set object properties from an associative array.
     * Ex: $aProperty = [
     *         'member_name_1' => 'value',
     *         'member_name_2' => array( 'value1', 'value2' )
     *     ];
     * @param $aProperty
     * @return int the number of matched properties
     */
    public function setAssoc($aProperty): int;

    /**
     * Set an inaccessible property value.
     * @param string $sProperty
     * @param $mValue
     * @return void
     * @throws AfrEntityException
     */
    public function __set(string $sProperty, $mValue): void;

    /**
     * Determine if an inaccessible property is set.
     * @param string $sProperty
     * @return bool
     */
    public function __isset(string $sProperty);

    /**
     * Retrieve an inaccessible property value.
     * @param $name
     * @return mixed
     */
    public function __get($name);

    /**
     * Return the string representation of the instance.
     * @return string
     */
    public function __toString(): string;

    /**
     * Get.
     * @param string $sProperty
     * @return mixed Entity value if exist or Null
     */
    public function get(string $sProperty);

    /**
     * Get a property by reference.
     * @param string $sProperty
     * @return mixed Entity value if exist or Null
     */
    public function getReferenced(string $sProperty);

    /**
     * Is dirty.
     * @return bool
     */
    public function isDirty(): bool;

    /**
     * Not dirty.
     * @return void
     */
    public function notDirty(): void;

    /**
     * Get dirty properties.
     * @return array
     */
    public function getDirtyProperties(): array;

    /**
     * It will be used when loading/creating a new entity from a database string record
     * @param string $sProperty
     * @param $mValue
     * @return void
     */
    public function castProperty(string $sProperty, $mValue): void;

    /**
     * Cast to data type.
     * @param string $sProperty
     * @param $mValue
     * @return array|bool|float|int|string|null|object|resource|mixed
     */
    public function castToDataType(string $sProperty, $mValue);

    /**
     * Get default value.
     * @param string $sProperty
     * @return array|false|float|int|object|string|null
     */
    public function getDefaultValue(string $sProperty);

    /**
     * Reset defaults.
     * @return int
     */
    public function resetDefaults(): int;

    /**
     * Copy public properties.
     * @param object $oSourceObject
     * @return bool
     */
    public function copyPublicProperties(object $oSourceObject): bool;

    /**
     * Cast for database.
     * @param bool $bOnlyDirty
     * @return array
     */
    public function castForDatabase(bool $bOnlyDirty = false): array;
}
