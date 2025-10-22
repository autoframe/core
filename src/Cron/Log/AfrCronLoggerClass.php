<?php

namespace Autoframe\Core\Cron\Log;

use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;
use Autoframe\Core\Cron\Log\Channel\AfrCronLogChannelLogInlineCli;
use Autoframe\Core\Cron\Log\Channel\AfrCronLogChannelSharedLogBuffer;
use Autoframe\Core\Cron\Log\Channel\AfrCronLogChannelInterface;
use Autoframe\Core\Cron\Log\Channel\AfrCronLogChannelDistinctFile;


class AfrCronLoggerClass extends AfrSingletonAbstractClass implements AfrCronLoggerInterface
{

	/** @var AfrCronLogChannelInterface[] */
	public array $aChannels = [];

	/** @var array AfrCronLogChannelInterface::class */
	public static array $aDefaultFallback = [
		AfrCronLogChannelDistinctFile::class,
		AfrCronLogChannelLogInlineCli::class,
		AfrCronLogChannelSharedLogBuffer::class,
		//	AfrCronLogChannelDoNotLog::class
	];
	protected ?string $sFullCommand = null;
	protected ?string $sAlias = null;
	protected ?string $sFlags = null;
	protected ?string $sTenant = null;
	protected ?string $sHash = null;
	protected ?bool $bWorker = null;

	protected array $aLogQueue = [];

	protected string $sMessage;
	protected bool $bError;
	protected ?int $exitCode;
	protected int $iLogTs;

	public function setCommandAliasTenantWorker(
		string $sFullCommand,
		string $sFlags,
		string $sAlias,
		string $sTenant,
		bool   $bIsWorker,
		string $sHash = null
	): void
	{
		$this->sFullCommand = $sFullCommand;
		$this->sFlags = $sFlags;
		$this->sAlias = $sAlias;
		$this->sTenant = $sTenant;
		$this->bWorker = $bIsWorker;
		$this->sHash = $sHash;
	}

	public function pushChannel(AfrCronLogChannelInterface $oChannel): void
	{
		$this->aChannels[get_class($oChannel)] ??= $oChannel; //avoid duplication of logs
	}

	public function resetChannels(): self
	{
		$this->aChannels = [];
		return $this;
	}

	protected function detectError(string $sMessage, bool &$bError, $exitCode): void
	{
		if($bError) return;
		if(
			$exitCode ||
			(strpos($sMessage, 'Fatal error:') !== false || strpos($sMessage, 'Uncaught Error:') !== false) &&
			(strpos($sMessage, '.php:') !== false || strpos($sMessage, '.php on line ') !== false) ||
			strpos($sMessage, '500 Internal Server Error') !== false
		) {
			$bError = true;
		}
	}

	public function log(string $sMessage, bool $bError = false, int $exitCode = null): void
	{
		$this->detectError($sMessage, $bError, $exitCode);
		$this->sMessage = $sMessage;
		$this->bError = $bError;
		$this->exitCode = $exitCode;
		$this->iLogTs = time();

		if (empty($this->aChannels)) {
			/** @var AfrCronLogChannelInterface $sFQCN */
			foreach (static::$aDefaultFallback as $sFQCN) {
				$this->pushChannel($sFQCN::getInstance());
			}
		}
		foreach ($this->aChannels as $oChannel) {
			$oChannel->log($this);
		}
		while(!empty($this->aLogQueue)) {
			$aExtraLog = array_shift($this->aLogQueue);
			$this->log(...$aExtraLog);
		}
	}
	public function logQueue(string $sMessage, bool $bError = false, int $exitCode = null): void
	{
		$this->aLogQueue[] = [$sMessage, $bError, $exitCode];
	}


	public function getFullCommand(): ?string
	{
		return $this->sFullCommand;
	}

	public function getFlags(): ?string
	{
		return $this->sFlags;
	}

	public function getAlias(): ?string
	{
		return $this->sAlias;
	}

	public function getTenant(): ?string
	{
		return $this->sTenant;
	}

	public function isWorker(): ?bool
	{
		return $this->bWorker;
	}

	public function getMessage(): string
	{
		return $this->sMessage;
	}

	public function isError(): bool
	{
		return $this->bError;
	}

	public function getExitCode(): ?int
	{
		return $this->exitCode;
	}

	public function getLogTs(): int
	{
		return $this->iLogTs;
	}

	public function getHash(): ?string
	{
		return $this->sHash;
	}
}