<?php
declare(strict_types=1);

namespace Autoframe\Core\Session;

use Autoframe\Core\Afr\Afr;
use Autoframe\Core\CliTools\AfrCliHttpDetect;
use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\Env\Exception\AfrEnvException;
use Autoframe\Core\Exception\AfrException;
use Autoframe\Core\Object\AfrObjectSingletonTrait;

use function headers_sent;
use function is_callable;
use function session_abort;
use function session_cache_expire;
use function session_cache_limiter;
use function session_create_id;
use function session_decode;
use function session_destroy;
use function session_encode;
use function session_gc;
use function session_get_cookie_params;
use function session_id;
use function session_module_name;
use function session_name;
use function session_regenerate_id;
use function session_register_shutdown;
use function session_reset;
use function session_save_path;
use function session_set_cookie_params;
use function session_set_save_handler;
use function session_start;
use function session_status;
use function session_unset;
use function session_write_close;
use function strtolower;

//TODO: align with AfrPhpIni
class AfrSessionPhp implements AfrSessionInterface
{
	use AfrObjectSingletonTrait;
	const SES_PROFILE = 'gc_maxlifetime=126144000&sid_bits_per_character=6&sid_length=54&name=AFRSSID&cache_limiter=nocache&cookie_lifetime=157680000&cookie_path=/&cookie_domain=*TENANT*&cookie_secure=1&cookie_httponly=1&cookie_samesite=strict';

	protected array $aConfig;

	/**
	 * @var \Closure|string|null  FQCNString
	 */
	protected static $mHandlerClosureOrFQCNResolvableByContainer = null;

	/**
	 * @param string $sKey
	 * @param string $sNameSpace
	 * @return mixed|null
	 * @throws AfrContainerException
	 * @throws AfrEnvException
	 * @throws AfrException
	 */
	public function get(string $sKey, string $sNameSpace = 'default')
	{
		if (empty($_SESSION)) {
			$this->session_start();
		}
		//AfrTenant::getTenantAlias();
		return $_SESSION[Afr::getTenantAlias() ?? 'Afr'][$sNameSpace][$sKey] ?? null;
	}

	/**
	 * @param string $sKey
	 * @param mixed $value
	 * @param string $sNameSpace
	 * @return mixed
	 * @throws AfrEnvException
	 * @throws AfrException
	 * @throws AfrContainerException
	 */
	public function set(string $sKey, $value, string $sNameSpace = 'default')
	{
		if (empty($_SESSION)) {
			$this->session_start();
		}
		//AfrTenant::getTenantAlias();
		return $_SESSION[Afr::getTenantAlias() ?? 'Afr'][$sNameSpace][$sKey] = $value;
	}

