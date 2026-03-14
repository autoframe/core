<?php

namespace Autoframe\Core\Cron\Log;

use Autoframe\Core\Cron\Log\Channel\AfrCronLogChannelInterface;
use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonInterface;

interface AfrCronLoggerInterface extends AfrSingletonInterface
{
	/**
	 * Push channel.
	 */
	public function pushChannel(AfrCronLogChannelInterface $oChannel): void;

	/**
	 * Reset channels.
	 */
	public function resetChannels(): self;


	/**
	 * Set command alias tenant worker.
	 */
	public function setCommandAliasTenantWorker(string $sFullCommand, string $sFlags, string $sAlias, string $sTenant, bool $bIsWorker, string $sHash = null): void;

	/**
	 * Log.
	 */
	public function log(string $sMessage, bool $bError = false, int $exitCode = null): void;
	/**
	 * Log queue.
	 */
	public function logQueue(string $sMessage, bool $bError = false, int $exitCode = null): void;

	/**
	 * Get full command.
	 */
	public function getFullCommand(): ?string;
	/**
	 * Get flags.
	 */
	public function getFlags(): ?string;
	/**
	 * Get alias.
	 */
	public function getAlias(): ?string;
	/**
	 * Get hash.
	 */
	public function getHash(): ?string;
	/**
	 * Get tenant.
	 */
	public function getTenant(): ?string;
	/**
	 * Is worker.
	 */
	public function isWorker(): ?bool;
	/**
	 * Get message.
	 */
	public function getMessage(): string;
	/**
	 * Is error.
	 */
	public function isError(): bool;
	/**
	 * Get exit code.
	 */
	public function getExitCode(): ?int;
	/**
	 * Get log ts.
	 */
	public function getLogTs(): int;
}
