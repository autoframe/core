<?php

namespace Autoframe\Core\Tenant;

use Autoframe\Core\Afr\Afr;
use Autoframe\Core\Afr\AfrExecutionThread;
use Autoframe\Core\Http\Request\AfrCliConstantsInterface;
use Autoframe\Core\CliTools\AfrCliHttpDetect;
use Autoframe\Core\CliTools\AfrCliPromptMenu;
use Autoframe\Core\CliTools\AfrGetOpt;
use Autoframe\Core\CliTools\AfrSysTempDir;
use Autoframe\Core\CliTools\AfrVendorDir;
use Autoframe\Core\Container\AfrDefaultBindings;
use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\Event\AfrEvent;
use Autoframe\Core\Event\Exception\AfrEventException;
use Autoframe\Core\Exception\AfrException;
use Autoframe\Core\FileSystem\DirPath\AfrDirPathClass;
use Autoframe\Core\Http\Header\AfrHttpStatusCode;
use Autoframe\Core\InterfaceToConcrete\AfrToConcreteStrategiesClass;
use Autoframe\Core\Module\AfrModuleBox;

/**
 * This class manages configuration settings and processes for an application that supports multiple tenants.
 */
class AfrTenant
{

	const AFR_NO_TENANT = '!Tenant';
	const AFR_BOOTSTRAP_PHP = 'bootstrap';
	/**
	 * @var AfrTenant[]
	 */
	protected static array $aTenantCfgIns = [];

	/**
	 * Following classes must implement AfrDefaultTenantConfigsInterface
	 * @var array|string[]
	 */
	protected static array $aAfrDefaultTenantConfigs = [
		AfrEvent::class,
		AfrDefaultBindings::class,
		AfrToConcreteStrategiesClass::class,
	//	AfrModuleBox::class,
	];

	public string $sTenantAlias;
	public string $sRoot;
	public bool $bDebug;
	public string $sEnv;
	public string $sTmpDir;
	public string $sLogsDirT;
	public string $sHtmlDir;
	public bool $bAssetDirMapAny;
	public string $sAssetsDir;
	public array $aProtocolDomain = [];
	protected array $aAssetsExtraDirs;


	/**
	 * @throws AfrException
	 */
	public function __construct(string $sTenantAlias)
	{
		$sTenantAlias = preg_replace('/[^ \w]+/', '_', $sTenantAlias);
		if (empty($sTenantAlias)) {
			throw new AfrException("Tenant '$sTenantAlias' can't be empty!");
		} elseif (isset(static::$aTenantCfgIns[$sTenantAlias])) {
			throw new AfrException("Tenant '$sTenantAlias' is already defined!");
		}
		$this->sTenantAlias = $sTenantAlias;
	}

	public static function pushDefaultTenantConfigs(array $aFQCN_implementing_AfrDefaultTenantConfigsInterface)
	{
		if (!$aFQCN_implementing_AfrDefaultTenantConfigsInterface) {
			return;
		}
		static::$aAfrDefaultTenantConfigs = array_merge(
			static::$aAfrDefaultTenantConfigs,
			$aFQCN_implementing_AfrDefaultTenantConfigsInterface
		);
	}

	/**
	 * @param bool $bCheckExistence
	 * @return string|null
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 * @throws AfrException
	 */
	public static function getTenantArgInCli(bool $bCheckExistence = true): ?string
	{
		$aTenant = AfrGetOpt::getInstanceNoContainerBindings()
			->setArgvFromArray($_SERVER['argv'] ?? [])
			->getopt('T:', ['tenant:']);
		$sTenant = (string)($aTenant['T'] ??
			$aTenant['tenant'] ??
			$_ENV['AFR_TENANT_CLI'] ??
			getenv('AFR_TENANT_CLI'));
		return empty($sTenant) || $bCheckExistence && empty(static::$aTenantCfgIns[$sTenant]) ? null : $sTenant;
	}

	public function setRoot(string $ROOT = '/'): self
	{
		$this->sRoot = $ROOT;
		return $this;
	}


	public function setEnv(string $AFR_ENV = null): self
	{
		if ($AFR_ENV === null) {
			$AFR_ENV = $_ENV['AFR_ENV'] ?? getenv('AFR_ENV') ?: 'DEV';
		}
		$this->sEnv = strtoupper((string)$AFR_ENV);
		return $this;
	}