	public function sessionConfigAfr(array $options = []): array
	{
		if (empty($this->aConfig)) {
			$mEnvConfig = Afr::app()->env()->getEnv('SES_PROFILE');
			if ($mEnvConfig === true || $mEnvConfig === 1 || $mEnvConfig === '1') {
				$mEnvConfig = static::SES_PROFILE;
			}

			if ($mEnvConfig && $aTmp = parse_url('?' . $mEnvConfig)) {
				parse_str($aTmp['query'] ?? 'name=AFRSSID', $aProfile);
			}
			$aProfile = array_merge($this->aConfig ?? [], $aProfile ?? []);
		} else {
			$aProfile = $this->aConfig;
		}

		foreach ($aProfile as $name => $value) {
			$value = $options[$name] ?? $value;
			if ($name === 'cache_limiter') {
				if (empty($value)) {
					ini_set('session.cache_limiter', '');
					unset($aProfile[$name]);
				}
			} elseif (in_array($name, ['cookie_secure', 'cookie_httponly', 'read_and_close'])) {
				$aProfile[$name] = (bool)$value;
			} elseif (in_array($name, ['sid_length', 'gc_divisor', 'gc_probability', 'gc_maxlifetime', 'cache_expire', 'cookie_lifetime'])) {
				$aProfile[$name] = (int)$value;
			} elseif ($name === 'cookie_domain') {
				if (empty($value)) {
					unset($aProfile[$name]);
					continue;
				}

				$bSubdomains = substr($value, 0, 1) === '.';
				$value = ltrim($value, '.');
				if (stripos($value, '*TENANT*') !== false) {
					$value = str_ireplace('*TENANT*', Afr::getHost(), $value);
				} elseif (empty($value)) {
					$value = $_SERVER['SERVER_NAME'] ?? '';
				}
				if (empty($value)) {
					unset($aProfile[$name]);
					continue;
				}
				if (filter_var($value, FILTER_VALIDATE_IP)) {
					$aProfile[$name] = $value;
					continue;
				}

				// extract port from hostname, because host should not contain port in cookie
				$value = explode(':', $value)[0];
				$aHostname = explode('.', $value);
				//extract domain.tld
				$aHostname = count($aHostname) > 1 ? array_slice($aHostname, -2, 2) : $aHostname;
				//add subdomain wildcard
				$aProfile[$name] = ($bSubdomains ? '.' : '') . implode('.', $aHostname);
				//TODO test (*TENANT*|.*TENANT|.$_SERVER['SERVER_NAME']|null)
			}
		}

		foreach ($options as $name => $value) {
			if ($value === null) {
				unset($aProfile[$name]);
			}
		}

		if (!AfrCliHttpDetect::isHttpsNativeOrForwarded()) {
			$aProfile['cookie_secure'] = false;
		}
		if (Afr::app()->env()->isDev()) {
			$aProfile['cookie_samesite'] = ''; //  dev(strict|lax|'') | prod(strict|lax|none)
		}

		if ($mHandler =
			self::$mHandlerClosureOrFQCNResolvableByContainer ??
			Afr::app()->env()->getEnv('SES_SET_SAVE_HANDLER')
		) {
			if ($mHandler instanceof \Closure) {
				//(SessionHandlerInterface + SessionUpdateTimestampHandlerInterface, $register_shutdown = true)
				$this->session_set_save_handler(...$mHandler->bindTo($this, $this)($aProfile));
			} elseif (is_string($mHandler) && strlen($mHandler) > 3) {
				$aHandler = explode(',', $mHandler);
				$this->session_set_save_handler(
					Afr::app()->container()->get(trim($aHandler[0])),
					(bool)($aHandler[1] ?? true)
				);
			}
			//set handler only once, and cleanup after....
			Afr::app()->env()->setEnv('SES_SET_SAVE_HANDLER', self::$mHandlerClosureOrFQCNResolvableByContainer = null);
		}
		return $this->aConfig = $aProfile;
	}

	public function session_started(): bool
	{
		return $this->session_status() === PHP_SESSION_ACTIVE;
	}


	/**
	 * @return bool
	 * PHP 7.2.0 The return type of this function is bool now. Formerly, it has been void.
	 * session_abort() finishes session without saving data. Thus the original values in session data are kept.
	 */
	public function session_abort(): bool
	{
		return session_abort();
	}

	/**
	 * @param int|null $value
	 * @return false|int
	 * returns the current setting of session.cache_expire.
	 * session_cache_expire() returns the current setting of session.cache_expire.
	 *
	 * The cache expire is reset to the default value of 180 minutes stored in session.cache_expire at request startup time.
	 * Thus, you need to call session_cache_expire() for every request (and before session_start() is called).
	 */
	public function session_cache_expire(?int $value = null)
	{
		return session_cache_expire($value);
	}

	/**
	 * @param string|null $value null|public,private_no_expire,private,nocache,''
	 * @return false|string
	 * Get and/or set the current cache limiter
	 * Setting the cache limiter to '' will turn off automatic sending of cache headers entirely.
	 * If value is specified and not null, the name of the current cache limiter is changed to the new value.
	 * https://www.php.net/manual/en/function.session-cache-limiter.php
	 */
	public function session_cache_limiter(?string $value = null)
	{
		return session_cache_limiter($value);
	}

	/**
	 * @return bool
	 * PHP 7.2.0    The return type of this function is bool now. Formerly, it has been void.
	 * Alias of session_write_close()
	 */
	public function session_commit(): bool
	{
		return $this->session_write_close();
	}

	/**
	 * @param string $prefix
	 * @return false|string
	 * session_create_id() is used to create new session id for the current session. It returns collision free session id.
	 * If prefix is specified, new session id is prefixed by prefix. Not all characters are allowed within the session id.
	 * Characters in the range a-z A-Z 0-9 , (comma) and - (minus) are allowed.
	 */
	public function session_create_id(string $prefix = '')
	{
		return session_create_id($prefix);
	}

