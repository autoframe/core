<?php

namespace Autoframe\Core\CliTools;

use Autoframe\Core\Exception\AfrException;
use Autoframe\Core\Http\Request\AfrRequestInterface;
use PHPUnit\Framework\TestCase;

class AfrGetOptTest extends TestCase
{
	/**
	 * @var AfrGetOpt
	 */
	private $afrGetOpt;

	protected function setUp(): void
	{
		parent::setUp();

		$this->afrGetOpt = AfrGetOpt::getInstance();
	}

	public function testTokenizeCliLineInput_WithValidInput_ExpectedValidOutput(): void
	{
		$input = 'test.php -f "value for f" --required value --optional="optional value"';

		$expectedResult = [
			'test.php',
			'-f',
			'value for f',
			'--required',
			'value',
			'--optional=optional value'
		];

		$actualResult = $this->afrGetOpt->tokenizeCliLineInput($input, true);

		$this->assertSame($expectedResult, $actualResult);
	}

	public function testTokenizeCliLineInput_WithInvalidInput_ExpectedValidOutput(): void
	{
		$input = 'test.php -f "value for f" --required --optional="optional value"';

		$expectedResult = [
			'test.php',
			'-f',
			'value for f',
			'--required',
			'--optional=optional value'
		];

		$actualResult = $this->afrGetOpt->tokenizeCliLineInput($input, true);

		$this->assertSame($expectedResult, $actualResult);
	}


	public function testGetopt_WithSetArgvFromRequest_ExpectedValidOutput(): void
	{
		$requestMock = $this->getMockBuilder(AfrRequestInterface::class)->getMock();
		$requestMock->method('isCli')->willReturn(true);
		$requestMock->method('getServerParam')->willReturn(['test.php', '--test=argument']);

		$this->afrGetOpt->setArgvFromRequest($requestMock);

		$expectedResult = ['test' => 'argument'];

		$actualResult = $this->afrGetOpt->getopt('', ['test::']);

		$this->assertSame($expectedResult, $actualResult);
	}

	public function testGetopt_WithSetArgvFromArray_ExpectedValidOutput(): void
	{
		$this->afrGetOpt->setArgvFromArray(['test.php', '--test=argument']);

		$expectedResult = ['test' => 'argument'];

		$actualResult = $this->afrGetOpt->getopt('', ['test::']);

		$this->assertSame($expectedResult, $actualResult);
	}

	public function testGetopt_WithTokenizeCliLineInput_ExpectedValidOutput(): void
	{
		$this->afrGetOpt->tokenizeCliLineInput('test.php --test="argument"', true);

		$expectedResult = ['test' => 'argument'];

		$actualResult = $this->afrGetOpt->getopt('', ['test::']);

		$this->assertSame($expectedResult, $actualResult);
	}

	public function testGetopt_WithoutSettingArgv_ExpectedException(): void
	{
		$this->expectException(AfrException::class);
		$this->expectExceptionMessage('The arguments must be set before calling getopt() using setArgvFromRequest() or setArgvFromArray() or tokenizeCliLineInput(). The first argument is like `path/script.php`');
		$this->afrGetOpt->setArgvFromArray(null);
		$this->afrGetOpt->getopt('', ['test::']);
	}
}