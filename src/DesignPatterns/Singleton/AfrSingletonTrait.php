<?php
declare(strict_types=1);

namespace Autoframe\Core\DesignPatterns\Singleton;

use Autoframe\Core\Container\AfrContainerFacade;
use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\Event\AfrEvent;
use Autoframe\Core\Event\Exception\AfrEventException;
use Autoframe\Core\Exception\AfrException;

/**
 * There are 2 methods to use the Singleton pattern:
 * - target class extends AfrSingletonAbstractClass or
 * - use AfrSingletonTrait if the target class is not extendable
 */
trait AfrSingletonTrait
{
	use AfrSingletonResolveTrait;
	/**
	 * The actual singleton's instance almost always resides inside a static
	 * field. In this case, the static field is an array, where each subclass of
	 * the Singleton stores its own instance.
	 */
	protected static array $instances = [];
	protected static array $instancesNoContainer = [];

	protected static bool $bCheckObjectInstanceType = true;

	/**
	 * Singleton's constructor should not be public. However, it can't be
	 * private either if we want to allow subclassing.
	 */
	//final protected function __construct() {}
	protected function __construct() {}

	/**
	 * Cloning and un-serialization are not permitted for singletons.
	 * @throws AfrException
	 */
	final public function __clone()
	{
		throw new AfrException('Cannot clone a singleton: ' . static::class);
	}

	/**
	 * @throws AfrException
	 */
	final public function __wakeup()
	{
		throw new AfrException('Cannot unserialize singleton: ' . static::class);
	}


	/**
	 * The method you use to get the Singleton's instance.
	 * @return self
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 */
	public static function getInstance()
	{
		if (!self::hasInstance()) {
			// Note that here we use the "static" keyword instead of the actual
			// class name. In this context, the "static" keyword means "the name
			// of the current class". That detail is important because when the
			// method is called on the subclass, we want an instance of that
			// subclass to be created here.
			//TODO: mark daca dispatch event este gol si nu am closure definit!!!!

			if (!isset(self::$instances[static::class])) {
				// set a temporary flag to prevent infinite looping inside container when resolving a singleton
				self::$instances[static::class] = false;
				AfrEvent::dispatchEvent(implode('\\', array_slice(explode('\\', static::class), -2, 2)) . '::' . __FUNCTION__);



			/*	$oResolved = AfrContainerFacade::getContainer()->get(static::class);
				if (is_object($oResolved)) {
					if ($oResolved instanceof \Closure) {
						$oResolvedClosureResult = $oResolved->bindTo(null,static::class)();

						if (static::checkObjectInstanceTypeStaticClass($oResolvedClosureResult)) {
							self::$instances[static::class] = $oResolvedClosureResult;
						}
					} elseif (static::checkObjectInstanceTypeStaticClass($oResolved)) {
						self::$instances[static::class] = $oResolved;
					}
				}*/

				$mResolved = static::getResolvedStatic(static::class);
				self::$instances[static::class] =
					static::checkObjectInstanceTypeStaticClass($mResolved) ?
					$mResolved : self::$instances[static::class];

			}
			if (!self::hasInstance()) {
				self::$instances[static::class] = new static();
			}

			// TODO add configurable actions with AfrConfig
			//TODO closure dupa constructor
			//todo closure la fiecare get instance
			//	return self::$instances[static::class];
		}
		return self::$instances[static::class];
	}



	/**
	 * @return self
	 * @throws AfrEventException|AfrContainerException
	 */
	final public static function getInstanceNoContainerBindings(): self
	{
		$staticClass = static::class;
		if (self::hasInstance() && self::$instances[$staticClass] instanceof $staticClass) {
			return self::getInstance();
		}
		//make the original singleton static class
		if (empty(self::$instancesNoContainer[$staticClass])) {
			AfrEvent::dispatchEvent(implode('\\', array_slice(explode('\\', $staticClass), -2, 2)) . '::' . __FUNCTION__);
			self::$instancesNoContainer[$staticClass] = new static();
		}
		return self::$instancesNoContainer[$staticClass];
	}


	/**
	 * @return bool
	 */
	final public static function hasInstance(): bool
	{
		return !empty(self::$instances[static::class]);
	}

	/**
	 * You can really go wilde here and set $bCheckInstanceOfStaticClass to false,
	 * or even redefine this method in the child class, but carefully define
	 * the same structure as an interface implementation. If the usage code checks
	 * for the family tree of the returned object, I suggest to extend the target class and play along!
	 * @param $mixedToCheck
	 * @return bool
	 */
	protected static function checkObjectInstanceTypeStaticClass($mixedToCheck): bool
	{
		if(!is_object($mixedToCheck)){
			return false;
		}
		if(static::$bCheckObjectInstanceType){ //TODO: add here integrity checks from interfaces
			return $mixedToCheck instanceof (static::class);
		}
		return true;
	}
}