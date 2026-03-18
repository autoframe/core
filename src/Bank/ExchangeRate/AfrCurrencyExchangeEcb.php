<?php

namespace Autoframe\Core\Bank\ExchangeRate;

use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;

/**
 * ECB-based currency exchange class.
 *
 * Source:
 * - https://data-api.ecb.europa.eu/service/data/EXR
 *
 * Cache strategy:
 * - one PHP file per UTC month: YYYY_MM.php in the configured cache directory
 * - the file contains all available daily rates for that full month
 * - each refresh fetches the entire current UTC month and overwrites the cache file
 * - cache is considered expired when older than 3600 seconds
 * - or when the current UTC time is past H:00:45 and the cache predates that threshold
 *
 * Internal base:
 * - EUR is always the canonical base currency
 *
 * Stored day semantics:
 * - days['2026-03-05']['USD'] = 1.0832  means 1 EUR = 1.0832 USD
 * - days['2026-03-05']['RON'] = 4.9714  means 1 EUR = 4.9714 RON
 * - days['2026-03-05']['EUR'] = 1.0     (base)
 */
class AfrCurrencyExchangeEcb extends AfrSingletonAbstractClass implements AfrCurrencyExchangeInterface
{
	use AfrCurrencyExchangeTrait;

	/**
	 * @var string
	 */
	protected string $sEcbApiUrl = 'https://data-api.ecb.europa.eu/service/data/EXR';

	/**
	 * @var int
	 */
	protected int $iMaxCacheAge = 3600;

	/**
	 * @var int
	 */
	protected int $iHourlyRefreshSecond = 45;

	/**
	 * @var int
	 */
	protected int $iHttpTimeout = 12;

	/**
	 * Returns EUR as the default target currency for this ECB implementation.
	 */
	protected function getDefaultCurrency(): string
	{
		return self::EUR;
	}

	/**
	 * Initializes cache directory and default currency on first singleton construction.
	 * @param string|null $sCacheDir Cache directory; defaults to __DIR__.'/cache/'.
	 * @param string|null $sDefaultToCurrency Default target currency for convert(); defaults to EUR.
	 */
	protected function __construct(
		?string $sCacheDir = null,
		?string $sDefaultToCurrency = null
	)
	{
		$this->setCacheDir($sCacheDir);
		if ($sDefaultToCurrency) $this->setDefaultToCurrency($sDefaultToCurrency);
	}

	/**
	 * Returns EUR as the internal base currency for this implementation.
	 */
	public function getBaseCurrency(): string
	{
		return self::EUR;
	}

	/**
	 * Fetches the full current UTC month's ECB rates and overwrites the monthly cache file.
	 */
	public function refresh(): self
	{
		$aFreshData = $this->fetchRemoteRatesFromEcb();

		// Set a placeholder so validateData() can check the key exists;
		// writeCacheFile() overwrites this with the actual write timestamp.
		$aFreshData['updated_at'] = 0;

		$this->validateData($aFreshData);

		// writeCacheFile sets updated_at and returns the array with that timestamp applied
		static::$aData[static::class] = $this->writeCacheFile($aFreshData);

		return $this;
	}

	/**
	 * Validates the full ECB monthly data structure (EUR-based).
	 */
	protected function validateData(array $aData): void
	{
		if (!isset($aData['base']) || !is_string($aData['base']) || $aData['base'] !== self::EUR) {
			throw new \InvalidArgumentException('Invalid ECB data: base must be EUR.');
		}

		if (!isset($aData['updated_at']) || !is_numeric($aData['updated_at'])) {
			throw new \InvalidArgumentException('Invalid ECB data: missing updated_at.');
		}

		if (!isset($aData['month']) || !is_string($aData['month']) || !$this->isValidMonth($aData['month'])) {
			throw new \InvalidArgumentException('Invalid ECB data: missing or invalid month.');
		}

		if (!isset($aData['days']) || !is_array($aData['days']) || $aData['days'] === []) {
			throw new \InvalidArgumentException('Invalid ECB data: missing days.');
		}

		foreach ($aData['days'] as $sDate => $aRates) {
			if (!is_string($sDate) || !$this->isValidDate($sDate)) {
				throw new \InvalidArgumentException('Invalid ECB data: bad day key.');
			}

			if (substr($sDate, 0, 7) !== $aData['month']) {
				throw new \InvalidArgumentException(
					'Invalid ECB data: day outside declared month: ' . $sDate
				);
			}

			if (!is_array($aRates) || $aRates === []) {
				throw new \InvalidArgumentException('Invalid ECB data: empty day rates for ' . $sDate);
			}

			if (!isset($aRates[self::EUR]) || (float)$aRates[self::EUR] !== 1.0) {
				throw new \InvalidArgumentException('Invalid ECB data: EUR base missing for ' . $sDate);
			}

			foreach ($aRates as $sCurrency => $mRate) {
				if (!is_string($sCurrency) || trim($sCurrency) === '') {
					throw new \InvalidArgumentException(
						'Invalid ECB data: bad currency key on ' . $sDate
					);
				}

				if (!is_numeric($mRate) || (float)$mRate <= 0.0) {
					throw new \InvalidArgumentException(
						'Invalid ECB data: bad rate for ' . $sCurrency . ' on ' . $sDate
					);
				}
			}
		}
	}

