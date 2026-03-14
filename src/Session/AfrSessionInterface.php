<?php


namespace Autoframe\Core\Session;


use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\Env\Exception\AfrEnvException;
use Autoframe\Core\Exception\AfrException;

interface AfrSessionInterface
{

	/**
	 * Get.
	 * @param string $sKey
	 * @param string $sNameSpace
	 * @return mixed|null
	 */
	public function get(string $sKey, string $sNameSpace = 'default');

	/**
	 * Set.
	 * @param string $sKey
	 * @param mixed $value
	 * @param string $sNameSpace
	 * @return mixed
	 */
	public function set(string $sKey, $value, string $sNameSpace = 'default');

	function session_started(): bool;

	function session_abort(): bool;

	/**
	 * @param int|null $value
	 * @return false|int
	 * returns the current setting of session.cache_expire.
	 * session_cache_expire() returns the current setting of session.cache_expire.
	 *
	 * The cache expire is reset to the default value of 180 minutes stored in session.cache_expire at request startup time.
	 * Thus, you need to call session_cache_expire() for every request (and before session_start() is called).
	 */
	function session_cache_expire(?int $value = null);

	/**
	 * @param string|null $value null|public,private_no_expire,private,nocache,''
	 * @return false|string
	 * Get and/or set the current cache limiter
	 * Setting the cache limiter to '' will turn off automatic sending of cache headers entirely.
	 * If value is specified and not null, the name of the current cache limiter is changed to the new value.
	 * https://www.php.net/manual/en/function.session-cache-limiter.php
	 */
	function session_cache_limiter(?string $value = null);

	/**
	 * @return bool
	 * PHP 7.2.0    The return type of this function is bool now. Formerly, it has been void.
	 * Alias of session_write_close()
	 */
	function session_commit(): bool;


	/**
	 * @param string $prefix
	 * @return false|string
	 * session_create_id() is used to create new session id for the current session. It returns collision free session id.
	 * If prefix is specified, new session id is prefixed by prefix. Not all characters are allowed within the session id.
	 * Characters in the range a-z A-Z 0-9 , (comma) and - (minus) are allowed.
	 */
	function session_create_id(string $prefix = '');

	/**
	 * @param string $data
	 * @return bool
	 * session_decode() decodes the serialized session data provided in $data, and populates the $_SESSION superglobal with the result.
	 */
	function session_decode(string $data): bool;


	function session_destroy(): bool;

	/**
	 * @return false|string
	 */
	function session_encode();

	/**
	 * @return false|int
	 */
	function session_gc();

	function session_get_cookie_params(): array;

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
	function session_id(?string $id = null);

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
	function session_module_name(?string $module = null);

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
	function session_name(?string $name = null);

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
	function session_regenerate_id(bool $delete_old_session = false): bool;

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
	function session_register_shutdown(): void;

	/**
	 * @return bool
	 * session_reset() reinitializes a session with original values stored in session storage.
	 * This function requires an active session and discards changes in $_SESSION.
	 */
	function session_reset(): bool;

	/**
	 * @param string|null $path
	 * @return false|string
	 * session_save_path — Get and/or set the current session save path
	 * session_save_path needs to be called before session_start() for that purpose.
	 */
	function session_save_path(?string $path = null);

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
	function session_set_cookie_params($lifetime_or_options,
	                                   ?string $path = null, //TODO CHECK predefined parameter types
	                                   ?string $domain = null,
	                                   ?bool $secure = null,
	                                   ?bool $httponly = null,
	                                   ?string $samesite = null

	): bool;

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
	function session_set_save_handler($sessionhandler_or_open,
	                                  $register_shutdown_or_close = true,
	                                  $read = null,
	                                  $write = null,
	                                  $destroy = null,
	                                  $gc = null,
	                                  $create_sid = null,
	                                  $validate_sid = null,
	                                  $update_timestamp = null
	): bool;

	/**
	 * @param array $aSessionOptions
	 * @return bool
	 * @throws AfrException To use a named session, call session_name() before calling session_start().
	 * When session.use_trans_sid is enabled, the session_start() function will register an internal output handler for URL rewriting.
	 * <a href="https://www.php.net/manual/en/session.configuration.php">https://www.php.net/manual/en/session.configuration.php</a>
	 * <a href="https://stackoverflow.com/questions/12071358/how-to-make-php-upload-progress-session-work">https://stackoverflow.com/questions/12071358/how-to-make-php-upload-progress-session-work</a>
	 * @throws AfrEnvException|AfrContainerException
	 */
	function session_start(array $aSessionOptions = []);

	function sessionConfigAfr(array $options = []): array;

	function session_status();

	function session_unset();

	function session_write_close();

	static public function setHandler($mHandlerClosureOrFQCNResolvableByContainer): void;

}
