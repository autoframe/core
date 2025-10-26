<?php

namespace Autoframe\Core\FsCache;

use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;
use Autoframe\Core\Exception\AfrException;
use Autoframe\Core\SocketCache\LaravelPort\Contracts\Cache\Store;

class AfrIncPhpCache extends AfrSingletonAbstractClass implements Store
{
	protected static string $sCacheDir = __DIR__ . DIRECTORY_SEPARATOR . 'cache';

	/**
	 * @param string $sCacheDir
	 * @return void
	 * @throws AfrException
	 */
	public static function setCacheDir(string $sCacheDir)
	{
		if (!is_dir(self::$sCacheDir = $sCacheDir) && !mkdir(self::$sCacheDir, 0775, true)) {
			throw new AfrException("Cache cache dir '$sCacheDir' is not a directory");
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
		$key = str_replace(array('-----', '----', '---', '--'), array('-', '-', '-', '-'), $key);
		return self::$sCacheDir . DIRECTORY_SEPARATOR . $key . '~' . $hash . '.php';

	}

	/**
	 * Retrieve an item from the cache by key.
	 *
	 * @param string|array $key
	 * @return mixed
	 */
	public function get($key)
	{
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
	public function put($key, $value, $seconds)
	{
		$f = $this->keyToFilename($key);
		return (bool)file_put_contents($f, '<?php return ' . var_export([time() + $seconds, $value], true) . ';');
	}

	/**
	 * Store multiple items in the cache for a given number of seconds.
	 *
	 * @param array $values
	 * @param int $seconds
	 * @return bool
	 */
	public function putMany(array $values, $seconds)
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
	public function forever($key, $value)
	{
		return $this->put($key, $value, 34560000);
	}

	/**
	 * Remove an item from the cache.
	 *
	 * @param string $key
	 * @return bool
	 */
	public function forget($key)
	{
		$f = $this->keyToFilename($key);
		if (is_file($f)) {
			return unlink($f);
		}
		return true;
		// TODO: Implement forget() method.
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
	 * Get the cache key prefix.
	 *
	 * @return string
	 */
	public function getPrefix()
	{
		return '';
	}
}