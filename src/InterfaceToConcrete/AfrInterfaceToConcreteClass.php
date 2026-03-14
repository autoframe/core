<?php
declare(strict_types=1);

namespace Autoframe\Core\InterfaceToConcrete;

use Autoframe\Core\ClassDependency\AfrClassDependencyException;
use Autoframe\Core\CliTools\AfrSysTempDir;
use Autoframe\Core\Env\AfrEnv;
use Autoframe\Core\Env\Exception\AfrEnvException;
use Autoframe\Core\InterfaceToConcrete\Exception\AfrInterfaceToConcreteException;
use Autoframe\Core\ClassDependency\AfrClassDependency;

use Autoframe\Core\Tenant\AfrTenant;
use function array_merge;
use function realpath;
use function print_r;
use function base_convert;
use function md5;
use function serialize;

/**
 * Copyright BSD-3-Clause / Nistor Alexadru Marius / Auroframe SRL Romania / https://github.com/autoframe
 * This will make a configuration object that contains the paths to be wired:
 *
 * $oAfrConfigWiredPaths = new AfrInterfaceToConcreteClass(
 * $aSettings [], //overwrite profile settings
 * $aExtraPaths = [] //all compose paths are covered
 * );
 * $oAfrConfigWiredPaths->getClassInterfaceToConcrete();
 *
 * STATIC CALL AFTER INSTANTIATING:  AfrInterfaceToConcreteClass::$oInstance->getClassInterfaceToConcrete();
 *
 */
class AfrInterfaceToConcreteClass implements AfrInterfaceToConcreteInterface
{
	/** @var array Paths to cache */
	protected array $aPaths = [];
	protected array $aClassInterfaceToConcrete;
	protected array $aSettings;
	public static AfrInterfaceToConcreteInterface $oLatestInstance;
	protected ?AfrToConcreteStrategiesInterface $oAfrToConcreteStrategies;

	/**
	 * Create a new instance.
	 * @param array $aSettings
	 * @param array $aExtraPaths
	 * @throws AfrInterfaceToConcreteException|AfrEnvException
	 */
	public function __construct(
		array $aSettings = [],
		array $aExtraPaths = []
	)
	{
		$this->setSettings($aSettings);
		$aPaths = [
			AfrMultiClassMapper::VendorPrefix => [],
			AfrMultiClassMapper::AutoloadPrefix => [],
			AfrMultiClassMapper::ExtraPrefix => [],
		];
		$this->applyExtraPrefix($aExtraPaths, $aPaths);
		$this->applyVendorPrefix($aPaths);
		$this->applyAutoloadPrefix($aPaths);

		foreach ($aPaths as $sPrefix => $aPathItem) {
			foreach ($aPathItem as $sPath) {
				if (isset($this->aPaths[$sPath])) {
					continue;
				}
				$this->aPaths[$sPath] = $sPrefix . $this->hashV(serialize([
						$sPath,
						$this->aSettings[AfrMultiClassMapper::DumpPhpFilePathAndMtime],
						$this->aSettings[AfrMultiClassMapper::RegexExcludeFqcnsAndPaths],
					]));
			}
		}
		self::$oLatestInstance = $this;
	}

	/**
	 * Get class interface to concrete.
	 * @param string|null $sFilterFQCN
	 * @return array
	 * @throws AfrClassDependencyException
	 * @throws AfrInterfaceToConcreteException
	 */
	public function getClassInterfaceToConcrete(string $sFilterFQCN = null): array
	{
		if (!isset($this->aClassInterfaceToConcrete)) {
			$aSaveSkipClassInfo = $aSaveSkipNamespaceInfo = [];
			if ($this->aSettings[AfrMultiClassMapper::ClassDependencyRestoreSkipped]) {
				$aSaveSkipClassInfo = AfrClassDependency::getSkipClassInfo();
				$aSaveSkipNamespaceInfo = AfrClassDependency::getSkipNamespaceInfo();
			}
			AfrClassDependency::flush();
			AfrClassDependency::setSkipClassInfo($this->aSettings[AfrMultiClassMapper::ClassDependencySetSkipClassInfo]);
			AfrClassDependency::setSkipNamespaceInfo($this->aSettings[AfrMultiClassMapper::ClassDependencySetSkipNamespaceInfo]);

			AfrMultiClassMapper::setAfrConfigWiredPaths($this);
			$this->aClassInterfaceToConcrete = AfrMultiClassMapper::getInterfaceToConcrete();

			if ($this->aSettings[AfrMultiClassMapper::MultiClassMapperFlush]) {
				AfrMultiClassMapper::flush(); //clean memory
			}
			if ($this->aSettings[AfrMultiClassMapper::ClassDependencyFlush]) {
				AfrClassDependency::flush(); //clean memory
			}
			if ($this->aSettings[AfrMultiClassMapper::ClassDependencyRestoreSkipped]) {
				AfrClassDependency::setSkipClassInfo($aSaveSkipClassInfo);
				AfrClassDependency::setSkipNamespaceInfo($aSaveSkipNamespaceInfo);
			}
		}
		if ($sFilterFQCN !== null) {
			return
				!empty($this->aClassInterfaceToConcrete[$sFilterFQCN]) &&
				is_array($this->aClassInterfaceToConcrete[$sFilterFQCN]) ?
					$this->aClassInterfaceToConcrete[$sFilterFQCN] : [];
		}
		return $this->aClassInterfaceToConcrete;
	}