	public function setTempDir(string $TMP_DIR = null, bool $bSystemTemp = false): self
	{
		if (empty($TMP_DIR)) {
			$TMP_DIR = ($bSystemTemp ? AfrSysTempDir::sysGetTempDir() : self::getBaseDirPath()) .
				DIRECTORY_SEPARATOR . 'AfrTemp';
		}
		$this->sTmpDir = (string)$TMP_DIR;
		return $this;
	}

	public function setHtmlDir(string $HTML_DIR = null): self
	{
		if (empty($HTML_DIR)) {
			$HTML_DIR = self::getBaseDirPath() . DIRECTORY_SEPARATOR . 'public_html_' . $this->sTenantAlias;
		}
		$this->sHtmlDir = (string)$HTML_DIR;
		return $this;
	}

	public function setLogsDir(string $LOGS_DIR = null): self
	{
		if (empty($LOGS_DIR)) {
			$LOGS_DIR = self::getBaseDirPath() . DIRECTORY_SEPARATOR .
				'logs' . DIRECTORY_SEPARATOR . $this->sTenantAlias;
		}
		$this->sLogsDirT = (string)$LOGS_DIR;
		return $this;
	}

	public function setAssetsDir(string $ASSETS_DIR = null, array $aMapExtra = [], bool $bMapAnyAsset = false): self
	{
		if (empty($ASSETS_DIR)) {
			$ASSETS_DIR = self::getBaseDirPath() . DIRECTORY_SEPARATOR . 'public_assets' .
				DIRECTORY_SEPARATOR . $this->sTenantAlias;
		}
		$this->bAssetDirMapAny = $bMapAnyAsset;
		$this->sAssetsDir = $ASSETS_DIR;

		$this->aAssetsExtraDirs = [];
		foreach (array_merge(['img', 'css', 'js', 'media', 'data'], $aMapExtra) as $sAsset) {
			$this->aAssetsExtraDirs[$sAsset] = $ASSETS_DIR . DIRECTORY_SEPARATOR . $sAsset;
		}

		return $this;
	}


	public function setDebug(bool $AFR_DEBUG = null): self
	{
		if ($AFR_DEBUG === null) {
			$AFR_DEBUG = self::isCli() ? (
				$_ENV['AFR_DEBUG'] ??
				getopt('', ['debug:'])['debug'] ??
				getopt('', ['debug::'])['debug'] ??
				getenv('AFR_DEBUG')
			) : (
				!empty($_COOKIE[md5(__FILE__)]) ||
				($_ENV['AFR_DEBUG'] ?? getenv('AFR_DEBUG'))
			);
		}
		$this->bDebug = (bool)$AFR_DEBUG;
		return $this;
	}


	public function setProtocolDomainName(array $aProtocolDomain = []): self
	{
		if (empty($aProtocolDomain)) {
			$aProtocolDomain = ['http://app.test', 'http://localhost:8088', 'http://localhost', 'http://127.0.0.1'];
			if ( //first tenant on dev
				empty(static::$aTenantCfgIns[$this->sTenantAlias]) &&
				!self::isCli() &&
				isset($this->sEnv) && $this->sEnv === 'DEV'
			) {
				$aProtocolDomain[] = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'];
			}
		}
		$this->aProtocolDomain = $aProtocolDomain;
		return $this;
	}

	public function autoSetupAndPushTenantConfig(): self
	{
		($this->sEnv ?? $this->setEnv());
		($this->bDebug ?? $this->setDebug());
		(empty($this->aProtocolDomain) ? $this->setProtocolDomainName() : false);
		(empty($this->sRoot) ? $this->setRoot() : false);
		(empty($this->sTmpDir) ? $this->setTempDir() : false);
		(empty($this->sHtmlDir) ? $this->setHtmlDir() : false);
		(empty($this->sLogsDirT) ? $this->setLogsDir() : false);
		(empty($this->aAssetsExtraDirs) ? $this->setAssetsDir() : false);
		return static::$aTenantCfgIns[$this->sTenantAlias] = $this;
	}

	public static function isCli(): bool
	{
		return AfrCliHttpDetect::isCli();
	}

	public static function getTenantEnvFilePath(): string { return self::$sTenantEnvFilePath; }