	/**
	 * Returns the stored units-per-EUR rate for $sCurrency from the rates array.
	 */
	protected function getRateFromArray(string $sCurrency, array $aRates): float
	{
		if ($sCurrency === self::EUR) {
			return 1.0;
		}

		if (!isset($aRates[$sCurrency])) {
			throw new \RuntimeException('Missing exchange rate for currency: ' . $sCurrency);
		}

		return (float)$aRates[$sCurrency];
	}

	/**
	 * Converts $fAmount between currencies using the EUR-based rates array.
	 * All rates are stored as units-of-currency per 1 EUR, so:
	 * - FROM EUR: amount * target_rate
	 * - TO EUR:   amount / source_rate
	 * - General:  convert to EUR first, then to target
	 */
	protected function convertUsingRates(
		float  $fAmount,
		string $sFromCurrency,
		string $sToCurrency,
		array  $aRates
	): float
	{
		if ($sFromCurrency === self::EUR) {
			return $fAmount * $this->getRateFromArray($sToCurrency, $aRates);
		}

		if ($sToCurrency === self::EUR) {
			return $fAmount / $this->getRateFromArray($sFromCurrency, $aRates);
		}

		$fAmountInEur = $fAmount / $this->getRateFromArray($sFromCurrency, $aRates);

		return $fAmountInEur * $this->getRateFromArray($sToCurrency, $aRates);
	}

	/**
	 * Fetches the current UTC month's ECB daily reference rates via the ECB Data Portal CSV API.
	 */
	protected function fetchRemoteRatesFromEcb(): array
	{
		$sMonthStart = gmdate('Y-m-01');
		$sMonthEnd = gmdate('Y-m-t');

		$sUrl = $this->sEcbApiUrl
			. '/D..EUR.SP00.A'
			. '?format=csvdata'
			. '&startPeriod=' . rawurlencode($sMonthStart)
			. '&endPeriod=' . rawurlencode($sMonthEnd);

		$sCsv = $this->httpGet($sUrl);
		$aParsed = $this->parseEcbCsvMonthlyRates($sCsv);

		if (empty($aParsed['days'])) {
			throw new \RuntimeException('ECB API returned no usable monthly exchange rates.');
		}

		return $aParsed;
	}

	/**
	 * Parses the ECB csvdata response into a month/day => rates structure.
	 * Note: updated_at is intentionally absent here; refresh() adds a placeholder (0)
	 * and writeCacheFile() sets the real timestamp before writing.
	 */
	protected function parseEcbCsvMonthlyRates(string $sCsv): array
	{
		$aLines = preg_split("/\r\n|\n|\r/", trim($sCsv));

		if (!$aLines || count($aLines) < 2) {
			throw new \RuntimeException('ECB CSV response is empty or malformed.');
		}

		$aHeader = str_getcsv(array_shift($aLines));
		$aHeaderMap = $this->buildHeaderMap($aHeader);

		$iCurrencyIdx = $aHeaderMap['CURRENCY'] ?? null;
		$iTimeIdx = $aHeaderMap['TIME_PERIOD'] ?? null;
		$iValueIdx = $aHeaderMap['OBS_VALUE'] ?? null;

		if ($iCurrencyIdx === null || $iTimeIdx === null || $iValueIdx === null) {
			throw new \RuntimeException(
				'ECB CSV header does not contain required columns CURRENCY, TIME_PERIOD, OBS_VALUE.'
			);
		}

		$aDays = [];

		foreach ($aLines as $sLine) {
			$sLine = trim($sLine);
			if ($sLine === '') continue;

			$aRow = str_getcsv($sLine);
			if (!isset($aRow[$iCurrencyIdx], $aRow[$iTimeIdx], $aRow[$iValueIdx])) continue;

			$sDate = trim((string)$aRow[$iTimeIdx]);
			$mValue = $aRow[$iValueIdx];
			if (!$this->isValidDate($sDate) || !is_numeric($mValue)) continue;

			$fRate = (float)$mValue;
			if ($fRate <= 0.0) continue;

			if (!isset($aDays[$sDate])) $aDays[$sDate] = [self::EUR => 1.0];
			$sCurrency = $this->normalizeCurrencyCode((string)$aRow[$iCurrencyIdx]);
			$aDays[$sDate][$sCurrency] = $fRate;
		}

		ksort($aDays, SORT_STRING);

		if ($aDays === []) {
			throw new \RuntimeException('ECB CSV response contains no usable daily data.');
		}

		$aDates = array_keys($aDays);
		$sFirstDate = (string)reset($aDates);
		$sMonth = substr($sFirstDate, 0, 7);

		return [
			'base' => self::EUR,
			'month' => $sMonth,
			'source' => $this->sEcbApiUrl,
			'days' => $aDays,
		];
	}

	/**
	 * Builds a column-name => index map from the CSV header row.
	 */
	protected function buildHeaderMap(array $aHeader): array
	{
		foreach ($aHeader as $i => $sName)
			$aMap[strtoupper(trim((string)$sName))] = $i;
		return $aMap ?? [];
	}
}
