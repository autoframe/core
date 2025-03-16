<?php
declare(strict_types=1);

namespace Unit\CliTools;

use Autoframe\Core\CliTools\AfrCliHttpDetect;
use Autoframe\Core\CliTools\AfrCliTextColors;
use Autoframe\Core\CliTools\AfrVendorDir;
use PHPUnit\Framework\TestCase;

class AfrCliToolsTest extends TestCase
{

	/**
	 * @test
	 */
	public function isCli(): void
	{
		$this->assertSame(AfrCliHttpDetect::isCli(), true);
		$this->assertSame(AfrCliHttpDetect::isHttpOrHttpsProtocolRequest(), false);
	}

	/**
	 * @test
	 */
	public function AfrCliTextColors(): void
	{
		AfrCliTextColors::demo();
		$this->assertSame(true, true);
	}

	/**
	 * @test
	 */
	public function AfrVendorDir(): void
	{
		$this->assertSame(true, AfrVendorDir::pathIsInsideVendorDir('/server/vendor/something'));
		$this->assertSame(true, AfrVendorDir::pathIsInsideVendorDir('/server/vendor'));
		$this->assertSame(true, AfrVendorDir::pathIsInsideVendorDir('/vendor/something'));
		$this->assertSame(false, AfrVendorDir::pathIsInsideVendorDir('/server/vendor/../notInsideVendorDir'));

		$this->assertSame(false, AfrVendorDir::pathIsInsideVendorDir('vendor/something'));
		$this->assertSame(false, AfrVendorDir::pathIsInsideVendorDir('/a/b/c'));
		$this->assertSame(false, AfrVendorDir::pathIsInsideVendorDir(''));


		$this->assertSame(false, strlen(AfrVendorDir::getVendorPath()) === 0);
		$this->assertSame(false, strlen(AfrVendorDir::getBaseDirPath()) === 0);
		$this->assertSame(false, empty(AfrVendorDir::getComposerJson()));
		$this->assertSame(false, empty(AfrVendorDir::getComposerTs()));
	}


}