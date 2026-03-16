<?php

namespace Autoframe\Core\Bank\ExchangeRate;

use InvalidArgumentException;

/**
 * BNR-based currency exchange class.
 *
 * Source:
 * - https://www.bnr.ro/nbrfxrates.xml
 *
 * Cache strategy:
 * - one PHP file per UTC month: YYYY_MM.php in the configured cache directory
 * - the file accumulates daily rates for that month (one entry per BNR publish day)
 * - each successful refresh merges only the current day's rates into the monthly file
 * - cache is considered expired when older than 3600 seconds
 * - or when the current UTC time is past H:00:45 and the cache predates that threshold
 *
 * Internal base:
 * - RON is always the canonical base currency
 *
 * Stored day semantics:
 * - days['2026-03-13']['EUR'] = 5.0947  means 1 EUR = 5.0947 RON
 * - days['2026-03-13']['USD'] = 4.4429  means 1 USD = 4.4429 RON
 * - days['2026-03-13']['RON'] = 1.0
 *
 * Multiplier handling:
 * - BNR publishes <Rate currency="HUF" multiplier="100">1.3029</Rate>
 *   which is normalised to HUF => 1.3029 / 100 = 0.013029 (RON per 1 HUF)
 */
class AfrCurrencyExchangeBnr implements AfrCurrencyExchangeInterface
{
	use AfrCurrencyExchangeTrait;

	protected string $sBnrXmlUrl = 'https://www.bnr.ro/nbrfxrates.xml';


	/** Default target currency — RON for BNR since the feed is RON-based.*/
	protected string $sDefaultToCurrency = self::RON;

	protected int $iMaxCacheAge = 3600;

	protected int $iHourlyRefreshSecond = 45;

	protected int $iHttpTimeout = 12;


	/**
	 * @param string|null $sCacheDir         Cache directory; defaults to __DIR__.'/cache/'.
	 * @param string      $sDefaultToCurrency Default target currency for convert(); defaults to RON.
	 */
	public function __construct(
		?string $sCacheDir = null,
		string $sDefaultToCurrency = self::RON
	) {
		$this->setCacheDir($sCacheDir);
		if($sDefaultToCurrency) $this->setDefaultToCurrency($sDefaultToCurrency);
	}

	/**
	 * Returns RON as the internal base currency for this implementation.
	 */
	public function getBaseCurrency(): string
	{
		return self::RON;
	}

	/**
	 * Fetches the latest BNR daily rates and merges them into the monthly rolling cache file.
	 */
	public function refresh(): self
	{
		$aFreshDayData = $this->fetchRemoteRatesFromBnr();
		$sMonth = substr($aFreshDayData['date'], 0, 7);
		$aMonthData = $this->readCacheFileByMonth($sMonth);

		if ($aMonthData === null) {
			$aMonthData = [
				'base'       => self::RON,
				'updated_at' => 0, // placeholder; writeCacheFile sets the real timestamp
				'month'      => $sMonth,
				'source'     => $this->sBnrXmlUrl,
				'days'       => [],
			];
		}
		$aMonthData['days'][$aFreshDayData['date']] = $aFreshDayData['rates'];
		$this->validateData($aMonthData);

		// writeCacheFile sets updated_at and returns the array with that timestamp applied
		static::$aData[static::class] = $this->writeCacheFile($aMonthData);
		return $this;
	}

	/**
	 * Validates the full BNR monthly data structure (RON-based).
	 */
	protected function validateData(array $aData): void
	{
		if (empty($aData['base'])) {
			throw new InvalidArgumentException('Invalid BNR data: base must be RON.');
		}
		if (!isset($aData['updated_at'])) {
			throw new InvalidArgumentException('Invalid BNR data: missing updated_at.');
		}

		if (!isset($aData['month']) || !is_string($aData['month']) || !$this->isValidMonth($aData['month'])) {
			throw new InvalidArgumentException('Invalid BNR data: missing or invalid month.');
		}

		if (!isset($aData['days']) || !is_array($aData['days']) || $aData['days'] === []) {
			throw new InvalidArgumentException('Invalid BNR data: missing days.');
		}

		foreach ($aData['days'] as $sDate => $aRates) {
			if (!is_string($sDate) || !$this->isValidDate($sDate)) {
				throw new InvalidArgumentException('Invalid BNR data: bad day key.');
			}

			if (substr($sDate, 0, 7) !== $aData['month']) {
				throw new InvalidArgumentException('Invalid BNR data: day outside declared month: ' . $sDate);
			}

			if (!is_array($aRates) || $aRates === []) {
				throw new InvalidArgumentException('Invalid BNR data: empty day rates for ' . $sDate);
			}

			if (!isset($aRates[self::RON]) || (float)$aRates[self::RON] !== 1.0) {
				throw new InvalidArgumentException('Invalid BNR data: RON base missing for ' . $sDate);
			}

			foreach ($aRates as $sCurrency => $mRate) {
				if (!is_string($sCurrency) || trim($sCurrency) === '') {
					throw new InvalidArgumentException('Invalid BNR data: bad currency key on ' . $sDate);
				}

				if (!is_numeric($mRate) || (float)$mRate <= 0.0) {
					throw new InvalidArgumentException(
						'Invalid BNR data: bad rate for ' . $sCurrency . ' on ' . $sDate
					);
				}
			}
		}
	}