	/**
	 * Get latest instance.
	 * @return AfrInterfaceToConcreteInterface|null
	 */
	public static function getLatestInstance(): ?AfrInterfaceToConcreteInterface
	{
		if (!empty(self::$oLatestInstance)) {
			return self::$oLatestInstance;
		}
		return null;
	}


	/**
	 * @param array $aOverwrite
	 * @throws AfrInterfaceToConcreteException
	 * @throws AfrEnvException
	 */
	protected function setSettings(array $aOverwrite = [])
	{
		$aSettings = [
			//time between changes checks. if something changed, then the cache is recalculated
			AfrMultiClassMapper::CacheExpireSeconds => 3600 * 24 * 365 * 2,

			//all php sources except the vendor because there we check vendor/composer/install.json timestamp
			AfrMultiClassMapper::ForceRegenerateAllButVendor => false,

			//ob_start is used and redirects / cli handling
			AfrMultiClassMapper::SilenceErrors => false,

			//exclude folder and file paths and namespaces in all checks
			//eg ['@src.{1,}Exception@','@PHPUnit.{1,}Telemetry@']
			AfrMultiClassMapper::RegexExcludeFqcnsAndPaths => [],

			// full path: /server/cacheDir
			// overwrite here or auto set by AfrMultiClassMapper::getCacheDir()
			// realpath(__DIR__) . DIRECTORY_SEPARATOR . 'cache';
			//AfrMultiClassMapper::CacheDir => realpath(__DIR__) . DIRECTORY_SEPARATOR . 'cache',
			//AfrMultiClassMapper::CacheDir => AfrSysTempDir::sysGetTempDir(),
			AfrMultiClassMapper::CacheDir => (fn() => AfrTenant::getTempDir()),

			// Clean memory after job or keep AfrMultiClassMapper::$aNsClassMergedFromPathMap
			// and access the raw data using AfrMultiClassMapper::getAllNsClassFilesMap()
			AfrMultiClassMapper::MultiClassMapperFlush => true,

			//flush after usage and clear memory or keep all for debug
			AfrMultiClassMapper::ClassDependencyFlush => true,

			//restore AfrMultiClassMapper skip info after flush
			AfrMultiClassMapper::ClassDependencyRestoreSkipped => true,

			//this will consume more space inside cache dir and memory to process
			AfrMultiClassMapper::DumpPhpFilePathAndMtime => false,

			//pointless in my application type, but it depends on what you do
			AfrMultiClassMapper::ClassDependencySetSkipClassInfo => [
				'ArrayAccess',
				'BadFunctionCallException',
				'BadMethodCallException',
				'Countable',
				'Exception',
				'Iterator',
				'IteratorAggregate',
				'IteratorIterator',
				'InvalidArgumentException',
				'JsonSerializable',
				'LogicException',
				'OuterIterator',
				'ReflectionException',
				'RuntimeException',
				'Serializable',
				'SplFileInfo',
				'Stringable',
				'Throwable',
				'Traversable',
				'Throwable',
			],
			AfrMultiClassMapper::ClassDependencySetSkipNamespaceInfo => [
				'PHPUnit\\',
				'PharIo\\',
				'SebastianBergmann\\',
				'TheSeer\\',
				'phpDocumentor\\',
				'Webmozart\\',
				'Symfony\\',
				'Doctrine\\',
				'Composer\\',
				'Assert\\',
				'Cose\\',
				'DeepCopy\\',
				'FG\\',
				'PHPStan\\',
				'ParagonIE\\',
				'PhpParser\\',
				'Prophecy\\',
			],
		];
		if (AfrEnv::getInstance()->isDev()) {
			$aSettings = array_merge($aSettings, [
				AfrMultiClassMapper::CacheExpireSeconds => 60,
			]);
		} else { // PRODUCTION | STAGING
			$aSettings = array_merge($aSettings, [
				AfrMultiClassMapper::SilenceErrors => true,
			]);
		}

		$aSettings = array_merge($aSettings,
			AfrEnv::getInstance()->isDev() ?
				[AfrMultiClassMapper::CacheExpireSeconds => 600,] : // DEV
				[AfrMultiClassMapper::SilenceErrors => true,] // PRODUCTION|STAGING
		);

		if (AfrEnv::getInstance()->isDebug() >= 10) {
			$aSettings = array_merge($aSettings, [
				AfrMultiClassMapper::CacheExpireSeconds => 15,
				AfrMultiClassMapper::ForceRegenerateAllButVendor => true,
				AfrMultiClassMapper::SilenceErrors => false,
				AfrMultiClassMapper::RegexExcludeFqcnsAndPaths => [],
				AfrMultiClassMapper::MultiClassMapperFlush => false,
				AfrMultiClassMapper::ClassDependencyFlush => false,
				AfrMultiClassMapper::ClassDependencyRestoreSkipped => false,
				AfrMultiClassMapper::DumpPhpFilePathAndMtime => true,
				AfrMultiClassMapper::ClassDependencySetSkipClassInfo => [],
				AfrMultiClassMapper::ClassDependencySetSkipNamespaceInfo => [],
			]);
		}
		foreach ($aSettings as $sKey => $mValue) {
			if (isset($aOverwrite[$sKey])) {
				$sType = substr($sKey, 0, 2);
				$sErr = self::class . '[' . $sKey . '] was given as ' . gettype($aOverwrite[$sKey]) . ' in stead of ';
				if ($sType === '$i') {
					if (!is_int($aOverwrite[$sKey])) {
						throw new AfrInterfaceToConcreteException($sErr . ' integer');
					}
					$aSettings[$sKey] = max(15, abs($aOverwrite[$sKey]));
				} elseif ($sType === '$b') {
					if (!is_bool($aOverwrite[$sKey])) {
						throw new AfrInterfaceToConcreteException($sErr . ' boolean');
					}
					$aSettings[$sKey] = $aOverwrite[$sKey];
				} elseif ($sType === '$a') {
					if (!is_array($aOverwrite[$sKey])) {
						throw new AfrInterfaceToConcreteException($sErr . ' array');
					}
					$aSettings[$sKey] = $aOverwrite[$sKey];
				} elseif ($sType === '$s') {
					if (!is_string($aOverwrite[$sKey])) {
						throw new AfrInterfaceToConcreteException($sErr . ' string');
					}
					$aSettings[$sKey] = $aOverwrite[$sKey];
				} else {
					throw new AfrInterfaceToConcreteException(self::class . '[' . $sKey . '] unknown format');
				}
			}
		}
		$this->aSettings = $aSettings;
	}

