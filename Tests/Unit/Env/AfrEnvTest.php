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
			file_put_contents($sBaseFile, base64_decode('Iy5lbnYKQVBQX0VOVj1ERVYKCiBGT08gPSBiYXIKCUJBUgk9CWJhegpTUEFDRUQ9IndpdGggc3BhY2VzIgoKI2Fzc2VydGlvbnMuZW52CkFTU0VSVFZBUjE9dmFsMQpBU1NFUlRWQVIyPSIiCkFTU0VSVFZBUjM9InZhbDMgICAiCkFTU0VSVFZBUjQ9IjAiICMgZW1wdHkgbG9va2luZyB2YWx1ZQpBU1NFUlRWQVI1PSIjZm9vIgpBU1NFUlRWQVI2PSJ2YWwxCnZhbDIiCkFTU0VSVFZBUjc9Igp2YWwzIiAjCkFTU0VSVFZBUjg9InZhbDMKIgpBU1NFUlRWQVI5PSIKCiIKCk5WQVIxPSJIZWxsxY0iCk5WQVIyPSJXb3JsZCEiCk5WQVIzPSJ7JE5WQVIxfSB7JE5WQVIyfSIKTlZBUjQ9IiR7TlZBUjF9ICR7TlZBUjJ9IgpOVkFSNT0iJE5WQVIxIHtOVkFSMn0iCk4uVkFSNj0iU3BlY2lhbCBWYWx1ZSIKTlZBUjc9IiR7Ti5WQVI2fSIKTlZBUjg9IiIKTlZBUjk9IiR7TlZBUjh9IgpOVkFSMTA9IiR7TlZBUjg4OH0iCk5WQVIxMT0iTlZBUjEiCk5WQVIxMj0iJHske05WQVIxMX19IgpOVkFSMTM9JyR7JHtOVkFSMTF9fScKTlZBUjE0PScke05WQVIxfSAke05WQVIyfScKTlZBUjE1PSJcJHtOVkFSMX0gXCR7TlZBUjJ9IgoKI2Jvb2xlYW5zLmVudgpWQUxJRF9FWFBMSUNJVF9MT1dFUkNBU0VfVFJVRT10cnVlClZBTElEX0VYUExJQ0lUX0xPV0VSQ0FTRV9GQUxTRT1mYWxzZQpWQUxJRF9FWFBMSUNJVF9VUFBFUkNBU0VfVFJVRT1UUlVFClZBTElEX0VYUExJQ0lUX1VQUEVSQ0FTRV9GQUxTRT1GQUxTRQpWQUxJRF9FWFBMSUNJVF9NSVhFRENBU0VfVFJVRT1UcnVlClZBTElEX0VYUExJQ0lUX01JWEVEQ0FTRV9GQUxTRT1GYWxzZQpWQUxJRF9OVU1CRVJfVFJVRT0xClZBTElEX05VTUJFUl9GQUxTRT0wCgpWQUxJRF9PTk9GRl9MT1dFUkNBU0VfVFJVRT1vbgpWQUxJRF9PTk9GRl9MT1dFUkNBU0VfRkFMU0U9b2ZmClZBTElEX09OT0ZGX1VQUEVSQ0FTRV9UUlVFPU9OClZBTElEX09OT0ZGX1VQUEVSQ0FTRV9GQUxTRT1PRkYKVkFMSURfT05PRkZfTUlYRURDQVNFX1RSVUU9T24KVkFMSURfT05PRkZfTUlYRURDQVNFX0ZBTFNFPU9mZgoKVkFMSURfWUVTTk9fTE9XRVJDQVNFX1RSVUU9eWVzClZBTElEX1lFU05PX0xPV0VSQ0FTRV9GQUxTRT1ubwpWQUxJRF9ZRVNOT19VUFBFUkNBU0VfVFJVRT1ZRVMKVkFMSURfWUVTTk9fVVBQRVJDQVNFX0ZBTFNFPU5PClZBTElEX1lFU05PX01JWEVEQ0FTRV9UUlVFPVllcwpWQUxJRF9ZRVNOT19NSVhFRENBU0VfRkFMU0U9Tm8KCiNjb21tZW50ZWQuZW52CiMgVGhpcyBpcyBhIGNvbW1lbnQKQ0ZPTz1iYXIKI0NCQVI9YmF6CiNDWk9PPWdvbyAjIGEgY29tbWVudCBvbiBhIGNvbW1lbnRlZCByb3cKQ1NQQUNFRD0id2l0aCBzcGFjZXMiICMgdGhpcyBpcyBhIGNvbW1lbnQKQ1FVT1RFUz0iYSB2YWx1ZSB3aXRoIGEgIyBjaGFyYWN0ZXIiICMgdGhpcyBpcyBhIGNvbW1lbnQKQ1FVT1RFU1dJVEhRVU9URT0iYSB2YWx1ZSB3aXRoIGEgIyBjaGFyYWN0ZXIgJiBhIHF1b3RlIFwiIGNoYXJhY3RlciBpbnNpZGUgcXVvdGVzIiAjICIgdGhpcyBpcyBhIGNvbW1lbnQKRU1QVFk9ICMgY29tbWVudCB3aXRoIGVtcHR5IHZhcmlhYmxlCkVNUFRZMj0jIGNvbW1lbnQgd2l0aCBlbXB0eSB2YXJpYWJsZQpGT09PPWZvbyMgY29tbWVudCB3aXRoIG5vIHNwYWNlCkJPT0xFQU49eWVzICMgKHllcywgbm8pCkNOVUxMPQojIyB0aGlzIGlzIGEgY29tbWVudCAjIwoKI2ludGVnZXJzLmVudgpWQUxJRF9aRVJPPTAKVkFMSURfT05FPTEKVkFMSURfVFdPPTIKClZBTElEX0xBUkdFPTk5OTk5OTk5ClZBTElEX0xBUkdFX01JTlVTPS04ODg4ODg4OApWQUxJRF9IVUdFPTk5OTk5OTk5OTk5OTk5OTk5OTk5OTk5OTk5OTk5OTk5CgpJTlZBTElEX1NPTUVUSElORz1zb21ldGhpbmcKSU5WQUxJRF9FTVBUWT0KSU5WQUxJRF9FTVBUWV9TVFJJTkc9IiIKSU5WQUxJRF9OVUxMPW51bGwKSU5WQUxJRF9ORUdBVElWRT0tMgpJTlZBTElEX01JTlVTPS0KSU5WQUxJRF9USUxEQT1+CklOVkFMSURfRVhDTEFNQVRJT049IQpJTlZBTElEX05VTUJFUl9QT1NJVElWRT0yCklOVkFMSURfTlVNQkVSX05FR0FUSVZFPS0yCklOVkFMSURfU1BBQ0VTPSAiIDEyMyIKSU5WQUxJRF9DT01NQVM9IjEyMywxMjMiCgpERUNJTUFMPTIuNDQKREVDSU1BTF9ORUdBVElWRT0tMi40NApMQVJHRT0nMTExMTExMTExMTExMScKSFVHRT0nQUFBQUFBQUFBQUFBQUFBQUFBQUFBJwoKTUIxPSLEgCDEgSDEgiDEgyDEhCDEhSDEhiDEhyDEiCDEiSDEiiDEiyDEjCDEjSDEjiDEjyDEkCDEkSDEkiDEkyDElCDElSDEliDElyDEmCDEmSDEmiDEmyIKTUIyPeihjOWGheaUr+S7mApBUFBfRU5WX1JPQ0tFVD3wn5qACgpURVNUPSJ0ZXN0CiAgICAgdGVzdFwidGVzdFwiCiAgICAgdGVzdCIKClRFU1RfTkQ9InRlc3RcbnRlc3QiClRFU1RfTlM9J3Rlc3RcbnRlc3QnCgpURVNUX0VRRD0iaHR0cHM6Ly92aXNpb24uZ29vZ2xlYXBpcy5jb20vdjEvaW1hZ2VzOmFubm90YXRlP2tleT0iClRFU1RfRVFTPSdodHRwczovL3Zpc2lvbi5nb29nbGVhcGlzLmNvbS92MS9pbWFnZXM6YW5ub3RhdGU/a2V5PScKCkJBU0U2NF9FTkNPREVEX01VTFRJTElORT0icVMxekN6TVZWVUpXUVNob2t2NllWWWkrcnVLU0MvYkhWN0dtRWl5VmtMYUJXSkhOVkhDSHNnVGtzRUJzeTh3Sgp1d3ljQXZSMDdaeU9KSmVkNFhUUk1LbktwMS92KzZVQVRwV3prSWpaWHl0SytwRCtYbFppbVVIVHgzdWlEY21VCmpoUVgxd1dTeEhEcXJTV3hlSUppVEQrQnVVeUlkOEZ6bVhRM1RjQnlkSjQ3NHRtT1UyRjQ5MnViazNMQWlaMTgKbWhpUkdvc2hYQU9TYlMvUDMrUlppNGJEZU5FL05vND0iCgpNVUxUSTE9Zm9vCk1VTFRJMj0ke01VTFRJMX0KTVVMVEkxPWJhcgoKUUZPTz0iYmFyIgpRQkFSPSJiYXoiClFTUEFDRUQ9IndpdGggc3BhY2VzIgpRRVFVQUxTPSJwZ3NxbDpob3N0PWxvY2FsaG9zdDtkYm5hbWU9dGVzdCIKClFOVUxMPSIiCiBRV0hJVEVTUEFDRSA9ICJubyBzcGFjZSIKClFFU0NBUEVEPSJ0ZXN0IHNvbWUgZXNjYXBlZCBjaGFyYWN0ZXJzIGxpa2UgYSBxdW90ZSAoXCIpIG9yIG1heWJlIGEgYmFja3NsYXNoIChcXCkiClFTTEFTSD0iaWlpaXZpaWlpeGlpaWl2aWlpaVxuIgpTUVNMQVNIPSdpaWlpdmlpaWl4aWlpaXZpaWlpXG4nCgojc3BlY2lhbGNoYXJzLmVudgpTUFZBUjE9IiRhNl5DN2slenMrZV4uanZqWGsiClNQVkFSMj0iP0JVdHkza29hVjMlR0EqaE1Bd0h9QiIKU1BWQVIzPSJqZGdFQjR7UWdFQ11ITCkpJkdjWHhva0Ird3FvTitqPnhrVjdLP20kciIKU1BWQVI0PSIyMjIyMjoyMiMyXnsiClNQVkFSNT0idGVzdCBzb21lIGVzY2FwZWQgY2hhcmFjdGVycyBsaWtlIGEgcXVvdGUgXCIgb3IgbWF5YmUgYSBiYWNrc2xhc2ggXFwiICMgbm90IGVzY2FwZWQKU1BWQVI2PXNlY3JldCFAIwpTUFZBUjc9J3NlY3JldCFAIycKU1BWQVI4PSJzZWNyZXQhQCMiCgojdW5pY29kZXZhcm5hbWVzLmVudgpBbGJlcnTDhWJlcmc9U2t5YmVydArQlNCw0YLQsNCX0LDQutGA0YvRgtC40Y/QoNCw0YHRh9C10YLQvdC+0LPQvtCf0LXRgNC40L7QtNCwPScyMDIyLTA0LTAxVDAwOjAwJw=='));
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