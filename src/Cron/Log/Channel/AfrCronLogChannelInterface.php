<?php

namespace Autoframe\Core\Cron\Log\Channel;

use Autoframe\Core\Cron\Log\AfrCronLoggerInterface;
use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonInterface;

interface AfrCronLogChannelInterface extends AfrSingletonInterface
{
	const CLEAN_LOGS_OLDER_THAN_N_MONTHS = 3;
	const GC_ONE_IN_N = 1000;

	/**
	 * Log.
	 */
	public function log(AfrCronLoggerInterface $oData): void;

	/**
	 * Cleanup gc older than n months.
	 */
	public function cleanupGcOlderThan_N_Months(float $fMonths = null, bool $bForce = false): ?bool;
}
