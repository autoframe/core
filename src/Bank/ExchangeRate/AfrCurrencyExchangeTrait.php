<?php

namespace Autoframe\Core\Bank\ExchangeRate;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Shared cache, HTTP fetch, date, and utility methods for currency exchange classes.
 *
 * The using class MUST declare these properties:
 *   protected int $iMaxCacheAge
 *   protected int $iHourlyRefreshSecond
 *   protected int $iHttpTimeout
 *
 * The using class MUST implement these abstract methods:
 *   protected function getBaseCurrency(): string
 *   protected function getDefaultCurrency(): string
 *   protected function validateData(array $aData): void
 *   protected function convertUsingRates(float $fAmount, string $sFrom, string $sTo, array $aRates): float
 *   protected function getRateFromArray(string $sCurrency, array $aRates): float
 *   public    function refresh(): self
 *
 * Static per-class state (keyed by static::class):
 *   static::$aData[static::class]          — in-memory monthly data cache
 *   static::$sCacheDir[static::class]      — resolved cache directory path
 *   static::$sDefaultToCurrency[static::class] — default target currency
 */
trait AfrCurrencyExchangeTrait
{

	protected string $sYmPattern = 'Y-m';
	protected static array $sCacheDir = [];
	protected static array $sDefaultToCurrency = [];
	protected static array $aData = [];

	// --- Abstract requirements that the using class must satisfy ---

	/** Returns the ISO currency code used internally as the base for all stored rates. */
	abstract protected function getBaseCurrency(): string;

	/** Returns the default target currency constant for this implementation (e.g. self::RON or self::EUR). */
	abstract protected function getDefaultCurrency(): string;

	/** Validates the full monthly data structure and throws on invalid data. */
	abstract protected function validateData(array $aData): void;

	/** Converts $fAmount from $sFromCurrency to $sToCurrency using the provided rates array. */
	abstract protected function convertUsingRates(
		float  $fAmount,
		string $sFromCurrency,
		string $sToCurrency,
		array  $aRates
	): float;

	/** Returns the stored raw rate for $sCurrency from the rates array. */
	abstract protected function getRateFromArray(string $sCurrency, array $aRates): float;

	/** Forces a remote refresh and overwrites the current monthly cache file. */
	abstract public function refresh(): self;

	// --- Interface method implementations ---

	/**
	 * Sets the cache directory and invalidates the in-memory data cache.
	 */
	public function setCacheDir(?string $sCacheDir): self
	{
		static::$sCacheDir[static::class] = $this->normalizeCacheDir($sCacheDir);
		$this->ensureCacheDirExists();
		static::$aData[static::class] = null;
		return $this;
	}

	/**
	 * Returns the current cache directory path.
	 */
	public function getCacheDir(): string
	{
		if (empty(static::$sCacheDir[static::class])) $this->setCacheDir(null);
		return static::$sCacheDir[static::class];
	}

	/**
	 * Sets the default target currency used when $sToCurrency is omitted in convert().
	 */
	public function setDefaultToCurrency(string $sCurrency): self
	{
		static::$sDefaultToCurrency[static::class] = $this->normalizeCurrencyCode($sCurrency);
		return $this;
	}

	/**
	 * Returns the current default target currency.
	 */
	public function getDefaultToCurrency(): string
	{
		if (empty(static::$sDefaultToCurrency[static::class])) {
			static::$sDefaultToCurrency[static::class] = $this->getDefaultCurrency();
		}
		return static::$sDefaultToCurrency[static::class];
	}

	/**
	 * Returns the raw stored rate for $sCurrency relative to the base currency,
	 * using the latest available UTC date from the loaded monthly cache.
	 * @throws Throwable
	 */
	public function getExchangeRateBaseCurrency(string $sCurrency): float
	{
		$sCurrency = $this->normalizeCurrencyCode($sCurrency);
		if ($sCurrency === $this->getBaseCurrency()) return 1.0;

		$aData = $this->getFreshData();
		$sLatestDate = $this->getLatestAvailableDate($aData);
		$aRates = $this->getRatesForDate($sLatestDate, $aData);

		if (!isset($aRates[$sCurrency])) {
			throw new RuntimeException('Missing exchange rate for currency: ' . $sCurrency);
		}

		return (float)$aRates[$sCurrency];
	}

