<?php

namespace Autoframe\Core\Cron\Log\Channel;

use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;
use Autoframe\Core\Cron\Log\AfrCronLoggerInterface;

class AfrCronLogChannelDoNotLog extends AfrSingletonAbstractClass implements AfrCronLogChannelInterface
{
	public function log(AfrCronLoggerInterface $oData): void
	{
	}

	public function cleanupGcOlderThan_N_Months(float $fMonths = null, bool $bForce = false): ?bool
	{
		return true;
	}
}