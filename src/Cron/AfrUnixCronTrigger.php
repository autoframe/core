<?php

namespace Autoframe\Core\Cron;

use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;

class AfrUnixCronTrigger extends AfrSingletonAbstractClass
{
	/**
	 * Validates a unix cron eg:  0 8-18/2,23 23-31/4 * 0-5
	 * At minute 0 past every 2nd hour from 8 through 18 and 23 on every 4th day-of-month from 23 through 31 and on every day-of-week from Sunday through Friday
	 *  “★”  any value;  ',' value list separator;   '-' range of values;  '/' step values
	 * More examples @ https://crontab.guru/
	 * @param string $sUnixCronFormat minute[0-59] hour[1-23] day[1-31] month[1-12] weekDay[0-6]
	 * @param array|int|null $now getdate()
	 * @return bool
	 */
	public function triggerUnixCron(string $sUnixCronFormat, $now = null): bool
	{
		if (is_integer($now)) {
			$now = getdate($now);
		} else {
			$now = is_array($now) && isset($now['wday']) ? $now : getdate();
		}

		$job = explode(' ', trim($sUnixCronFormat));
		foreach ($job as $v) {
			if (strlen($v) < 1) {//no empty values
				return false;
			}
		}
		return
			$this->matchTime($job[0], $now['minutes']) &&
			$this->matchTime($job[1], $now['hours']) &&
			$this->matchTime($job[2], $now['mday']) &&
			$this->matchTime($job[3], $now['mon']) &&
			$this->matchTime($job[4], $now['wday']);
	}

	protected function matchTime(string $exprLong, int $current): bool
	{
		if ($exprLong === '*' || $this->compareStringNumberIsInteger($exprLong, $current)) {
			return true;
		}
		$aExpr = strpos($exprLong, ',') !== false ? explode(',', $exprLong) : [$exprLong];

		foreach ($aExpr as $expr) {
			if (strlen($expr) < 1) {
				continue;
			}
			if ($this->compareStringNumberIsInteger($expr, $current)) {
				return true;
			}
			if (strpos($expr, '/') !== false) { //step
				[$base, $step] = explode('/', $expr);
				if (ctype_digit($step)) {//step match
					if($current % (int)$step === 0){
						if ($base === '*' || $this->compareRange($base, $current)) {
							return true;
						}
					}
					elseif (ctype_digit($base) && (int)$base % (int)$step === 0) {
						return true; //always true/false because of math
					}
				}
			} elseif ($this->compareRange($expr, $current)) {
				return true;
			}
		}
		return false;
	}

	protected function compareStringNumberIsInteger(string $expr, int $current): bool
	{
		return is_numeric($expr) && ctype_digit($expr) && $current === (int)$expr;
	}

	protected function compareRange(string $expr, int $current): bool //range
	{
		if (strpos($expr, '-') !== false) {//range
			[$start, $end] = explode('-', $expr);
			return $current >= (int)$start && $current <= (int)$end;
		}
		return false;
	}
}