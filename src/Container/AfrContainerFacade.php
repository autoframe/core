<?php

namespace Autoframe\Core\Container;

use Autoframe\Core\Exception\AfrException;

/**
 * @method static mixed get(string $id)
 * @method static mixed make(string $abstract, array $parameters = [])
 * @method static bool has(string $abstract)
 * @method static void bind(string $abstract, $concrete, bool $shared = false)
 * @method static mixed registerInstance(string $abstract, object $instance)
 * @see AfrContainerInterface
 */
final class AfrContainerFacade
{
	/**
	 * @var AfrContainerInterface|string implementing AfrContainerInterface
	 */
	protected static string $sContainerFQCN = AfrLiteContainer::class;

	/**
	 * @param string|null $sContainerFQCN
	 * @return string FQCN implementing AfrContainerInterface
	 * @throws AfrException
	 */
	public static function xetContainerClass(string $sContainerFQCN = null): string
	{
		if (!empty($sContainerFQCN)) {
			if (
				$sContainerFQCN !== AfrLiteContainer::class &&
				!isset(class_implements($sContainerFQCN)[AfrContainerInterface::class])
			) {
				throw new AfrException("The class $sContainerFQCN  does not implement AfrContainerInterface");
			}
			self::$sContainerFQCN = $sContainerFQCN;
		}
		return self::$sContainerFQCN;
	}

	/**
	 * @return AfrLiteContainer|AfrContainerInterface
	 */
	public static function getContainer(): AfrContainerInterface
	{
		return self::$sContainerFQCN::getInstance();
	}

	/**
	 * @param $method
	 * @param $args
	 * @return mixed
	 */
	public static function __callStatic($method, $args)
	{
		return self::getContainer()->$method(...$args);
	}

}