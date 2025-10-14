<?php
declare(strict_types=1);

namespace Autoframe\Core\Env\Validator;

use Autoframe\Core\Env\Exception\AfrEnvException;

class AfrEnvValidatorClass implements AfrEnvValidatorInterface
{
	protected array $aQueue = [];
	protected array $aTargetKeys = [];
	protected array $aDataSet = [];

	/**
	 * @param array $aDataSet
	 * @return bool
	 * @throws AfrEnvException
	 */
	public function validateAll(array $aDataSet): bool
	{
		$this->aDataSet = $aDataSet;
		print_r(__CLASS__.'@'.__FUNCTION__.'$this->aDataSet:');
		print_r($this->aDataSet);
		debug_print_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);
		foreach ($this->aQueue as $sKey => $aRules) {
			foreach ($aRules as $aRule) {
				$mResponse = $aRule[0](...$aRule[1]);
				if (!$mResponse) {
					throw new AfrEnvException(
						'Validation failed in ' .
						__CLASS__ . '@' . ($aRule[2]) . ' for env key: ' .
						$sKey . ' » `' . $this->aDataSet[$sKey] . '`'
					);
				}
				if ($mResponse === 's') {
					break;
				}
			}
		}
		return true;
	}

	/**
	 * @param array $aKeys
	 * @return self
	 */
	public function required(array $aKeys): self
	{
		$this->setTargets($aKeys);
		foreach ($this->aTargetKeys as $sKey) {
			$this->aQueue[$sKey][0] = [
				function ($sKey) {
					return isset($this->aDataSet[$sKey]);
				},
				[$sKey]
			];
		}
		return $this;
	}

	/**
	 * @param array $aKeys
	 * @return self
	 */
	public function ifPresent(array $aKeys): self
	{
		$this->setTargets($aKeys);
		foreach ($this->aTargetKeys as $sKey) {
			$this->aQueue[$sKey][0] = [
				function ($sKey) {
					if (!isset($this->aDataSet[$sKey])) {
						//skip other validations
						return 's';
					}
					return true;
				},
				[$sKey]
			];
		}
		return $this;
	}


	/**
	 * @param callable $fX
	 * @return void
	 */
	public function customClosure(callable $fX): void
	{
		foreach ($this->aTargetKeys as $sKey) {
			$this->aQueue[$sKey]['c'] = [$fX, [$sKey, $this->aDataSet], __FUNCTION__];
		}
	}

	/**
	 * @param array $aAllowed
	 * @return void
	 */
	public function allowedValues(array $aAllowed): void
	{
		foreach ($this->aTargetKeys as $sKey) {
			$this->aQueue[$sKey]['al'] = [
				function ($sKey, $aAllowed) {
					if (!in_array($this->aDataSet[$sKey], $aAllowed)) {
						throw new AfrEnvException(
							'Validation failed in ' .
							__CLASS__ . '@allowedValues for env key: ' .
							$sKey . ' » `' . $this->aDataSet[$sKey] . '`; ' .
							'allowed data set: ' . implode('; ', $aAllowed)
						);
					}
					return true;
				},
				[$sKey, $aAllowed],
				__FUNCTION__
			];
		}
	}


	/**
	 * @return self
	 */
	public function reset(): self
	{
		$this->aDataSet = $this->aQueue = $this->aTargetKeys = [];
		return $this;
	}

	/**
	 * @param array $aKeys
	 * @return self
	 */
	public function unrequire(array $aKeys): self
	{
		$this->setTargets($aKeys);
		foreach ($this->aTargetKeys as $sKey) {
			if (isset($this->aQueue[$sKey])) {
				unset($this->aQueue[$sKey]);
			}
		}
		$this->aTargetKeys = [];
		return $this;
	}

	/**
	 * @return void
	 */
	public function isInteger(): void
	{
		foreach ($this->aTargetKeys as $sKey) {
			$this->aQueue[$sKey]['i'] = [
				function ($sKey) {
					return is_int($this->aDataSet[$sKey]);
				},
				[$sKey],
				__FUNCTION__
			];
		}
	}

	/**
	 * @return void
	 */
	public function isFloat(): void
	{
		foreach ($this->aTargetKeys as $sKey) {
			$this->aQueue[$sKey]['f'] = [
				function ($sKey) {
					return is_float($this->aDataSet[$sKey]);
				},
				[$sKey],
				__FUNCTION__
			];
		}
	}

	/**
	 * @return void
	 */
	public function isBoolean(): void
	{
		foreach ($this->aTargetKeys as $sKey) {
			$this->aQueue[$sKey]['b'] = [
				function ($sKey) {
					return is_bool($this->aDataSet[$sKey]);
				},
				[$sKey],
				__FUNCTION__
			];
		}
	}

	/**
	 * @return void
	 */
	public function isArray(): void
	{
		foreach ($this->aTargetKeys as $sKey) {
			$this->aQueue[$sKey]['a'] = [
				function ($sKey) {
					return is_array($this->aDataSet[$sKey]);
				},
				[$sKey],
				__FUNCTION__
			];
		}
	}

	/**
	 * @return void
	 */
	public function isString(): void
	{
		foreach ($this->aTargetKeys as $sKey) {
			$this->aQueue[$sKey]['s'] = [
				function ($sKey) {
					return is_string($this->aDataSet[$sKey]);
				},
				[$sKey],
				__FUNCTION__
			];
		}
	}

	/**
	 * @return void
	 */
	public function notEmpty(): void
	{
		foreach ($this->aTargetKeys as $sKey) {
			$this->aQueue[$sKey]['ne'] = [
				function ($sKey) {
					return !empty($this->aDataSet[$sKey]);
				},
				[$sKey],
				__FUNCTION__
			];
		}
	}

	/**
	 * @return void
	 */
	public function isDateTime(): void
	{
		foreach ($this->aTargetKeys as $sKey) {
			$this->aQueue[$sKey]['dt'] = [
				function ($sKey) {
					return strtotime($this->aDataSet[$sKey]) > 0;
				},
				[$sKey],
				__FUNCTION__
			];
		}
	}

	/**
	 * @param array $aKeys
	 * @return void
	 */
	protected function setTargets(array $aKeys): void
	{
		$this->aTargetKeys = [];
		foreach ($aKeys as $sKey) {
			$sKey = (string)$sKey;
			if (strlen($sKey) < 1) {
				continue;
			}
			$this->aTargetKeys[] = $sKey;
		}
	}
}