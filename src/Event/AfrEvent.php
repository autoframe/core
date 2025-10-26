<?php

namespace Autoframe\Core\Event;

//TODO : integrare ddtrace https://docs.datadoghq.com/tracing/trace_collection/custom_instrumentation/php/dd-api?tab=currentspan

use Autoframe\Core\Afr\Afr;
use Autoframe\Core\Event\Exception\AfrEventException;
use Autoframe\Core\String\Obj\AfrClosureToStr;
use Autoframe\Core\Tenant\AfrDefaultTenantConfigsInterface;
use Autoframe\Core\Tenant\AfrTenant;
use Throwable;
use Closure;

/**
 * Class AfrEvent
 *
 *
 *
 *
 * AfrEvent::addEventClosure('*afr*',function ($aData) {
 * echo "\n~~addEventClosure Class: ";
 * if(!empty($this)){
 * if($this instanceof Afr){
 * //    $this->dick = '9999';
 * }
 * }
 * debug_print_backtrace();
 * echo (!empty($this) ?  (get_class($this).": ") : '~~~~-php~~~~:').print_r($aData,true);
 * echo "\n\n";
 * },true);
 *
 * AfrEvent::addEventClosure('*',function (&$allData) {
 * $aData['AfrEvent::addEventClosure(*'] = 'rand(112,998)';
 * return rand(112,998).count($aData);
 * });
 * AfrEvent::addEventClosure('Connection\AfrDbConnectionManagerClass::getInstance',function ($aData) {
 * return 'Connection\AfrDbConnectionManagerClass::getInstance--'.rand(112,998).count($aData);
 * });
 *
 * AfrEvent::dispatchEvent('',['s1',2,['a3']]);
 * AfrEvent::dispatchEvent('SomeEvD');
 *
 * AfrEvent::dispatchEvent('Some.Last.Eventx', ['SomeEvDArg1']);
 */
class AfrEvent implements AfrDefaultTenantConfigsInterface
{
	const AFR_EXCEPTION = 'afr.exception';
	const AFR_BOOTSTRAP = 'afr.bootstrap';
	const AFR_RUN = 'afr.run';
	const DB_ACTION_START = 'db.action.start0';
	const DB_QUERY_START = 'db.query.start1';
	const DB_QUERY_END = 'db.query.complete2';
	const DB_FETCH_START = 'db.fetch.start3';
	const DB_FETCH_DONE = 'db.fetch.done4';
	const DB_ACTION_DONE = 'db.action.done5';

	const MODULE_READ_START = 'mod.read.start';
	const MODULE_READ_END = 'mod.read.end';
	const MODULE_LOAD = 'mod.load';

	const HTTP_HEADER_SET = 'http.header.set';
	const HTTP_COOKIE_SET = 'http.cookie.set';
	const HTTP_STATUS_SET = 'http.status.set';
	const HTTP_REDIRECT = 'http.redirect';

	const SESSION_START = 'session.start';
	const SESSION_READ = 'session.read';
	const SESSION_WRITE = 'session.write';

	const ROUTER_BEFORE_ROUTING_0 = 'router.before.routing0';
	const ROUTER_ON_MIDDLEWARE_1 = 'router.on.middleware1';
	const ROUTER_ON_CODE_2 = 'router.on.code2';
	const ROUTER_ON_AFTER_3 = 'router.on.after3';
	const ROUTER_BEFORE_VIEW_4 = 'router.before.view4';
	const ROUTER_VIEW_RENDER_START_5 = 'router.view.render.start5';
	const ROUTER_VIEW_RENDER_DONE_6 = 'router.view.render.done6';
	const ROUTER_VIEW_OUTPUT_START_7 = 'router.view.output.start7';
	const ROUTER_VIEW_OUTPUT_DONE_8 = 'router.view.output.done8';


	const CACHE_READ_START = 'cache.read.start';
	const CACHE_READ_END = 'cache.read.end';
	const CACHE_HIT = 'cache.hit';
	const CACHE_MISS = 'cache.miss';
	const CRON_DAEMON = 'cron.daemon';
	const CRON_WORKER = 'cron.worker';
	const CRON_JOB = 'cron.job';

	const X_EVT = 'evt';
	const X_ARGS = 'args';
	const X_TRACE = 'trace';
	const X_TIMING = 'rqt';
	const X_EVENT_TIME = 'et';
	const X_DELTA_TIME = 'delta';
	const X_WILDCARDS = 'wildcards';
	const X_CLOSURE_RESULTS = 'res';
	const X_MEMORY_MB = 'mem';

