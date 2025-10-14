<?php
declare(strict_types=1);

namespace Unit\Env;

use Autoframe\Core\Env\AfrEnv;

use Autoframe\Core\Env\Exception\AfrEnvException;
use PHPUnit\Framework\TestCase;

class AfrEnvTest extends TestCase
{

	public static function insideProductionVendorDir(): bool
	{
		return strpos(__DIR__, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false;
	}


	/**
	 * @test
	 */
	public function AfrEnvAllInOneTest(): void
	{
		$oEnv = AfrEnv::getInstance()->setBaseDir(__DIR__ . DIRECTORY_SEPARATOR . 'Env');

		$oEnv->xetAfrEnvParser();
		if (self::insideProductionVendorDir()) {
			$oEnv->readEnv(0);
		} else {
			$oEnv->readEnv(1)->flush();
			sleep(1);
			$oEnv->setBaseDir(__DIR__ . DIRECTORY_SEPARATOR . 'Env');
			$oEnv->readEnv(2);
		}

		$oEnv->setEnv('ARRAY_DATA', [2]);
		try {
			$oEnv->ifPresent(['VALID_EXPLICIT_LOWERCASE_TRUE'])->isBoolean();
			$oEnv->ifPresent(['VALID_LARGE'])->isInteger();
			$oEnv->ifPresent(['VALID_LARGE'])->notEmpty();
			$oEnv->ifPresent(['DECIMAL_NEGATIVE'])->isFloat();
			$oEnv->ifPresent(['NVAR1'])->isString();
			$oEnv->ifPresent(['ДатаЗакрытияРасчетногоПериода'])->isDateTime();
			$oEnv->ifPresent(['ARRAY_DATA'])->isArray();
			$oEnv->getEnv('NVAR1');
			$this->assertSame(true, true);

		} catch (AfrEnvException $e) {
			$this->assertSame(true, false, 'ifXXX: ' . $e->getMessage());
		}

		try {
			$this->assertSame(true, $oEnv->isDev());
		} catch (AfrEnvException $e) {
			$oEnv->setEnv('AFR_ENV','DEV');
			$this->assertSame(true, $oEnv->isDev());
		}


		$this->assertSame(false, $oEnv->isProduction());
		$this->assertSame(false, $oEnv->isStaging());


		try {
			$oEnv->required(['NVAR2'])->allowedValues(['World!X']);
			$oEnv->getEnv('NVAR2');
			$this->assertSame(true, false);

		} catch (AfrEnvException $e) {
			$this->assertSame(true, true);
			$oEnv->unrequire(['NVAR2']);
		}

		$oEnv->required(['NVAR2'])->allowedValues(['World!']);
		$this->assertSame(true, $oEnv->getEnv('NVAR2') === 'World!');
		$oEnv->registerEnv(true, true);
		$this->assertSame(true, $_SERVER['NVAR2'] === 'World!');
		$this->assertSame(true, $_ENV['NVAR2'] === 'World!');
		$this->assertSame(true, getenv('NVAR2') === 'World!');
		$oEnv->unrequire(['NVAR2']);

		$aEnv = $oEnv->getEnv(); //print_r($aEnv); die;

		$this->assertSame(true, $aEnv['VALID_EXPLICIT_LOWERCASE_TRUE']);
		$this->assertSame(true, $aEnv['VALID_EXPLICIT_UPPERCASE_TRUE']);
		$this->assertSame(true, $aEnv['VALID_EXPLICIT_MIXEDCASE_TRUE']);
		$this->assertSame(true, $aEnv['VALID_ONOFF_LOWERCASE_TRUE']);
		$this->assertSame(true, $aEnv['VALID_ONOFF_MIXEDCASE_TRUE']);
		$this->assertSame(true, $aEnv['VALID_YESNO_LOWERCASE_TRUE']);
		$this->assertSame(true, $aEnv['VALID_YESNO_UPPERCASE_TRUE']);
		$this->assertSame(true, $aEnv['VALID_YESNO_MIXEDCASE_TRUE']);
		$this->assertSame(true, $aEnv['EXTRA']);

		$this->assertSame(false, $aEnv['VALID_EXPLICIT_LOWERCASE_FALSE']);
		$this->assertSame(false, $aEnv['VALID_EXPLICIT_UPPERCASE_FALSE']);
		$this->assertSame(false, $aEnv['VALID_EXPLICIT_MIXEDCASE_FALSE']);
		$this->assertSame(false, $aEnv['VALID_ONOFF_LOWERCASE_FALSE']);
		$this->assertSame(false, $aEnv['VALID_ONOFF_UPPERCASE_FALSE']);
		$this->assertSame(false, $aEnv['VALID_ONOFF_MIXEDCASE_FALSE']);
		$this->assertSame(false, $aEnv['VALID_YESNO_LOWERCASE_FALSE']);
		$this->assertSame(false, $aEnv['VALID_YESNO_UPPERCASE_FALSE']);
		$this->assertSame(false, $aEnv['VALID_YESNO_MIXEDCASE_FALSE']);

		//nested
		$this->assertSame('Hellō World!', $aEnv['NVAR14']);
		$this->assertSame('Hellō World!', $aEnv['NVAR15']);
		//crlf
		$this->assertSame("\r\n\r\n", $aEnv['ASSERTVAR9']);
		$this->assertSame('bar', $aEnv['FOO']);
		$this->assertSame('baz', $aEnv['BAR']);
		$this->assertSame('val1', $aEnv['ASSERTVAR1']);
		$this->assertSame('', $aEnv['ASSERTVAR2']);
		$this->assertSame("iiiiviiiixiiiiviiii\n", $aEnv['QSLASH']);
		$this->assertSame("iiiiviiiixiiiiviiii\n", $aEnv['SQSLASH']);
		$this->assertSame('2022-04-01T00:00', $aEnv['ДатаЗакрытияРасчетногоПериода']);
		$this->assertSame('Skybert', $aEnv['AlbertÅberg']);

		//numbers
		$this->assertSame(0, $aEnv['ASSERTVAR4']);
		$this->assertSame(1, $aEnv['VALID_NUMBER_TRUE']);
		$this->assertSame(0, $aEnv['VALID_NUMBER_FALSE']);
		$this->assertSame(99999999, $aEnv['VALID_LARGE']);
		$this->assertSame(-2, $aEnv['INVALID_NUMBER_NEGATIVE']);
		$this->assertSame(-88888888, $aEnv['VALID_LARGE_MINUS']);
		$this->assertSame('99999999999999999999999999999999', $aEnv['VALID_HUGE']);
		$this->assertSame(2.44, $aEnv['DECIMAL']);
		$this->assertSame(-2.44, $aEnv['DECIMAL_NEGATIVE']);

		//strings
		$this->assertSame('Ā ā Ă ă Ą ą Ć ć Ĉ ĉ Ċ ċ Č č Ď ď Đ đ Ē ē Ĕ ĕ Ė ė Ę ę Ě ě', $aEnv['MB1']);
		$this->assertSame('行内支付', $aEnv['MB2']);
		$this->assertSame('🚀', $aEnv['APP_ENV_ROCKET']);
		$this->assertSame(null, $aEnv['INVALID_NULL']);
		$this->assertSame('', $aEnv['EMPTY']);
		$this->assertSame('bar', $aEnv['MULTI1']);
		$this->assertSame('$a6^C7k%zs+e^.jvjXk', $aEnv['SPVAR1']);
		$this->assertSame('~', $aEnv['INVALID_TILDA']);
		$this->assertSame('!', $aEnv['INVALID_EXCLAMATION']);
		$this->assertSame('-', $aEnv['INVALID_MINUS']);
		$this->assertSame(getenv()['ALBERTÅBERG'], $aEnv['AlbertÅberg']);

		$oEnv->ifPresent(['APP_ENV'])->allowedValues([
			'DEV',
			'PRODUCTION',
			'STAGING',
			'LOCAL',
			'CUSTOM',
		]);
		$oEnv->setEnv('APP_ENV', 'CUSTOM');
		$this->assertSame('CUSTOM', $oEnv->getEnv('APP_ENV'));
		$this->assertSame(true, $oEnv->isDev());
		$this->assertSame(true, $oEnv->setEnv('AFR_ENV', 'PRODUCTION')->isProduction());
		$oEnv->flush();
	}


}