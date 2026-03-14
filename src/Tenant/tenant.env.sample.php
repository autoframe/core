<?php

use Autoframe\Core\Tenant\AfrTenant;

/**
 * HTTP:
 * $_SERVER['HTTP_HOST'] match inside AfrTenant::setHostList
 *
 * CLI:
 * set using argv params; eg: php index.php
 *      -T tenantName
 *      -T="tenantName"
 *      --tenant 'domain.com'
 *      --tenant='sub.domain.tld'
 *
 *      $_ENV['AFR_TENANT_CLI'] ?? getenv('AFR_TENANT_CLI') ?: null;
 *      !! set using ENV, but this is not recommended for multi tenant in CLI calling
 *
 * FALLBACK:
 * If no tenant match found, then we render the first tenant key
 *
 */

//AfrTenant::getBaseDirPath() || AfrTenant::setBaseDirPath(__DIR__);

(new AfrTenant('www'))->setProtocolDomainName([
	'https://www.app.com',
	'https://app.com',
	'http://www.app.test',
	'http://app.test',
	'http://localhost',
	'http://localhost:808',
	'http://localhost:8080',
	'http://127.0.0.1',
])
	->setEnv($sEnv = 'dev')
	->setDebug($bDebug = true)
	->setRoot('/') //route mounting
	->setTempDir()
	->setHtmlDir()
	->setAssetsDir()
	->setLogsDir()
	->autoSetupAndPushTenantConfig();

(new AfrTenant('tenant2-app'))->setProtocolDomainName([
	'https://online.app.com',
	'http://online.app.test',
	'http://online.test',
])
	->setEnv($sEnv)
	->setDebug($bDebug)
	->autoSetupAndPushTenantConfig();

AfrTenant::pushDefaultTenantConfigs([

]);