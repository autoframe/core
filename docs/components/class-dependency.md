
Namespace\Class:
- Autoframe\Core\ClassDependency


Static methods:
- public static function getClassInfo(mixed $obj_sFQCN): AfrClassDependency;
- public static function clearClassInfo(mixed $obj_sFQCN): bool;
- public static function isSkipped(mixed $obj_sFQCN): bool;
- public static function getDependencyInfo(): array;
- public static function clearDependencyInfo(): void;
- public static function getDebugFatalError(): array;
- public static function clearDebugFatalError(): void;
- public static function flush(): void;
- public static function setSkipClassInfo(array $aFQCN, bool $bMergeWithExisting = false): array;
- public static function setSkipNamespaceInfo(array $aNamespaces, bool $bMergeWithExisting = false): array;
- public static function getSkipClassInfo(): array;
- public static function getSkipNamespaceInfo(): array;

Instance methods:
- public function getType(): string;
- public function getAllDependencies(): array;
- public function getClassName(): string;
- public function __toString(): string;
- public function getParents(): array;
- public function getTraits(): array;
- public function getInterfaces(): array;
- public function isClass(): bool;
- public function isTrait(): bool;
- public function isInterface(): bool;
- public function isEnum(): bool;
- public function isAbstract(): bool;
- public function isInstantiable(): bool;
- public function isSingleton(): bool;
- public function doIDependOn($mClass): bool;

Instance methods are available using getClassInfo(className or object): AfrClassDependency
