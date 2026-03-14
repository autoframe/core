<?php

namespace Autoframe\Core\Afr;


use Autoframe\Core\CliTools\AfrCliHttpDetect;
use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\Env\AfrEnv;
use Autoframe\Core\Env\Exception\AfrEnvException;
use Autoframe\Core\Event\Exception\AfrEventException;

class AfrPhpIni
{
	/**
	 * Apply php ini env config.
	 * @return void
	 * @throws AfrContainerException
	 * @throws AfrEnvException
	 * @throws AfrEventException
	 */
	public static function applyPhpIniEnvConfig(): void
	{

		if (is_bool($ignore_user_abort = AfrEnv::getInstance()->getEnv('AFR_IGNORE_USER_ABORT', null))) {
			/**
			 * Set/Get whether a client disconnect should abort script execution
			 * If set, this function will set the ignore_user_abort ini setting to the given value.
			 * If not, this function will only return the previous setting without changing it.
			 */
			ignore_user_abort($ignore_user_abort);
		}
		if (is_int($seconds = AfrEnv::getInstance()->getEnv(
			AfrCliHttpDetect::isCli() ? 'AFR_MAX_EXECUTION_TIME_CLI' : 'AFR_MAX_EXECUTION_TIME_WEB',
			null
		))) {
			set_time_limit($seconds);
		}
		if (is_string($memory = AfrEnv::getInstance()->getEnv('AFR_MEMORY_LIMIT', null))) {
			ini_set('memory_limit', $memory);
		}
		if (is_int($seconds = AfrEnv::getInstance()->getEnv('AFR_DEFAULT_SOCKET_TIMEOUT', null))) {
			ini_set('default_socket_timeout', $seconds);
		}
		if (is_bool($castedToBool = AfrEnv::getInstance()->getEnv('AFR_ALLOW_URL_FOPEN', null))) {
			ini_set('allow_url_fopen', $castedToBool ? 'On' : 'Off');
		}


		if (is_string($sEvalStr = AfrEnv::getInstance()->getEnv('AFR_ERROR_REPORTING', null)) && strlen($sEvalStr)) {
			$iEval = E_ALL;
			eval('$iEval=' . $sEvalStr . ';');
			error_reporting($iEval);
		} else {
			if (AfrEnv::getInstance()->isDevOrDebug()) {
				error_reporting(E_ALL);
			} else {
				error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
			}
		}
		if (is_bool($castedToBool = AfrEnv::getInstance()->getEnv('AFR_DISPLAY_ERRORS', null))) {
			ini_set('display_errors', $castedToBool ? 'On' : 'Off');
		}


		if (is_int($seconds = AfrEnv::getInstance()->getEnv('AFR_MAX_INPUT_TIME', null))) {
			ini_set('max_input_time', $seconds);
		}
		if (is_string($sMb = AfrEnv::getInstance()->getEnv('AFR_POST_MAX_SIZE', null))) {
			ini_set('post_max_size', $sMb);
		}
		if (is_bool($castedToBool = AfrEnv::getInstance()->getEnv('AFR_FILE_UPLOADS', null))) {
			ini_set('file_uploads', $castedToBool ? 'On' : 'Off');
		}
		if (is_string($sMb = AfrEnv::getInstance()->getEnv('AFR_UPLOAD_MAX_FILESIZE', null))) {
			ini_set('upload_max_filesize', $sMb);
		}
		if (is_int($seconds = AfrEnv::getInstance()->getEnv('AFR_MAX_FILE_UPLOADS', null))) {
			ini_set('max_file_uploads', $seconds);
		}

		if (is_string($sRq = AfrEnv::getInstance()->getEnv('AFR_REQUEST_ORDER', null))) {
			ini_set('request_order', $sRq);
		}

		//TODO??
/*
SES_SUPPRESS_START_FATAL_ERROR=false # defaults to false and will throw an error if the output has already started

## session_start args instead of ini_get(session.*)
SES_MAX_INACTIVE_SECONDS=126144000 #gc_maxlifetime=4Y
SES_HTTP_CACHE_LIMITER=nocache #cache_limiter=nocache  (nocache|public|private|private_no_expire) use only no cache!
SES_COOKIE_LIFETIME_SECONDS=157680000 #cookie_lifetime=5Y or 0, until browser is restarted.
SES_COOKIE_HTTP_ONLY=1 #cookie_httponly=1 protected the session from javascript
SES_COOKIE_SAMESITE=strict #cookie_samesite=strict   production(strict|lax|none)   dev(strict|lax|'')
SES_COOKIE_DOMAIN=*TENANT*  # '' will not be set, (*TENANT*|.*TENANT|.$_SERVER['SERVER_NAME']) will be pulled from AfrTenant::getHost(); and formatted (domain.tld|.domain.tld) subdomains...
SES_PROFILE = gc_maxlifetime=${SES_MAX_INACTIVE_SECONDS}&sid_bits_per_character=6&sid_length=40&name=AFRSSID&cache_limiter=${SES_HTTP_CACHE_LIMITER}&cookie_lifetime=${SES_COOKIE_LIFETIME_SECONDS}&cookie_path=/&cookie_domain=${SES_COOKIE_DOMAIN}&cookie_httponly=${SES_COOKIE_HTTP_ONLY}&cookie_secure=1&cookie_samesite=${SES_COOKIE_SAMESITE}

*/

	}
}
