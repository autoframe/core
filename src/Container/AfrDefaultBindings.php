<?php

namespace Autoframe\Core\Container;

use Autoframe\Core\Afr\Afr;
use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\Cron\Log\Channel\AfrCronLogChannelDoNotLog;
use Autoframe\Core\Cron\Log\Channel\AfrCronLogChannelInterface;
use Autoframe\Core\Cron\Log\AfrCronLoggerClass;
use Autoframe\Core\Cron\Log\AfrCronLoggerInterface;
use Autoframe\Core\Http\Request\AfrRequestClass;
use Autoframe\Core\Http\Request\AfrRequestInterface;
use Autoframe\Core\Tenant\AfrDefaultTenantConfigsInterface;
use Autoframe\Core\Tenant\AfrTenant;
use Autoframe\Core\Arr\Export\AfrArrExportArrayAsStringClass;
use Autoframe\Core\Arr\Export\AfrArrExportArrayAsStringInterface;
use Autoframe\Core\Arr\Merge\AfrArrMergeProfileClass;
use Autoframe\Core\Arr\Merge\AfrArrMergeProfileInterface;
use Autoframe\Core\Arr\Sort\AfrArrSortBySubKeyClass;
use Autoframe\Core\Arr\Sort\AfrArrSortBySubKeyInterface;
use Autoframe\Core\Arr\Sort\AfrArrXSortInterface;
use Autoframe\Core\Arr\Sort\AfrArrXSortClass;
use Autoframe\Core\Env\AfrEnv;
use Autoframe\Core\Env\AfrEnvInterface;
use Autoframe\Core\Env\Parser\AfrEnvParserClass;
use Autoframe\Core\Env\Parser\AfrEnvParserInterface;
use Autoframe\Core\Env\Validator\AfrEnvValidatorClass;
use Autoframe\Core\Env\Validator\AfrEnvValidatorInterface;
use Autoframe\Core\FileMime\AfrFileMimeClass;
use Autoframe\Core\FileMime\AfrFileMimeInterface;
use Autoframe\Core\FileSystem\DirPath\AfrDirPathClass;
use Autoframe\Core\FileSystem\DirPath\AfrDirPathInterface;
use Autoframe\Core\FileSystem\Encode\AfrBase64InlineDataClass;
use Autoframe\Core\FileSystem\Encode\AfrBase64InlineDataInterface;
use Autoframe\Core\FileSystem\OverWrite\AfrOverWriteClass;
use Autoframe\Core\FileSystem\OverWrite\AfrOverWriteInterface;
use Autoframe\Core\FileSystem\SplitMerge\AfrSplitMergeClass;
use Autoframe\Core\FileSystem\SplitMerge\AfrSplitMergeInterface;
use Autoframe\Core\FileSystem\SplitMergeCopyDir\AfrSplitMergeCopyDirClass;
use Autoframe\Core\FileSystem\SplitMergeCopyDir\AfrSplitMergeCopyDirInterface;
use Autoframe\Core\FileSystem\Traversing\AfrDirTraversingCollectionClass;
use Autoframe\Core\FileSystem\Traversing\AfrDirTraversingCollectionInterface;
use Autoframe\Core\FileSystem\Traversing\AfrDirTraversingCountChildrenDirsClass;
use Autoframe\Core\FileSystem\Traversing\AfrDirTraversingCountChildrenDirsInterface;
use Autoframe\Core\FileSystem\Traversing\AfrDirTraversingFileListClass;
use Autoframe\Core\FileSystem\Traversing\AfrDirTraversingFileListInterface;
use Autoframe\Core\FileSystem\Traversing\AfrDirTraversingGetAllChildrenDirsClass;
use Autoframe\Core\FileSystem\Traversing\AfrDirTraversingGetAllChildrenDirsInterface;
use Autoframe\Core\FileSystem\Versioning\AfrDirMaxFileMtimeClass;
use Autoframe\Core\FileSystem\Versioning\AfrDirMaxFileMtimeInterface;
use Autoframe\Core\FileSystem\Versioning\AfrFileVersioningMtimeHashClass;
use Autoframe\Core\FileSystem\Versioning\AfrFileVersioningMtimeHashInterface;
use Autoframe\Core\ProcessControl\Lock\AfrLockFileClass;
use Autoframe\Core\ProcessControl\Lock\AfrLockInterface;
use Autoframe\Core\ProcessControl\Worker\Background\AfrBackgroundWorkerClass;
use Autoframe\Core\ProcessControl\Worker\Background\AfrBackgroundWorkerInterface;
use Autoframe\Core\Session\AfrSessionInterface;
use Autoframe\Core\Session\AfrSessionPhp;


class AfrDefaultBindings implements AfrDefaultTenantConfigsInterface
{
	protected static array $aSet = [];

	/**
	 * Set autoframe default container bindings.
	 * @throws AfrContainerException
	 */
	public static function setAutoframeDefaultContainerBindings(bool $bForce = false): void
	{
		if (!empty(self::$aSet[__FUNCTION__]) && !$bForce) return;
		self::$aSet[__FUNCTION__] = true;
		static::bind(static::getDefaultContainerBindingsMap());
	}

