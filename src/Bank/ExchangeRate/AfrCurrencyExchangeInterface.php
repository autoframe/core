<?php

namespace Autoframe\Core\Bank\ExchangeRate;

/**
 * Exchange service contract.
 */
interface AfrCurrencyExchangeInterface extends AfrCurrencyCodeInterface
{
	public function setCacheDir(string $sCacheDir): self;

	public function getCacheDir(): string;

	public function setDefaultToCurrency(string $sCurrency): self;

	public function getDefaultToCurrency(): string;

	/**
	 * Returns the ISO currency code used internally as the base for all stored rates.
	 * ECB uses EUR; BNR uses RON.
	 */
	public function getBaseCurrency(): string;

	/**
	 * Returns the raw stored rate for $sCurrency relative to the implementation's base currency,
	 * using the latest available UTC date from the loaded monthly cache.
	 *
	 * The numeric meaning depends on the base currency of the implementation:
	 * - EUR-based (ECB): how many units of $sCurrency equal 1 EUR.
	 * - RON-based (BNR): how many RON equal 1 unit of $sCurrency.
	 *
	 * Use convert() for cross-currency conversion between arbitrary currency pairs.
	 */
	public function getExchangeRate(string $sCurrency): float;

	/**
	 * Returns the raw stored rate for $sCurrency relative to the base currency
	 * for a specific UTC date in Y-m-d format.
	 *
	 * Use convertByDate() for cross-currency conversion on historical dates.
	 */
	public function getExchangeRateByDate(string $sCurrency, string $sDate): float;

	/**
	 * Converts $fAmount from $sFromCurrency to $sToCurrency using the latest cached UTC day.
	 * If $sToCurrency is null the configured default target currency is used.
	 */
	public function convert(float $fAmount, string $sFromCurrency, ?string $sToCurrency = null): float;

	/**
	 * Converts $fAmount from $sFromCurrency to $sToCurrency using a specific UTC date in Y-m-d format.
	 * If $sToCurrency is null the configured default target currency is used.
	 */
	public function convertByDate(
		float $fAmount,
		string $sFromCurrency,
		string $sDate,
		?string $sToCurrency = null,
		bool $bUseCurrentExchangeRateIfHistoryIsMissing = true
	): float;

	/**
	 * Forces a remote refresh and overwrites the current monthly cache file.
	 */
	public function refresh(): self;

	/**
	 * Returns the full loaded monthly data structure.
	 *
	 * Example shape:
	 * [
	 *     'base'       => 'EUR',
	 *     'updated_at' => 1234567890,
	 *     'month'      => '2026-03',
	 *     'source'     => 'ECB_EXR',
	 *     'days'       => ['2026-03-05' => ['USD' => 1.0832, ...], ...]
	 * ]
	 */
	public function getRates(): array;
}
