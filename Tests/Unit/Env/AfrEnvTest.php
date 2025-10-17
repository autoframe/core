<?php
declare(strict_types=1);

namespace Unit\Env;

use Autoframe\Core\Env\AfrEnv;

use Autoframe\Core\Env\Exception\AfrEnvException;
use Autoframe\Core\FileSystem\Traversing\AfrDirTraversingFileListClass;
use http\Env;
use PHPUnit\Framework\TestCase;

class AfrEnvTest extends TestCase
{

	public static function insideProductionVendorDir(): bool
	{
		return strpos(__DIR__, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false;
	}

	protected static function mockDevEnvFileCheck()
	{
		$sBaseFile = __DIR__ . DIRECTORY_SEPARATOR . 'Env/dev.env';
		if(!is_file($sBaseFile) || !is_readable($sBaseFile) || filesize($sBaseFile) <1) {
			file_put_contents($sBaseFile, base64_decode('77u/Iy5lbnYNCkFQUF9FTlY9REVWDQoNCiBGT08gPSBiYXINCglCQVIJPQliYXoNClNQQUNFRD0id2l0aCBzcGFjZXMiDQoNCiNhc3NlcnRpb25zLmVudg0KQVNTRVJUVkFSMT12YWwxDQpBU1NFUlRWQVIyPSIiDQpBU1NFUlRWQVIzPSJ2YWwzICAgIg0KQVNTRVJUVkFSND0iMCIgIyBlbXB0eSBsb29raW5nIHZhbHVlDQpBU1NFUlRWQVI1PSIjZm9vIg0KQVNTRVJUVkFSNj0idmFsMQ0KdmFsMiINCkFTU0VSVFZBUjc9Ig0KdmFsMyIgIw0KQVNTRVJUVkFSOD0idmFsMw0KIg0KQVNTRVJUVkFSOT0iDQoNCiINCg0KTlZBUjE9IkhlbGzFjSINCk5WQVIyPSJXb3JsZCEiDQpOVkFSMz0ieyROVkFSMX0geyROVkFSMn0iDQpOVkFSND0iJHtOVkFSMX0gJHtOVkFSMn0iDQpOVkFSNT0iJE5WQVIxIHtOVkFSMn0iDQpOLlZBUjY9IlNwZWNpYWwgVmFsdWUiDQpOVkFSNz0iJHtOLlZBUjZ9Ig0KTlZBUjg9IiINCk5WQVI5PSIke05WQVI4fSINCk5WQVIxMD0iJHtOVkFSODg4fSINCk5WQVIxMT0iTlZBUjEiDQpOVkFSMTI9IiR7JHtOVkFSMTF9fSINCk5WQVIxMz0nJHske05WQVIxMX19Jw0KTlZBUjE0PScke05WQVIxfSAke05WQVIyfScNCk5WQVIxNT0iXCR7TlZBUjF9IFwke05WQVIyfSINCg0KI2Jvb2xlYW5zLmVudg0KVkFMSURfRVhQTElDSVRfTE9XRVJDQVNFX1RSVUU9dHJ1ZQ0KVkFMSURfRVhQTElDSVRfTE9XRVJDQVNFX0ZBTFNFPWZhbHNlDQpWQUxJRF9FWFBMSUNJVF9VUFBFUkNBU0VfVFJVRT1UUlVFDQpWQUxJRF9FWFBMSUNJVF9VUFBFUkNBU0VfRkFMU0U9RkFMU0UNClZBTElEX0VYUExJQ0lUX01JWEVEQ0FTRV9UUlVFPVRydWUNClZBTElEX0VYUExJQ0lUX01JWEVEQ0FTRV9GQUxTRT1GYWxzZQ0KVkFMSURfTlVNQkVSX1RSVUU9MQ0KVkFMSURfTlVNQkVSX0ZBTFNFPTANCg0KVkFMSURfT05PRkZfTE9XRVJDQVNFX1RSVUU9b24NClZBTElEX09OT0ZGX0xPV0VSQ0FTRV9GQUxTRT1vZmYNClZBTElEX09OT0ZGX1VQUEVSQ0FTRV9UUlVFPU9ODQpWQUxJRF9PTk9GRl9VUFBFUkNBU0VfRkFMU0U9T0ZGDQpWQUxJRF9PTk9GRl9NSVhFRENBU0VfVFJVRT1Pbg0KVkFMSURfT05PRkZfTUlYRURDQVNFX0ZBTFNFPU9mZg0KDQpWQUxJRF9ZRVNOT19MT1dFUkNBU0VfVFJVRT15ZXMNClZBTElEX1lFU05PX0xPV0VSQ0FTRV9GQUxTRT1ubw0KVkFMSURfWUVTTk9fVVBQRVJDQVNFX1RSVUU9WUVTDQpWQUxJRF9ZRVNOT19VUFBFUkNBU0VfRkFMU0U9Tk8NClZBTElEX1lFU05PX01JWEVEQ0FTRV9UUlVFPVllcw0KVkFMSURfWUVTTk9fTUlYRURDQVNFX0ZBTFNFPU5vDQoNCiNjb21tZW50ZWQuZW52DQojIFRoaXMgaXMgYSBjb21tZW50DQpDRk9PPWJhcg0KI0NCQVI9YmF6DQojQ1pPTz1nb28gIyBhIGNvbW1lbnQgb24gYSBjb21tZW50ZWQgcm93DQpDU1BBQ0VEPSJ3aXRoIHNwYWNlcyIgIyB0aGlzIGlzIGEgY29tbWVudA0KQ1FVT1RFUz0iYSB2YWx1ZSB3aXRoIGEgIyBjaGFyYWN0ZXIiICMgdGhpcyBpcyBhIGNvbW1lbnQNCkNRVU9URVNXSVRIUVVPVEU9ImEgdmFsdWUgd2l0aCBhICMgY2hhcmFjdGVyICYgYSBxdW90ZSBcIiBjaGFyYWN0ZXIgaW5zaWRlIHF1b3RlcyIgIyAiIHRoaXMgaXMgYSBjb21tZW50DQpFTVBUWT0gIyBjb21tZW50IHdpdGggZW1wdHkgdmFyaWFibGUNCkVNUFRZMj0jIGNvbW1lbnQgd2l0aCBlbXB0eSB2YXJpYWJsZQ0KRk9PTz1mb28jIGNvbW1lbnQgd2l0aCBubyBzcGFjZQ0KQk9PTEVBTj15ZXMgIyAoeWVzLCBubykNCkNOVUxMPQ0KIyMgdGhpcyBpcyBhIGNvbW1lbnQgIyMNCg0KI2ludGVnZXJzLmVudg0KVkFMSURfWkVSTz0wDQpWQUxJRF9PTkU9MQ0KVkFMSURfVFdPPTINCg0KVkFMSURfTEFSR0U9OTk5OTk5OTkNClZBTElEX0xBUkdFX01JTlVTPS04ODg4ODg4OA0KVkFMSURfSFVHRT05OTk5OTk5OTk5OTk5OTk5OTk5OTk5OTk5OTk5OTk5OQ0KDQpJTlZBTElEX1NPTUVUSElORz1zb21ldGhpbmcNCklOVkFMSURfRU1QVFk9DQpJTlZBTElEX0VNUFRZX1NUUklORz0iIg0KSU5WQUxJRF9OVUxMPW51bGwNCklOVkFMSURfTkVHQVRJVkU9LTINCklOVkFMSURfTUlOVVM9LQ0KSU5WQUxJRF9USUxEQT1+DQpJTlZBTElEX0VYQ0xBTUFUSU9OPSENCklOVkFMSURfTlVNQkVSX1BPU0lUSVZFPTINCklOVkFMSURfTlVNQkVSX05FR0FUSVZFPS0yDQpJTlZBTElEX1NQQUNFUz0gIiAxMjMiDQpJTlZBTElEX0NPTU1BUz0iMTIzLDEyMyINCg0KREVDSU1BTD0yLjQ0DQpERUNJTUFMX05FR0FUSVZFPS0yLjQ0DQpMQVJHRT0nMTExMTExMTExMTExMScNCkhVR0U9J0FBQUFBQUFBQUFBQUFBQUFBQUFBQScNCg0KTUIxPSLEgCDEgSDEgiDEgyDEhCDEhSDEhiDEhyDEiCDEiSDEiiDEiyDEjCDEjSDEjiDEjyDEkCDEkSDEkiDEkyDElCDElSDEliDElyDEmCDEmSDEmiDEmyINCk1CMj3ooYzlhoXmlK/ku5gNCkFQUF9FTlZfUk9DS0VUPfCfmoANCg0KVEVTVD0idGVzdA0KICAgICB0ZXN0XCJ0ZXN0XCINCiAgICAgdGVzdCINCg0KVEVTVF9ORD0idGVzdFxudGVzdCINClRFU1RfTlM9J3Rlc3RcbnRlc3QnDQoNClRFU1RfRVFEPSJodHRwczovL3Zpc2lvbi5nb29nbGVhcGlzLmNvbS92MS9pbWFnZXM6YW5ub3RhdGU/a2V5PSINClRFU1RfRVFTPSdodHRwczovL3Zpc2lvbi5nb29nbGVhcGlzLmNvbS92MS9pbWFnZXM6YW5ub3RhdGU/a2V5PScNCg0KQkFTRTY0X0VOQ09ERURfTVVMVElMSU5FPSJxUzF6Q3pNVlZVSldRU2hva3Y2WVZZaStydUtTQy9iSFY3R21FaXlWa0xhQldKSE5WSENIc2dUa3NFQnN5OHdKDQp1d3ljQXZSMDdaeU9KSmVkNFhUUk1LbktwMS92KzZVQVRwV3prSWpaWHl0SytwRCtYbFppbVVIVHgzdWlEY21VDQpqaFFYMXdXU3hIRHFyU1d4ZUlKaVREK0J1VXlJZDhGem1YUTNUY0J5ZEo0NzR0bU9VMkY0OTJ1YmszTEFpWjE4DQptaGlSR29zaFhBT1NiUy9QMytSWmk0YkRlTkUvTm80PSINCg0KTVVMVEkxPWZvbw0KTVVMVEkyPSR7TVVMVEkxfQ0KTVVMVEkxPWJhcg0KDQpRRk9PPSJiYXIiDQpRQkFSPSJiYXoiDQpRU1BBQ0VEPSJ3aXRoIHNwYWNlcyINClFFUVVBTFM9InBnc3FsOmhvc3Q9bG9jYWxob3N0O2RibmFtZT10ZXN0Ig0KDQpRTlVMTD0iIg0KIFFXSElURVNQQUNFID0gIm5vIHNwYWNlIg0KDQpRRVNDQVBFRD0idGVzdCBzb21lIGVzY2FwZWQgY2hhcmFjdGVycyBsaWtlIGEgcXVvdGUgKFwiKSBvciBtYXliZSBhIGJhY2tzbGFzaCAoXFwpIg0KUVNMQVNIPSJpaWlpdmlpaWl4aWlpaXZpaWlpXG4iDQpTUVNMQVNIPSdpaWlpdmlpaWl4aWlpaXZpaWlpXG4nDQoNCiNzcGVjaWFsY2hhcnMuZW52DQpTUFZBUjE9IiRhNl5DN2slenMrZV4uanZqWGsiDQpTUFZBUjI9Ij9CVXR5M2tvYVYzJUdBKmhNQXdIfUIiDQpTUFZBUjM9ImpkZ0VCNHtRZ0VDXUhMKSkmR2NYeG9rQit3cW9OK2o+eGtWN0s/bSRyIg0KU1BWQVI0PSIyMjIyMjoyMiMyXnsiDQpTUFZBUjU9InRlc3Qgc29tZSBlc2NhcGVkIGNoYXJhY3RlcnMgbGlrZSBhIHF1b3RlIFwiIG9yIG1heWJlIGEgYmFja3NsYXNoIFxcIiAjIG5vdCBlc2NhcGVkDQpTUFZBUjY9c2VjcmV0IUAjDQpTUFZBUjc9J3NlY3JldCFAIycNClNQVkFSOD0ic2VjcmV0IUAjIg0KDQojdW5pY29kZXZhcm5hbWVzLmVudg0KQWxiZXJ0w4ViZXJnPVNreWJlcnQNCtCU0LDRgtCw0JfQsNC60YDRi9GC0LjRj9Cg0LDRgdGH0LXRgtC90L7Qs9C+0J/QtdGA0LjQvtC00LA9JzIwMjItMDQtMDFUMDA6MDAn'));
		}
		$sBaseFile2 = __DIR__ . DIRECTORY_SEPARATOR . 'Env/extra.env';
		if(!is_file($sBaseFile2) || !is_readable($sBaseFile2) || filesize($sBaseFile2) <1) {
			file_put_contents($sBaseFile2, base64_decode('I2V4dHJhIGZpbGUKRVhUUkE9IllFUyIKQUZSX0VOVl9ET0xMQVJfRU5WPXskQUZSX0VOVn0='));
		}
	}

	/**
	 * @test
	 */
	public function AfrEnvAllInOneTest(): void
	{
		self::mockDevEnvFileCheck();
		$sBaseDir = __DIR__ . DIRECTORY_SEPARATOR . 'Env';
		$oEnv = AfrEnv::getInstance()->setBaseDir($sBaseDir);

		$oEnv->xetAfrEnvParser();
		if ($bInsideProductionVendorDir = self::insideProductionVendorDir()) {
			$oEnv->readEnv(0);
		} else {
			$oEnv->readEnv(1)->flush();
			usleep(50*1000);
			$oEnv->setBaseDir(__DIR__ . DIRECTORY_SEPARATOR . 'Env');
			$oEnv->readEnv(2);
		}

		$aEnv = $oEnv->getEnv();
		if(count($aEnv)<10){
			$sCacheFileName = $oEnv->getCacheFileName();
			$sDebugSources = "\n sBaseDir : $sBaseDir\n";
			$sDebugSources .= "\n bInsideProductionVendorDir : `$bInsideProductionVendorDir`\n";
			$sDebugSources .= "\n oEnv->getCacheFileName() : `$sCacheFileName`\n";
			if($sCacheFileName && is_file($sCacheFileName)){
				$sDebugSources .= "\n filemtime(oEnv->getCacheFileName()) : `".filemtime($sCacheFileName)."` vs time:".time()."\n";
				$sDebugSources .= "\n file_size(oEnv->getCacheFileName()) : `".file_size($sCacheFileName)."` bytes \n";
			}
			$aEnvsFiles = AfrDirTraversingFileListClass::getInstance()->getDirFileList($sBaseDir);
			$sDebugSources .= "\n AfrDirTraversingFileListClass::getInstance()->getDirFileList(sBaseDir) : ".print_r($aEnvsFiles,true)."\n";
			$sDebugSources .= "\n file_exists($sBaseDir/dev.env) : `".file_exists($sBaseDir.'/dev.env')."`\n";
			$sDebugSources .= "\n file_exists($sBaseDir/extra.env) : `".file_exists($sBaseDir.'/extra.env')."`\n";

			$this->assertSame(true, false,'$oEnv->getEnv() has less than 10 entries '.print_r($aEnv,true).$sDebugSources);
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
		$this->assertSame(true, $oEnv->getEnv('NVAR2') === 'World!','$oEnv->getEnv() '.print_r($oEnv->getEnv(),true));
		$oEnv->registerEnv(true, true);
		$this->assertSame(true, $_SERVER['NVAR2'] === 'World!');
		$this->assertSame(true, $_ENV['NVAR2'] === 'World!');
		$this->assertSame(true, getenv('NVAR2') === 'World!');
		$oEnv->unrequire(['NVAR2']);

		$aEnv = $oEnv->getEnv(); //print_r($aEnv); die;
		//$this->assertSame(true, $aEnv,'$oEnv->getEnv() '.print_r($aEnv,true));


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