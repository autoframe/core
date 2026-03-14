<?php

namespace Autoframe\Core\Container;

use Autoframe\Core\Container\Exception\AfrContainerException;
use ArrayAccess;



interface AfrContainerInterface extends ArrayAccess
{
	/**
	 * Get instance.
	 * @return AfrLiteContainer|AfrContainerInterface
	 */
	public static function getInstance(): AfrContainerInterface;

	/**
	 * Get.
	 * @param string $id
	 * @return object|mixed
	 * @throws AfrContainerException
	 */
	public function get(string $id);

	/**
	 * Make.
	 * @param string $abstract
	 * @param array $parameters
	 * @return mixed
	 * @throws AfrContainerException
	 */
	public function make(string $abstract, array $parameters = []);

	/**
	 * Has.
	 * @param string $abstract
	 * @return bool
	 */
	public function has(string $abstract): bool;

	/**
	 * Bind.
	 * @param string $abstract
	 * @param callable|string $concrete
	 * @param bool $shared
	 * @return void
	 * @throws AfrContainerException
	 */
	public function bind(string $abstract, $concrete, bool $shared = false): void;

	/**
	 * Register instance.
	 * @param string $abstract
	 * @param object $instance
	 * @return object
	 */
	public function registerInstance(string $abstract, object $instance):object;
}
