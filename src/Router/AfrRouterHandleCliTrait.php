<?php

namespace Autoframe\Core\Router;

use Autoframe\Core\Afr\Afr;
use Autoframe\Core\AfrCoreModules\FnContracts\AfrCliRoutesContract;
use Autoframe\Core\Env\Exception\AfrEnvException;
use Autoframe\Core\Http\Request\AfrCliConstantsInterface;
use Autoframe\Core\CliTools\AfrCliTextColors;
use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\Cron\AfrConJobSources;
use Autoframe\Core\Cron\AfrCronJobDaemon;
use Autoframe\Core\Cron\Log\Channel\AfrCronLogChannelSharedLogBuffer;
use Autoframe\Core\Event\Exception\AfrEventException;
use Autoframe\Core\Exception\AfrException;
use Autoframe\Core\Http\Request\AfrRequestClass;
use Autoframe\Core\Http\Request\AfrRequestInterface;


use Autoframe\Core\ModuleBox\Exception\AfrModuleException;
use Autoframe\Core\ModuleBox\Exception\AfrModuleFunctionalityException;
use Autoframe\Core\Router\Exception\AfrRouterException;
use Autoframe\Core\Tenant\AfrTenant;
use Autoframe\Core\Cron\Log\AfrCronLoggerInterface;
use Autoframe\Core\Cron\Log\AfrCronLoggerClass;
use Closure;

