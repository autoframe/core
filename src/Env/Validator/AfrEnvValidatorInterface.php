<?php

namespace Autoframe\Core\Env\Validator;

use Autoframe\Core\Env\Exception\AfrEnvException;

interface AfrEnvValidatorInterface
{
    /**
     * Required.
     * @param array $aKeys
     * @return $this
     */
    public function required(array $aKeys): self;

    /**
     * If present.
     * @param array $aKeys
     * @return $this
     */
    public function ifPresent(array $aKeys): self;

    /**
     * Custom closure.
     * @param callable $fX
     * @return void
     */
    public function customClosure(callable $fX): void;

    /**
     * Allowed values.
     * @param array $aAllowed
     * @return void
     */
    public function allowedValues(array $aAllowed): void;

    /**
     * Validate all.
     * @param array $aDataSet
     * @return bool
     * @throws AfrEnvException
     */
    public function validateAll(array $aDataSet): bool;

    /**
     * Reset.
     * @return $this
     */
    public function reset(): self;

    /**
     * Unrequire.
     * @param array $aKeys
     * @return $this
     */
    public function unrequire(array $aKeys): self;

    /**
     * Is integer.
     * @return void
     */
    public function isInteger(): void;

    /**
     * Is float.
     * @return void
     */
    public function isFloat(): void;

    /**
     * Is boolean.
     * @return void
     */
    public function isBoolean(): void;

    /**
     * Is array.
     * @return void
     */
    public function isArray(): void;

    /**
     * Is string.
     * @return void
     */
    public function isString(): void;

    /**
     * Not empty.
     * @return void
     */
    public function notEmpty(): void;
    /**
     * Is date time.
     * @return void
     */
    public function isDateTime(): void;
}
