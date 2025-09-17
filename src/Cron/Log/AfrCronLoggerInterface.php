<?php

namespace Autoframe\Core\Cron\Log;

use Autoframe\Core\Cron\Log\Channel\AfrCronLogChannelInterface;
use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonInterface;

interface AfrCronLoggerInterface extends AfrSingletonInterface
{
	public function pushChannel(AfrCronLogChannelInterface $oChannel): void;

	public function resetChannels(): self;


	public function setCommandAliasTenantWorker(string $sFullCommand, string $sFlags, string $sAlias, string $sTenant, bool $bIsWorker, string $sHash = null): void;

	public function log(string $sMessage, bool $bError = false, int $exitCode = null): void;

	public function getFullCommand(): ?string;
	public function getFlags(): ?string;
	public function getAlias(): ?string;
	public function getHash(): ?string;
	public function getTenant(): ?string;
	public function isWorker(): ?bool;
	public function getMessage(): string;
	public function isError(): bool;
	public function getExitCode(): ?int;
	public function getLogTs(): int;
}