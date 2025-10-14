<?php

namespace Autoframe\Core\Cron;

use Autoframe\Core\Afr\Afr;
use Autoframe\Core\Cron\Log\AfrCronLoggerClass;
use Autoframe\Core\Cron\Log\AfrCronLoggerInterface;
use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;
use Autoframe\Core\Env\Exception\AfrEnvException;
use Autoframe\Core\Exception\AfrException;
use Autoframe\Core\Module\AfrModuleBox;
use Autoframe\Core\Module\AfrModuleCLIRoutesInterface;
use Autoframe\Core\Module\AfrModuleInterface;
use Autoframe\Core\CliTools\AfrSysTempDir;
use Autoframe\Core\Tenant\AfrTenant;


final class AfrConJobSources extends AfrSingletonAbstractClass
{
	const FGC = 'file.get.contents';
	const URL_S = 'curl';
	const CLOSURE_FN = 'closure';
	protected array $aSources = [];
	protected ?array $aJobs = null;
	protected array $aCronSourceNotFoundSafeguard = [];
	protected array $aCronLoadTime = [];
	public static int $iCronNotFoundSafeguardMax = 60;
	public static array $aDefaultCurlSetOpt; //curl_setopt_array

	/**
	 * @var AfrCronLoggerClass|AfrCronLoggerInterface|null
	 */
	protected ?AfrCronLoggerInterface $oAfrCronLogger = null;

	/**
	 * @throws AfrException
	 */
	public function getSourcesFreshFromModules() //TODO!!!!!!
	{
		//TODO: REMOVE THIS
		die('TODO: IMPLEMENT ' . __FUNCTION__);
		//	AfrConJobSources::getInstance()->addFileSource('demo','http://localhost:808/core/src/Cron/AfrCronJobDaemon.DemoCron.txt');

		if (empty(Afr::app())) {
			throw new AfrException('Afr app not configured');
		}
		$this->flushSources();
		AfrRouterConstantsInterface::CLI_CRON_JOB_REQUEST;
		Afr::app()
			->container()
			->get(AfrModuleBox::class)
			->registerModulesThatImplementTheInterface(AfrModuleCLIRoutesInterface::class); //TODO!!!!!!
	}

	/**
	 * @param string $sAliasKey
	 * @param string $sFullFilePath
	 * @param resource|null $rFileGetContentsResourceContext
	 * @return $this
	 */
	public function addFileSource(string $sAliasKey, string $sFullFilePath, $rFileGetContentsResourceContext = null): self
	{
		$this->aSources[$sAliasKey] = [self::FGC, $sFullFilePath, $rFileGetContentsResourceContext];
		return $this->flushJobsForAlias($sAliasKey);
	}

	public function getFileSources(): array
	{
		return $this->getFilteredSources(self::FGC);
	}

	/**
	 * @param string $sAliasKey
	 * @param string $sUrl
	 * @param array|null $aCurlSetOptArray
	 * @return $this
	 */
	public function addUrlSource(string $sAliasKey, string $sUrl, array $aCurlSetOptArray = null): self
	{
		$this->aSources[$sAliasKey] = [self::URL_S, $sUrl, $aCurlSetOptArray ?? []];
		return $this->flushJobsForAlias($sAliasKey);
	}


	public function getUrlSources(): array
	{
		return $this->getFilteredSources(self::URL_S);

	}

	/**
	 * @param string $sAliasKey
	 * @param \Closure $closure The closure must return a multiline string with the correct job description
	 * @return $this
	 */
	public function addSourceFromClosure(string $sAliasKey, \Closure $closure): self
	{
		$this->aSources[$sAliasKey] = [self::CLOSURE_FN, $closure];
		return $this->flushJobsForAlias($sAliasKey);
	}

	public function getClosureSources(): array
	{
		return $this->getFilteredSources(self::CLOSURE_FN);
	}

	protected function getFilteredSources(string $sType): array
	{
		$aReturn = [];
		foreach ($this->aSources as $sAliasKey => $aSources) {
			if ($aSources[0] === $sType) {
				$aReturn[$sAliasKey] = $aSources;
			}
		}
		return $aReturn;
	}

	public function getAllSources(): array
	{
		return $this->aSources;
	}

	/**
	 * @param bool $bRefreshInstanceCache
	 * @return AfrCronJob[][] Layered array of having key aliases for class AfrCronJobDaemon
	 * @throws AfrEnvException
	 */
	public function getAllJobs(bool $bRefreshInstanceCache = false): array
	{
		if (!$bRefreshInstanceCache && !empty($this->aJobs)) {
			return $this->aJobs;
		}
		$this->aJobs = [];
		foreach ($this->aSources as $sAliasKey => $aSources) {
			$sType = $aSources[0];
			if ($sType === self::CLOSURE_FN) {
				$this->loadCronJobsFromClosure($sAliasKey);
			} elseif ($sType === self::FGC) {
				$this->loadCronJobsFromFile($sAliasKey);
			} elseif ($sType === self::URL_S) {
				$this->loadCronJobsFromHttp($sAliasKey);
			}
		}
		return $this->aJobs;
	}


