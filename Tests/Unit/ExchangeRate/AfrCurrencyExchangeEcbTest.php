<?php

declare(strict_types=1);

namespace Unit\ExchangeRate;

use Autoframe\Core\Bank\ExchangeRate\AfrCurrencyExchangeEcb;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;

class AfrCurrencyExchangeEcbTest extends TestCase
{
	private string $sTempDir = '';
	private AfrCurrencyExchangeEcb $oEcb;

	// Fixture rates matching example.ecb.cache.php (three days, latest = 2026-03-05)
	private const FIXTURE_MONTH  = '2026-03';
	private const FIXTURE_DATE_1 = '2026-03-03';
	private const FIXTURE_DATE_2 = '2026-03-04';
	private const FIXTURE_DATE_3 = '2026-03-05'; // latest → used by getExchangeRateBaseCurrency / convert

	// Day 1 rates (2026-03-03)
	private const D1_USD = 1.0821;
	private const D1_RON = 4.9711;
	private const D1_GBP = 0.8415;

	// Day 2 rates (2026-03-04)
	private const D2_USD = 1.0842;
	private const D2_RON = 4.9739;
	private const D2_GBP = 0.8420;

	// Day 3 rates (2026-03-05) — latest
	private const D3_USD = 1.0860;
	private const D3_RON = 4.9750;
	private const D3_GBP = 0.8424;

	protected function setUp(): void
	{
		echo __CLASS__ . '->' . __FUNCTION__ . PHP_EOL;
		$this->sTempDir = sys_get_temp_dir()
			. DIRECTORY_SEPARATOR . 'afr_ecb_test_' . uniqid() . DIRECTORY_SEPARATOR;
		mkdir($this->sTempDir, 0775, true);

		$this->oEcb = AfrCurrencyExchangeEcb::getInstance();
		$this->oEcb->setCacheDir($this->sTempDir);
		$this->injectMemoryData($this->freshFixtureData());
	}