	/**
	 * @param string $data
	 * @return bool
	 * session_decode() decodes the serialized session data provided in $data, and populates the $_SESSION superglobal with the result.
	 */
	public function session_decode(string $data): bool
	{
		return session_decode($data);
	}

	/**
	 * @return bool
	 * session_destroy() destroys all of the data associated with the current session.
	 * It does not unset any of the global variables associated with the session, or unset the session cookie.
	 * To use the session variables again, session_start() has to be called.
	 *
	 * Note: You do not have to call session_destroy() from usual code.
	 * Cleanup $_SESSION array rather than destroying session data.
	 *
	 * In order to kill the session altogether, the session ID must also be unset.
	 * If a cookie is used to propagate the session ID (default behavior), then the session cookie must be deleted.
	 * setcookie() may be used for that.
	 *
	 * When session.use_strict_mode is enabled. You do not have to remove obsolete session ID cookie because
	 * session module will not accept session ID cookie when there is no data associated to the session ID
	 * and set new session ID cookie. Enabling session.use_strict_mode is recommended for all sites.
	 */
	public function session_destroy(): bool
	{
		return session_destroy();
	}

	/**
	 * @return false|string
	 * session_encode() returns a serialized string of the contents of the current session data stored in the $_SESSION superglobal.
	 * By default, the serialization method used is internal to PHP, and is not the same as serialize().
	 * The serialization method can be set using session.serialize_handler.
	 * Warning: Must call session_start() before using session_encode().
	 */
	public function session_encode()
	{
		return session_encode();
	}

	/**
	 * @return false|int
	 * session_gc() returns number of deleted session data for success, false for failure.
	 * session_gc() is used to perform session data GC(garbage collection). PHP does probability based session GC by default.
	 * Probability based GC works somewhat but it has few problems.
	 * 1) Low traffic sites' session data may not be deleted within the preferred duration.
	 * 2) High traffic sites' GC may be too frequent GC.
	 * 3) GC is performed on the user's request and the user will experience a GC delay.
	 * Therefore, it is recommended to execute GC periodically for production systems using, e.g., "cron" for UNIX-like systems.
	 * Make sure to disable probability based GC by setting session.gc_probability to 0.
	 */

	public function session_gc()
	{
		return session_gc();
	}

	/**
	 * @return array
	 * Returns an array with the current session cookie information, the array contains the following items:
	 * lifetime, path, domain, secure, httponly, samesite
	 */
	public function session_get_cookie_params(): array
	{
		return session_get_cookie_params();
	}

	/**
	 * @param string|null $id
	 * @return false|string
	 * session_id() is used to get or set the session id for the current session.
	 * The constant SID can also be used to retrieve the current name and session id as a string suitable for adding to URLs.
	 *
	 * If id is specified and not null, it will replace the current session id.
	 * session_id() needs to be called before session_start() for that purpose.
	 * Depending on the session handler, not all characters are allowed within the session id.
	 * For example, the file session handler only allows characters in the range a-z A-Z 0-9 , (comma) and - (minus)!
	 *
	 * Note: When using session cookies, specifying an id for session_id() will always send a new cookie when
	 * session_start() is called, regardless if the current session id is identical to the one being set.
	 */
	public function session_id(?string $id = null)
	{
		return session_id($id);
	}

	/**
	 * @param string|null $module
	 * @return false|string
	 * PHP 8.0.0    module is nullable now.
	 * PHP 7.2.0    It is now explicitly forbidden to set the module name to "user". Formerly, this has been silently ignored.
	 * session_module_name() gets the name of the current session module, which is also known as session.save_handler.
	 * If module is specified and not null, that module will be used instead. Passing "user" to this parameter is forbidden.
	 * Instead session_set_save_handler() has to be called to set a user defined session handler.
	 * NOTE: You must use this function before starting session with session_start(); to make it work properly
	 *
	 * session_module_name('memcache'); // or pgsql or redis etc
	 * session_save_path('localhost:11211'); // memcache uses port 11211
	 * session_save_path('localhost:11211:41,otherhost:11211:60') // First part is hostname or path to socket, next is port and the last is the weight for that server
	 */
	public function session_module_name(?string $module = null)
	{
		return session_module_name($module);
	}