	/**
	 * Returns the raw stored rate for $sCurrency relative to the base currency
	 * for a specific UTC date in Y-m-d format.
	 * @throws Throwable
	 */
	public function getExchangeRateByDate(string $sCurrency, string $sDate): float
	{
		$sCurrency = $this->normalizeCurrencyCode($sCurrency);
		$sDate = $this->normalizeDate($sDate);

		if ($sCurrency === $this->getBaseCurrency()) {
			return 1.0;
		}

		$aData = $this->getDataForDate($sDate);
		$aRates = $this->getRatesForDate($sDate, $aData);

		if (!isset($aRates[$sCurrency])) {
			throw new RuntimeException('Missing exchange rate for currency ' . $sCurrency . ' on ' . $sDate);
		}

		return (float)$aRates[$sCurrency];
	}

	/**
	 * Converts $fAmount from $sFromCurrency to $sToCurrency using the latest cached UTC day.
	 * If $sToCurrency is null the configured default target currency is used.
	 * @throws Throwable
	 */
	public function convert(float $fAmount, string $sFromCurrency, ?string $sToCurrency = null): float
	{
		$sFromCurrency = $this->normalizeCurrencyCode($sFromCurrency);
		$sToCurrency = $this->normalizeCurrencyCode($sToCurrency ?? $this->getDefaultToCurrency());
		if ($sFromCurrency === $sToCurrency) return $fAmount;


		$aData = $this->getFreshData();
		$sLatestDate = $this->getLatestAvailableDate($aData);
		$aRates = $this->getRatesForDate($sLatestDate, $aData);

		return $this->convertUsingRates($fAmount, $sFromCurrency, $sToCurrency, $aRates);
	}

	/**
	 * Converts $fAmount from $sFromCurrency to $sToCurrency using a specific UTC date's cached rates.
	 * If $sToCurrency is null the configured default target currency is used.
	 * @throws Throwable
	 */
	public function convertByDate(
		float   $fAmount,
		string  $sFromCurrency,
		string  $sDate,
		?string $sToCurrency = null,
		bool    $bUseCurrentExchangeRateIfHistoryIsMissing = true
	): float
	{
		$sFromCurrency = $this->normalizeCurrencyCode($sFromCurrency);
		$sToCurrency = $this->normalizeCurrencyCode($sToCurrency ?? $this->getDefaultToCurrency());
		if ($sFromCurrency === $sToCurrency) return $fAmount;

		$sDate = $this->normalizeDate($sDate);
		try {
			$aData = $this->getDataForDate($sDate);
			$aRates = $this->getRatesForDate($sDate, $aData);
		} catch (Throwable $e) {
			if ($bUseCurrentExchangeRateIfHistoryIsMissing && $e->getCode() == 91) return $this->convert($fAmount, $sFromCurrency, $sToCurrency);
			else throw $e;
		}
		return $this->convertUsingRates($fAmount, $sFromCurrency, $sToCurrency, $aRates);
	}

	/**
	 * Returns the full loaded monthly data structure.
	 * @throws Throwable
	 */
	public function getRates(): array
	{
		return $this->getFreshData();
	}

	// --- Internal data management ---

	/**
	 * Returns fresh data, loading from the PHP cache file or refreshing from remote if expired.
	 * @throws Throwable
	 */
	protected function getFreshData(): array
	{
		if (!empty(static::$aData[static::class]) && !$this->isDataExpired(static::$aData[static::class]))
			return static::$aData[static::class];

		$aData = $this->readCacheFile();
		if ($aData !== null && !$this->isDataExpired($aData)) return static::$aData[static::class] = $aData;


		try {
			$this->refresh();
		} catch (Throwable $e) {
			if ($aData !== null) {
				// Serve stale cache on network failure rather than throwing
				return static::$aData[static::class] = $aData;
			}
			throw $e;
		}
		if (empty(static::$aData[static::class])) {
			throw new RuntimeException('Unable to load exchange rate data.');
		}
		return static::$aData[static::class];
	}

	/**
	 * Returns the monthly data file that contains $sDate, using in-memory data if month matches.
	 * @throws Throwable
	 */
	protected function getDataForDate(string $sDate): array
	{
		$sMonth = substr($sDate, 0, 7);
		$aCurrent = $this->getFreshData();
		if (isset($aCurrent['month']) && $aCurrent['month'] === $sMonth) return $aCurrent;

		$aMonthData = $this->readCacheFileByMonth($sMonth);
		if ($aMonthData === null) {
			throw new RuntimeException('No cache file found for month: ' . $sMonth, 91);
		}
		return $aMonthData;
	}

	/**
	 * Returns true if the cached data has expired and a remote refresh is required.
	 * Considers both max age (3600 s) and the per-hour H:00:45 UTC threshold.
	 */
	protected function isDataExpired(array $aData): bool
	{
		$iUpdatedAt = isset($aData['updated_at']) ? (int)$aData['updated_at'] : 0;
		if ($iUpdatedAt < 1) return true;
		if ((($iNow = time()) - $iUpdatedAt) > $this->iMaxCacheAge) return true;

		$iThreshold = $this->getCurrentHourRefreshThresholdUtc();
		return $iNow >= $iThreshold && $iUpdatedAt < $iThreshold;
	}

