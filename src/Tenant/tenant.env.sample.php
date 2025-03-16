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
	'https://www.b2b-app.ro',
	'https://b2b-app.ro',
	'http://www.b2b-app.test',
	'http://b2b-app.test',
	'http://localhost',
	'http://localhost:808',
	'http://localhost:8080',
	'http://127.0.0.1',
])
	->setEnv($sEnv = 'dev')
	->setDebug($bDebug = true)
	->setRoot('/')
	->setTempDir()
	->setHtmlDir()
	->setAssetsDir()
	->autoSetupAndPushTenantConfig();

(new AfrTenant('online-b2b-app'))->setProtocolDomainName([
	'https://online.b2b-app.ro',
	'http://online.b2b-app.test',
	'http://online.test',
])
	->setEnv($sEnv)
	->setDebug($bDebug)
	->autoSetupAndPushTenantConfig();

AfrTenant::pushDefaultTenantConfigs([

]);