	public static function getProtocolHost(): string { return self::$sProtocolHost; }

	public static function getHost(): string { return explode('://', self::getProtocolHost())[1] ?? ''; }

	public static function getBaseDirPath(): ?string { return self::$sBaseDirPath ?? null; }

	public static function getTenantAlias(): ?string { return self::$sAppTenantAlias ?? null; }

	/**
	 * The fully qualified class name (string) or an object to extract class name from.
	 * @param string|object $sFQCN_implementing_AfrDefaultTenantConfigsInterface
	 *
	 * @return string The full file path for the class tenant config file
	 */
	public static function getAfrDefaultTenantConfigsForFqcn($sFQCN_implementing_AfrDefaultTenantConfigsInterface): ?string
	{
		if (empty($sBDP = static::getBaseDirPath())/* || empty($sTa = static::getTenantAlias())*/) return null;
		$sTa = self::$sAppTenantAlias; //TODO: daca nu am tenant alias, atunci nu se aplica config?
		return
			$sBDP . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR .
			$sTa . '.' .
			static::fqcnToBaseName($sFQCN_implementing_AfrDefaultTenantConfigsInterface) . '.php';
	}


//	public static function getTenantModulesConfigFilePath(): string { return self::$sTenantModuleConfigFilePath; }

//	public static function getTenantRoutesFilePath(): string { return self::$sTenantRoutesFilePath; }

	public static function getPublicHtmlDir(): string { return self::$sPublicHtmlDir; }

	public static function getPublicAssetsPath(): string { return self::$sPublicAssetsPath; }

	public static function getPublicAssetsDirs(): array { return self::$aPublicAssetsDirs; }

	public static function getPublicAssetsDirCssWeb(): string { return self::$sPublicAssetsDirCssWeb; }

	public static function getPublicAssetsDirJsWeb(): string { return self::$sPublicAssetsDirJsWeb; }

	public static function getPublicAssetsDirImgWeb(): string { return self::$sPublicAssetsDirImgWeb; }

	public static function getPublicAssetsDirMediaWeb(): string { return self::$sPublicAssetsDirMediaWeb; }

	public static function getPublicAssetsDirDataWeb(): string { return self::$sPublicAssetsDirDataWeb; }

	public static function getStorageDir(): string { return self::$sStorageDir . DIRECTORY_SEPARATOR . self::getTenantAlias(); }

	public static function getLogsDir(): string { return self::$sLogsDir; }

	public static function getCronLogsDir(): string { return self::getLogsDir() . DIRECTORY_SEPARATOR . 'Cron'; }

	/**
	 * @param string|object|null $soAliasSubDir
	 * @return string
	 * @throws AfrException
	 */
	public static function getTempDir($soAliasSubDir = null): string //todo: test
	{
		//AfrTenant::$sTempDir = ($bSystemTemp=0 ? AfrSysTempDir::sysGetTempDir() : AfrTenant::getBaseDirPath()) .DIRECTORY_SEPARATOR . 'AfrTemp';
		if (!isset(self::$sTempDir)) {
			if (!empty(self::$sBaseDirPath)) {
				self::loadConfigResolveTenantAliasProcessConfigOrInitSample();
			} else {
				return AfrSysTempDir::sysGetTempDirAliasSubDir($soAliasSubDir);//no tenant
			}
		}
		return AfrSysTempDir::sysGetTempDirAliasSubDir(
			$soAliasSubDir,
			self::$sTempDir . (self::getTenantAlias() ? DIRECTORY_SEPARATOR . self::getTenantAlias() : '')
		);
	}

	public static function getWebRoot(): string { return self::$sWebRoot; }

	public static function getAllTenants(): array { return static::$aTenantCfgIns; }

	protected static string $sTenantEnvFilePath;
//	protected static string $sTenantModuleConfigFilePath;
//	protected static string $sTenantRoutesFilePath;
	//protected static string $sToConcreteStrategiesFilePath;// = ''; //TODO not done: php file having a closure(AfrToConcreteStrategiesInterface)
	//protected static string $sContainerBindingsFilePath; // php file having a closure(###  container  ###)
	protected static string $sProtocolHost;
	protected static string $sWebRoot = '/';

	protected static array $aHttpParts;