	/**
	 * Returns the UTC Unix timestamp for the current hour's H:00:45 refresh threshold.
	 */
	protected function getCurrentHourRefreshThresholdUtc(): int
	{
		$iNowUtc = time();
		return $iNowUtc - ($iNowUtc % 3600) + $this->iHourlyRefreshSecond;
	}

	// --- Cache file I/O ---

	/**
	 * Returns the cache file path for the current UTC month (YYYY_MM.php).
	 */
	protected function getCacheFilePath(): string
	{
		return $this->getCacheFilePathByMonth(gmdate($this->sYmPattern));
	}

	/**
	 * Returns the cache file path for the given UTC month string (YYYY-MM → YYYY_MM.php).
	 */
	protected function getCacheFilePathByMonth(string $sMonth): string
	{
		return $this->getCacheDir() . str_replace('-', '_', $sMonth) . '.php';
	}

	/**
	 * Reads and returns the current UTC month cache file, or null on missing or invalid data.
	 */
	protected function readCacheFile(): ?array
	{
		return $this->readCacheFileByMonth(gmdate($this->sYmPattern));
	}

	/**
	 * Reads and returns the cache file for a specific UTC month, or null on missing or invalid data.
	 */
	protected function readCacheFileByMonth(string $sMonth): ?array
	{
		$sFile = $this->getCacheFilePathByMonth($sMonth);

		if (!is_file($sFile)) return null;
		if (!is_array($mData = include $sFile)) return null;

		try {
			$this->validateData($mData);
		} catch (Throwable $e) {
			return null;
		}

		return $mData;
	}

	/**
	 * Writes the monthly data array to a PHP cache file using an atomic temp-file + rename strategy.
	 * Sets updated_at to the current UTC time and returns the array with that timestamp applied.
	 */
	protected function writeCacheFile(array $aData): array
	{
		$sMonth = isset($aData['month']) ? (string)$aData['month'] : gmdate($this->sYmPattern);
		$sFile = $this->getCacheFilePathByMonth($sMonth);
		$sTempFile = $sFile . '.tmp';

		// Bug #7 fix (ECB): updated_at is set once here so the returned array
		// and the file content always share the same timestamp.
		$aData['updated_at'] = time();

		$sPhp = "<?php\nreturn " . var_export($aData, true) . ";\n";

		if (file_put_contents($sTempFile, $sPhp, LOCK_EX) === false) {
			throw new RuntimeException('Unable to write temp cache file: ' . $sTempFile);
		}

		if (!@rename($sTempFile, $sFile)) {
			@unlink($sTempFile);
			throw new RuntimeException('Unable to replace cache file: ' . $sFile);
		}

		return $aData;
	}

	// --- HTTP helpers ---

	/**
	 * Performs an HTTP GET request using file_get_contents() and returns the response body.
	 */
	protected function httpGet(string $sUrl): string
	{
		$rContext = stream_context_create([
			'http' => [
				'method' => 'GET',
				'timeout' => $this->iHttpTimeout,
				'ignore_errors' => true,
				'header' =>
					"Accept: application/xml, text/xml, text/csv, application/csv, */*;q=0.8\r\n" .
					"User-Agent: AutoframeCurrencyExchange/1.0 PHP/" . PHP_VERSION . "\r\n",
			],
			'ssl' => [
				'verify_peer' => true,
				'verify_peer_name' => true,
			],
		]);

		$sFallbackPath = __DIR__ . DIRECTORY_SEPARATOR . substr(strrchr('\\' . static::class, '\\'), 1) . '.xml';
		// 1. Try remote first
		$sBody = (string)(@file_get_contents($sUrl, false, $rContext));
		if (strlen($sBody) > 50 && strpos($sBody, 'USD') && strpos($sBody, 'EUR')) {
			// Save last known good remote XML locally
			@file_put_contents($sFallbackPath, $sBody, LOCK_EX);
		}

		// 2. Fallback to local last known good XML
		if (empty($sBody)) $sBody = @file_get_contents($sFallbackPath);

		if (empty($sBody)) {
			throw new RuntimeException('HTTP GET failed for URL: ' . $sUrl);
		}

		// $http_response_header is populated by file_get_contents() in the local scope
		$iStatusCode = $this->extractHttpStatusCode(
			$http_response_header ?? []
		);

		if ($iStatusCode < 200 || $iStatusCode >= 300) {
			throw new RuntimeException(
				'Unexpected HTTP status ' . $iStatusCode . ' from URL: ' . $sUrl
			);
		}

		return $sBody;
	}

