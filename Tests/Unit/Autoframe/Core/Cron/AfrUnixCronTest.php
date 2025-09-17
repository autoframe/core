<?php

namespace Autoframe\Core\Cron;

use PHPUnit\Framework\TestCase;

/**
 * Class AfrUnixCronTest
 * @package Autoframe\Core\Cron
 * This class will test the public method triggerUnixCron from the class AfrUnixCron.
 *
 */
class AfrUnixCronTest extends TestCase
{
	/**
	 * @var AfrUnixCronTrigger
	 */
	private $afrUnixCron;

	protected function setUp(): void
	{
		$this->afrUnixCron = AfrUnixCronTrigger::getInstance();
	}

	/**
	 * @dataProvider triggerUnixCronDataProvider
	 *
	 * This test will simulate the running of a unix cron job.
	 */
	public function testTriggerUnixCron(string $sUnixCronFormat, $now, bool $expected)
	{
		$this->assertEquals($expected, $this->afrUnixCron->triggerUnixCron($sUnixCronFormat, $now), print_r(func_get_args(), true));
	}

	public function triggerUnixCronDataProvider(): array
	{
		// minute[0-59] hour[1-23] day[1-31] month[1-12] weekDay[0-6]
		return [
			["* * * * *", getdate(), true], // Cron expression: every minute of every day
			["0-29,30-59 * * * *", getdate(), true], // Cron expression: every minute of every day
			["1 3/2 17-31 * 1-2", null, false], // A more complex cron expression that never validates
//			["* 8-16/2 * * *", getdate(), false],
			["30-59 * * * *", null, (int)date('i') >= 30], // min
			["* */3 * * *", null, (intval(date('H')) % 3) === 0], // hour
			["* 0-23/2 * * *", null, (intval(date('H')) % 2) === 0], // hour
			["* * 11-25 * *", null, date('d') >= 11 && date('d') <= 25], // day
			["* * * 10,11,12 *", null, date('m') >= 10], // month
			["* * * * 2", null, date('w') == 2], // weekDay
		];
	}

}