	/**
	 * @param string|null $name
	 * @return false|string
	 * session_name() returns the name of the current session. If name is given, session_name() will update the session name and return the old session name.
	 *
	 * If a new session name is supplied, session_name() modifies the HTTP cookie (and output content when session.transid is enabled).
	 * Once the HTTP cookie is sent, session_name() raises error. session_name() must be called before session_start() for the session to work properly.
	 * The session name is reset to the default value stored in session.name at request startup time.
	 * Thus, you need to call session_name() for every request (and before session_start() is called).
	 */
	public function session_name(?string $name = null)
	{
		return session_name($name);
	}

	/**
	 * @param bool $delete_old_session
	 * @return bool
	 * session_regenerate_id() will replace the current session id with a new one, and keep the current session information.
	 * When session.use_trans_sid is enabled, output must be started after session_regenerate_id() call. Otherwise, old session ID is used.
	 *
	 * Warning: Currently, session_regenerate_id does not handle an unstable network well, e.g. Mobile and WiFi network.
	 * Therefore, you may experience a lost session by calling session_regenerate_id.
	 *
	 * You should not destroy old session data immediately, but should use destroy time-stamp and control access to old session ID.
	 * Otherwise, concurrent access to page may result in inconsistent state, or you may have lost session,
	 * or it may cause client(browser) side race condition and may create many session ID needlessly.
	 * Immediate session data deletion disables session hijack attack detection and prevention also.
	 */
	public function session_regenerate_id(bool $delete_old_session = false): bool
	{
		return session_regenerate_id($delete_old_session);
	}

	/**
	 *  Registers session_write_close() as a shutdown function.
	 *
	 * The session shutdown function is called when the session is destroyed, giving you the opportunity to perform a
	 * final action with the session before it isn't available anymore (e.g. you could extract parameters from the $_SESSION variable).
	 * If you call die() or exit in a php script the session will not be properly closed (especially if you have a custom session handler).
	 * This function is nothing more than a shortcut.
	 *
	 * This function is registered itself as a shutdown function by session_set_save_handler($obj). The reason we now register another
	 * shutdown function is in case the user registered their own shutdown function after calling session_set_save_handler(), which expects
	 * the session still to be available.
	 */
	public function session_register_shutdown(): void
	{
		session_register_shutdown();
	}

	/**
	 * @return bool
	 * session_reset() reinitializes a session with original values stored in session storage.
	 * This function requires an active session and discards changes in $_SESSION.
	 */
	public function session_reset(): bool
	{
		return session_reset();
	}

	/**
	 * @param string|null $path
	 * @return false|string
	 * session_save_path — Get and/or set the current session save path
	 * session_save_path needs to be called before session_start() for that purpose.
	 */
	public function session_save_path(?string $path = null)
	{
		return session_save_path($path);
	}

	/**
	 * TODO CHECK predefined parameter types
	 * @param int|array $lifetime_or_options
	 * @param string|null $path
	 * @param string|null $domain
	 * @param bool|null $secure
	 * @param bool|null $httponly
	 * @param string|null $samesite
	 * @return bool
	 * @throws AfrException
	 * Alternative signature available as of PHP 7.3.0: session_set_cookie_params(array $lifetime_or_options): bool
	 */
	public function session_set_cookie_params($lifetime_or_options,
	                                          ?string $path = null, //TODO CHECK predefined parameter types
	                                          ?string $domain = null,
	                                          ?bool $secure = null,
	                                          ?bool $httponly = null,
	                                          ?string $samesite = null

	): bool
	{
		if ($samesite && !in_array(strtolower($samesite), ['lax', 'strict'])) {
			throw new AfrException('Samesite policy must be Lax or Strict, (' . $samesite . ') given!');
		}
		if (PHP_VERSION_ID < 70300) {
			if (!$path) {
				$path = '/';
			}
			return session_set_cookie_params(
				(int)$lifetime_or_options,
				$path . ($samesite ? '; samesite=' . $samesite : ''),
				$domain,
				$secure,
				$httponly
			);
		} else {
			/*$eg =[
				'lifetime' => time() + 3600,
				'path' => '/',
				'domain' => $_SERVER['HTTP_HOST'],
				'secure' => true,     // if you only want to receive the cookie over HTTPS
				'httponly' => true, // prevent JavaScript access to session cookie
				'samesite' => 'Strict'
			];*/
			return session_set_cookie_params($lifetime_or_options);
		}
	}