	/**
	 * Apply default tenant config.
	 * @throws AfrContainerException
	 */
	public static function applyDefaultTenantConfig(bool $bForce = false): void
	{
		$sBindingsFile = Afr::getAfrDefaultTenantConfigsForFqcn(static::class);
		if (empty($sBindingsFile) || (!empty(self::$aSet[__FUNCTION__]) && !$bForce)) {
			return;
		}
		//	static::default();
		self::$aSet[__FUNCTION__] = true;
		if (!file_exists($sBindingsFile)) {
			throw new AfrContainerException('Container bindings file is missing: ' . $sBindingsFile);
		}
		static::bind(include $sBindingsFile);
	}

	/**
	 * Sample tenant default config.
	 */
	public static function sampleTenantDefaultConfig(): ?string
	{
		return file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'config.sample.AfrDefaultBindings.php');
	}

	/**
	 * Bind.
	 * @throws AfrContainerException
	 */
	public static function bind(array $aBound): void
	{
		$oContainer = Afr::app() ? Afr::app()->container() : AfrContainerFacade::getContainer();
		foreach ($aBound as $sAbstractFQCN => $mImplementationOrClosure) {
			if (is_array($mImplementationOrClosure)) {
				$oContainer->bind(...$mImplementationOrClosure);
			} else {
				$oContainer->bind($sAbstractFQCN, $mImplementationOrClosure);
			}
		}
	}

	/**
	 * Default bindings as array, under the next forms or combinations:
	 *
	 * 'routerAlias' => AfrRouterCliInterface::class, //alias to concrete
	 * AfrRouterCliInterface::class => CliCache::class, //interface to concrete
	 * AfrContainerInterface::class => [AfrContainerInterface::class, get_class(Afr::app()->container()), true], //self resolve container as singleton on first bind
	 * AfrArrMergeProfileInterface::class => fn() => AfrArrMergeProfileClass::getInstance(), //singleton access using closure
	 *
	 * @return string[]
	 */
	protected static function getDefaultContainerBindingsMap(): array
	{

		//README: use string keys, because  when extending / merging the bindings, the numeric keys are lost!
		return [
			//	'router' => AfrRouterCliInterface::class, //todo change :D
			//	AfrRouterCliInterface::class => CliCache::class, //todo change :D
			//	AfrRouter::class => AfrRouter::class, //todo change :D

			AfrRequestInterface::class => AfrRequestClass::class,
			AfrContainerInterface::class => [AfrContainerInterface::class, Afr::app() ? get_class(Afr::app()->container()) : AfrContainerFacade::xetContainerClass(), true], //self resolve container as singleton on first bind

			AfrSessionInterface::class => AfrSessionPhp::class,

			AfrArrMergeProfileInterface::class => fn() => AfrArrMergeProfileClass::getInstance(), //singleton access using closure
			AfrArrExportArrayAsStringInterface::class => AfrArrExportArrayAsStringClass::class,
			AfrArrSortBySubKeyInterface::class => AfrArrSortBySubKeyClass::class,
			AfrArrXSortInterface::class => AfrArrXSortClass::class,
			AfrEnvInterface::class => AfrEnv::class,
			AfrEnvParserInterface::class => AfrEnvParserClass::class,
			AfrEnvValidatorInterface::class => AfrEnvValidatorClass::class,
			AfrFileMimeInterface::class => AfrFileMimeClass::class,
			AfrDirPathInterface::class => AfrDirPathClass::class,
			AfrFileVersioningMtimeHashInterface::class => AfrFileVersioningMtimeHashClass::class,
			AfrBase64InlineDataInterface::class => AfrBase64InlineDataClass::class,
			AfrOverWriteInterface::class => AfrOverWriteClass::class,
			AfrSplitMergeInterface::class => AfrSplitMergeClass::class,
			AfrSplitMergeCopyDirInterface::class => AfrSplitMergeCopyDirClass::class,
			AfrDirTraversingCollectionInterface::class => AfrDirTraversingCollectionClass::class,
			AfrDirTraversingCountChildrenDirsInterface::class => AfrDirTraversingCountChildrenDirsClass::class,
			AfrDirTraversingFileListInterface::class => AfrDirTraversingFileListClass::class,
			AfrDirTraversingGetAllChildrenDirsInterface::class => AfrDirTraversingGetAllChildrenDirsClass::class,
			AfrDirMaxFileMtimeInterface::class => AfrDirMaxFileMtimeClass::class,
			AfrLockInterface::class => AfrLockFileClass::class,

			#DAEMON
			AfrCronLogChannelInterface::class => AfrCronLogChannelDoNotLog::class,
			AfrCronLoggerInterface::class => AfrCronLoggerClass::class,
			AfrBackgroundWorkerInterface::class => AfrBackgroundWorkerClass::class,

		];
	}


}