	const  WILDCARD_STARTS_WITH = 'str*'; //applies on any event starting with afr.*
	const  WILDCARD_ENDS_WITH = '*str'; //applies on any event ending with *afr
	const  WILDCARD_CONTAINS = '*str*'; //applies on any event containing the substring
	const  WILDCARD_STAR = '*'; //applies on any event

	public static bool $bSlimTrace = true;
	public static ?array $aPrevTrace = null;
	public static int $iDefaultTraceDepth = 5;

	/**
	 * Bool means on or off, else int is the chance to trigger rand(0,x)<1
	 * Set to 99 for a 1% trigger chance
	 * @var bool|int
	 */
	public static $mMemoryUsage = false;

	protected static array $aOnEventClosures = [];
	protected static array $aWildcardClosure = [];

	protected static array $aTriggeredEventsStats = [];
	public static array $aDataCollectFlags = [ //TODO load from tenant and defaults, done acolo, nu aici!
		'Connection\AfrDbConnectionManagerClass::getInstance' => [self::X_ARGS => false, self::X_TRACE => 5],
		//	'SomeEvD' => [self::X_ARGS => true, self::X_TRACE => 7],
		'*Last*' => [self::X_ARGS => true, self::X_TRACE => 0],
	];
	protected static bool $bDefaultTenantConfigWasApplied = false;


