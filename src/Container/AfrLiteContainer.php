<?php
declare(strict_types=1);

namespace Autoframe\Core\Container;


use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\InterfaceToConcrete\AfrInterfaceToConcreteInterface;
use Closure;
use ReflectionMethod;

class AfrLiteContainer implements AfrContainerInterface
{
	/**
	 * @var array of callable|string|instance
	 */
	protected static array $aClassMap = [];
	/**
	 * @var array of bool flags to register as singleton
	 */
	protected static array $aShared = [];
	protected static ?AfrContainerInterface $oAfrDIContainer;

	protected function __construct() {}

	/**
	 * @return AfrContainerInterface
	 */
	public static function getInstance(): AfrContainerInterface
	{
		if (empty(self::$oAfrDIContainer)) {
			self::$oAfrDIContainer = new static();
		}
		return self::$oAfrDIContainer;
	}

	/**
	 * @param string $id
	 * @return object|mixed
	 * @throws AfrContainerException
	 */
	public function get(string $id)
	{
		if ($this->has($id)) {
			$entry = self::$aClassMap[$id];
			if (is_string($entry)) {
				return $id !== $entry && $this->has($entry) ?
					$this->get($entry) :  //alias
					$this->make($entry);
			} elseif (is_object($entry) && !($entry instanceof Closure)) {
				return $entry;
			} elseif (is_callable($entry)) {
				return $entry($this);
			}
			return $entry;
		}
		return $this->make($id);
	}

	/**
	 * @param string $abstract
	 * @param array $parameters
	 * @return mixed|object
	 * @throws AfrContainerException
	 */
	public function make(string $abstract, array $parameters = [])
	{
		if (!empty(self::$aShared[$abstract])) {
			unset(self::$aShared[$abstract]);
			return $this->registerInstance($abstract, $this->make($abstract, $parameters));
		}
		try {
			$reflectionClass = new \ReflectionClass($abstract);
		} catch (\ReflectionException $e) {
			throw new AfrContainerException($e->getMessage(), $e->getCode(), $e);
		}
		if (!$reflectionClass->isInstantiable()) {
			if (
				method_exists($abstract, 'getInstance') &&
				(new ReflectionMethod($abstract, 'getInstance'))->isStatic()
			) {
				// return $abstract::getInstance();
				return $this->registerInstance($abstract, $abstract::getInstance());
			} elseif (interface_exists($abstract) && $this->has(AfrInterfaceToConcreteInterface::class)) {
				//TODO  daca chiar vreau sa tin incurcatura asta, o sa registrez ca si servicii restul de dependinte pentru interfata
				// $oAfrToConcreteStrategiesClass = AfrToConcreteStrategiesClass::getLatestInstance();
				list($sFlag, $implementingClass) = explode(
					'|',
					$this->get(AfrInterfaceToConcreteInterface::class)->resolve(
						$abstract, //not concrete, but the interface call this concrete :P
						$bUseCache = true,
						$sTemporaryContextOverwrite = null, //$this->getContext()
						$sTemporaryPriorityRuleOverwrite = null
					)
				);
				if ($sFlag === '1' || $sFlag === '2') {
					return $sFlag === '2' ?
						$implementingClass::getInstance() :
						$this->make($implementingClass, $parameters);
				}
			}

			throw new AfrContainerException('Class is not instantiable: ' . $abstract, 22);
		}
		$constructor = $reflectionClass->getConstructor();
		if (empty($constructor)) {
			return new $abstract();
		}
		if (!empty($parameters)) { //TODO: test in conjunction with constructor existence / dynamic params
			return new $abstract(...$parameters);
		}
		$aConstructorParameters = $constructor->getParameters();
		if (empty($aConstructorParameters)) {
			return new $abstract();
		}

		$dependencies = array_map(function (\ReflectionParameter $param) use ($abstract) {
			$name = $param->getName();
			$type = $param->getType();
			if (!$type) {
				throw new AfrContainerException('No type hint for class ' . $abstract . ' parameter: ' . $name);
			}
			if (version_compare(PHP_VERSION, '8.0.0', '>=')) { //php8
				if ($type instanceof \ReflectionUnionType) {
					throw new AfrContainerException('Failed to resolve class ' . $abstract . ' because of union type parameter: ' . $name);
				}
			}
			if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
				return $this->get($type->getName());
			}
			throw new AfrContainerException('Failed to resolve class ' . $abstract . ' because of invalid parameter: ' . $name);
		}, $aConstructorParameters);

		//return $reflectionClass->newInstanceArgs($dependencies);
		return new $abstract(...$dependencies);
	}

	/**
	 * @param string $abstract
	 * @return bool
	 */
	public function has(string $abstract): bool
	{
		return !empty(self::$aClassMap[$abstract]);
	}

	/**
	 * @param string $abstract
	 * @param callable|string $concrete
	 * @param bool $shared
	 * @return void
	 * @throws AfrContainerException
	 */
	public function bind(string $abstract, $concrete, bool $shared = false): void
	{
		if (!is_callable($concrete) && !is_string($concrete)) {
			throw new AfrContainerException('Container set second parameter must be callable|string');
		}
		self::$aClassMap[$abstract] = $concrete;
		if ($shared) {
			self::$aShared[$abstract] = true;
		}
	}

	/**
	 * @param string $abstract
	 * @param object $instance
	 * @return object
	 */
	public function registerInstance(string $abstract, object $instance): object
	{
		return self::$aClassMap[$abstract] = $instance;
	}


	public static function flush(): void
	{
		self::$aClassMap = self::$aShared = [];
		self::$oAfrDIContainer = null;
	}


	/**
	 * Determine if a given offset exists. Implements interface ArrayAccess
	 * @param string $offset
	 * @return bool
	 */
	public function offsetExists($offset): bool
	{
		return $this->has($offset);
	}

	/**
	 * Get the value at a given offset. Implements interface ArrayAccess
	 * @param string $offset
	 * @return mixed
	 * @throws AfrContainerException
	 */
	#[\ReturnTypeWillChange]
	public function offsetGet($offset)
	{
		return $this->get($offset);
	}

	/**
	 * Set the value at a given offset. Implements interface ArrayAccess
	 * @param string $offset
	 * @param mixed $value
	 * @return void
	 * @throws AfrContainerException
	 */
	public function offsetSet($offset, $value): void
	{
		$this->bind($offset, $value instanceof Closure ? $value : fn() => $value);
	}

	/**
	 * Unset the value at a given offset. Implements interface ArrayAccess
	 *
	 * @param string $offset
	 * @return void
	 */
	public function offsetUnset($offset): void
	{
		unset(self::$aClassMap[$offset], self::$aShared[$offset]);
	}

	/**
	 * Dynamically access container services. PHP Property overloading
	 *
	 * @param string $key
	 * @return mixed
	 */
	public function __get(string $key)
	{
		return $this[$key];
	}

	/**
	 * Dynamically set container services. PHP Property overloading
	 *
	 * @param string $key
	 * @param mixed $value
	 * @return void
	 */
	public function __set(string $key, $value)
	{
		$this[$key] = $value;
	}

	/**
	 * Dynamically check for container services. PHP Property overloading
	 *
	 * @param string $key
	 * @return bool
	 */
	public function __isset(string $key): bool
	{
		return isset($this[$key]);
	}

	/**
	 * Unset container services. PHP Property overloading
	 *
	 * @param string $key
	 * @return void
	 */
	public function __unset(string $key): void
	{
		unset($this[$key]);
	}


}