	/**
	 * @param $sessionhandler_or_open
	 * @param bool $register_shutdown_or_close
	 * @param null $read
	 * @param null $write
	 * @param null $destroy
	 * @param null $gc
	 * @param null $create_sid
	 * @param null $validate_sid
	 * @param null $update_timestamp
	 * @return bool
	 * @throws AfrException
	 * https://www.php.net/manual/en/function.session-set-save-handler.php
	 */
	public function session_set_save_handler($sessionhandler_or_open,
	                                         $register_shutdown_or_close = true,
	                                         $read = null,
	                                         $write = null,
	                                         $destroy = null,
	                                         $gc = null,
	                                         $create_sid = null,
	                                         $validate_sid = null,
	                                         $update_timestamp = null
	): bool
	{
		if (
			is_callable($sessionhandler_or_open) &&
			is_callable($register_shutdown_or_close) &&
			is_callable($read) &&
			is_callable($write) &&
			is_callable($destroy) &&
			is_callable($gc) &&
			is_callable($create_sid) &&
			is_callable($validate_sid) &&
			is_callable($update_timestamp)
		) {
			return session_set_save_handler(
				$sessionhandler_or_open,
				$register_shutdown_or_close,
				$read,
				$write,
				$destroy,
				$gc,
				$create_sid,
				$validate_sid,
				$update_timestamp
			);
		} elseif (is_object($sessionhandler_or_open) && is_bool($register_shutdown_or_close)) {
			return session_set_save_handler($sessionhandler_or_open, $register_shutdown_or_close);
		} else {
			throw new AfrException('Invalid arguments supplied to session_set_save_handler()');
		}
	}


	static public function setHandler($mHandlerClosureOrFQCNResolvableByContainer): void
	{
		self::$mHandlerClosureOrFQCNResolvableByContainer = $mHandlerClosureOrFQCNResolvableByContainer;
	}


	/**
	 * @param array $aSessionOptions
	 * @return bool
	 * @throws AfrException To use a named session, call session_name() before calling session_start().
	 * When session.use_trans_sid is enabled, the session_start() function will register an internal output handler for URL rewriting.
	 * <a href="https://www.php.net/manual/en/session.configuration.php">https://www.php.net/manual/en/session.configuration.php</a>
	 * <a href="https://stackoverflow.com/questions/12071358/how-to-make-php-upload-progress-session-work">https://stackoverflow.com/questions/12071358/how-to-make-php-upload-progress-session-work</a>
	 * @throws AfrEnvException|AfrContainerException
	 */
	public function session_start(array $aSessionOptions = []): bool
	{
		if (
			Afr::isCli() ||
			in_array($this->session_status(), [PHP_SESSION_DISABLED, PHP_SESSION_ACTIVE])
		) {
			return false;
		}

		if (!empty(ini_get('session.auto_start')) && empty($this->aConfig)) {
			session_write_close();//close once the forced ini start session
		}

		if (
			headers_sent($filename, $lineNum) === true &&
			!Afr::app()->env()->getEnv('SUPRESS_SESSION_START_FATAL_ERROR', false)
		) {
			throw new AfrException(
				'Warning: ' . __CLASS__ . '->' . __FUNCTION__ . ': ' .
				'Cannot send session cache limiter - ' .
				"Headers already sent in $filename on line $lineNum\n"
			);
		}

		if (empty($this->aConfig) || !empty($aSessionOptions)) {
			$this->sessionConfigAfr($aSessionOptions);
		}

		return session_start($this->aConfig);
	}


	/**
	 * @return int
	 * PHP_SESSION_DISABLED if sessions are disabled.
	 * PHP_SESSION_NONE if sessions are enabled, but none exists.
	 * PHP_SESSION_ACTIVE if sessions are enabled, and one exists.
	 */
	public function session_status(): int
	{
		return session_status();
	}

	/**
	 * @return bool
	 * PHP 7.2.0    The return type of this function is bool now. Formerly, it has been void.
	 */
	public function session_unset(): bool
	{
		return session_unset();
	}

	/**
	 * @return bool
	 * PHP 7.2.0 The return type of this function is bool now. Formerly, it has been void.
	 * End the current session and store session data.
	 * Session data is usually stored after your script terminated without the need to call session_write_close(),
	 * but as session data is locked to prevent concurrent writes only one script may operate on a session at any time.
	 */
	public function session_write_close(): bool
	{
		return session_write_close();
	}


}