	protected static string $sBaseDirPath;
	protected static string $sAppTenantAlias;
	protected static string $sPublicHtmlDir;
	protected static string $sLogsDir;
	protected static array $aPublicAssetsDirs;
	protected static string $sPublicAssetsPath;
	protected static string $sStorageDir;
	protected static string $sPublicAssetsDirImgWeb;
	protected static string $sPublicAssetsDirCssWeb;
	protected static string $sPublicAssetsDirJsWeb;
	protected static string $sPublicAssetsDirMediaWeb;
	protected static string $sPublicAssetsDirDataWeb;
	protected static string $sTempDir; // ($bSystemTemp=0 ? AfrSysTempDir::sysGetTempDir() : AfrTenant::getBaseDirPath()) .DIRECTORY_SEPARATOR . 'AfrTemp';
	protected static array $aInitSystemDirList = [];


	/**
	 * @throws AfrException
	 */
	public static function setBaseDirPath(string $sBaseDirPath): void
	{
		if (!empty(self::$sBaseDirPath) && $sBaseDirPath !== self::$sBaseDirPath)
			throw new AfrException("The base dir path already defined!");
		self::$sBaseDirPath = $sBaseDirPath;
	}


	public static function includeCommonTenantConstantsAllFromBaseDir(): void //TODO test namespaces
	{
		if (defined(AfrExecutionThread::BOOTSTRAP_BASEDIR_CONSTANTS_FOR_ALL_TENANTS)) return;
		define(AfrExecutionThread::BOOTSTRAP_BASEDIR_CONSTANTS_FOR_ALL_TENANTS, true);

		if (empty(self::$sBaseDirPath))
			die(AfrHttpStatusCode::getInstanceNoContainerBindings()->hStatusHeaderAndHtml(
				500, 'MISS CONFIGURED TENANT BASE PATH @ ' . __FUNCTION__
			));

		if (is_file($sConstantsPath = self::$sBaseDirPath . DIRECTORY_SEPARATOR . 'constants.php'))
			include_once $sConstantsPath;


	}

	/**
	 * @throws AfrException
	 */
	public static function loadConfigResolveTenantAliasProcessConfigOrInitSample(string $sBasePath = null): void
	{
		if (!empty(static::$sAppTenantAlias)) return;

		if ($sBasePath) self::setBaseDirPath($sBasePath);
		if (empty(self::getBaseDirPath())) throw new AfrException("The base dir path is empty!");

		if (!is_file($sTf = self::getBaseDirPath() . DIRECTORY_SEPARATOR . 'tenant.env.php')) {
			if (!is_dir(self::getBaseDirPath()) || !is_writable(self::getBaseDirPath()))
				throw new AfrException("The base dir path is not writable!");
			copy(__DIR__ . DIRECTORY_SEPARATOR . 'tenant.env.sample.php', $sTf);
			echo "\nInitialized sample tenant config file: $sTf\n";
			sleep(1);
			include($sTf);
			$sErrMsg = '';
			static::$sProtocolHost = '';//fix initialization errors
			foreach (static::$aTenantCfgIns as $oTenant) {
				static::$sAppTenantAlias = $oTenant->sTenantAlias;
				static::processConfig($oTenant);
				try {
					static::initFileSystem(true);
				} catch (AfrException $e) {
					$sErrMsg .= $e->getMessage();
				}
				static::$aInitSystemDirList = [];
				static::$sAppTenantAlias = '';
			}
			if ($sErrMsg) {
				throw new AfrException($sErrMsg);
			}
		} else {
			include($sTf);
		}
		static::processConfig(
			self::resolveTenantAlias()
		);
	}


	/**
	 * Converts a fully qualified class name (FQCN) to the base class name.
	 *
	 * @param string|object $mClassOrFqcn The fully qualified class name (string) or an object to extract class name from.
	 *
	 * @return string The base class name extracted from the FQCN.
	 */
	public static function fqcnToBaseName($mClassOrFqcn): string
	{
		$mClassOrFqcn = is_object($mClassOrFqcn) ? get_class($mClassOrFqcn) : (string)$mClassOrFqcn;
		$iPos = strrpos($mClassOrFqcn, '\\');
		return $iPos !== false ? substr($mClassOrFqcn, $iPos + 1) : $mClassOrFqcn;
	}