	protected function tearDown(): void
	{
		$this->clearStaticKey(AfrCurrencyExchangeEcb::class, 'aData');
		$this->clearStaticKey(AfrCurrencyExchangeEcb::class, 'sDefaultToCurrency');
		$this->removeDir($this->sTempDir);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function freshFixtureData(): array
	{
		return [
			'base'       => 'EUR',
			'updated_at' => time(),
			'month'      => self::FIXTURE_MONTH,
			'source'     => 'https://data-api.ecb.europa.eu/service/data/EXR',
			'days'       => [
				self::FIXTURE_DATE_1 => ['EUR' => 1.0, 'USD' => self::D1_USD, 'RON' => self::D1_RON, 'GBP' => self::D1_GBP],
				self::FIXTURE_DATE_2 => ['EUR' => 1.0, 'USD' => self::D2_USD, 'RON' => self::D2_RON, 'GBP' => self::D2_GBP],
				self::FIXTURE_DATE_3 => ['EUR' => 1.0, 'USD' => self::D3_USD, 'RON' => self::D3_RON, 'GBP' => self::D3_GBP],
			],
		];
	}

	/** Injects data directly into the static in-memory cache so no disk/network is needed. */
	private function injectMemoryData(array $aData): void
	{
		$oProp = new ReflectionProperty(AfrCurrencyExchangeEcb::class, 'aData');
		$oProp->setAccessible(true);
		$aAll = $oProp->getValue();
		$aAll[AfrCurrencyExchangeEcb::class] = $aData;
		$oProp->setValue(null, $aAll);
	}

	/** Removes the given key from a static array property via reflection. */
	private function clearStaticKey(string $sClass, string $sProp): void
	{
		$oProp = new ReflectionProperty($sClass, $sProp);
		$oProp->setAccessible(true);
		$aAll = $oProp->getValue();
		unset($aAll[$sClass]);
		$oProp->setValue(null, $aAll);
	}

	/** Invokes a protected method on the singleton instance. */
	private function callProtected(string $sMethod, ...$aArgs)
	{
		$oRef = new ReflectionMethod(AfrCurrencyExchangeEcb::class, $sMethod);
		$oRef->setAccessible(true);
		return $oRef->invoke($this->oEcb, ...$aArgs);
	}

	/** Recursively deletes a directory. */
	private function removeDir(string $sDir): void
	{
		if (!is_dir($sDir)) return;
		foreach (glob($sDir . '*') ?: [] as $sFile) {
			is_dir($sFile) ? $this->removeDir($sFile . DIRECTORY_SEPARATOR) : unlink($sFile);
		}
		rmdir($sDir);
	}

	// -------------------------------------------------------------------------
	// Base currency and defaults
	// -------------------------------------------------------------------------

	/** @test */
	public function testGetBaseCurrencyIsEur(): void
	{
		$this->assertSame('EUR', $this->oEcb->getBaseCurrency());
	}

	/** @test */
	public function testGetDefaultToCurrencyDefaultsToEur(): void
	{
		$this->assertSame('EUR', $this->oEcb->getDefaultToCurrency());
	}

	/** @test */
	public function testSetDefaultToCurrencyChangesValue(): void
	{
		$this->oEcb->setDefaultToCurrency('USD');
		$this->assertSame('USD', $this->oEcb->getDefaultToCurrency());
	}

	/** @test */
	public function testSetDefaultToCurrencyNormalizesToUppercase(): void
	{
		$this->oEcb->setDefaultToCurrency('usd');
		$this->assertSame('USD', $this->oEcb->getDefaultToCurrency());
	}

	/** @test */
	public function testSetDefaultToCurrencyThrowsOnEmptyString(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->oEcb->setDefaultToCurrency('');
	}

	// -------------------------------------------------------------------------
	// Cache directory
	// -------------------------------------------------------------------------

	/** @test */
	public function testGetCacheDirEndsWithDirectorySeparator(): void
	{
		$sDir     = $this->oEcb->getCacheDir();
		$sLastChr = substr($sDir, -1);
		$this->assertTrue($sLastChr === '/' || $sLastChr === DIRECTORY_SEPARATOR);
	}

	/** @test */
	public function testSetCacheDirClearsInMemoryCache(): void
	{
		$sNewDir = sys_get_temp_dir()
			. DIRECTORY_SEPARATOR . 'afr_ecb_reset_' . uniqid() . DIRECTORY_SEPARATOR;
		mkdir($sNewDir, 0775, true);

		$this->oEcb->setCacheDir($sNewDir);

		$oProp = new ReflectionProperty(AfrCurrencyExchangeEcb::class, 'aData');
		$oProp->setAccessible(true);
		$aAll = $oProp->getValue();
		$this->assertNull($aAll[AfrCurrencyExchangeEcb::class] ?? null);

		$this->removeDir($sNewDir);

		// Restore for other tests
		$this->oEcb->setCacheDir($this->sTempDir);
		$this->injectMemoryData($this->freshFixtureData());
	}

	// -------------------------------------------------------------------------
	// getExchangeRateBaseCurrency (uses latest available day = 2026-03-05)
	// -------------------------------------------------------------------------

	/** @test */
	public function testGetExchangeRateForBaseCurrencyIsOne(): void
	{
		$this->assertSame(1.0, $this->oEcb->getExchangeRateBaseCurrency('EUR'));
	}

	/** @test */
	public function testGetExchangeRateReturnsLatestDayUsdRate(): void
	{
		$this->assertEqualsWithDelta(self::D3_USD, $this->oEcb->getExchangeRateBaseCurrency('USD'), 0.0001);
	}

	/** @test */
	public function testGetExchangeRateReturnsLatestDayRonRate(): void
	{
		$this->assertEqualsWithDelta(self::D3_RON, $this->oEcb->getExchangeRateBaseCurrency('RON'), 0.0001);
	}

	/** @test */
	public function testGetExchangeRateLowercaseCurrencyNormalized(): void
	{
		$this->assertEqualsWithDelta(self::D3_USD, $this->oEcb->getExchangeRateBaseCurrency('usd'), 0.0001);
	}

	/** @test */
	public function testGetExchangeRateThrowsForUnknownCurrency(): void
	{
		$this->expectException(RuntimeException::class);
		$this->oEcb->getExchangeRateBaseCurrency('XYZ');
	}

	// -------------------------------------------------------------------------
	// getExchangeRateByDate
	// -------------------------------------------------------------------------

	/** @test */
	public function testGetExchangeRateByDateReturnsCorrectRateForDay1(): void
	{
		$this->assertEqualsWithDelta(
			self::D1_USD,
			$this->oEcb->getExchangeRateByDate('USD', self::FIXTURE_DATE_1),
			0.0001
		);
	}

	/** @test */
	public function testGetExchangeRateByDateReturnsCorrectRateForDay3(): void
	{
		$this->assertEqualsWithDelta(
			self::D3_USD,
			$this->oEcb->getExchangeRateByDate('USD', self::FIXTURE_DATE_3),
			0.0001
		);
	}

	/** @test */
	public function testGetExchangeRateByDateFallsBackToLatestForDateAfterLastKnown(): void
	{
		// '2026-03-10' is after the last available day (2026-03-05); fallback returns
		// the last available entry (2026-03-05 data).
		$fRate = $this->oEcb->getExchangeRateByDate('USD', '2026-03-10');
		$this->assertEqualsWithDelta(self::D3_USD, $fRate, 0.0001);
	}

	/** @test */
	public function testGetExchangeRateByDateThrowsOnInvalidDateFormat(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->oEcb->getExchangeRateByDate('USD', '05-03-2026');
	}

	// -------------------------------------------------------------------------
	// convert (uses latest available day = 2026-03-05)
	// -------------------------------------------------------------------------

	/** @test */
	public function testConvertFromEurToUsd(): void
	{
		// FROM EUR: amount * target_rate
		$fExpected = 100.0 * self::D3_USD;
		$fActual   = $this->oEcb->convert(100.0, 'EUR', 'USD');
		$this->assertEqualsWithDelta($fExpected, $fActual, 0.0001);
	}

	/** @test */
	public function testConvertFromUsdToEur(): void
	{
		// TO EUR: amount / source_rate
		$fExpected = 100.0 / self::D3_USD;
		$fActual   = $this->oEcb->convert(100.0, 'USD', 'EUR');
		$this->assertEqualsWithDelta($fExpected, $fActual, 0.0001);
	}

	/** @test */
	public function testConvertFromEurToRon(): void
	{
		$fExpected = 100.0 * self::D3_RON;
		$fActual   = $this->oEcb->convert(100.0, 'EUR', 'RON');
		$this->assertEqualsWithDelta($fExpected, $fActual, 0.0001);
	}

	/** @test */
	public function testConvertFromRonToEur(): void
	{
		$fExpected = 100.0 / self::D3_RON;
		$fActual   = $this->oEcb->convert(100.0, 'RON', 'EUR');
		$this->assertEqualsWithDelta($fExpected, $fActual, 0.0001);
	}

	/** @test */
	public function testConvertBetweenTwoCurrencies(): void
	{
		// USD → RON: to EUR first (100 / USD_RATE), then to RON (* RON_RATE)
		$fExpected = (100.0 / self::D3_USD) * self::D3_RON;
		$fActual   = $this->oEcb->convert(100.0, 'USD', 'RON');
		$this->assertEqualsWithDelta($fExpected, $fActual, 0.0001);
	}

	/** @test */
	public function testConvertSameCurrencyReturnsOriginalAmount(): void
	{
		$this->assertSame(100.0, $this->oEcb->convert(100.0, 'USD', 'USD'));
		$this->assertSame(100.0, $this->oEcb->convert(100.0, 'EUR', 'EUR'));
	}

	/** @test */
	public function testConvertUsesDefaultToCurrency(): void
	{
		$this->oEcb->setDefaultToCurrency('USD');
		$fExpected = 100.0 * self::D3_USD;  // EUR → USD
		$fActual   = $this->oEcb->convert(100.0, 'EUR'); // no explicit $sToCurrency
		$this->assertEqualsWithDelta($fExpected, $fActual, 0.0001);
	}

	// -------------------------------------------------------------------------
	// convertByDate
	// -------------------------------------------------------------------------

	/** @test */
	public function testConvertByDateOnSpecificDay(): void
	{
		// Day 1 rates: EUR → USD
		$fExpected = 100.0 * self::D1_USD;
		$fActual   = $this->oEcb->convertByDate(100.0, 'EUR', self::FIXTURE_DATE_1, 'USD');
		$this->assertEqualsWithDelta($fExpected, $fActual, 0.0001);
	}

	/** @test */
	public function testConvertByDateGivesDifferentRatesForDifferentDays(): void
	{
		$fDay1 = $this->oEcb->convertByDate(100.0, 'EUR', self::FIXTURE_DATE_1, 'USD');
		$fDay3 = $this->oEcb->convertByDate(100.0, 'EUR', self::FIXTURE_DATE_3, 'USD');
		$this->assertNotEquals($fDay1, $fDay3);
	}

	/** @test */
	public function testConvertByDateWithMissingMonthFallsBackToCurrentRates(): void
	{
		// Falls back to convert() using in-memory fixture data (EUR→USD at day 3 rates)
		$fExpected = 100.0 * self::D3_USD;
		$fActual   = $this->oEcb->convertByDate(
			100.0, 'EUR', '1999-01-15', 'USD',
			true // bUseCurrentExchangeRateIfHistoryIsMissing
		);
		$this->assertEqualsWithDelta($fExpected, $fActual, 0.0001);
	}

	/** @test */
	public function testConvertByDateWithMissingMonthThrowsWhenFallbackDisabled(): void
	{
		$this->expectException(RuntimeException::class);
		$this->oEcb->convertByDate(100.0, 'EUR', '1999-01-15', 'USD', false);
	}

	// -------------------------------------------------------------------------
	// validateData
	// -------------------------------------------------------------------------

	/** @test */
	public function testValidateDataPassesOnValidStructure(): void
	{
		$this->callProtected('validateData', $this->freshFixtureData());
		$this->assertTrue(true);
	}

	/** @test */
	public function testValidateDataThrowsOnNonEurBase(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$aData         = $this->freshFixtureData();
		$aData['base'] = 'RON';
		$this->callProtected('validateData', $aData);
	}

	/** @test */
	public function testValidateDataThrowsOnMissingBase(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$aData = $this->freshFixtureData();
		unset($aData['base']);
		$this->callProtected('validateData', $aData);
	}

	/** @test */
	public function testValidateDataThrowsOnMissingUpdatedAt(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$aData = $this->freshFixtureData();
		unset($aData['updated_at']);
		$this->callProtected('validateData', $aData);
	}

	/** @test */
	public function testValidateDataThrowsOnInvalidMonth(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$aData          = $this->freshFixtureData();
		$aData['month'] = '2026-13'; // month 13 doesn't exist
		$this->callProtected('validateData', $aData);
	}

	/** @test */
	public function testValidateDataThrowsOnEmptyDays(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$aData         = $this->freshFixtureData();
		$aData['days'] = [];
		$this->callProtected('validateData', $aData);
	}

	/** @test */
	public function testValidateDataThrowsOnMissingEurBaseInDayRates(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$aData = $this->freshFixtureData();
		unset($aData['days'][self::FIXTURE_DATE_1]['EUR']);
		$this->callProtected('validateData', $aData);
	}

	/** @test */
	public function testValidateDataThrowsOnDayOutsideDeclaredMonth(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$aData                       = $this->freshFixtureData();
		$aData['days']['2025-12-31'] = ['EUR' => 1.0, 'USD' => 1.08];
		$this->callProtected('validateData', $aData);
	}

	/** @test */
	public function testValidateDataThrowsOnZeroRate(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$aData                                         = $this->freshFixtureData();
		$aData['days'][self::FIXTURE_DATE_1]['USD']    = 0.0;
		$this->callProtected('validateData', $aData);
	}

	// -------------------------------------------------------------------------
	// Cache file I/O
	// -------------------------------------------------------------------------

	/** @test */
	public function testWriteCacheFileCreatesReadablePhpFile(): void
	{
		$aData    = $this->freshFixtureData();
		$aWritten = $this->callProtected('writeCacheFile', $aData);

		$this->assertIsArray($aWritten);
		$this->assertGreaterThan(0, $aWritten['updated_at']);

		$sExpectedFile = $this->sTempDir . '2026_03.php';
		$this->assertFileExists($sExpectedFile);

		$aRead = include $sExpectedFile;
		$this->assertIsArray($aRead);
		$this->assertSame('EUR', $aRead['base']);
		$this->assertCount(3, $aRead['days']);
	}

	/** @test */
	public function testWriteCacheFileSetsUpdatedAtToCurrentTime(): void
	{
		$iBefore  = time();
		$aWritten = $this->callProtected('writeCacheFile', $this->freshFixtureData());
		$iAfter   = time();

		$this->assertGreaterThanOrEqual($iBefore, $aWritten['updated_at']);
		$this->assertLessThanOrEqual($iAfter, $aWritten['updated_at']);
	}

	/** @test */
	public function testReadCacheFileByMonthReturnsDataForValidFile(): void
	{
		$this->callProtected('writeCacheFile', $this->freshFixtureData());

		$aRead = $this->callProtected('readCacheFileByMonth', self::FIXTURE_MONTH);

		$this->assertIsArray($aRead);
		$this->assertSame('EUR', $aRead['base']);
		$this->assertArrayHasKey(self::FIXTURE_DATE_3, $aRead['days']);
	}

	/** @test */
	public function testReadCacheFileByMonthReturnsNullForMissingFile(): void
	{
		$this->assertNull($this->callProtected('readCacheFileByMonth', '1900-01'));
	}

	// -------------------------------------------------------------------------
	// getRates
	// -------------------------------------------------------------------------

	/** @test */
	public function testGetRatesReturnsCompleteDataStructure(): void
	{
		$aRates = $this->oEcb->getRates();

		$this->assertIsArray($aRates);
		$this->assertSame('EUR', $aRates['base']);
		$this->assertSame(self::FIXTURE_MONTH, $aRates['month']);
		$this->assertCount(3, $aRates['days']);
		$this->assertArrayHasKey(self::FIXTURE_DATE_3, $aRates['days']);
		$this->assertSame(1.0, $aRates['days'][self::FIXTURE_DATE_3]['EUR']);
		$this->assertEqualsWithDelta(self::D3_USD, $aRates['days'][self::FIXTURE_DATE_3]['USD'], 0.0001);
	}

	// -------------------------------------------------------------------------
	// parseEcbCsvMonthlyRates
	// -------------------------------------------------------------------------

	/** @test */
	public function testParseEcbCsvMonthlyRatesExtractsRatesCorrectly(): void
	{
		$sCsv =
			"KEY,FREQ,CURRENCY,CURRENCY_DENOM,EXR_TYPE,EXR_SUFFIX,TIME_PERIOD,OBS_VALUE\n"
			. "EXR.D.USD.EUR.SP00.A,D,USD,EUR,SP00,A,2026-03-05,1.0860\n"
			. "EXR.D.RON.EUR.SP00.A,D,RON,EUR,SP00,A,2026-03-05,4.9750\n"
			. "EXR.D.GBP.EUR.SP00.A,D,GBP,EUR,SP00,A,2026-03-05,0.8424\n";

		$aResult = $this->callProtected('parseEcbCsvMonthlyRates', $sCsv);

		$this->assertIsArray($aResult);
		$this->assertSame('EUR', $aResult['base']);
		$this->assertSame('2026-03', $aResult['month']);
		$this->assertArrayHasKey('2026-03-05', $aResult['days']);

		$aDay = $aResult['days']['2026-03-05'];
		$this->assertSame(1.0, $aDay['EUR']);
		$this->assertEqualsWithDelta(1.0860, $aDay['USD'], 0.0001);
		$this->assertEqualsWithDelta(4.9750, $aDay['RON'], 0.0001);
		$this->assertEqualsWithDelta(0.8424, $aDay['GBP'], 0.0001);
	}

	/** @test */
	public function testParseEcbCsvMonthlyRatesAggregatesMultipleDays(): void
	{
		$sCsv =
			"KEY,FREQ,CURRENCY,CURRENCY_DENOM,EXR_TYPE,EXR_SUFFIX,TIME_PERIOD,OBS_VALUE\n"
			. "EXR.D.USD.EUR.SP00.A,D,USD,EUR,SP00,A,2026-03-03,1.0821\n"
			. "EXR.D.USD.EUR.SP00.A,D,USD,EUR,SP00,A,2026-03-04,1.0842\n"
			. "EXR.D.USD.EUR.SP00.A,D,USD,EUR,SP00,A,2026-03-05,1.0860\n";

		$aResult = $this->callProtected('parseEcbCsvMonthlyRates', $sCsv);

		$this->assertCount(3, $aResult['days']);
		$this->assertArrayHasKey('2026-03-03', $aResult['days']);
		$this->assertArrayHasKey('2026-03-04', $aResult['days']);
		$this->assertArrayHasKey('2026-03-05', $aResult['days']);
	}

	/** @test */
	public function testParseEcbCsvMonthlyRatesThrowsOnMissingRequiredColumns(): void
	{
		$this->expectException(RuntimeException::class);
		// CSV missing TIME_PERIOD column
		$sCsv =
			"KEY,FREQ,CURRENCY,OBS_VALUE\n"
			. "EXR.D.USD.EUR.SP00.A,D,USD,1.0860\n";
		$this->callProtected('parseEcbCsvMonthlyRates', $sCsv);
	}

	/** @test */
	public function testParseEcbCsvMonthlyRatesThrowsOnEmptyCsv(): void
	{
		$this->expectException(RuntimeException::class);
		$this->callProtected('parseEcbCsvMonthlyRates', '');
	}

	// -------------------------------------------------------------------------
	// buildHeaderMap
	// -------------------------------------------------------------------------

	/** @test */
	public function testBuildHeaderMapReturnsCorrectColumnIndices(): void
	{
		$aHeader = ['KEY', 'FREQ', 'CURRENCY', 'TIME_PERIOD', 'OBS_VALUE'];
		$aMap    = $this->callProtected('buildHeaderMap', $aHeader);

		$this->assertSame(0, $aMap['KEY']);
		$this->assertSame(2, $aMap['CURRENCY']);
		$this->assertSame(3, $aMap['TIME_PERIOD']);
		$this->assertSame(4, $aMap['OBS_VALUE']);
	}

	/** @test */
	public function testBuildHeaderMapNormalizesToUppercase(): void
	{
		$aHeader = ['key', 'currency', 'time_period', 'obs_value'];
		$aMap    = $this->callProtected('buildHeaderMap', $aHeader);

		$this->assertArrayHasKey('CURRENCY', $aMap);
		$this->assertArrayHasKey('TIME_PERIOD', $aMap);
		$this->assertArrayHasKey('OBS_VALUE', $aMap);
	}

	// -------------------------------------------------------------------------
	// isDataExpired
	// -------------------------------------------------------------------------

	/** @test */
	public function testIsDataExpiredReturnsTrueForZeroTimestamp(): void
	{
		$this->assertTrue($this->callProtected('isDataExpired', ['updated_at' => 0]));
	}

	/** @test */
	public function testIsDataExpiredReturnsTrueForStaleTimestamp(): void
	{
		$this->assertTrue(
			$this->callProtected('isDataExpired', ['updated_at' => time() - 3700])
		);
	}

	/** @test */
	public function testIsDataExpiredReturnsFalseForCurrentTimestamp(): void
	{
		$this->assertFalse(
			$this->callProtected('isDataExpired', ['updated_at' => time()])
		);
	}

	// -------------------------------------------------------------------------
	// Normalization helpers
	// -------------------------------------------------------------------------

	/** @test */
	public function testNormalizeCacheDirWithNullUsesDefaultPathContainingClassName(): void
	{
		$sNormPath = $this->callProtected('normalizeCacheDir', null);
		$this->assertStringContainsString('AfrCurrencyExchangeEcb', $sNormPath);
	}

	/** @test */
	public function testNormalizeCacheDirAddsTrailingSeparator(): void
	{
		$sPath     = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'afr_ecb_norm_' . uniqid();
		$sNormPath = $this->callProtected('normalizeCacheDir', $sPath);
		$sLastChr  = substr($sNormPath, -1);
		$this->assertTrue($sLastChr === '/' || $sLastChr === DIRECTORY_SEPARATOR);
	}
}