	public function moveAliasFirst(string $sAliasKey): bool
	{
		if (empty($this->aSources[$sAliasKey])) {
			return false;
		}
		$aData = $this->aSources[$sAliasKey];
		unset($this->aSources[$sAliasKey]);
		$this->aSources = [$sAliasKey => $aData] + $this->aSources;
		return true;
	}

	public function moveAliasLast(string $sAliasKey): bool
	{
		if (empty($this->aSources[$sAliasKey])) {
			return false;
		}
		$aData = $this->aSources[$sAliasKey];
		unset($this->aSources[$sAliasKey]);
		$this->aSources[$sAliasKey] = $aData;
		return true;
	}

	public function moveAliasAfter(string $sAliasKeyFirst, $sAliasKeySecond): bool
	{
		if (empty($this->aSources[$sAliasKeyFirst]) || empty($this->aSources[$sAliasKeySecond])) {
			return false;
		}
		$aNewSources = [];
		foreach ($this->aSources as $sAliasKey => $aData) {
			if ($sAliasKey === $sAliasKeySecond) {
				continue;
			}
			$aNewSources[$sAliasKey] = $aData;
			if ($sAliasKey === $sAliasKeyFirst) {
				$aNewSources[$sAliasKeySecond] = $this->aSources[$sAliasKeySecond];
			}
		}
		$this->aSources = $aNewSources;
		return true;
	}

	public function flushSources(): self
	{
		foreach ($this->aSources as $sAliasKey => $aSources) {
			$this->flushJobsForAlias($sAliasKey);
		}
		$this->aSources = [];
		return $this;
	}

	public function flushAliasSource(string $sAliasKey): self
	{
		if (!empty($this->aSources[$sAliasKey])) {
			$this->flushJobsForAlias($sAliasKey);
			unset($this->aSources[$sAliasKey]);
		}
		return $this;
	}

	/**
	 * @param string $sSourceAlias
	 * @return void
	 */
	public function flushJobsForAlias(string $sSourceAlias): self
	{
		if (!empty($this->aJobs[$sSourceAlias])) {
			foreach ($this->aJobs[$sSourceAlias] as $k => $oJob) {
				unset($this->aJobs[$sSourceAlias][$k]);
				unset($oJob); //destruct
			}
		}
		if (isset($this->aJobs[$sSourceAlias])) {
			unset($this->aJobs[$sSourceAlias]);
		}
		$this->aCronLoadTime[$sSourceAlias] ??= -1;
		return $this;
	}

	public function setAfrCronLogger(?AfrCronLoggerInterface $oAfrCronLogger): self
	{
		$this->oAfrCronLogger = $oAfrCronLogger;
		return $this;
	}

	//TODO:  fallback logger din DAEMON / WORKER DACA GASESC ACOLO!!!!
	public function log(string $sMessage, bool $bError = false, int $exitCode = null): void
	{
		if (!$this->oAfrCronLogger) {
			$bError && error_log($sMessage . (isset($exitCode) ? ' #' . $exitCode : ''));
			if (Afr::app()) {
				Afr::app()->container()->get(AfrCronLoggerInterface::class)->log($sMessage, $bError, $exitCode);
			}
			return;
		}
		$this->oAfrCronLogger->log($sMessage, $bError, $exitCode);
	}

