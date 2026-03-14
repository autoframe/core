<?php
declare(strict_types=1);


namespace Autoframe\Core\Object;

//use ArrayAccess;

/**
 * Trait AfrObjectAndArrayAccessTrait
 * @package Autoframe\Core\Object
 *
 * Class that uses trait should implement ArrayAccess
 * use ArrayAccess;
 * class AfrObjectAndArrayAccess implements ArrayAccess;
 *
 * https://www.php.net/manual/en/class.arrayaccess.php
 */
trait AfrObjectAndArrayAccessTrait
{
    private array $aData = [];

    /**
     * Get a data by key
     * @param string|int|float $key
     * @return mixed
     */
    public function &__get($key)
    {
        return $this->aData[$this->__castToKey($key)];
    }

    /**
     * Assigns a value to the specified data
     * @param string|int|float $key
     * @param $value
     * @return void
     */
    public function __set($key, $value)
    {
        $this->aData[$this->__castToKey($key)] = $value;
    }

    /**
     * @param string|int|float $key
     * @return string|int|float
     */
    private function __castToKey($key)
    {
        if(!is_string($key) || !is_numeric($key)){
            return (string)$key;
        }
        return $key;
    }

    /**
     * Whether a data exists by key
     * @param string|int|float $key
     * @return bool
     * @abstracting ArrayAccess
     */
    public function __isset($key): bool
    {
        return isset($this->aData[$this->__castToKey($key)]);
    }

    /**
     * Unsets a data by key
     * @param string|int|float $key
     */
    public function __unset($key)
    {
        unset($this->aData[$this->__castToKey($key)]);
    }

    /**
     * Whether an offset exists
     * @param string|int|float $offset
     * @abstracting ArrayAccess
     */
    public function offsetExists($offset): bool
    {
        return isset($this->aData[$offset]);
    }


    /**
     * Returns the value at specified offset
     *
     * @param string|int|float $offset
     * @return mixed
     * @abstracting ArrayAccess
     */
    #[\ReturnTypeWillChange]
    /**
     * Offset get.
     */
    public function offsetGet($offset)
    {
        return $this->offsetExists($offset) ? $this->aData[$offset] : null;
    }

    /**
     * Assigns a value to the specified offset
     * @param string $offset The offset to assign the value to
     * @param mixed $value The value to set
     * @abstracting ArrayAccess
     */
    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->aData[] = $value;
        } else {
            $this->aData[$this->__castToKey($offset)] = $value;
        }
    }



    /**
     * Unsets an offset
     *
     * @param string|int|float $offset
     * @access public
     * @abstracting ArrayAccess
     */
    public function offsetUnset($offset): void
    {
        if ($this->offsetExists($offset)) {
            unset($this->aData[$offset]);
        }
    }


}