	/**
	 * @param AfrTenant $oTenant
	 * @return void
	 * @throws AfrException
	 */
	protected static function processConfig(AfrTenant $oTenant): void
	{
		$sBaseDirPath = self::getBaseDirPath() . DIRECTORY_SEPARATOR;
		$sTenantSubDir = DIRECTORY_SEPARATOR . static::$sAppTenantAlias;

		static::$sTenantEnvFilePath = $sBaseDirPath . static::$sAppTenantAlias . '.' . $oTenant->sEnv . '.env';
		//static::$sTenantModuleConfigFilePath = $sBaseDirPath . 'modules' . $sTenantSubDir . '.modules.php';
		//static::$aInitSystemDirList[] = $sBaseDirPath . 'modules';
		//static::$sTenantRoutesFilePath = $sBaseDirPath . 'routes' . $sTenantSubDir . '.routes.php';
		//static::$aInitSystemDirList[] = $sBaseDirPath . 'routes';


		static::$sStorageDir = $sBaseDirPath . 'storage';
		static::$sTempDir = $oTenant->sTmpDir;
		static::$aInitSystemDirList[] = $sBaseDirPath . 'config';
		static::$aInitSystemDirList[] = static::getStorageDir();
		static::$aInitSystemDirList[] = static::getTempDir();
		static::$aInitSystemDirList[] = static::getTempDir() . DIRECTORY_SEPARATOR . 'AfrMultiClassMapper';
		static::$aInitSystemDirList[] = static::$sPublicHtmlDir = $oTenant->sHtmlDir;
		static::$aInitSystemDirList[] = static::$sLogsDir = $oTenant->sLogsDirT;
		static::$aInitSystemDirList[] = static::getCronLogsDir();

		if (static::$sPublicAssetsPath = $oTenant->bAssetDirMapAny ? $oTenant->sAssetsDir : '') {
			static::$aInitSystemDirList[] = static::$sPublicAssetsPath;
		}
		static::$aInitSystemDirList[] = static::$aPublicAssetsDirs = $oTenant->aAssetsExtraDirs;
		static::$sPublicAssetsDirCssWeb = $oTenant->aAssetsExtraDirs['css'];
		static::$sPublicAssetsDirJsWeb = $oTenant->aAssetsExtraDirs['js'];
		static::$sPublicAssetsDirImgWeb = $oTenant->aAssetsExtraDirs['img'];
		static::$sPublicAssetsDirMediaWeb = $oTenant->aAssetsExtraDirs['media'];
		static::$sPublicAssetsDirDataWeb = $oTenant->aAssetsExtraDirs['data'];

		static::$sWebRoot = $oTenant->sRoot;

		static::$aInitSystemDirList[] = $sBaseDirPath . 'DataLayer'; // AfrDbConnectionManagerClass->dataLayerPath
		static::$aHttpParts = (array)parse_url(static::$sProtocolHost . $oTenant->sRoot);


	}

