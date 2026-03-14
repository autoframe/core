<?php
declare(strict_types=1);

namespace Autoframe\Core\ClassDependency;

use ReflectionClass;
use ReflectionMethod;

use function array_flip;
use function is_string;
use function strlen;
use function trim;
use function strpos;
use function explode;
use function array_pop;
use function implode;
use function rtrim;
use function substr;
use function is_object;
use function get_class;
use function class_exists;
use function trait_exists;
use function interface_exists;
use function array_merge;
use function method_exists;
use function class_uses;
use function class_implements;
use function class_parents;

//use function enum_exists; //php8.1

/**
 * Class containing static methods for managing class dependencies.
 */
class AfrClassDependency
{
	/**
	 * Static methods for global scoping:
	 */

	/** @var self[] */
	protected static array $aDependency = [];

	/** @var self[] */
	protected static array $aFatalErr = [];

	protected static array $aSkipClasses = [];
	protected static array $aSkipNamespaces = [];

	/**
	 * Object or string containing Fully Qualified Class Name
	 * @param mixed $obj_sFQCN
	 * @return self
	 */
	public static function getClassInfo($obj_sFQCN): self
	{
		$sFQCN = is_object($obj_sFQCN) ? get_class($obj_sFQCN) : (string)$obj_sFQCN;
		if (!isset(self::$aDependency[$sFQCN])) {
			if (self::isSkipped($sFQCN)) {
				self::$aDependency[$sFQCN] = self::makeBlank($sFQCN, self::S);
			} else {
				self::$aDependency[$sFQCN] = self::makeBlank($sFQCN, self::F);
				self::$aFatalErr[$sFQCN] = 'Fatal error?';
				// register_shutdown_function + error_get_last() because fatal are not catchable
				try {
					self::$aDependency[$sFQCN] = new static($sFQCN);
					unset(self::$aFatalErr[$sFQCN]);
				} catch (\Throwable $oEx) {
					self::$aFatalErr[$sFQCN] = $oEx->getMessage();
					self::$aDependency[$sFQCN] = self::makeBlank($sFQCN, self::F);
				}
			}
		}
		return self::$aDependency[$sFQCN];
	}

	/**
	 * Object or string containing Fully Qualified Class Name
	 * @param mixed $obj_sFQCN
	 * @return bool
	 */
	public static function clearClassInfo($obj_sFQCN): bool
	{
		$sFQCN = is_object($obj_sFQCN) ? get_class($obj_sFQCN) : (string)$obj_sFQCN;

		if (isset(self::$aDependency[$sFQCN])) {
			unset(self::$aDependency[$sFQCN]);
			return true;
		}
		return false;
	}

	/**
	 * Flush.
	 * @return void
	 */
	public static function flush(): void
	{
		self::$aFatalErr = [];
		self::$aDependency = [];
		self::$aSkipClasses = [];
		self::$aSkipNamespaces = [];
	}

	/**
	 * Get dependency info.
	 * @return self[]
	 */
	public static function getDependencyInfo(): array
	{
		return self::$aDependency;
	}

	/**
	 * Clear dependency info.
	 * @return void
	 */
	public static function clearDependencyInfo(): void
	{
		if (!empty(self::$aDependency)) {
			foreach (self::$aDependency as $sFQCN => $obj) {
				unset($obj);
				unset(self::$aDependency[$sFQCN]);
			}
		}
		self::$aDependency = [];
	}


	/**
	 * Use in context with register_shutdown_function + error_get_last() because fatal are not catchable
	 * @return self[]
	 */
	public static function getDebugFatalError(): array
	{
		return self::$aFatalErr;
	}

	/**
	 * Clear debug fatal error.
	 * @return void
	 */
	public static function clearDebugFatalError(): void
	{
		self::$aFatalErr = [];
	}


