<?php

declare(strict_types=1);

namespace Unit\ExchangeRate;

use Autoframe\Core\Bank\ExchangeRate\AfrCurrencyExchangeBnr;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;

class AfrCurrencyExchangeBnrTest extends TestCase
{
	private string $sTempDir = '';
	private AfrCurrencyExchangeBnr $oBnr;

	// Fixture rates matching example.bnr.cache.php
	private const FIXTURE_MONTH = '2026-03';
	private const FIXTURE_DATE  = '2026-03-13';
	private const RATE_EUR      = 5.0947;
	private const RATE_USD      = 4.4429;
	private const RATE_HUF      = 0.013029;
	private const RATE_JPY      = 0.027877;

	protected function setUp(): void
	{
		echo __CLASS__ . '->' . __FUNCTION__ . PHP_EOL;
		$this->sTempDir = sys_get_temp_dir()
			. DIRECTORY_SEPARATOR . 'afr_bnr_test_' . uniqid() . DIRECTORY_SEPARATOR;
		mkdir($this->sTempDir, 0775, true);

		$this->oBnr = AfrCurrencyExchangeBnr::getInstance();
		$this->oBnr->setCacheDir($this->sTempDir);
		$this->injectMemoryData($this->freshFixtureData());
	}

	protected function tearDown(): void
	{
		$this->clearStaticKey(AfrCurrencyExchangeBnr::class, 'aData');
		$this->clearStaticKey(AfrCurrencyExchangeBnr::class, 'sDefaultToCurrency');
		$this->removeDir($this->sTempDir);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function freshFixtureData(): array
	{
		return [
			'base'       => 'RON',
			'updated_at' => time(),
			'month'      => self::FIXTURE_MONTH,
			'source'     => 'https://www.bnr.ro/nbrfxrates.xml',
			'days'       => [
				self::FIXTURE_DATE => [
					'RON' => 1.0,
					'EUR' => self::RATE_EUR,
					'USD' => self::RATE_USD,
					'HUF' => self::RATE_HUF,
					'JPY' => self::RATE_JPY,
				],
			],
		];
	}

	/** Injects data directly into the static in-memory cache so no disk/network is needed. */
	private function injectMemoryData(array $aData): void
	{
		$oProp = new ReflectionProperty(AfrCurrencyExchangeBnr::class, 'aData');
		$oProp->setAccessible(true);
		$aAll = $oProp->getValue();
		$aAll[AfrCurrencyExchangeBnr::class] = $aData;
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
		$oRef = new ReflectionMethod(AfrCurrencyExchangeBnr::class, $sMethod);
		$oRef->setAccessible(true);
		return $oRef->invoke($this->oBnr, ...$aArgs);
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
	public function testGetBaseCurrencyIsRon(): void
	{
		$this->assertSame('RON', $this->oBnr->getBaseCurrency());
	}

	/** @test */
	public function testGetDefaultToCurrencyDefaultsToRon(): void
	{
		$this->assertSame('RON', $this->oBnr->getDefaultToCurrency());
	}

	/** @test */
	public function testSetDefaultToCurrencyChangesValue(): void
	{
		$this->oBnr->setDefaultToCurrency('EUR');
		$this->assertSame('EUR', $this->oBnr->getDefaultToCurrency());
	}

	/** @test */
	public function testSetDefaultToCurrencyNormalizesToUppercase(): void
	{
		$this->oBnr->setDefaultToCurrency('eur');
		$this->assertSame('EUR', $this->oBnr->getDefaultToCurrency());
	}

	/** @test */
	public function testSetDefaultToCurrencyThrowsOnEmptyString(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->oBnr->setDefaultToCurrency('');
	}

	// -------------------------------------------------------------------------
	// Cache directory
	// -------------------------------------------------------------------------

	/** @test */
	public function testGetCacheDirEndsWithDirectorySeparator(): void
	{
		$sDir     = $this->oBnr->getCacheDir();
		$sLastChr = substr($sDir, -1);
		$this->assertTrue($sLastChr === '/' || $sLastChr === DIRECTORY_SEPARATOR);
	}

	/** @test */
	public function testSetCacheDirClearsInMemoryCache(): void
	{
		$sNewDir = sys_get_temp_dir()
			. DIRECTORY_SEPARATOR . 'afr_bnr_reset_' . uniqid() . DIRECTORY_SEPARATOR;
		mkdir($sNewDir, 0775, true);

		$this->oBnr->setCacheDir($sNewDir);

		$oProp = new ReflectionProperty(AfrCurrencyExchangeBnr::class, 'aData');
		$oProp->setAccessible(true);
		$aAll = $oProp->getValue();
		$this->assertNull($aAll[AfrCurrencyExchangeBnr::class] ?? null);

		$this->removeDir($sNewDir);

		// Restore for other tests
		$this->oBnr->setCacheDir($this->sTempDir);
		$this->injectMemoryData($this->freshFixtureData());
	}

	// -------------------------------------------------------------------------
	// getExchangeRateBaseCurrency
	// -------------------------------------------------------------------------

	/** @test */
	public function testGetExchangeRateForBaseCurrencyIsOne(): void
	{
		$this->assertSame(1.0, $this->oBnr->getExchangeRateBaseCurrency('RON'));
	}

	/** @test */
	public function testGetExchangeRateReturnsCorrectEurValue(): void
	{
		$this->assertEqualsWithDelta(self::RATE_EUR, $this->oBnr->getExchangeRateBaseCurrency('EUR'), 0.0001);
	}

	/** @test */
	public function testGetExchangeRateReturnsCorrectUsdValue(): void
	{
		$this->assertEqualsWithDelta(self::RATE_USD, $this->oBnr->getExchangeRateBaseCurrency('USD'), 0.0001);
	}

	/** @test */
	public function testGetExchangeRateLowercaseCurrencyNormalized(): void
	{
		$this->assertEqualsWithDelta(self::RATE_EUR, $this->oBnr->getExchangeRateBaseCurrency('eur'), 0.0001);
	}

	/** @test */
	public function testGetExchangeRateThrowsForUnknownCurrency(): void
	{
		$this->expectException(RuntimeException::class);
		$this->oBnr->getExchangeRateBaseCurrency('XYZ');
	}

	// -------------------------------------------------------------------------
	// getExchangeRateByDate
	// -------------------------------------------------------------------------

	/** @test */
	public function testGetExchangeRateByDateReturnsCorrectRate(): void
	{
		$fRate = $this->oBnr->getExchangeRateByDate('EUR', self::FIXTURE_DATE);
		$this->assertEqualsWithDelta(self::RATE_EUR, $fRate, 0.0001);
	}

	/** @test */
	public function testGetExchangeRateByDateThrowsOnInvalidDateFormat(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->oBnr->getExchangeRateByDate('EUR', '13-03-2026');
	}

	/** @test */
	public function testGetExchangeRateByDateFallsBackToNearestAvailable(): void
	{
		// FIXTURE_DATE is '2026-03-13'; requesting a later date in the same month
		// falls back to the last available date (2026-03-13).
		$fRate = $this->oBnr->getExchangeRateByDate('EUR', '2026-03-25');
		$this->assertEqualsWithDelta(self::RATE_EUR, $fRate, 0.0001);
	}

	// -------------------------------------------------------------------------
	// convert
	// -------------------------------------------------------------------------

	/** @test */
	public function testConvertFromRonToEur(): void
	{
		// FROM RON: amount / target_rate
		$fExpected = 100.0 / self::RATE_EUR;
		$fActual   = $this->oBnr->convert(100.0, 'RON', 'EUR');
		$this->assertEqualsWithDelta($fExpected, $fActual, 0.0001);
	}

	/** @test */
	public function testConvertFromEurToRon(): void
	{
		// TO RON: amount * source_rate
		$fExpected = 100.0 * self::RATE_EUR;
		$fActual   = $this->oBnr->convert(100.0, 'EUR', 'RON');
		$this->assertEqualsWithDelta($fExpected, $fActual, 0.0001);
	}

	/** @test */
	public function testConvertBetweenTwoCurrencies(): void
	{
		// USD → EUR: to RON first (100 * USD_RATE), then to EUR (/ EUR_RATE)
		$fExpected = (100.0 * self::RATE_USD) / self::RATE_EUR;
		$fActual   = $this->oBnr->convert(100.0, 'USD', 'EUR');
		$this->assertEqualsWithDelta($fExpected, $fActual, 0.0001);
	}

	/** @test */
	public function testConvertSameCurrencyReturnsOriginalAmount(): void
	{
		$this->assertSame(100.0, $this->oBnr->convert(100.0, 'USD', 'USD'));
		$this->assertSame(100.0, $this->oBnr->convert(100.0, 'RON', 'RON'));
	}

	/** @test */
	public function testConvertUsesDefaultToCurrency(): void
	{
		$this->oBnr->setDefaultToCurrency('EUR');
		// 100 RON → EUR (no explicit target)
		$fExpected = 100.0 / self::RATE_EUR;
		$fActual   = $this->oBnr->convert(100.0, 'RON');
		$this->assertEqualsWithDelta($fExpected, $fActual, 0.0001);
	}

	// -------------------------------------------------------------------------
	// convertByDate
	// -------------------------------------------------------------------------

	/** @test */
	public function testConvertByDate(): void
	{
		// EUR → RON on fixture date
		$fExpected = 100.0 * self::RATE_EUR;
		$fActual   = $this->oBnr->convertByDate(100.0, 'EUR', self::FIXTURE_DATE, 'RON');
		$this->assertEqualsWithDelta($fExpected, $fActual, 0.0001);
	}

	/** @test */
	public function testConvertByDateBetweenTwoCurrencies(): void
	{
		$fExpected = (100.0 * self::RATE_USD) / self::RATE_EUR;
		$fActual   = $this->oBnr->convertByDate(100.0, 'USD', self::FIXTURE_DATE, 'EUR');
		$this->assertEqualsWithDelta($fExpected, $fActual, 0.0001);
	}

	/** @test */
	public function testConvertByDateWithMissingMonthFallsBackToCurrentRates(): void
	{
		// '1999-01-01' has no cache → code-91 exception → falls back to convert()
		$fExpected = 100.0 * self::RATE_EUR; // same as EUR→RON with fixture data
		$fActual   = $this->oBnr->convertByDate(
			100.0, 'EUR', '1999-01-01', 'RON',
			true // bUseCurrentExchangeRateIfHistoryIsMissing
		);
		$this->assertEqualsWithDelta($fExpected, $fActual, 0.0001);
	}

	/** @test */
	public function testConvertByDateWithMissingMonthThrowsWhenFallbackDisabled(): void
	{
		$this->expectException(RuntimeException::class);
		$this->oBnr->convertByDate(100.0, 'EUR', '1999-01-01', 'RON', false);
	}

	// -------------------------------------------------------------------------
	// validateData
	// -------------------------------------------------------------------------

	/** @test */
	public function testValidateDataPassesOnValidStructure(): void
	{
		// Should not throw
		$this->callProtected('validateData', $this->freshFixtureData());
		$this->assertTrue(true);
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
		$aData['month'] = 'not-a-month';
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
	public function testValidateDataThrowsOnMissingRonBaseInDayRates(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$aData = $this->freshFixtureData();
		unset($aData['days'][self::FIXTURE_DATE]['RON']);
		$this->callProtected('validateData', $aData);
	}

	/** @test */
	public function testValidateDataThrowsOnDayOutsideDeclaredMonth(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$aData                       = $this->freshFixtureData();
		$aData['days']['2025-01-01'] = ['RON' => 1.0, 'EUR' => 5.0];
		$this->callProtected('validateData', $aData);
	}

	/** @test */
	public function testValidateDataThrowsOnZeroRate(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$aData                                      = $this->freshFixtureData();
		$aData['days'][self::FIXTURE_DATE]['EUR']   = 0.0;
		$this->callProtected('validateData', $aData);
	}

	/** @test */
	public function testValidateDataThrowsOnNegativeRate(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$aData                                    = $this->freshFixtureData();
		$aData['days'][self::FIXTURE_DATE]['USD'] = -4.44;
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
		$this->assertSame('RON', $aRead['base']);
		$this->assertSame(self::FIXTURE_MONTH, $aRead['month']);
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
		$this->assertSame('RON', $aRead['base']);
		$this->assertArrayHasKey(self::FIXTURE_DATE, $aRead['days']);
	}

	/** @test */
	public function testReadCacheFileByMonthReturnsNullForMissingFile(): void
	{
		$aResult = $this->callProtected('readCacheFileByMonth', '1900-01');
		$this->assertNull($aResult);
	}

	// -------------------------------------------------------------------------
	// getRates
	// -------------------------------------------------------------------------

	/** @test */
	public function testGetRatesReturnsCompleteDataStructure(): void
	{
		$aRates = $this->oBnr->getRates();

		$this->assertIsArray($aRates);
		$this->assertSame('RON', $aRates['base']);
		$this->assertSame(self::FIXTURE_MONTH, $aRates['month']);
		$this->assertArrayHasKey('days', $aRates);
		$this->assertArrayHasKey(self::FIXTURE_DATE, $aRates['days']);
		$this->assertSame(1.0, $aRates['days'][self::FIXTURE_DATE]['RON']);
		$this->assertEqualsWithDelta(self::RATE_EUR, $aRates['days'][self::FIXTURE_DATE]['EUR'], 0.0001);
	}

	// -------------------------------------------------------------------------
	// isLikelyValidBnrXml
	// -------------------------------------------------------------------------

	/** @test */
	public function testIsLikelyValidBnrXmlAcceptsWellFormedXml(): void
	{
		$sXml = '<DataSet>'
			. '<OrigCurrency>RON</OrigCurrency>'
			. '<Cube date="2026-03-13">'
			. '<Rate currency="EUR">5.0947</Rate>'
			. '</Cube>'
			. '<PublishingDate>2026-03-13</PublishingDate>'
			. '</DataSet>';

		$this->assertTrue($this->callProtected('isLikelyValidBnrXml', $sXml));
	}

	/** @test */
	public function testIsLikelyValidBnrXmlRejectsEmptyString(): void
	{
		$this->assertFalse($this->callProtected('isLikelyValidBnrXml', ''));
	}

	/** @test */
	public function testIsLikelyValidBnrXmlRejectsMissingClosingTag(): void
	{
		$sIncomplete = '<Cube date="2026-03-13"><Rate currency="EUR">5.0</Rate>';
		$this->assertFalse($this->callProtected('isLikelyValidBnrXml', $sIncomplete));
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
	public function testIsDataExpiredReturnsTrueForMissingUpdatedAt(): void
	{
		$this->assertTrue($this->callProtected('isDataExpired', []));
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
		// updated_at = now: age 0, cannot be expired by max-age or hourly threshold
		$this->assertFalse(
			$this->callProtected('isDataExpired', ['updated_at' => time()])
		);
	}

	// -------------------------------------------------------------------------
	// Normalization helpers (via public surface)
	// -------------------------------------------------------------------------

	/** @test */
	public function testNormalizeCacheDirAddsTrailingSeparator(): void
	{
		$sPath     = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'afr_norm_' . uniqid();
		$sNormPath = $this->callProtected('normalizeCacheDir', $sPath);
		$sLastChr  = substr($sNormPath, -1);
		$this->assertTrue($sLastChr === '/' || $sLastChr === DIRECTORY_SEPARATOR);
	}

	/** @test */
	public function testNormalizeCacheDirWithNullUsesDefaultPath(): void
	{
		$sNormPath = $this->callProtected('normalizeCacheDir', null);
		$this->assertStringContainsString('AfrCurrencyExchangeBnr', $sNormPath);
	}
}
