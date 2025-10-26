<?php

namespace Autoframe\Core\Router;

use Autoframe\Core\Afr\Afr;
use Autoframe\Core\CliTools\AfrCliTextColors;
use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\Cron\AfrConJobSources;
use Autoframe\Core\Cron\AfrCronJobDaemon;
use Autoframe\Core\Cron\Log\Channel\AfrCronLogChannelSharedLogBuffer;
use Autoframe\Core\Event\Exception\AfrEventException;
use Autoframe\Core\Exception\AfrException;
use Autoframe\Core\Http\Request\AfrRequestInterface;
use Autoframe\Core\Module\AfrModuleBox;
use Autoframe\Core\Module\AfrModuleCLIRoutesInterface;
use Autoframe\Core\Router\Exception\AfrRouterException;
use Autoframe\Core\Tenant\AfrTenant;
use Autoframe\Core\Cron\Log\AfrCronLoggerInterface;
use Autoframe\Core\Cron\Log\AfrCronLoggerClass;
use Closure;

trait AfrRouterHandleCliTrait
{
	/**
	 * @param AfrRequestInterface $oRequest
	 * @param Closure|null $oClosureAfterRoute
	 * @return int
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 * @throws AfrException
	 */
	public function handleCliRoutes(AfrRequestInterface $oRequest, Closure $oClosureAfterRoute = null): int
	{

		list($bIsQa, $sQaIndexStack) = $oRequest->detectArgvKeyPresence(self::QA_ARGV_KEY);
		if ($bIsQa) {
			$iTotalRegistered = Afr::app()
				->container()
				->get(AfrModuleBox::class)
				->registerModulesThatImplementTheInterface(AfrModuleCLIRoutesInterface::class);
			//AfrCore::getInstance()->registerCLIRoutes();
			$iCalled = AfrCliQaRouter::run($sQaIndexStack);
			if ($oClosureAfterRoute) {
				$oClosureAfterRoute(self::QA_ARGV_KEY, $oRequest, $iCalled, $iTotalRegistered);
			}
			return $iCalled;
		}

		list($bIsCronLiveLogViewer, $sCronLiveLogViewer) = $oRequest->detectArgvKeyPresence(self::CRON_LIVE_LOGS_ARGV_KEY);
		list($bIsCron, $sCronIndexStack) = $oRequest->detectArgvKeyPresence(self::CRON_DAEMON_ARGV_KEY);
		list($bIsCronWorker, $sWorkerValue) = $oRequest->detectArgvKeyPresence(self::CRON_WORKER_ARGV_KEY);
		if($bIsCronLiveLogViewer){
			AfrCronLogChannelSharedLogBuffer::getInstance()->viewLogs($sCronLiveLogViewer);
		}
		elseif ($bIsCron || $bIsCronWorker) {
			if ($bIsCronWorker && empty($sWorkerValue)) {
				throw new AfrRouterException('Cron Worker payload is not configured! This must be a base64 @_');
			}
			// TODO: !!!!!!!!!! INREGISTRARE / CITIRE DIN MODULE PENTRU SURSE DE JOBS, CARE POATE FI ASIGNAT DE MULTIPLE ORI!!
			if(1){
				AfrConJobSources::getInstance()->addUrlSource('demo','http://localhost:808/core/src/Cron/AfrCronJobDaemon.DemoCron.txt');
			}
			else{
				AfrConJobSources::getInstance()->getSourcesFreshFromModules();
			}

			AfrCronJobDaemon::make(
				$oCronLogger = Afr::app()->container()->get(AfrCronLoggerInterface::class), //AfrCronLoggerClass::getInstance();
				$bIsCronWorker ? $sWorkerValue : null
			)->run();

			if ($oClosureAfterRoute) {
				$oClosureAfterRoute(
					$bIsCron ? [self::CRON_DAEMON_ARGV_KEY => $sCronIndexStack] : [self::CRON_WORKER_ARGV_KEY => $sWorkerValue],
					$oRequest,
					$oCronLogger
				);
			}

			return (int)ceil(microtime(true) - $oRequest->getServerParam('REQUEST_TIME_FLOAT', time())); //number of seconds
		}
		$sScriptServer = $_SERVER['argv'][0] ?? '';
		$sScriptRequest = $oRequest->getServerParam('argv')[0] ?? '';

		$oColors = AfrCliTextColors::getInstance()->
		styleDefaultAllBgColor()->
		bgBlueLight('*** AFR CLI ***')->
		bgDefault(' @' . AfrTenant::getTenantAlias())->
		colorGreen("\nRequest: $sScriptRequest");
		if ($sScriptRequest != $sScriptServer) {
			$oColors->colorYellowLight("\nServer:  $sScriptServer");
		}
		$oColors->styleDefaultAllBgColor("\n\n")->
		textPrint();
		if ($oClosureAfterRoute) {
			$oClosureAfterRoute($sScriptRequest, $sScriptServer, $oRequest);
		}
		return 0;
	}

}