	/**
	 * @throws AfrException
	 */
	public static function initFileSystem(bool $bThrowErrorIfActionsDetected): array
	{
		$ds = DIRECTORY_SEPARATOR;
		$aActionMessages = [];
		static::mkdir(static::$aInitSystemDirList, $aActionMessages);


		if (!is_file($sAfrBootstrapFile = static::getBaseDirPath() . $ds . self::AFR_BOOTSTRAP_PHP . '.php')) {
			$sContents = "<?php\nrequire_once __DIR__ . DIRECTORY_SEPARATOR . ";
			$sContents .= "'" . AfrDirPathClass::getInstanceNoContainerBindings()->getRelativePath(
					AfrVendorDir::getVendorPath() . $ds . 'autoload.php',
					$sAfrBootstrapFile
				) . "';\n\n";
			$sContents .= "use Autoframe\Core\Afr\Afr;\n\n";
			$sContents .= 'Afr::makeApp(__DIR__, null)->run();' . "\n";
			file_put_contents($sAfrBootstrapFile, $sContents);
			$aActionMessages[] = 'File created: ' . $sAfrBootstrapFile;
		}

		/** @var $sFQCN_DTCI AfrDefaultTenantConfigsInterface */
		foreach (static::$aAfrDefaultTenantConfigs as $sFQCN_DTCI) {
			$sPath = static::getAfrDefaultTenantConfigsForFqcn($sFQCN_DTCI);
			if (!empty($sPath) && !file_exists($sPath) && ($sConfigPhpInc = $sFQCN_DTCI::sampleTenantDefaultConfig())) {
				$aActionMessages[] = 'Sample file initialized with bytes #' . ((int)file_put_contents(
						$sPath,
						$sConfigPhpInc
					)) . ' -> ' . $sPath;
			}
		}


		foreach (static::$aTenantCfgIns as $oTenant) {
			if (!file_exists($htaccessFile = $oTenant->sHtmlDir . DIRECTORY_SEPARATOR . '.htaccess')) {
				copy(__DIR__ . DIRECTORY_SEPARATOR . '.htaccess.sample', $htaccessFile);
				$aActionMessages[] = 'Sample htaccess initialized ' . $htaccessFile;
			}
			//TODO: tenant-alias.php
			$tenantEntryPhp = static::getBaseDirPath() . DIRECTORY_SEPARATOR .
				self::AFR_BOOTSTRAP_PHP . '.' . $oTenant->sTenantAlias . '.php';
			if (!file_exists($tenantEntryPhp)) {
				file_put_contents(
					$tenantEntryPhp,
					"<?php\n" .
					'$_ENV["AFR_TENANT_CLI"] = "' . $oTenant->sTenantAlias . '";' . PHP_EOL .
					'include(__DIR__.DIRECTORY_SEPARATOR."' . self::AFR_BOOTSTRAP_PHP . '.php");'
				);
				$aActionMessages[] = 'Tenant Entry php initialized ' . $tenantEntryPhp;
			}
		}


		if (!is_file($f = self::getTempDir() . $ds . '.gitignore')) {
			file_put_contents($f, "*\n!.gitignore\n");
		}


		if (!file_exists(static::getTenantEnvFilePath())) {
			copy(__DIR__ . DIRECTORY_SEPARATOR . 'env.sample.env', static::getTenantEnvFilePath());
			//file_put_contents(static::getTenantEnvFilePath(), "APP_ENV_ROCKET=🚀\nMULTI1=foo\nMULTI2=\${MULTI1}");
			$aActionMessages[] = '🚀 Environment file blank initialized: ' . static::getTenantEnvFilePath();
		}

		if ($bThrowErrorIfActionsDetected && !empty($aActionMessages)) {
			throw new AfrException("\n🌟 🚀 🏆\n" . implode("\n", $aActionMessages) . "\n✨ 🚀 🎉\n");
		}
		return $aActionMessages;
	}

	protected static function mkdir(array $aDirs, array &$aErrors): void
	{
		foreach ($aDirs as $sPath) {
			if (is_string($sPath) && !is_dir($sPath)) {
				if (!AfrDirPathClass::dirExistAndWritableS($sPath, true, 0775)) {
					$aErrors[] = 'Directory initialised: ' . $sPath;
				}
			} elseif (is_array($sPath)) {
				static::mkdir($sPath, $aErrors);
			}
		}
	}

	public static function getTenantEnvFile(): string
	{
		return self::getBaseDirPath() . DIRECTORY_SEPARATOR . self::getTenantAlias() . '.' . $_ENV['AFR_ENV'] . '.env';
	}