	/**
	 * Returns the stored RON-per-unit rate for $sCurrency from the rates array.
	 */
	protected function getRateFromArray(string $sCurrency, array $aRates): float
	{
		if ($sCurrency === self::RON) return 1.0;
		if (!isset($aRates[$sCurrency])) {
			throw new \RuntimeException('Missing exchange rate for currency: ' . $sCurrency);
		}
		return (float)$aRates[$sCurrency];
	}

	/**
	 * Converts $fAmount between currencies using the RON-based rates array.
	 * All rates are stored as RON per 1 unit of currency, so:
	 * - FROM RON: amount / target_rate
	 * - TO RON:   amount * source_rate
	 * - General:  convert to RON first, then to target
	 */
	protected function convertUsingRates(
		float $fAmount,
		string $sFromCurrency,
		string $sToCurrency,
		array $aRates
	): float {
		if ($sFromCurrency === self::RON) {
			return $fAmount / $this->getRateFromArray($sToCurrency, $aRates);
		}

		if ($sToCurrency === self::RON) {
			return $fAmount * $this->getRateFromArray($sFromCurrency, $aRates);
		}

		$fAmountInRon = $fAmount * $this->getRateFromArray($sFromCurrency, $aRates);
		return $fAmountInRon / $this->getRateFromArray($sToCurrency, $aRates);
	}

	protected function isLikelyValidBnrXml(string $sXml): bool
	{
		return $sXml !== ''
			&& strpos($sXml, '<OrigCurrency') !== false
			&& strpos($sXml, '<Cube') !== false
			&& strpos($sXml, '<Rate ') !== false
			&& strpos($sXml, 'PublishingDate') !== false
			&& strpos($sXml, '</DataSet>') !== false;
	}


	/**
	 * Fetches the latest BNR daily XML and converts it to a normalised single-day payload:
	 * [ 'date' => 'YYYY-MM-DD', 'rates' => [ 'RON' => 1.0, 'EUR' => 5.09, ... ] ]
	 */
	protected function fetchRemoteRatesFromBnr(): array
	{
		$sFallbackPath = __DIR__ . DIRECTORY_SEPARATOR . 'AfrCurrencyExchangeBnr.xml';
		$sXml = '';

		// 1. Try remote first
		$sRemoteXml = @file_get_contents($this->sBnrXmlUrl);
		if ($this->isLikelyValidBnrXml((string)$sRemoteXml)) {
			$sXml = $sRemoteXml;
			// Save last known good remote XML locally
			@file_put_contents($sFallbackPath, $sXml, LOCK_EX);
		}

		// 2. Fallback to local last known good XML
		if (empty($sXml)) {
			$sXml = @file_get_contents($sFallbackPath);
		}

		// 4. OrigCurrency
		$sOrigCurrency = self::RON;
		if (preg_match('~<OrigCurrency>\s*([A-Z]{3})\s*</OrigCurrency>~i', $sXml, $aMatch)) {
			$sOrigCurrency = $this->normalizeCurrencyCode($aMatch[1]);
		}

		// 5. Cube date
		if (!preg_match('~<Cube\b[^>]*\bdate="(\d{4}-\d{2}-\d{2})"[^>]*>~i', $sXml, $aMatch)) {
			throw new \RuntimeException('BNR XML Cube date is missing.');
		}

		$sDate = $aMatch[1];
		if (!$this->isValidDate($sDate)) {
			throw new \RuntimeException('BNR XML Cube date is invalid: ' . $sDate);
		}

		// 6. Extract all rate nodes
		$aRates = [$sOrigCurrency => 1.0];

		if (!preg_match_all(
			'~<Rate\b([^>]*)>([-+]?\d+(?:\.\d+)?)</Rate>~i',
			$sXml,
			$aRateMatches,
			PREG_SET_ORDER
		)) {
			throw new \RuntimeException('BNR XML returned no usable rate entries.');
		}

		foreach ($aRateMatches as $aRateMatch) {
			$sAttributes = $aRateMatch[1] ?? '';
			$sValue = isset($aRateMatch[2]) ? trim($aRateMatch[2]) : '';

			if ($sValue === '' || !is_numeric($sValue)) continue;
			if (($fRate = (float)$sValue) <= 0.0) continue;

			if (!preg_match('~\bcurrency="([A-Z]{3,4})"~i', $sAttributes, $aCurrencyMatch)) continue;
			$sCurrency = $this->normalizeCurrencyCode($aCurrencyMatch[1]);

			$aRates[$sCurrency] = $fRate;
			if (preg_match('~\bmultiplier="(\d+)"~i', $sAttributes, $aMultiplierMatch)) {
				if($iMultiplier = (int)$aMultiplierMatch[1]){
					$aRates[$sCurrency] = $fRate / $iMultiplier;
				}
			}

		}
		if (count($aRates) < 2) {
			throw new \RuntimeException('BNR XML returned no usable normalized rate entries.');
		}

		return [
			'date' => $sDate,
			'rates' => $aRates,
		];
	}

}
