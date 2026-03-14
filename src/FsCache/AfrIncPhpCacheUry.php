<?php


class AfrIncPhpCacheUry
{
	protected static string $sCacheDir = __DIR__ . DIRECTORY_SEPARATOR . 'cache';

	/**
	 * Set cache dir.
	 * @param string $sCacheDir
	 * @return void
	 * @throws \Exception
	 */
	public static function setCacheDir(string $sCacheDir): void
	{
		if (!is_dir(self::$sCacheDir = $sCacheDir) && !mkdir(self::$sCacheDir, 0775, true)) {
			throw new \Exception("Cache cache dir '$sCacheDir' is not a directory");
		}
	}

	protected function keyToFilename($key): string
	{
		$hash = md5($key);
		if (strlen($key) > 200) {
			$key = substr($key, 0, 100);
		}
		$key = str_replace(array("\n", "\r"), array(' ', ' '), $key);
		$key = preg_replace('/[^A-Za-z0-9 \'`_.-]/', '-', $key);
		$key = str_replace(array('-----', '----', '---', '--'), array('-', '-', '-', '-'), $key);;
		return self::$sCacheDir . DIRECTORY_SEPARATOR . $this->getPrefix().$key . '~' . $hash . '.php';

	}

	/**
	 * Retrieve an item from the cache by key.
	 *
	 * @param string|array $key
	 * @return mixed
	 */
	public function get($key)
	{
		//    return null;
		$f = $this->keyToFilename($key);

		if (is_file($f)) {
			[$iExpire, $mData] = include($f);
			if ($iExpire >= time()) {
				return $mData;
			}
			unlink($f);
		}
		return null;
	}



	//  	$key = implode('_',	array_merge([basename(__FILE__, '.php'), __FUNCTION__,],	func_get_args()	));
	/**
	 * Set if null.
	 */
	public function setIfNull(string $key, int $expire, \Closure $oSetter)
	{
		if (($value = $this->get($key)) !== null) {
			return $value;
		}
		$this->put($key, $value = $oSetter(), $expire);
		return $value;
	}

	/**
	 * Retrieve multiple items from the cache by key.
	 *
	 * Items not found in the cache will have a null value.
	 *
	 * @param array $keys
	 * @return array
	 */
	public function many(array $keys)
	{
		$aReturn = [];
		foreach ($keys as $key) {
			$mVal = $this->get($key);
			if ($mVal !== null) {
				$aReturn[$key] = $mVal;
			}
		}
		return $aReturn;
	}

	/**
	 * Store an item in the cache for a given number of seconds.
	 *
	 * @param string $key
	 * @param mixed $value
	 * @param int $seconds
	 * @return bool
	 */
	public function put($key, $value, $seconds): bool
	{
		$f = $this->keyToFilename($key);
		//return (bool)file_put_contents($f, '<?php return ' . var_export([time() + $seconds, $value], true) . ';');
		$f2Write = $f.'_write_'.md5((string)(mt_rand(4324,5356464)+(rand(535,3546)/10000)));
		$sData = '<?php return ' . var_export([time() + $seconds, $value], true) . ';';
		if(file_put_contents($f2Write, $sData) && file_get_contents($f2Write) === $sData) {
			return rename($f2Write, $f);
		}
		if(file_exists($f2Write)) {
			@unlink($f2Write);
		}
		return false;
	}

	/**
	 * Store multiple items in the cache for a given number of seconds.
	 *
	 * @param array $values
	 * @param int $seconds
	 * @return bool
	 */
	public function putMany(array $values, $seconds): bool
	{
		foreach ($values as $key => $value) {
			$this->put($key, $value, $seconds);
		}
		return true;
	}

	/**
	 * Increment the value of an item in the cache.
	 *
	 * @param string $key
	 * @param mixed $value
	 * @return int|bool
	 */
	public function increment($key, $value = 1)
	{
		$f = $this->keyToFilename($key);
		if (is_file($f)) {
			[$iExpire, $mData] = include($f);
			if ($iExpire >= time()) {
				$iExpire += $value;
			}
			return $this->put($key, $mData, $iExpire) ? $iExpire : false;

		}
		return false;
	}

	/**
	 * Decrement the value of an item in the cache.
	 *
	 * @param string $key
	 * @param mixed $value
	 * @return int|bool
	 */
	public function decrement($key, $value = 1)
	{
		return $this->increment($key, -$value);
	}

	/**
	 * Store an item in the cache indefinitely.
	 *
	 * @param string $key
	 * @param mixed $value
	 * @return bool
	 */
	public function forever($key, $value): bool
	{
		return $this->put($key, $value, 34560000);
	}

	/**
	 * Remove an item from the cache.
	 *
	 * @param string $key
	 * @return bool
	 */
	public function forget($key): bool
	{
		$f = $this->keyToFilename($key);
		if (is_file($f)) {
			return unlink($f);
		}
		return true;
	}

	/**
	 * Remove all items from the cache.
	 *
	 * @return bool
	 */
	public function flush()
	{
		$dh = opendir(self::$sCacheDir);
		while (($file = readdir($dh)) !== false) {
			if ($file !== '.' && $file !== '..' && substr($file, -4, 4) === '.php') {
				unlink(self::$sCacheDir . DIRECTORY_SEPARATOR . $file);
			}
		}
		closedir($dh);
		return true;
	}


	/**
	 * The actual singleton's instance almost always resides inside a static
	 * field. In this case, the static field is an array, where each subclass of
	 * the Singleton stores its own instance.
	 */
	protected static AfrIncPhpCacheUry $instance;

	/**
	 * Singleton's constructor should not be public. However, it can't be
	 * private either if we want to allow subclassing.
	 */
	final protected function __construct() {}

	/**
	 * Cloning and un-serialization are not permitted for singletons.
	 * @throws \Exception
	 */
	final public function __clone()
	{
		throw new \Exception('Cannot clone a singleton: ' . static::class);
	}

	/**
	 * @throws \Exception
	 */
	final public function __wakeup()
	{
		throw new \Exception('Cannot unserialize singleton: ' . static::class);
	}


	/**
	 * The method you use to get the Singleton's instance.
	 * @return self
	 */
	final public static function getInstance(): self
	{
		if (empty(self::$instance)) {
			return self::$instance = new static();
		}
		return self::$instance;
	}

	/**
	 * Get the cache key prefix.
	 *
	 * @return string
	 */
	public function getPrefix()
	{
		return strtolower(!empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'cli') . '_';
	}

}