	/**
	 * @return void
	 * @throws AfrException
	 */
	protected static function resolveTenantAlias(): AfrTenant
	{
		if (!empty(static::$sAppTenantAlias)) return static::$aTenantCfgIns[static::$sAppTenantAlias];

		if (empty(static::$sAppTenantAlias)) {
			if (empty(static::$aTenantCfgIns)) {
				//(new AfrTenant('dev'))->setEnv('dev')->setDebug(true)->autoSetupAndPushTenantConfig();
				throw new AfrException(
					'At least one tenant is required for processing configuration.' . PHP_EOL .
					'Check ' . self::getBaseDirPath() . DIRECTORY_SEPARATOR . 'tenant.env.php'
				);
			}
			if (!static::IsCli()) {
				$sProtocolDomain = strtolower($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST']);
				/**
				 * HTTP:
				 * $_SERVER['SERVER_NAME'] match inside AfrTenant::HOST_LIST
				 */
				foreach (static::$aTenantCfgIns as $sAppTenantAlias => $oTenant) {
					if (
						in_array($sProtocolDomain, $oTenant->aProtocolDomain) ||
						($_ENV['AFR_TENANT_CLI'] ?? '') === $oTenant->sTenantAlias
					) {
						static::$sAppTenantAlias = $sAppTenantAlias;
						static::$sProtocolHost = $sProtocolDomain;
						//
						break;
					}
				}
			} else {
				/**
				 * CLI:
				 * set using argv params; eg: php index.php
				 * -T="tenantName"
				 * --tenant='sub.domain.tld'
				 * -T tenantName
				 * --tenant 'domain.com'
				 * $sTenantEnv = getenv('AFR_TENANT') ?? $_ENV['AFR_TENANT'] ?? null;
				 * !! set using ENV, but this is not recommended for multi tenant in CLI calling
				 */

				$sTenantArg = self::getTenantArgInCli();
				if (!empty($sTenantArg) && !empty(static::$aTenantCfgIns[$sTenantArg])) {
					static::$sAppTenantAlias = $sTenantArg;
				} else {

					if (count(static::$aTenantCfgIns) === 1) {
						static::$sAppTenantAlias = (string)key(static::$aTenantCfgIns);
					} else {
						$options = array_keys(static::$aTenantCfgIns);
						static::$sAppTenantAlias = self::getAutoTenantSelectArgvFlags() ?? AfrCliPromptMenu::promptMenu(
							'Or run php script.php -T="tenantName" --tenant="sub.domain.tld"',
							$options,
							$options[0],
							2,
							"️️What tenant to choose from this list?\n🔥 Available Tenants"
						);
					}
				}
				static::$sProtocolHost = reset((static::$aTenantCfgIns[static::$sAppTenantAlias])->aProtocolDomain);
			}
		}

		// FALLBACK: If no tenant match found, then we render the first tenant key
		if (empty(static::$sAppTenantAlias) && defined($sTenantDefault = 'AFR_TENANT_DEFAULT')) {
			static::$sAppTenantAlias = constant($sTenantDefault);
			// static::$sAppTenantAlias = (string)key(static::$aTenantCfgIns);
		}

		if (empty(static::$sAppTenantAlias) || empty(static::$aTenantCfgIns[static::$sAppTenantAlias])) {
			if (!static::isCli()) {
				http_response_code(421);
				throw new AfrException(
					'Tenant host was not matched for: ' . $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST']
				);
			} else {
				throw new AfrException(
					'Tenant miss configured!' . PHP_EOL . ' Use params eg: ' .
					'php index.php -T="tenantName" --tenant="sub.domain.tld"'
				);
			}

		}

		/** @var AfrTenant $oTenant */
		$oTenant = static::$aTenantCfgIns[static::$sAppTenantAlias];
		foreach (['AFR_ENV' => $oTenant->sEnv, 'AFR_DEBUG' => $oTenant->bDebug] as $key => $value) {
			$value = strtoupper($value);
			$_ENV[$key] = $value;
			$_SERVER[$key] = $value;
			putenv(sprintf('%s=%s', $key, $value));
			//if (!defined($key)) {	define($key, $value);	}
		}
		return $oTenant;

	}

	public static function getAutoTenantSelectArgvFlags(): ?string
	{
		if (empty(static::$aTenantCfgIns)) return null;

		$aFramework = [
			AfrCliConstantsInterface::CRON_LIVE_LOGS_ARGV_KEY => '::first',
		];
		if (defined('AutoTenantSelectArgvFlags') && is_array(constant('AutoTenantSelectArgvFlags'))) {
			$aFramework = array_merge($aFramework, constant('AutoTenantSelectArgvFlags'));
		}

		$aTenantsAliases = array_keys(static::$aTenantCfgIns);
		$aCliArgs = AfrGetOpt::getInstanceNoContainerBindings()
			->getoptDetectAllArgs($_SERVER['argv'] ?? [], true);

		foreach ($aFramework as $sArgKey => $sModify) {
			if (isset($aCliArgs[$sArgKey])) {
				if ($sModify === '::last' || $sModify == -1) {
					return array_pop($aTenantsAliases);
				} elseif (in_array($sModify, $aTenantsAliases)) {
					return $sModify;
				} elseif (!empty($aTenantsAliases[$sModify])) {
					return $aTenantsAliases[$sModify];
				}
				return array_shift($aTenantsAliases);
			}
		}
		return null;
	}


}