	/**
	 * Set skip class info.
	 * @param array $aFQCN
	 * @param bool $bMergeWithExisting
	 * @return array
	 * @throws AfrClassDependencyException
	 */
	public static function setSkipClassInfo(array $aFQCN, bool $bMergeWithExisting = false): array
	{
		if (!$bMergeWithExisting) {
			self::$aSkipClasses = []; //cleanup
		}
		foreach ($aFQCN as $sFQCN) {
			if (!is_string($sFQCN)) {
				if (is_object($sFQCN)) {
					$sFQCN = get_class($sFQCN);
				} else {
					throw new AfrClassDependencyException(
						'Class name must be a string! Please use an array of FQCNs in ' .
						__CLASS__ . '::' . __FUNCTION__
					);
				}
			}
			self::$aSkipClasses[$sFQCN] = true;
		}

		foreach (self::$aSkipClasses as $sFQCN => &$bUnsetted) {
			$bUnsetted = isset(self::$aDependency[$sFQCN]); //internal debug flag
			if ($bUnsetted) {
				unset(self::$aDependency[$sFQCN]);
			}
		}
		self::processNewSkipRules();
		return self::$aSkipClasses;
	}

	/**
	 * Returns unset info into key prefix: 1|FQCN or 0|FQCN
	 * @return array
	 */
	public static function getSkipClassInfo(): array
	{
		if (empty(self::$aSkipClasses)) {
			return [];
		}
		$aOut = [];
		foreach (self::$aSkipClasses as $sFQCN => $bUnsetted) {
			$aOut[($bUnsetted ? '1' : '0') . '|' . $sFQCN] = $sFQCN;
		}
		return $aOut;
	}

	/**
	 * Namespace match rules:
	 * '' match classes without namespace;
	 * '\' will match all classes;
	 * 'Afr' will match classes having the exact namespace;
	 * 'Afr\' will match all the classes the parent namespace;
	 * @param array $aNamespaces
	 * @param bool $bMergeWithExisting
	 * @return array
	 * @throws AfrClassDependencyException
	 */
	public static function setSkipNamespaceInfo(array $aNamespaces, bool $bMergeWithExisting = false): array
	{
		if (!$bMergeWithExisting || !isset(self::$aSkipNamespaces)) {
			self::$aSkipNamespaces = []; //clear
		}
		foreach ($aNamespaces as $sNs) {
			if (!is_string($sNs)) {
				throw new AfrClassDependencyException(
					'Namespace must be a string! Please use an array of Namespaces in ' .
					__CLASS__ . '::' . __FUNCTION__
				);
			}
			self::$aSkipNamespaces[$sNs] = strlen($sNs); //length is used for loop speed improvements
		}
		self::processNewSkipRules();

		return self::$aSkipNamespaces;
	}

	/**
	 * Get skip namespace info.
	 * @return array
	 */
	public static function getSkipNamespaceInfo(): array
	{
		return array_keys(self::$aSkipNamespaces);
	}

	/**
	 * Is skipped.
	 * @param $obj_sFQCN
	 * @return bool
	 */
	public static function isSkipped($obj_sFQCN): bool
	{
		$sFQCN = is_object($obj_sFQCN) ? get_class($obj_sFQCN) : (string)$obj_sFQCN;
		return self::mustSkipNamespaceInfoGatheringForClass($sFQCN) || isset(self::$aSkipClasses[$sFQCN]);
	}

	/**
	 * @param string $sFQCN
	 * @param string $sType
	 * @return static
	 */
	protected static function makeBlank(string $sFQCN, string $sType = ''): self
	{
		$oBlank = new static('');//blank init
		$oBlank->sFQCN = $sFQCN;
		if (!$sType) {
			$sType = __FUNCTION__;
		}
		$oBlank->sType = $sType;
		return $oBlank;
	}