	/**
	 * Extracts the HTTP status code from the $http_response_header array set by file_get_contents().
	 */
	protected function extractHttpStatusCode(array $aHeaders): int
	{
		if (!$aHeaders) {
			return 0;
		}

		foreach ($aHeaders as $sHeader) {
			if (preg_match('/^HTTP\/\d+(?:\.\d+)?\s+(\d{3})\b/i', (string)$sHeader, $aMatch)) {
				return (int)$aMatch[1];
			}
		}

		return 0;
	}

	// --- Rate helpers ---

	/**
	 * Returns the most recent UTC date key from the days array.
	 */
	protected function getLatestAvailableDate(array $aData): string
	{
		if (empty($aData['days']) || !is_array($aData['days'])) {
			throw new RuntimeException('No daily exchange rate data available.');
		}

		$aDates = array_keys($aData['days']);
		rsort($aDates, SORT_STRING);

		return (string)$aDates[0];
	}

	/**
	 * Returns the rates sub-array for the given date from the loaded monthly data.
	 */
	protected function getRatesForDate(string $sDate, array $aData): array
	{
		if (!isset($aData['days']) || !is_array($aData['days'])) {
			throw new RuntimeException('No exchange rates available in the provided data set!', 21);
		}
		if (!isset($aData['days'][$sDate]) || !is_array($aData['days'][$sDate])) {
			//fallback to the latest available data set
			foreach ($aData['days'] as $sPrevDate => $aPrevDateData) {
				if ($sPrevDate >= $sDate) break;
			}
			if (!empty($aPrevDateData)) return $aPrevDateData;
			throw new RuntimeException('No exchange rates available for date: ' . $sDate, 22);
		}
		return $aData['days'][$sDate];
	}

	// --- Normalization and validation helpers ---

	/**
	 * Returns the currency code normalized to uppercase with whitespace stripped.
	 */
	protected function normalizeCurrencyCode(string $sCurrency): string
	{
		if (($sCurrency = strtoupper(trim($sCurrency))) === '') {
			throw new InvalidArgumentException('Currency code cannot be empty.');
		}
		return $sCurrency;
	}

	/**
	 * Validates and returns a trimmed date string; throws on invalid Y-m-d format.
	 */
	protected function normalizeDate(string $sDate): string
	{
		$sDate = trim($sDate);

		if (!$this->isValidDate($sDate)) {
			throw new InvalidArgumentException('Invalid date format, expected Y-m-d: ' . $sDate);
		}

		return $sDate;
	}

	/**
	 * Returns true if the value is a valid calendar date in Y-m-d format.
	 */
	protected function isValidDate(string $sValue): bool
	{
		return isset($sValue[9])
			&& $sValue[4] === '-'
			&& $sValue[7] === '-'
			&& ctype_digit($sValue[0] . $sValue[1] . $sValue[2] . $sValue[3] . $sValue[5] . $sValue[6] . $sValue[8] . $sValue[9])
			&& checkdate(
				(int)($sValue[5] . $sValue[6]),
				(int)($sValue[8] . $sValue[9]),
				(int)($sValue[0] . $sValue[1] . $sValue[2] . $sValue[3])
			);
	}

	/**
	 * Returns true if the value is a valid calendar month in Y-m format.
	 */
	protected function isValidMonth(string $sValue): bool
	{
		return isset($sValue[6])
			&& !isset($sValue[7])
			&& $sValue[4] === '-'
			&& ctype_digit($sValue[0] . $sValue[1] . $sValue[2] . $sValue[3] . $sValue[5] . $sValue[6])
			&& (int)($sValue[5] . $sValue[6]) >= 1
			&& (int)($sValue[5] . $sValue[6]) <= 12;
	}

	/**
	 * Returns the path normalized to always end with the OS directory separator.
	 */
	protected function normalizeCacheDir(?string $sCacheDir): string
	{
		$sCacheDir = !empty($sCacheDir) ? $sCacheDir :
			__DIR__ . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . substr(strrchr('\\' . static::class, '\\'), 1);
		return rtrim($sCacheDir, '/\\') . DIRECTORY_SEPARATOR;
	}

	/**
	 * Creates the cache directory if it does not already exist.
	 */
	protected function ensureCacheDirExists(): void
	{
		if (is_dir(static::$sCacheDir[static::class])) return;
		if (!mkdir(static::$sCacheDir[static::class], 0775, true) && !is_dir(static::$sCacheDir[static::class])) {
			throw new RuntimeException('Unable to create cache directory: ' . static::$sCacheDir[static::class]);
		}
	}
}
