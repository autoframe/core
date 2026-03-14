<?php
declare(strict_types=1);

namespace Autoframe\Core\Config;


/**
 * https://laravel.com/docs/10.x/configuration
 * 'debug' => env('APP_DEBUG', false), //The second value passed to the env function is the "default value".
 * This value will be returned if no environment variable exists for the given key.
 * APP_NAME="My Application"
 * Before loading your application's environment variables, Laravel determines if an APP_ENV environment
 * variable has been externally provided or if the --env CLI argument has been specified. If so,
 * Laravel will attempt to load an .env.[APP_ENV] file if it exists. If it does not exist, the default .env file will be loaded.
 *
 * use Illuminate\Support\Facades\App;
 * $environment = App::environment();
 * if (App::environment('dev')) { // The environment is local }
 * if (App::environment(['dev', 'staging'])) { // The environment is either local OR staging... }
 *
 * $value = config('app.timezone');
 * config(['app.timezone' => 'America/Chicago']);
 *
 * https://www.bannerbear.com/ # crop poze cu centrare conform ai
 *
 */

/**
 * Nice config using namespace + ClassName or InterfaceName or TraitName
 * The string argument from the constructor is used as an array key for identification
 * Can also store some array extra data by using assignData()
 */
final class AfrConfig
{
    private string $sNamespaceAndClassOrTraitOrInterfaceOrKey;
    private array $aConstructorArgs = [];
    private array $aConstants = [];
    protected bool $bConstantsDeployed = false;
    private array $aMethods = [];
    private array $aStaticMethods = [];
    private array $aProperties = [];
    private array $aStaticProperties = [];
    private array $aData = [];
    private bool $bPreventExistenceErrors = false;

    /**
     * Nice config using namespace + ClassName or InterfaceName or TraitName
     * The string argument from the constructor is used as an array key for identification
     * Can also store some array extra data by using assignData()
     * @param string $sNamespaceAndClassOrTraitOrInterfaceOrKey
     * @param array $aData
     * @param bool $bMergeWithExistingDataFromAfrConfigRegister
     */
    public function __construct(
        string $sNamespaceAndClassOrTraitOrInterfaceOrKey,
        array  $aData = [],
        bool   $bMergeWithExistingDataFromAfrConfigRegister = true
    )
    {
        $this->sNamespaceAndClassOrTraitOrInterfaceOrKey = $sNamespaceAndClassOrTraitOrInterfaceOrKey;
        // Magic, if class is already registered, then do not overwrite existing data and load it into the current config
        if ($bMergeWithExistingDataFromAfrConfigRegister) {
            $oExisting = AfrConfigRegister::getInstance()->getConfigByKey($this->sNamespaceAndClassOrTraitOrInterfaceOrKey);
            if ($oExisting instanceof AfrConfig) {
                $this->aConstructorArgs = $oExisting->getConstructorArgs();
                $this->aConstants = [['', $oExisting->getConstants()]];
                $this->aMethods = $oExisting->getMethods();
                $this->aStaticMethods = $oExisting->getStaticMethods();
                $this->aProperties = $oExisting->getProperties();
                $this->aStaticProperties = $oExisting->getStaticProperties();
                $this->aData = $oExisting->getData();
                $this->bPreventExistenceErrors = $oExisting->getPreventExistenceErrors();
                $this->bConstantsDeployed = $oExisting->bConstantsDeployed;
            }
        }
        if (!empty($aData)) {
            $this->assignData($aData);
        }
    }

    /**
     * Assign data.
     * @param array $aData
     * @return $this
     */
    public function assignData(array $aData): AfrConfig
    {
        $this->aData = array_merge($this->aData, $aData);
        $this->saveConfig();
        return $this;
    }

    /**
     * Assign KEY => value array
     * Namespace is optional
     * @param array $aConstants
     * @param string $sNamespace
     * @return $this
     */
    public function assignConstants(array $aConstants, string $sNamespace = '\\'): AfrConfig
    {
        $this->aConstants[] = [$sNamespace, $this->sanitizeProperties($aConstants)];
        $this->bConstantsDeployed = false;
        $this->saveConfig();
        return $this;
    }

    /**
     * Define constants.
     * @return AfrConfig
     */
    public function defineConstants(): AfrConfig
    {
        if (!$this->bConstantsDeployed) {
            foreach ($this->getConstants() as $sNsConstant => $mValue) {
                if (!defined($sNsConstant)) {
                    define($sNsConstant, $mValue);
                }
            }
            $this->bConstantsDeployed = true;
            $this->saveConfig();
        }
        return $this;
    }