	/**
	 * @param string $sFQCN
	 * @return bool
	 */
	protected static function mustSkipNamespaceInfoGatheringForClass(string $sFQCN): bool
	{
		if (!isset(self::$aSkipNamespaces) || empty(self::$aSkipNamespaces)) {
			return false; //no rules set
		}
		if (isset(self::$aSkipNamespaces['\\'])) {
			return true; //this will match any class
		}
		$sFQCN = trim($sFQCN, '\\');
		if (strpos($sFQCN, '\\') === false) { //class without namespace
			return isset(self::$aSkipNamespaces['']); //matching classes from the default blank namespace
		}

		$aClassNs = explode('\\', $sFQCN);
		array_pop($aClassNs); //clear class name
		$sClassNs = implode('\\', $aClassNs);

		if (strlen($sClassNs) < 1) {
			return false;
		}

		foreach (self::$aSkipNamespaces as $sSkipNs => $iSkipNsLength) {
			if (!$iSkipNsLength) {
				continue; //class without namespace
			}
			if ($sSkipNs === $sClassNs || rtrim($sSkipNs, '\\') === $sClassNs) {
				return true; //exact match
			} elseif (substr($sSkipNs, -1, 1) === '\\') {
				if (substr($sClassNs, 0, $iSkipNsLength) === $sSkipNs) {
					return true; //class is contained into parent ns
				}
			}

		}
		return false;
	}

	/**
	 * Instantiable methods:
	 * Object or String Full Qualified Class Name
	 * Everything else is cast to string and can fail!
	 * @param $mClass
	 */

	protected const C = 'class';
	protected const T = 'trait';
	protected const I = 'interface';
	protected const E = 'enum';
	protected const U = 'unknown';
	protected const S = 'skip';
	protected const F = 'fatal-error';

	protected string $sFQCN;
	protected string $sType;
	protected array $aParents;
	protected array $aTraits;
	protected array $aInterfaces;
	protected bool $bAbstract;
	protected bool $bInstantiable;
	protected bool $bSingleton;

	protected function __construct(string $sFQCN)
	{
		$this->sFQCN = $sFQCN;
		$this->sType = $this->getType();
		$this->detectComponents();
	}

	/**
	 * Get type.
	 * @return string
	 */
	public function getType(): string
	{
		if (isset($this->sType)) {
			return $this->sType;
		}
		$this->sType = self::U; //set as unknown until we find out what it is
		if (!$this->sFQCN) {
			return $this->sType;
		}
		if (class_exists($this->sFQCN)) {
			$this->sType = self::C;
		} elseif (trait_exists($this->sFQCN)) {
			$this->sType = self::T;
		} elseif (interface_exists($this->sFQCN)) {
			$this->sType = self::I;
		} elseif (PHP_VERSION_ID >= 81000 && \enum_exists($this->sFQCN)) {
			$this->sType = self::E;
		}
		return $this->sType;
	}

	/**
	 * @return void
	 */
	protected static function processNewSkipRules(): void
	{
		// Class can be resolved on demand / later with self::getClassInfo($sFQCN)
		// For now, just clear ram memory
		foreach (self::$aDependency as $sFQCN => $oSelf) {
			$bIsSkipped = self::isSkipped($sFQCN);
			if (
				// Cleanup previously skipped, so they can be resolved with a new request
				$oSelf->getType() === self::S && !$bIsSkipped ||
				// Apply new rules for existing
				$oSelf->getType() !== self::S && $bIsSkipped
			) {
				unset($oSelf);
				unset(self::$aDependency[$sFQCN]);
			}
		}
	}


	/**
	 * Get all dependencies.
	 * @return array
	 */
	public function getAllDependencies(): array
	{
		return array_merge($this->getParents(), $this->getTraits(), $this->getInterfaces());
	}

	/**
	 * Get class name.
	 * @return string
	 */
	public function getClassName(): string
	{
		return $this->sFQCN;
	}

	/**
	 * Return the string representation of the instance.
	 * @return string
	 */
	public function __toString(): string
	{
		return $this->getClassName();
	}


	/** Get parent Classes
	 * Get parents.
	 * @return array
	 */
	public function getParents(): array
	{
		if (!isset($this->aParents)) {
			return [];
		}
		return $this->aParents;
	}

	/** Get parent Traits
	 * Get traits.
	 * @return array
	 */
	public function getTraits(): array
	{
		if (!isset($this->aTraits)) {
			return [];
		}
		return $this->aTraits;
	}

	/** Get parent Interfaces
	 * Get interfaces.
	 * @return array
	 */
	public function getInterfaces(): array
	{
		if (!isset($this->aInterfaces)) {
			return [];
		}
		return $this->aInterfaces;
	}


	/**
	 * Is class.
	 * @return bool
	 */
	public function isClass(): bool
	{
		return $this->getType() === self::C;
	}