	/**
	 * Get settings.
	 * @param string|null $sType
	 * @return array|mixed
	 */
	public function getSettings(string $sType = null)
	{
		if ($sType) {
			return $this->aSettings[$sType];
		}
		return $this->aSettings;
	}


	/**
	 * Hash v.
	 * @param string $s
	 * @return string
	 */
	public function hashV(string $s): string
	{
		return substr(base_convert(md5($s), 16, 32), 0, 6);
	}

	/**
	 * Get paths.
	 * @return array
	 */
	public function getPaths(): array
	{
		return $this->aPaths;
	}


	/**
	 * @param array $aExtraPaths
	 * @param array $aPaths
	 * @return void
	 * @throws AfrInterfaceToConcreteException
	 */
	protected function applyExtraPrefix(array $aExtraPaths, array &$aPaths): void
	{
		foreach ($aExtraPaths as $sPath) {
			$sPath = (string)$sPath;
			if (strlen($sPath) < 1) {
				continue;
			}
			$sPath = realpath($sPath);
			if ($sPath === false) {
				throw new AfrInterfaceToConcreteException(
					'Invalid paths for ' . __CLASS__ . '->' . __FUNCTION__ . '->' . print_r($aExtraPaths, true)
				);
			}
			$aPaths[AfrMultiClassMapper::ExtraPrefix][] = $sPath;
		}
	}