trait AfrRouterHandleCliTrait
{
	/**
	 * Handle cli routes.
	 * @param AfrRequestInterface|AfrRequestClass $oRequest
	 * @param Closure|null $oClosureAfterRoute
	 * @return int
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 * @throws AfrException|\ReflectionException
	 */
	public function handleCliRoutes(AfrRequestInterface $oRequest, Closure $oClosureAfterRoute = null): int
	{
		if (!$oRequest->isCli()) return $this->dispatchHttpRoute($oRequest, $oClosureAfterRoute);


		list($bIsQa, $sQaIndexStack) = $oRequest->detectArgvKeyPresence(AfrCliConstantsInterface::QA_ARGV_KEY);
		if ($bIsQa) return $this->handleCliQa($oRequest, $oClosureAfterRoute, $sQaIndexStack);

		list($bIsExec, $sExecutePHP) = $oRequest->detectArgvKeyPresence(AfrCliConstantsInterface::CLI_EXECUTE_ARGV_KEY);
		if ($bIsExec) return $this->handleCliExecutePHP($oRequest, $oClosureAfterRoute, $sExecutePHP);

		list($bIsCliInvoke, $sInvokeFQCN) = $oRequest->detectArgvKeyPresence(AfrCliConstantsInterface::CLI_INVOKE_ARGV_KEY);
		if ($bIsCliInvoke) return $this->handleCliInvoke($sInvokeFQCN, $oRequest, $oClosureAfterRoute);

		list($bIsCliClosure, $sClIndexStack) = $oRequest->detectArgvKeyPresence(AfrCliConstantsInterface::CLI_CLOSURE_ROUTE_ARGV_KEY);
		if ($bIsCliClosure) return $this->handleCliClosure($oRequest, $oClosureAfterRoute, $sClIndexStack);

		list($bIsCronLiveLogViewer, $sCronLiveLogViewer) = $oRequest->detectArgvKeyPresence(AfrCliConstantsInterface::CRON_LIVE_LOGS_ARGV_KEY);
		if ($bIsCronLiveLogViewer) return $this->handleCliLiveCronLogViewer($oRequest, $oClosureAfterRoute, $sCronLiveLogViewer);

		list($bIsCron, $sCronIndexStack) = $oRequest->detectArgvKeyPresence(AfrCliConstantsInterface::CRON_DAEMON_ARGV_KEY);
		list($bIsCronWorker, $sWorkerValue) = $oRequest->detectArgvKeyPresence(AfrCliConstantsInterface::CRON_WORKER_ARGV_KEY);
		if ($bIsCron || $bIsCronWorker) {
			if ($bIsCronWorker && empty($sWorkerValue)) {
				throw new AfrRouterException('Cron Worker payload is not configured! This must be a base64 @_');
			}
			AfrConJobSources::getInstance()->registerCronJobSourcesFromModules();
			//TODO: 2026: sa mut addUrlSource si citirea direct in daemon
//			AfrConJobSources::getInstance()->addUrlSource('demo', 'http://localhost:808/core/src/Cron/AfrCronJobDaemon.DemoCron.txt');
			AfrCronJobDaemon::make(
				$oCronLogger = Afr::app()->container()->get(AfrCronLoggerInterface::class), //AfrCronLoggerClass::getInstance();
				$bIsCronWorker ? $sWorkerValue : null
			)->run();

			if ($oClosureAfterRoute) {
				$oClosureAfterRoute(
					$bIsCron ? [AfrCliConstantsInterface::CRON_DAEMON_ARGV_KEY => $sCronIndexStack] : [AfrCliConstantsInterface::CRON_WORKER_ARGV_KEY => $sWorkerValue],
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


	/**
	 * @throws AfrModuleException
	 * @throws AfrModuleFunctionalityException
	 * @throws AfrEventException
	 * @throws AfrException
	 * @throws AfrContainerException
	 * @throws AfrEnvException
	 */
	protected function handleCliQa(AfrRequestInterface $oRequest, ?Closure $oClosureAfterRoute, $sQaIndexStack, bool $bMergeQA = true): int
	{
		$iTotalCliOfType = 0; //$iTotalCliOfType = Afr::app()->container()->get(AfrModuleBox::class)->registerModulesThatImplementTheInterface(AfrModuleCLIRoutesInterface::class);
		/** @var AfrCliRoutesContract[] $aOCliRoutes */
		$aOCliRoutes = Afr::app()->box()->resolveFunctionalityGroup(AfrCliRoutesContract::class);
		foreach ($aOCliRoutes as $oCliRoute) {
//			$iTotalCliOfType += $oCliRoute->registerCliRoutes($oRequest, [AfrCliConstantsInterface::CLI_QA_ROUTES_STACK]);
			foreach ($oCliRoute->getCliRoutes()[AfrCliConstantsInterface::CLI_QA_ROUTES_STACK] ?? [] as $sKeyCluster => $mStack) {
				// $mStack should be an array of closures OR Closure that returns array of closures
				$iTotalCliOfType += AfrCliQaRouter::addActionGroup($sKeyCluster, $mStack, $bMergeQA) ?? 0;
			}
		}
		$iCalled = AfrCliQaRouter::run($sQaIndexStack); //AfrCore::getInstance()->registerCLIRoutes();
		if ($oClosureAfterRoute)
			$oClosureAfterRoute(AfrCliConstantsInterface::QA_ARGV_KEY, $oRequest, $sQaIndexStack, $iCalled, $iTotalCliOfType);

		return $iCalled;
	}

	protected function handleCliExecutePHP(AfrRequestInterface $oRequest, ?Closure $oClosureAfterRoute, $sExecutePHP): int
	{
		$evalReturn = !empty($sExecutePHP) ? eval($sExecutePHP) : null;
		if ($oClosureAfterRoute)
			$oClosureAfterRoute(AfrCliConstantsInterface::CLI_EXECUTE_ARGV_KEY, $oRequest, $evalReturn);
		return empty($sExecutePHP) ? 0 : 1;
	}

	protected function handleCliLiveCronLogViewer(AfrRequestInterface $oRequest, ?Closure $oClosureAfterRoute, $sCronLiveLogViewer): int
	{
		AfrCronLogChannelSharedLogBuffer::getInstance()->viewLogs($sCronLiveLogViewer);
		if ($oClosureAfterRoute)
			$oClosureAfterRoute(AfrCliConstantsInterface::CRON_LIVE_LOGS_ARGV_KEY, $oRequest, $sCronLiveLogViewer);
		return 1;
	}

	/**
	 * @param $sInvokeFQCN
	 * @param $oRequest
	 * @param Closure|null $oClosureAfterRoute
	 * @return int
	 * @throws AfrContainerException
	 * @throws AfrRouterException
	 */
	protected function handleCliInvoke($sInvokeFQCN, $oRequest, ?Closure $oClosureAfterRoute): int
	{
		if (empty($sInvokeFQCN))
			throw new AfrRouterException('InvokeFQCN Not Found in arg: "' . AfrCliConstantsInterface::CLI_INVOKE_ARGV_KEY . '"');
		$sInvokeFQCN = strtr($sInvokeFQCN, ['/' => '\\', '.' => '\\', '~' => '\\']); //unescape the FQCN
		if (!class_exists($sInvokeFQCN))
			throw new AfrRouterException('Invalid CLASS: ' . AfrCliConstantsInterface::CLI_INVOKE_ARGV_KEY . '=' . $sInvokeFQCN);
		$oInvoke = Afr::app()->container()->get($sInvokeFQCN);
		AfrCliTextColors::getInstance()->
		styleDefaultAllBgColor()->
		bgBlueLight('*** AFR ' . AfrCliConstantsInterface::CLI_INVOKE_ARGV_KEY . ' ***')->
		bgDefault(' @' . AfrTenant::getTenantAlias() . "\n")->
		colorGreen("***  $sInvokeFQCN->cliInvoke(AfrRequestInterface, ?Closure) ***")->styleDefaultAllBgColor("\n\n")->
		textPrint();
		if (is_object($oInvoke)) {
			$oInvoke->cliInvoke($oRequest, $oClosureAfterRoute);
			return 1;
		}
		throw new AfrRouterException('Invalid OBJECT: ' . AfrCliConstantsInterface::CLI_INVOKE_ARGV_KEY . '=' . $sInvokeFQCN);
	}


	protected function handleCliClosure($sCliStackAlias, $oRequest, ?Closure $oClosureAfterRoute): int
	{
		if (empty($sCliStackAlias))
			throw new AfrRouterException('Cli closure route alias can`t be empty in arg: "' . AfrCliConstantsInterface::CLI_CLOSURE_ROUTE_ARGV_KEY . '"');
		$onFoundClosure = null;
		/** @var AfrCliRoutesContract[] $aOCliRoutes */
		$aOCliRoutes = Afr::app()->box()->resolveFunctionalityGroup(AfrCliRoutesContract::class);
		foreach ($aOCliRoutes as $oCliRoute) {
			foreach ($oCliRoute->getCliRoutes()[AfrCliConstantsInterface::CLI_CLOSURE_ROUTES_STACK] ?? [] as $sKeyAlias => $oClosureLoop) {
				if(strtolower($sKeyAlias) !== strtolower($sCliStackAlias)) continue;
				$onFoundClosure = $oClosureLoop;
			}
		}


		$sCliStackAlias = strtr($sCliStackAlias, ['/' => '\\', '.' => '\\', '~' => '\\']); //unescape the FQCN
		if (!class_exists($sCliStackAlias))
			throw new AfrRouterException('Invalid CLASS: ' . AfrCliConstantsInterface::CLI_INVOKE_ARGV_KEY . '=' . $sCliStackAlias);
		$oInvoke = Afr::app()->container()->get($sCliStackAlias);
		AfrCliTextColors::getInstance()->
		styleDefaultAllBgColor()->
		bgBlueLight('*** AFR ' . AfrCliConstantsInterface::CLI_INVOKE_ARGV_KEY . ' ***')->
		bgDefault(' @' . AfrTenant::getTenantAlias() . "\n")->
		colorGreen("***  $sCliStackAlias->cliInvoke(AfrRequestInterface, ?Closure) ***")->styleDefaultAllBgColor("\n\n")->
		textPrint();
		if (is_object($oInvoke)) {
			$oInvoke->cliInvoke($oRequest, $oClosureAfterRoute);
			return 1;
		}
		throw new AfrRouterException('Invalid OBJECT: ' . AfrCliConstantsInterface::CLI_INVOKE_ARGV_KEY . '=' . $sCliStackAlias);
	}

}
