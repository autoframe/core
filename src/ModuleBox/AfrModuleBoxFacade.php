<?php

namespace Autoframe\Core\ModuleBox;

use Autoframe\Core\Afr\Afr;
use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\Event\Exception\AfrEventException;
use Autoframe\Core\Exception\AfrException;
use Autoframe\Core\Tenant\AfrDefaultTenantConfigsInterface;


/**
 * @mixin AfrModuleBoxInterface
 */
final class AfrModuleBoxFacade implements AfrDefaultTenantConfigsInterface
{
	/**
	 * @var string implementing AfrModuleBoxInterface
	 */
	protected static string $sBoxFQCN;// = AfrModuleBoxClass::class;

	/**
	 * @param string|null $sBoxFQCN
	 * @return AfrModuleBoxInterface|string FQCN implementing AfrModuleBoxInterface
	 * @throws AfrException
	 */
	public static function xetBoxClass(string $sBoxFQCN = null): string
	{
		if (!empty($sBoxFQCN)) {//set
			if (!isset(class_implements($sBoxFQCN)[AfrModuleBoxInterface::class])) {
				throw new AfrException("The class $sBoxFQCN  does not implement AfrModuleBoxInterface");
			}
			self::$sBoxFQCN = $sBoxFQCN;
		} elseif (empty(self::$sBoxFQCN)) {//set default
			if (Afr::app() && ($sAfrBoxFQCN = Afr::app()->env()->getEnv('AFR_BOX'))) self::xetBoxClass($sAfrBoxFQCN);
			elseif (defined($c = '\AFR_BOX')) self::xetBoxClass(constant($c));
			else self::$sBoxFQCN = AfrModuleBoxClass::class;
		}
		return self::$sBoxFQCN;//get
	}

	/**
	 * @return AfrModuleBoxClass|AfrModuleBoxInterface
	 * @throws AfrContainerException|AfrEventException|AfrException
	 */
	public static function getBox(): AfrModuleBoxInterface
	{
		$sBoxFQCN = self::xetBoxClass();
		return $sBoxFQCN::getInstance();
	}

	/**
	 * @param $method
	 * @param $args
	 * @return mixed
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 */
	public static function __callStatic($method, $args)
	{
		$sBoxFQCN = self::$sBoxFQCN;
		return $sBoxFQCN::$method(...$args);
	}

	/**
	 * @param $method
	 * @param $args
	 * @return mixed
	 * @throws AfrContainerException|AfrEventException|AfrException
	 */
	public function __call($method, $args)
	{
		return self::getBox()->$method(...$args);
	}

	public static function sampleTenantDefaultConfig(): ?string
	{
		return file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'config.sample.AfrModuleBox.php');
	}
}