	/**
	 * @param array $aPaths
	 * @return void
	 * @throws AfrInterfaceToConcreteException
	 */
	protected function applyVendorPrefix(array &$aPaths): void
	{
		$aPaths[AfrMultiClassMapper::VendorPrefix] = [AfrVendorPath::getVendorPath()];
		if (empty($aPaths[AfrMultiClassMapper::VendorPrefix])) {
			throw new AfrInterfaceToConcreteException(
				'Composer vendor path not found ' . __CLASS__ . '->' . __FUNCTION__);
		}
	}

	/**
	 * @param array $aPaths
	 * @return void
	 */
	protected function applyAutoloadPrefix(array &$aPaths): void
	{
		$aPaths[AfrMultiClassMapper::AutoloadPrefix] = [];
		foreach (AfrVendorPath::getComposerAutoloadX()['autoload'] as $sType => $mixed) {
			if ($sType === 'psr4' || $sType === 'psr0') {
				foreach ($mixed as $aPsr) {
					if (!is_array($aPsr)) {
						continue;
					}
					$aPaths[AfrMultiClassMapper::AutoloadPrefix] =
						array_merge($aPaths[AfrMultiClassMapper::AutoloadPrefix], $aPsr);
				}
			}
			//if ($sType === 'classmap') {} // classmap covered under AfrMultiClassMapper::VendorPrefix
		}
	}

	/**
	 * Get afr to concrete strategies.
	 * @return AfrToConcreteStrategiesInterface
	 */
	public function getAfrToConcreteStrategies(): AfrToConcreteStrategiesInterface
	{
		//use default concrete
		if (empty($this->oAfrToConcreteStrategies)) {
			$this->oAfrToConcreteStrategies = AfrToConcreteStrategiesClass::getLatestInstance();
		}
		return $this->oAfrToConcreteStrategies;
	}

	/**
	 * Set afr to concrete strategies.
	 * @param AfrToConcreteStrategiesInterface $oAfrToConcreteStrategies
	 * @return AfrToConcreteStrategiesInterface
	 */
	public function setAfrToConcreteStrategies(
		AfrToConcreteStrategiesInterface $oAfrToConcreteStrategies
	): AfrToConcreteStrategiesInterface
	{
		return $this->oAfrToConcreteStrategies = $oAfrToConcreteStrategies;
	}

	/**
	 * Returns: 1|FQCN for instantiable; 2|FQCN for singleton; 0|notConcreteFQCN for fail
	 * @param string $sNotConcreteFQCN
	 * @param bool $bUseCache
	 * @param string|null $sTemporaryContextOverwrite
	 * @param string|null $sTemporaryPriorityRuleOverwrite
	 * @return string
	 * @throws AfrClassDependencyException
	 * @throws AfrInterfaceToConcreteException
	 */
	public function resolve(
		string $sNotConcreteFQCN,
		bool   $bUseCache = true,
		string $sTemporaryContextOverwrite = null, //TODO: addContextualBinding / getContextualConcrete|findInContextualBindings
		string $sTemporaryPriorityRuleOverwrite = null
	): string
	{
		if ($sTemporaryContextOverwrite !== null) {
			$sBackupContext = $this->getAfrToConcreteStrategies()->getContext();
			$this->getAfrToConcreteStrategies()->setContext($sTemporaryContextOverwrite);
		}

		if ($sTemporaryPriorityRuleOverwrite !== null) {
			$sBackupPriorityRule = $this->getAfrToConcreteStrategies()->getPriorityRule();
			$this->getAfrToConcreteStrategies()->setPriorityRule($sTemporaryPriorityRuleOverwrite);
		}

		if (!isset($this->aClassInterfaceToConcrete)) {
			$this->getClassInterfaceToConcrete(); //init map
		}
		$aMappings =
			!empty($this->aClassInterfaceToConcrete[$sNotConcreteFQCN]) &&
			is_array($this->aClassInterfaceToConcrete[$sNotConcreteFQCN]) ?
				$this->aClassInterfaceToConcrete[$sNotConcreteFQCN] : [];

		$sResoled =
			$this->getAfrToConcreteStrategies()->
			resolveMap($aMappings, $sNotConcreteFQCN, $bUseCache);

		//restore
		if ($sTemporaryContextOverwrite !== null) {
			$this->getAfrToConcreteStrategies()->setContext($sBackupContext);
		}
		if ($sTemporaryPriorityRuleOverwrite !== null) {
			$this->getAfrToConcreteStrategies()->setPriorityRule($sBackupPriorityRule);
		}
		return $sResoled;
	}


}