	protected function loadCronJobsFromFile(string $sSourceAlias): int
	{
		if (empty($this->aSources[$sSourceAlias])) {
			$this->log('Empty cron job data source alias: ' . $sSourceAlias, true);
			return 0;
		}
		[$sType, $sFilePath, $rFileGetContentsResourceContext] = $this->aSources[$sSourceAlias];
		if ($sType != self::FGC || empty($sFilePath)) {
			$this->log('Not a file cron job data source alias: ' . $sSourceAlias, true);
			return 0;
		}
		$this->aCronSourceNotFoundSafeguard[$sFilePath] ??= 0;

		$sErrMsg = null;
		if (!file_exists($sFilePath)) {
			$this->aCronSourceNotFoundSafeguard[$sFilePath]++;
			$sErrMsg = 'Cron jobs file disappeared: ' . $sSourceAlias . ', ' . $sFilePath;
		}
		if ($this->aCronSourceNotFoundSafeguard[$sFilePath] > self::$iCronNotFoundSafeguardMax) {
			$sErrMsg = 'Cron jobs file not found: ' .
				$this->aCronSourceNotFoundSafeguard[$sFilePath] .
				' times! ' . $sSourceAlias . ', ' . $sFilePath;
		}
		if ($sErrMsg) {
			$this->log($sErrMsg, true);
			return 0;
		}

		if (($iFmTime = filemtime($sFilePath)) + 2 >= time()) {
			$this->log('Cron job file just written, so we wait at least 2 seconds: ' . $sSourceAlias . ', ' . $sFilePath);
			return 0; //cron file was just written, so we wait
		}

		if ($this->aCronLoadTime[$sSourceAlias] < $iFmTime) {
			$this->aCronLoadTime[$sSourceAlias] = $iFmTime;
		} else {
			$this->log('Cron job file not changed: ' . $sSourceAlias . ', ' . $sFilePath);
			return count($this->aJobs[$sSourceAlias]);
		}
		$sData = $rFileGetContentsResourceContext ?
			file_get_contents($sFilePath, false, $rFileGetContentsResourceContext) :
			file_get_contents($sFilePath);
		if ($sData !== false) {
			$this->aCronSourceNotFoundSafeguard[$sFilePath] = 0; //reset
		} else {
			$this->aCronSourceNotFoundSafeguard[$sFilePath]++;
			$this->log('Unable to read job file: ' . $sSourceAlias . ', ' . $sFilePath, true);
			return -2;
		}

		$this->flushJobsForAlias($sSourceAlias);
		$this->aJobs[$sSourceAlias] = AfrCronJob::parseLines($sData);
		if (($iLoaded = count($this->aJobs[$sSourceAlias])) < 1) {
			$this->log('The job file has no jobs inside: ' . $sSourceAlias . ', ' . $sFilePath, true);
		}

		return $iLoaded;
	}


	/**
	 * @throws AfrEnvException
	 */
	protected function loadCronJobsFromHttp(string $sSourceAlias): int
	{
		if (empty($this->aSources[$sSourceAlias])) {
			$this->log('Empty cron job url data source alias: ' . $sSourceAlias, true);
			return 0;
		}
		[$sType, $sUrl, $aCurlSettings] = $this->aSources[$sSourceAlias];
		if ($sType != self::URL_S || empty($sUrl)) {
			$this->log('Not a url cron job data source alias: ' . $sSourceAlias, true);
			return 0;
		} elseif (empty(filter_var($sUrl, FILTER_VALIDATE_URL))) {
			$this->log('Invalid url cron job data source alias: ' . $sSourceAlias . ', ' . $sUrl, true);
			return 0;
		}

		$sTempCache = AfrSysTempDir::sysGetTempDirAliasSubDir(__CLASS__) .
			DIRECTORY_SEPARATOR . md5($sUrl) . '.url';


		$sErrMsg = $bLoadedFromCacheFile = null;
		$sContents = $this->getUrlData($sUrl, $aCurlSettings);
		if ($sContents !== false) {
			$this->aCronLoadTime[$sSourceAlias] = time();
			$this->aCronSourceNotFoundSafeguard[$sUrl] = 0; //reset
			file_put_contents($sTempCache, $sContents);
		} else {
			$this->aCronSourceNotFoundSafeguard[$sUrl] ??= 0; //init null
			$this->aCronSourceNotFoundSafeguard[$sUrl]++;
			if (is_file($sTempCache)) {
				$this->aCronLoadTime[$sSourceAlias] = filemtime($sTempCache);
				$sContents = file_get_contents($sTempCache);
				$bLoadedFromCacheFile = true;
			} else {
				$sSinceTs = !empty($this->aCronLoadTime[$sSourceAlias]) ? date('Y-m-d H:i:sO', $this->aCronLoadTime[$sSourceAlias]) : 'NEVER';
				$sErrMsg = trim(
					'Unable open job file: ' . $sSourceAlias . ', ' . $sUrl . ' since Ts(' . $sSinceTs .	')' . (error_get_last()['message'] ?? '')
				);
			}
		}

		if ($bLoadedFromCacheFile) {
			$this->log('Using fallback jobs cache for url ' . $sUrl);
		} elseif ($this->aCronSourceNotFoundSafeguard[$sUrl] > self::$iCronNotFoundSafeguardMax) {
			$sSinceTs = !empty($this->aCronLoadTime[$sSourceAlias]) ? date('Y-m-d H:i:sO', $this->aCronLoadTime[$sSourceAlias]) : 'NEVER';
			$sErrMsg = 'Cron jobs url source not accessible: ' .
				$this->aCronSourceNotFoundSafeguard[$sUrl] .
				' times, ' . ' since Ts(' . $sSinceTs . ')' .
				$sSourceAlias . ', ' . $sUrl;
		}
		if ($sErrMsg) {
			$this->log($sErrMsg, true);
			return 0;
		}
		$this->flushJobsForAlias($sSourceAlias);
		if (strlen($sContents) < 10) {
			$iLoaded = 0;
			if(!$bLoadedFromCacheFile){
				$this->log('Cron jobs url source is empty: ' . $sUrl . ' B64:' . base64_encode($sContents), true);
			}
		} else {
			$this->aJobs[$sSourceAlias] = AfrCronJob::parseLines($sContents);
			if (($iLoaded = count($this->aJobs[$sSourceAlias])) < 1) {
				$this->log('The job url has no jobs inside: ' . $sSourceAlias . ', ' . $sUrl . ' B64:' . base64_encode($sContents), true);
			}
		}

		return $iLoaded;
	}