	/**
	 * Is trait.
	 * @return bool
	 */
	public function isTrait(): bool
	{
		return $this->getType() === self::T;
	}

	/**
	 * Is interface.
	 * @return bool
	 */
	public function isInterface(): bool
	{
		return $this->getType() === self::I;
	}

	/**
	 * Is enum.
	 * @return bool
	 */
	public function isEnum(): bool
	{
		return $this->getType() === self::E;
		//return (new ReflectionClass($this->getClassName()))->isEnum();
	}

	/**
	 * Is abstract.
	 * @return bool
	 */
	public function isAbstract(): bool
	{
		if (isset($this->bAbstract)) {
			return $this->bAbstract;
		}
		if ($this->getType() !== self::C) { //only classes can be abstract
			return $this->bAbstract = false;
		}
		if (isset($this->bInstantiable) && $this->bInstantiable) { //abstract classes can't be instanced
			return $this->bAbstract = false;
		}
		try {
			return $this->bAbstract = (new ReflectionClass($this->getClassName()))->isAbstract();
		} catch (\ReflectionException $e) {
			return $this->bAbstract = false;
		}
	}

	/**
	 * Is instantiable.
	 * @return bool
	 */
	public function isInstantiable(): bool
	{
		if (isset($this->bInstantiable)) {
			return $this->bInstantiable;
		}
		if ($this->getType() !== self::C) { //only classes can have instances
			return $this->bInstantiable = false;
		}
		if (isset($this->bAbstract) && $this->bAbstract) { //abstract classes can't have instances
			return $this->bInstantiable = false;
		}
		try {
			return $this->bInstantiable = (new ReflectionClass($this->getClassName()))->isInstantiable();
		} catch (\ReflectionException $e) {
			return $this->bInstantiable = false;
		}
	}


	/**
	 * Is singleton.
	 * @return bool
	 */
	public function isSingleton(): bool
	{
		if (isset($this->bSingleton)) {
			return $this->bSingleton;
		}
		if ($this->getType() !== self::C) {
			return $this->bSingleton = false;
		}
		try {
			return (
				method_exists($this->getClassName(), 'getInstance') &&
				((new ReflectionMethod($this->getClassName(), 'getInstance'))->isStatic())
			);
		} catch (\ReflectionException $e) {
			return $this->bSingleton = false;
		}
	}

	/**
	 * Object or String Fully Qualified Class Name
	 * @param $mClass
	 * @return bool
	 */
	public function doIDependOn($mClass): bool
	{
		return isset($this->getAllDependencies()[is_object($mClass) ? get_class($mClass) : (string)$mClass]);
	}

	/**
	 * @return void
	 */
	protected function detectComponents(): void
	{
		if (!$this->sFQCN || $this->sType === self::U) {
			return;
		}
		foreach ((array)class_uses($this->sFQCN) as $sTrait) {
			$oCompound = self::getClassInfo($sTrait);
			$this->aTraits[(string)$oCompound] = true;
			$this->detectTraits((string)$oCompound);
		}
		foreach ((array)class_implements($this->sFQCN) as $sInterface) {
			$oCompound = self::getClassInfo($sInterface);
			$this->aInterfaces[(string)$oCompound] = true;
		}
		foreach ((array)class_parents($this->sFQCN) as $sParent) {
			$oCompound = self::getClassInfo($sParent);
			$this->aParents[(string)$oCompound] = true;
			$this->detectTraits((string)$oCompound);
		}
		//https://www.php.net/manual/en/language.enumerations.php
		//if (PHP_VERSION_ID >= 81000 && $this->isEnum()) { new \ReflectionEnum($this->sFQCN); }
	}

	/**
	 * class_uses will show only the traits that make up the current class, so we recursively get them
	 * @param string $sFQCN
	 * @return void
	 */
	protected function detectTraits(string $sFQCN): void
	{
		foreach (self::getClassInfo($sFQCN)->getTraits() as $sParentTrait => $nx) {
			if (!isset($this->aTraits[$sParentTrait])) {
				$this->aTraits[$sParentTrait] = true;
			}
		}
	}

}