	public static function sampleTenantDefaultConfig(): ?string
	{
		return file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'config.sample.AfrEvent.php');
	}

	public static function applyDefaultTenantConfig(): void
	{
		if (!empty(static::$bDefaultTenantConfigWasApplied)) {
			return;
		}
		if (!empty($sConfigFilePath = AfrTenant::getAfrDefaultTenantConfigsForFqcn(static::class))) {
			static::$bDefaultTenantConfigWasApplied = true;
			if (file_exists($sConfigFilePath)) {
				static::extendConfigFlagsFromArray((include $sConfigFilePath));
			}
		}
	}

	/**
	 * @param string $sEvent
	 * @param array|null $aArgs
	 * @param int|null $iTraceDepth
	 * @param bool|null $mMemoryUsage
	 * @return array
	 * @throws AfrEventException
	 */
	public static function dispatchEvent(
		string $sEvent = '',
		array  $aArgs = null,
		int    $iTraceDepth = null,
		bool   $mMemoryUsage = null
	): array
	{
		if (empty(self::$aTriggeredEventsStats)) {
			self::$aTriggeredEventsStats[] = [
				self::X_EVT => 'REQUEST_TIME_FLOAT',
				self::X_TIMING => $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true),
				self::X_EVENT_TIME => 0.0,
				self::X_DELTA_TIME => 0.0,
			];
		}

		self::$aPrevTrace = null;
		$sEvent = $sEvent ?: static::autoEventName(
			2,
			(self::$aPrevTrace = debug_backtrace(1, 2))
		);
		$aData = [
			self::X_EVT => $sEvent,
			//	self::X_TIMING => microtime(true),
			self::X_EVENT_TIME => $t = (microtime(true) - self::$aTriggeredEventsStats[0][self::X_TIMING]) * 1000,
			self::X_DELTA_TIME => $t - end(self::$aTriggeredEventsStats)[self::X_EVENT_TIME],
		];

		if ($mMemoryUsage || self::$mMemoryUsage === true || is_integer(self::$mMemoryUsage) && rand(0, self::$mMemoryUsage) < 1) {
			$aData[self::X_MEMORY_MB] = round((memory_get_usage() / (1024 * 1024)), 2);
		}

		if (static::logArgs($sEvent)) {
			if ($aArgs === null) { //auto if configured from config flag or env
				self::$aPrevTrace ??= debug_backtrace(1, 2);
				$aArgs = self::$aPrevTrace[1]['args'] ?? null;
			}
			if ($aArgs !== null) {
				$aData[self::X_ARGS] = $aArgs;
			}
		}
		if (($iTraceDepth = static::getTraceDepth($iTraceDepth, $sEvent))) {
			$aData[self::X_TRACE] = static::slimDebugTrace(
				empty(self::$aPrevTrace) || $iTraceDepth > 2 ?
					(self::$aPrevTrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $iTraceDepth)) :
					self::$aPrevTrace,
				self::$bSlimTrace
			);
		}
		$aResults = self::callEventClosure($sEvent, $aData, []);

		$X_WILDCARDS = !empty(self::$aWildcardClosure) &&
			(self::getEnv('AFR_EVENT_X_COLLECT_WILDCARDS_INFO', false) || self::getConfigFlag($sEvent, self::X_WILDCARDS));
		//wildcards
		foreach (self::$aWildcardClosure ?? [] as $sWildcardEvent => $aHow) {
			$bTrigger = ($sWildcardEvent === self::WILDCARD_STAR);
			if (!$bTrigger && !empty($aHow[self::WILDCARD_STARTS_WITH]) && $aHow[self::WILDCARD_STARTS_WITH][0] ===
				substr($sEvent, 0, $aHow[self::WILDCARD_STARTS_WITH][1])) {
				$bTrigger = true;
			}
			if (!$bTrigger && !empty($aHow[self::WILDCARD_ENDS_WITH]) && $aHow[self::WILDCARD_ENDS_WITH][0] ===
				substr($sEvent, -$aHow[self::WILDCARD_ENDS_WITH][1], $aHow[self::WILDCARD_ENDS_WITH][1])) {
				$bTrigger = true;
			}
			if (!$bTrigger && !empty($aHow[self::WILDCARD_CONTAINS])) {
				foreach ($aHow[self::WILDCARD_CONTAINS] as $sContains) {
					if (strpos($sEvent, $sContains) !== false) {
						$bTrigger = true;
						break;
					}
				}
			}
			if (!$bTrigger) {
				continue;
			}

			$aResults = self::callEventClosure($sWildcardEvent, $aData, $aResults);
			if ($X_WILDCARDS) {
				$aData[self::X_WILDCARDS] ??= [];
				$aData[self::X_WILDCARDS][] = [
					self::X_EVT => $sWildcardEvent,
					//	self::X_TIMING => microtime(true),
					self::X_EVENT_TIME => $t = (microtime(true) - self::$aTriggeredEventsStats[0][self::X_TIMING]) * 1000,
					self::X_DELTA_TIME => $t - end(self::$aTriggeredEventsStats)[self::X_EVENT_TIME],

				];
			}
		}


		if (self::getConfigFlag($sEvent, self::X_CLOSURE_RESULTS) || self::getEnv('AFR_EVENT_X_CLOSURE_RESULTS', false)) {
			$aData[self::X_CLOSURE_RESULTS] = $aResults;
		}

		self::$aPrevTrace = null;
		return self::$aTriggeredEventsStats[] = $aData;
	}


	/**
	 * @param string $sEvent
	 * @param array $aData
	 * @param array $aResults
	 * @return array
	 * @throws AfrEventException|\ReflectionException
	 */
	protected static function callEventClosure(string $sEvent, array &$aData, array $aResults): array
	{
		$oScopeInstance = null;
		foreach (self::$aOnEventClosures[$sEvent] ?? [] as $i => $aClosureAndScopeBound) {
			/** @var Closure $closure */
			[$closure, $bInnerBoundScope, $newScope] = $aClosureAndScopeBound;
			if ($bInnerBoundScope) {
				if ($oScopeInstance === null) { //detect once on each trace
					if (empty(self::$aPrevTrace)) {
						self::$aPrevTrace = debug_backtrace(1, 3);
						array_shift(self::$aPrevTrace); //shif this current level
					}
					$oScopeInstance = self::$aPrevTrace[1]['object'] ?? false;
				}
				if ($oScopeInstance) {
					if ($oScopeInstance instanceof Throwable) {
						$oScopeInstance = false;
						continue; //prevent infinite loop inside error scoping
					}
					$newBoundClosure = $closure->bindTo(
						$oScopeInstance,
						$newScope === true ? $oScopeInstance : $newScope
					);
					if ($newBoundClosure instanceof Closure) {
						$closure = $newBoundClosure;
					} else {
						if (Afr::app() && self::getEnv('AFR_EVENT_EXCEPTION_IS_FATAL_ERROR', true)) {
							unset($aClosureAndScopeBound[0]);
							throw new AfrEventException(
								'Unable to bind #index.' . $i . ' from event(' . $sEvent . ', ' . $aData[self::X_EVT] .
								') to scope(' . get_class($oScopeInstance) . ') for Closure(scope,' .
								implode(',', $aClosureAndScopeBound) . '): ' . AfrClosureToStr::dump($closure)
							);
						}
					}
				}
			}
			// FINALLY, call
			$aResults[$sEvent] = $closure($aData); //call closure
		}

		return $aResults;
	}


	protected static function autoEventName(int $iStackLevel, array $aEvents = null): string
	{
		if (!empty($aEvents[$iStackLevel - 1]['class']) && !empty($aEvents[$iStackLevel - 1]['function'])) {
			return array_slice(explode('\\', $aEvents[$iStackLevel - 1]['class']), -1, 1)[0] .
				'.' . $aEvents[$iStackLevel - 1]['function'];
		}
		return basename($aEvents[$iStackLevel - 2]['file']) . '.L' . $aEvents[$iStackLevel - 2]['line'];
	}

	protected static function getEnv(string $sKey, $mDefault = null, $mCatch = null)
	{
		$sKey = (string)preg_replace("/[^A-Za-z0-9_]/", '_', strtoupper($sKey));
		if (Afr::app()) {
			try {
				return Afr::app()->env()->getEnv($sKey, $mDefault);
			} catch (Throwable $e) {
				if ($mCatch !== null) {
					return $mCatch;
				}
			}
		} elseif (!empty($_ENV[$sKey])) {
			return $_ENV[$sKey];
		}
		return $mDefault;
	}


	/**
	 * @param string $sEvent
	 * @param string $sFlag
	 * @return int|bool|null|mixed
	 */
	public static function getConfigFlag(string $sEvent, string $sFlag)
	{
		if (isset(self::$aDataCollectFlags[$sEvent][$sFlag])) {
			return self::$aDataCollectFlags[$sEvent][$sFlag];
		}
		if (isset(self::$aDataCollectFlags[self::WILDCARD_STAR][$sFlag])) {
			return self::$aDataCollectFlags[self::WILDCARD_STAR][$sFlag];
		}
		//parse wildcard settings
		foreach (self::$aDataCollectFlags as $sDataCollectFlag => $aFlags) {
			if (empty($aFlags[$sFlag])) {
				continue;
			}
			if (strpos($sDataCollectFlag, self::WILDCARD_STAR) !== false) {
				$aSections = explode(self::WILDCARD_STAR, $sDataCollectFlag);
				$iSections = count($aSections);
				foreach ($aSections as $i => $sSection) {
					$iSl = strlen($sSection);
					if ($iSl < 1) {
						continue;
					}
					if ($i == 0) {
						if (substr($sEvent, 0, $iSl) === $sSection) {
							return $aFlags[$sFlag];
						}
					} elseif ($i == $iSections - 1) {
						if (substr($sEvent, -$iSl, $iSl) === $sSection) {
							return $aFlags[$sFlag];
						}
					} else {
						if (strpos($sEvent, $sSection) !== false) {
							return $aFlags[$sFlag];
						}
					}
				}
			}
		}
		return null;
		// return self::$aDataCollectFlags[$sEvent][$sFlag] ?? self::$aDataCollectFlags[self::WILDCARD_STAR][$sFlag] ?? null;
	}

	public static function setConfigFlag(string $sEvent, string $sFlag, $bOnOrInt): void
	{
		if ($bOnOrInt) {
			self::$aDataCollectFlags[$sEvent][$sFlag] = $bOnOrInt;
		} elseif (!empty(self::$aDataCollectFlags[$sEvent][$sFlag])) {
			unset(self::$aDataCollectFlags[$sEvent][$sFlag]);
			if (empty(self::$aDataCollectFlags[$sEvent])) {
				unset(self::$aDataCollectFlags[$sEvent]);
			}
		}
	}

	public static function extendConfigFlagsFromArray(array $aFlags): void
	{
		foreach ($aFlags as $sEvent => $aFlagVal) {
			foreach ($aFlagVal as $sFlag => $bOn) {
				self::setConfigFlag($sEvent, $sFlag, $bOn);
			}
		}
	}


	/**
	 * @param string $sEvent Event name: db.query.start or wildcard: db.* | *.start | db.*.start | *.*.*.rt
	 * @param Closure $closure What to do, and receive ($aData)
	 * @param bool $bPrependQueue the closure should be placed at the stack beginning or appended at the end?
	 * @param bool $bInnerBoundScope true = $newThis on Closure::bindTo(?object $newThis, object|string|null $newScope = "static")
	 * @param object|string|null $newScope true = $newThis|"static"|null
	 * @return void
	 */
	public static function addEventClosure(
		string  $sEvent,
		Closure $closure,
		bool    $bInnerBoundScope = false,
		        $newScope = true,
		bool    $bPrependQueue = false
	): void
	{
		self::$aOnEventClosures[$sEvent] ??= [];
		if ($bPrependQueue) {
			self::$aOnEventClosures[$sEvent] = array_merge(
				[[$closure, $bInnerBoundScope, $newScope]],
				self::$aOnEventClosures[$sEvent]
			);
		} else {
			self::$aOnEventClosures[$sEvent][] = [$closure, $bInnerBoundScope, $newScope];
		}

		self::addWildcardClosure($sEvent);

	}

	public static function getTriggeredEventsLog(): array
	{
		return self::$aTriggeredEventsStats;
	}

	public static function getEventClosures(): array
	{
		return self::$aOnEventClosures;
	}

	public static function getWildcardConfig(): array
	{
		return self::$aWildcardClosure;

	}

	/**
	 * @param int|null $iTraceDepth
	 * @param string $sEvent
	 * @return int
	 */
	protected static function getTraceDepth(?int $iTraceDepth, string $sEvent): int
	{
		if (!Afr::app()) { //app not booted
			return is_null($iTraceDepth) ? self::$iDefaultTraceDepth : $iTraceDepth;
		}

		if (is_integer($cFlagVal = self::getConfigFlag($sEvent, self::X_TRACE))) {
			return $cFlagVal; //can overwrite any other setting
		}
		if (static::getEnv('AFR_EVENT_X_TRACE', false)) {
			if (($cFlagVal = (int)static::getEnv('AFR_EVENT_TRACE_DEPTH_' . $sEvent, -1, -1)) > -1) {
				return $cFlagVal; //also, can overwrite any other code setting, Eg: AFR_EVENT_TRACE_DEPTH_SOME_XYZ_EVENT = 15
			}
			return is_null($iTraceDepth) ? static::getEnv('AFR_EVENT_DEFAULT_TRACE_DEPTH', 0) : $iTraceDepth;
		}
		return 0;
	}


	protected static function logArgs(string $sEvent): bool
	{
		if (!Afr::app() || self::getConfigFlag($sEvent, self::X_ARGS)) {
			return true;
		}
		if (
			static::getEnv('AFR_EVENT_X_ARGS_' . $sEvent, false, false)) {
			return true; //also, can overwrite any other code setting, Eg: AFR_EVENT_X_ARGS_SOME_XYZ_EVENT = true
		}
		return false;
	}

	/**
	 * @param array $aDebugBacktrace
	 * @param bool $bSlim
	 * @return array
	 */
	protected static function slimDebugTrace(array $aDebugBacktrace, bool $bSlim): array
	{
		$aTrace = [];
		foreach ($aDebugBacktrace as $aInfo) {
			$file = strtr($aInfo['file'] ?? '[PHP.Kernel⚙️]', '\\', '/');
			if ($bSlim) {
				$file = implode(
					'/',
					array_slice(explode('/', $file), -2, 2)
				);
			}
			$sClass = '';
			if (!empty($aInfo['object']) && is_object($aInfo['object'])) {
				$sClass = get_class($aInfo['object']);
			} elseif (!empty($aInfo['class'])) {
				$sClass = $aInfo['class'];
			}
			if (!empty($sClass)) {
				$sClass = ($bSlim ? basename($sClass) : $sClass) . ($aInfo['type'] ?? ' ');
			}

			$aTrace[] = $file . ':' . ($aInfo['line'] ?? 0) . "\t" . trim($sClass) . ($aInfo['function'] ?? '') . '()';
		}
		return $aTrace;
	}


	/**
	 * @param string $sEvent
	 * @return void
	 */
	protected static function addWildcardClosure(string $sEvent): void
	{
		if (empty(self::$aWildcardClosure[$sEvent]) && substr_count($sEvent, self::WILDCARD_STAR) > 0) {
			$aMap = [];
			if ($sEvent === self::WILDCARD_STAR) { //*
				self::$aWildcardClosure[$sEvent] = $aMap[self::WILDCARD_STAR] = true; //match any event
			} else {
				$aSections = explode(self::WILDCARD_STAR, $sEvent);
				$iSections = count($aSections);

				foreach ($aSections as $i => $sSection) {
					if (strlen($sSection) < 1) {
						continue;
					}
					if ($i === 0) {
						$aMap[self::WILDCARD_STARTS_WITH] = [$sSection, strlen($sSection)];
					} elseif ($i === $iSections - 1) {
						$aMap[self::WILDCARD_ENDS_WITH] = [$sSection, strlen($sSection)];
					} else {
						$aMap[self::WILDCARD_CONTAINS] = array_merge(
							$aMap[self::WILDCARD_CONTAINS] ?? [],
							[$sSection]
						);
					}
				}
			}
			self::$aWildcardClosure[$sEvent] = $aMap;
		}
	}
}