	protected function loadCronJobsFromClosure(string $sSourceAlias): int
	{
		if (empty($this->aSources[$sSourceAlias])) {
			$this->log('Empty cron job Closure data source alias: ' . $sSourceAlias, true);
			return 0;
		}
		[$sType, $oClosure] = $this->aSources[$sSourceAlias];
		if ($sType != self::CLOSURE_FN || !$oClosure instanceof \Closure) {
			$this->log('Not a Closure cron job data source alias: ' . $sSourceAlias, true);
			return 0;
		}
		try {
			$sContents = $oClosure();
			$this->aCronLoadTime[$sSourceAlias] = time();
		} catch (\Throwable $ex) {
			$this->log(
				'CORRUPTED cron job Closure method: ' . $sSourceAlias . ': ' .
				$ex->getMessage() . "\t " .
				'Code(' . $ex->getCode() . ') ' .
				'File ' . $ex->getFile() . ':' . $ex->getLine(),
				true
			);
			return 0;
		}

		$sType = gettype($sContents ?? null);
		$sContents ??= '';
		if (!is_string($sContents)) {
			$this->log('Invalid cron jobs Closure source ' . $sSourceAlias . '. Return is of type: ' . $sType, true);
			return 0;
		}

		$this->flushJobsForAlias($sSourceAlias);
		if (strlen($sContents) < 10) {
			$iLoaded = 0;
			$this->log('Cron jobs Closure source is empty: ' . $sSourceAlias . ' B64:' . base64_encode($sContents), true);
		} else {
			$this->aJobs[$sSourceAlias] = AfrCronJob::parseLines($sContents);
			if (($iLoaded = count($this->aJobs[$sSourceAlias])) < 1) {
				$this->log('The job Closure source has no jobs inside: ' . $sSourceAlias . ' B64:' . base64_encode($sContents), true);
			}
		}

		return $iLoaded;
	}

	/**
	 * @param string $sUrl
	 * @param array|null $aSettings
	 * @return false|string
	 * @throws AfrEnvException
	 */
	protected function getUrlData(string $sUrl, array $aSettings = null)
	{
		$aSettings = $this->getDefaultCurlSetOpt() + ($aSettings ?? []); //var_dump($aSettings);

		if (empty($ch = curl_init($sUrl))) {
			$this->log('Fail to initiate cURL: ' . $sUrl, true);
		} elseif (empty(curl_setopt_array($ch, $aSettings))) {
			$this->log('Fail to set options cURL: ' . $sUrl, true);
		} elseif (($response = curl_exec($ch)) === false) {
			$this->log('Fail to exec cURL: ' . $sUrl . "\t" . curl_error($ch), true);
		} elseif (($httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE)) != 200) {
			$this->log('Http status #' . $httpCode . ' cURL: ' . $sUrl . "\t" . curl_error($ch), true);
			$response = false;
		}
		empty($ch) ?: curl_close($ch);

		return $response ?? false;
	}

	/**
	 * @throws AfrEnvException
	 */
	protected function getDefaultCurlSetOpt(): array
	{
		if (empty(self::$aDefaultCurlSetOpt)) {
			$iTimeoutMs = 1000;
			$iConTimeoutMs = 2000;
			if (Afr::app()) {
				$iTimeoutMs = Afr::app()->env()->getEnv('AFR_CRON_DAEMON_CURL_TIMEOUT_MS', $iTimeoutMs);
				$iConTimeoutMs = Afr::app()->env()->getEnv('AFR_CRON_DAEMON_CURL_TIMEOUT_MS', max($iConTimeoutMs, $iTimeoutMs * 2));
			}
			self::$aDefaultCurlSetOpt = [
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HEADER => false,
				CURLOPT_TIMEOUT_MS => $iTimeoutMs,
				CURLOPT_CONNECTTIMEOUT_MS => $iConTimeoutMs,
				CURLOPT_FOLLOWLOCATION => true,
			];
		}
		return self::$aDefaultCurlSetOpt;
	}
}