    /**
     * Get constants.
     * @return array
     */
    public function getConstants(): array
    {
        $aOut = [];
        foreach ($this->aConstants as $aConstantNsBlock) {
            $sNamespace = $aConstantNsBlock[0];
            foreach ($aConstantNsBlock[1] as $sConstantName => $mValue) {
                $sNsConstant = trim($sNamespace . $sConstantName, '\ ');
                if (!isset($aOut[$sNsConstant])) {
                    $aOut[$sNsConstant] = defined($sNsConstant) ? constant($sNsConstant) : $mValue;
                }
            }
        }
        return $aOut;
    }


    /**
     * Assign constructor args.
     * @param array $aArgs
     * @return $this
     */
    public function assignConstructorArgs(array $aArgs): AfrConfig
    {
        $this->aConstructorArgs[] = $aArgs;
        $this->saveConfig();
        return $this;
    }

    /**
     * Assign properties.
     * @param array $aProperties
     * @return $this
     */
    public function assignProperties(array $aProperties): AfrConfig
    {
        $this->aProperties = array_merge($this->aProperties, $this->sanitizeProperties($aProperties));
        $this->saveConfig();
        return $this;
    }

    /**
     * Assign static properties.
     * @param array $aProperties
     * @return $this
     */
    public function assignStaticProperties(array $aProperties): AfrConfig
    {
        $this->aStaticProperties = array_merge($this->aStaticProperties, $this->sanitizeProperties($aProperties));
        $this->saveConfig();
        return $this;
    }


    /**
     * Assign method.
     * @param string $sMethodName
     * @param array $aArgs
     * @return $this
     */
    public function assignMethod(string $sMethodName, array $aArgs = []): AfrConfig
    {
        $this->aMethods[] = [$sMethodName, $aArgs];
        $this->saveConfig();
        return $this;
    }

    /**
     * Assign static method.
     * @param string $sMethodName
     * @param array $aArgs
     * @return $this
     */
    public function assignStaticMethod(string $sMethodName, array $aArgs = []): AfrConfig
    {
        $this->aStaticMethods[] = [$sMethodName, $aArgs];
        $this->saveConfig();
        return $this;
    }

    /**
     * Assign prevent existence errors.
     * @param bool $bPreventExistenceErrors
     * @return $this
     */
    public function assignPreventExistenceErrors(bool $bPreventExistenceErrors): AfrConfig
    {
        $this->bPreventExistenceErrors = $bPreventExistenceErrors;
        $this->saveConfig();
        return $this;
    }

    /**
     * Get namespace and class or trait or interface or key.
     * @return string
     */
    public function getNamespaceAndClassOrTraitOrInterfaceOrKey(): string
    {
        return $this->sNamespaceAndClassOrTraitOrInterfaceOrKey;
    }

    /**
     * Get constructor args.
     * @return array
     */
    public function getConstructorArgs(): array
    {
        return $this->aConstructorArgs;
    }

    /**
     * Get methods.
     * @return array
     */
    public function getMethods(): array
    {
        return $this->aMethods;
    }

    /**
     * Get static methods.
     * @return array
     */
    public function getStaticMethods(): array
    {
        return $this->aStaticMethods;
    }

    /**
     * Get properties.
     * @return array
     */
    public function getProperties(): array
    {
        return $this->aProperties;
    }

    /**
     * Get static properties.
     * @return array
     */
    public function getStaticProperties(): array
    {
        return $this->aStaticProperties;
    }

    /**
     * Get data.
     * @return array
     */
    public function getData(): array
    {
        return $this->aData;
    }

    /**
     * Get prevent existence errors.
     * @return bool
     */
    public function getPreventExistenceErrors(): bool
    {
        return $this->bPreventExistenceErrors;
    }

    /**
     * @return bool
     */
    private function saveConfig(): bool
    {
        $bStatus = AfrConfigRegister::getInstance()->registerConfig($this);
        if (!$bStatus) {
            echo 'Error to apply config to ' . $this->getNamespaceAndClassOrTraitOrInterfaceOrKey() . PHP_EOL;
            //TODO adauga log eroare aici pe live
        }
        return $bStatus;
    }

    /**
     * @param array $aProperties
     * @return array
     */
    private function sanitizeProperties(array $aProperties): array
    {
        //exclude integer keys
        foreach ($aProperties as $key => $val) {
            if (is_numeric($key)) {
                unset($aProperties[$key]);
            }
        }
        return $aProperties;
    }

}
