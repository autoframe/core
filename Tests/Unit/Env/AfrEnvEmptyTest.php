<?php
declare(strict_types=1);

namespace Unit\Env;

use Autoframe\Core\Env\AfrEnv;

use Autoframe\Core\Env\Exception\AfrEnvException;
use PHPUnit\Framework\TestCase;

class AfrEnvEmptyTest extends TestCase
{

    /**
     * @test
     */
    public function AfrEnvEmptyTest(): void
    {
        $oEnv = AfrEnv::getInstance();
        $this->assertSame('fallback',  $oEnv->getEnv('some_undefined_key','fallback'));
	    try {
		    $this->assertSame(true, $oEnv->isDev());
	    } catch (AfrEnvException $e) {
		    $oEnv->setEnv('AFR_ENV', 'DEV');
		    $this->assertSame(true, $oEnv->isDev());
	    }

	    $oEnv->setEnv('AFR_ENV', 'STAGING');
        $this->assertSame('STAGING',  $oEnv->getEnv('AFR_ENV'));
    }


}