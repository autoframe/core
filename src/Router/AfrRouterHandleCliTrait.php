<?php

namespace Autoframe\Core\Router;

use Autoframe\Core\Afr\Afr;
use Autoframe\Core\AfrCoreModules\FnContracts\AfrCliRoutesContract;
use Autoframe\Core\CliTools\AfrGetOpt;
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
	protected AfrRequestInterface $oRequest;
	protected ?Closure $oClosureAfterRoute = null;
	protected ?string $snCliArg = null;
	protected ?string $snCliArgVal = null;
	protected array $aAllCliArgs = [];
	protected array $aExtraCollected = [];
	protected ?array $aCachedCliRoutes = null;

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
		if (!$oRequest->isCli())
			return $this->dispatchHttpRoute($oRequest, $oClosureAfterRoute);

		$this->oRequest = $oRequest;
		$this->oClosureAfterRoute = $oClosureAfterRoute;
		$this->aCachedCliRoutes = null;
		$this->aAllCliArgs = AfrGetOpt::getInstance()->setArgvFromRequest($oRequest)->getoptDetectAllArgs(null, true);

		if (array_key_exists($this->snCliArg = AfrCliConstantsInterface::QA_ARGV_KEY, $this->aAllCliArgs))
			$iCountHandled = $this->handleCliQa($this->snCliArgVal = $this->aAllCliArgs[$this->snCliArg]);
		elseif (array_key_exists($this->snCliArg = AfrCliConstantsInterface::CLI_EXECUTE_ARGV_KEY, $this->aAllCliArgs))
			$iCountHandled = $this->handleCliExecutePHP($this->snCliArgVal = $this->aAllCliArgs[$this->snCliArg]);
		elseif (array_key_exists($this->snCliArg = AfrCliConstantsInterface::CLI_INVOKE_ARGV_KEY, $this->aAllCliArgs))
			$iCountHandled = $this->handleCliInvoke($this->snCliArgVal = $this->aAllCliArgs[$this->snCliArg]);
		elseif (array_key_exists($this->snCliArg = AfrCliConstantsInterface::CLI_CLOSURE_ROUTE_ARGV_KEY, $this->aAllCliArgs))
			$iCountHandled = $this->handleCliClosure($this->snCliArgVal = $this->aAllCliArgs[$this->snCliArg]);
		elseif (array_key_exists($this->snCliArg = AfrCliConstantsInterface::CRON_LIVE_LOGS_ARGV_KEY, $this->aAllCliArgs))
			$iCountHandled = $this->handleCliLiveCronLogViewer($this->snCliArgVal = $this->aAllCliArgs[$this->snCliArg]);
		elseif (array_key_exists($this->snCliArg = AfrCliConstantsInterface::CRON_DAEMON_ARGV_KEY, $this->aAllCliArgs))
			$iCountHandled = $this->handleCronDaemonWorker(null);
		elseif (array_key_exists($this->snCliArg = AfrCliConstantsInterface::CRON_WORKER_ARGV_KEY, $this->aAllCliArgs))
			$iCountHandled = $this->handleCronDaemonWorker($this->snCliArgVal = $this->aAllCliArgs[$this->snCliArg]);
		else
			$iCountHandled = $this->handleCliFallback();

		if ($this->oClosureAfterRoute) {
			($this->oClosureAfterRoute)(
				$this->oRequest,
				[$this->snCliArg, $this->snCliArgVal, $iCountHandled],
				$this->aExtraCollected
			);
		}

		return $iCountHandled;
	}

	/**
	 * @return AfrCliRoutesContract[]
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 * @throws AfrException
	 */
	protected function getCachedCliRoutes(): array
	{
		return $this->aCachedCliRoutes ??= Afr::app()->box()->resolveFunctionalityGroup(AfrCliRoutesContract::class);
	}

	/**
	 * @throws AfrModuleException
	 * @throws AfrModuleFunctionalityException
	 * @throws AfrEventException
	 * @throws AfrException
	 * @throws AfrContainerException
	 * @throws AfrEnvException
	 */
	protected function handleCliQa($snQaIndexStack): int
	{
		$iTotalCliOfType = 0; //$iTotalCliOfType = Afr::app()->container()->get(AfrModuleBox::class)->registerModulesThatImplementTheInterface(AfrModuleCLIRoutesInterface::class);
		foreach ($this->getCachedCliRoutes() as $oCliRoute) {
//			$iTotalCliOfType += $oCliRoute->registerCliRoutes($oRequest, [AfrCliConstantsInterface::CLI_QA_ROUTES_STACK]);
			foreach ($oCliRoute->getCliRoutes()[AfrCliConstantsInterface::CLI_QA_ROUTES_STACK] ?? [] as $sKeyCluster => $mStack) {
				// $mStack should be an array of closures OR Closure that returns array of closures
				$iTotalCliOfType += AfrCliQaRouter::addActionGroup($sKeyCluster, $mStack) ?? 0;
			}
		}
		//AfrCore::getInstance()->registerCLIRoutes();
		return AfrCliQaRouter::run($snQaIndexStack);
	}

	protected function handleCliExecutePHP($sExecutePHP): int
	{
		$this->aExtraCollected = [!empty($sExecutePHP) ? eval($sExecutePHP) : null,__FUNCTION__];
		return empty($sExecutePHP) ? 0 : 1;
	}

	protected function handleCliLiveCronLogViewer($sCronLiveLogViewer): int
	{
		AfrCronLogChannelSharedLogBuffer::getInstance()->viewLogs($sCronLiveLogViewer);
		return 1;
	}

	/**
	 * @param $sInvokeFQCN
	 * @return int
	 * @throws AfrContainerException
	 * @throws AfrRouterException
	 */
	protected function handleCliInvoke($sInvokeFQCN): int
	{
		if (empty($sInvokeFQCN)) {
			throw new AfrRouterException('InvokeFQCN Not Found in arg: "' . AfrCliConstantsInterface::CLI_INVOKE_ARGV_KEY . '"');
		}
		$sInvokeFQCN = strtr($sInvokeFQCN, ['/' => '\\', '.' => '\\', '~' => '\\']); //unescape the FQCN
		if (!class_exists($sInvokeFQCN)) {
			throw new AfrRouterException('Invalid CLASS: ' . AfrCliConstantsInterface::CLI_INVOKE_ARGV_KEY . '=' . $sInvokeFQCN);
		}
		$oInvoke = Afr::app()->container()->get($sInvokeFQCN);
		AfrCliTextColors::getInstance()->
		styleDefaultAllBgColor()->
		bgBlueLight('*** AFR ' . AfrCliConstantsInterface::CLI_INVOKE_ARGV_KEY . ' ***')->
		bgDefault(' @' . AfrTenant::getTenantAlias() . "\n")->
		colorGreen("***  $sInvokeFQCN->cliInvoke(AfrRequestInterface oRequest) ***")->styleDefaultAllBgColor("\n\n")->
		textPrint();

		if (!is_object($oInvoke) || !method_exists($oInvoke, 'cliInvoke')) {
			throw new AfrRouterException('Invalid OBJECT: ' . AfrCliConstantsInterface::CLI_INVOKE_ARGV_KEY . '=' . $sInvokeFQCN);
		}
		$this->aExtraCollected = [$oInvoke->cliInvoke($this->oRequest),__FUNCTION__];
		return 1;
	}


	/**
	 * @throws AfrEventException
	 * @throws AfrException
	 * @throws AfrContainerException
	 * @throws AfrRouterException
	 */
	protected function handleCliClosure($sCliStackAlias): int
	{
		if (empty($sCliStackAlias)) {
			throw new AfrRouterException('Cli closure route alias can`t be empty in arg: "' . AfrCliConstantsInterface::CLI_CLOSURE_ROUTE_ARGV_KEY . '"');
		}
		$onFoundClosure = null;
		$sCliStackAliasLower = strtolower($sCliStackAlias);
		foreach ($this->getCachedCliRoutes() as $oCliRoute) {
			$aCliRoutes = $oCliRoute->getCliRoutes();
			foreach ($aCliRoutes[AfrCliConstantsInterface::CLI_CLOSURE_ROUTES_STACK] ?? [] as $sKeyAlias => $oClosureLoop) {
				if (strtolower($sKeyAlias) !== $sCliStackAliasLower) continue;
				$onFoundClosure = $oClosureLoop;
				//	break 2;
			}
		}
		if ($onFoundClosure) $this->aExtraCollected = [$onFoundClosure($this->oRequest),__FUNCTION__];

		return $onFoundClosure ? 1 : 0;

	}

	protected function handleCliFallback(): int
	{
		$this->snCliArg = $this->snCliArgVal = null;
		$sScriptServer = $_SERVER['argv'][0] ?? '';
		$sScriptRequest = $this->oRequest->getServerParam('argv')[0] ?? '';
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
		$this->aExtraCollected = [$sScriptRequest, $sScriptServer,__FUNCTION__];

		return 0;
	}


	/**
	 * @param $snWorkerValue
	 * @return int
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 * @throws AfrException
	 * @throws AfrRouterException
	 */
	protected function handleCronDaemonWorker($snWorkerValue): int
	{
		if (empty($snWorkerValue) && $this->snCliArg === AfrCliConstantsInterface::CRON_WORKER_ARGV_KEY)
			throw new AfrRouterException('Cron Worker payload is not configured! This must be a base64 @_');

		AfrConJobSources::getInstance()->registerCronJobSourcesFromModules();
		//TODO: 2026: sa mut addUrlSource si citirea direct in daemon
		//TODO: 2026: DE TESTAT IN APRILIE 26
		// AfrConJobSources::getInstance()->addUrlSource('demo', 'http://localhost:808/core/src/Cron/AfrCronJobDaemon.DemoCron.txt');
		// AfrCronLoggerClass::getInstance();
		/** @var AfrCronLoggerClass $oCronLogger */
		$oCronLogger = Afr::app()->container()->get(AfrCronLoggerInterface::class);
		AfrCronJobDaemon::make($oCronLogger, $snWorkerValue)->run();
		$this->aExtraCollected = [$oCronLogger, $snWorkerValue,__FUNCTION__];

		return (int)ceil(microtime(true) - $this->oRequest->getServerParam('REQUEST_TIME_FLOAT', time())); //number of seconds
	}

}