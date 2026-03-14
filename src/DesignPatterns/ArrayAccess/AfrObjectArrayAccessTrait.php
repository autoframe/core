<?php
declare(strict_types=1);

namespace Autoframe\Core\DesignPatterns\ArrayAccess;

/**
 * implements ArrayAccess, Iterator, Countable
 */
trait AfrObjectArrayAccessTrait
{
    protected array $aArrayAccessData = [];
    protected array $aArrayAccessKeys = [];      //We use a separate array of keys rather than $this->position directly so that we can
    protected int $iArrayAccessPointer = 0;      //have an associative array.

    protected function keyMaping(bool $bResetPointer): void
    {
        $this->iArrayAccessPointer = 0;
        $this->aArrayAccessKeys = array_keys($this->aArrayAccessData);
    }


    /**
     * Count.
     */
    public function count(): int
    { //This is necessary for the Countable interface. It could as easily return
        return count($this->aArrayAccessKeys);    //count($this->container). The number of elements will be the same.
    }

    /**
     * Rewind.
     */
    public function rewind(): void
    {  //Necessary for the Iterator interface. $this->position shows where we are in our list of
        $this->iArrayAccessPointer = 0;      //keys. Remember we want everything done via $this->keys to handle associative arrays.
    }

    /**
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    /**
     * Current.
     */
    public function current()
    { //Necessary for the Iterator interface.
        return $this->aArrayAccessData[$this->aArrayAccessKeys[$this->iArrayAccessPointer]];
    }

    /**
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    /**
     * Key.
     */
    public function key()
    { //Necessary for the Iterator interface.
        return $this->aArrayAccessKeys[$this->iArrayAccessPointer];
    }

    /**
     * Next.
     */
    public function next(): void
    { //Necessary for the Iterator interface.
        ++$this->iArrayAccessPointer;
    }

    /**
     * Valid.
     */
    public function valid(): bool
    { //Necessary for the Iterator interface.
        return isset($this->aArrayAccessKeys[$this->iArrayAccessPointer]);
    }

    /**
     * Offset set.
     */
    public function offsetSet($offset, $value): void
    { //Necessary for the ArrayAccess interface.
        if (is_null($offset)) {
            $this->aArrayAccessData[] = $value;
            $this->aArrayAccessKeys[] = array_key_last($this->aArrayAccessData); //THIS IS ONLY VALID FROM php 7.3 ONWARDS. See note below for alternative.
        } else {
            $this->aArrayAccessData[$offset] = $value;
            if (!in_array($offset, $this->aArrayAccessKeys)) $this->aArrayAccessKeys[] = $offset;
        }
    }

    /**
     * Offset exists.
     */
    public function offsetExists($offset): bool
    {
        return isset($this->aArrayAccessData[$offset]);
    }

    /**
     * Offset unset.
     */
    public function offsetUnset($offset): void
    {
        unset($this->aArrayAccessData[$offset]);
        $this->keyMaping(false);
        //This line re-indexes the array of container keys because if someone
    }

    /**
     * @param $offset
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function &offsetGet($offset)
    {
        return $this->aArrayAccessData[$offset];
    }

    public function &__get($key)
    {
        return $this->aArrayAccessData[$key];
    }

    /**
     * Set an inaccessible property value.
     */
    public function __set($key, $value)
    {
        $this->aArrayAccessData[$key] = $value;
    }

    /**
     * Determine if an inaccessible property is set.
     */
    public function __isset($key)
    {
        return isset($this->aArrayAccessData[$key]);
    }

    /**
     * Unset an inaccessible property.
     */
    public function __unset($key)
    {
        unset($this->aArrayAccessData[$key]);
    }
}
