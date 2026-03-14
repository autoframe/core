<?php

namespace Autoframe\Core\Cron\Log\Channel;

use Autoframe\Core\CliTools\AfrCliHttpDetect;
use Autoframe\Core\CliTools\AfrCliTextColors;
use Autoframe\Core\Cron\Log\AfrCronLoggerInterface;
use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;
use Autoframe\Core\Http\Buffer\AfrHttpImplicitFlush;
use Autoframe\Core\Tenant\AfrTenant;

class AfrCronLogChannelSharedLogBuffer extends AfrSingletonAbstractClass implements AfrCronLogChannelInterface
{
	protected AfrCronLogSharedLogBuffer $oAfrCronLogSharedLogBuffer;

	protected function getAfrCronLogSharedLogBuffer(): AfrCronLogSharedLogBuffer
	{
		if (empty($this->oAfrCronLogSharedLogBuffer)) {
			$this->oAfrCronLogSharedLogBuffer = new AfrCronLogSharedLogBuffer();
		}
		return $this->oAfrCronLogSharedLogBuffer;
	}

	/**
	 * Log.
	 */
	public function log(AfrCronLoggerInterface $oData): void
	{
		$sText = implode(' ', [
			'》' . date('Y-m-d H:i:sO'),
			$oData->isWorker() ? '[WORKER.' . $oData->getHash() . ']' : '[DAEMON.' . $oData->getHash() . ']',
			$oData->isError() ? '[ERROR]' : '[INFO]',
			'{' . ($oData->getTenant() ?? AfrTenant::AFR_NO_TENANT) . '}',
			$oData->getMessage() . "\n"
		]);
		$this->getAfrCronLogSharedLogBuffer()->writeLog($sText, $oData->isError());
	}

	/**
	 * Cleanup gc older than n months.
	 */
	public function cleanupGcOlderThan_N_Months(float $fMonths = null, bool $bForce = false): ?bool
	{
		return true;
	}

	/**
	 * View logs.
	 */
	public function viewLogs($iReadTimeout = null, bool $bFlushAfterRead = true): void
	{
		//null timeout is the default
		if (is_bool($iReadTimeout) || empty($iReadTimeout)) {
			$iReadTimeout = null;
		} else {
			$iReadTimeout = intval($iReadTimeout);
			$iReadTimeout = $iReadTimeout > 0 ? $iReadTimeout : null;
		}

		$sHeader = "\n*** AFR CRON LOG LIVE VIEWER ***";
		if (AfrCliHttpDetect::isCli()) {
			$oColors = AfrCliTextColors::getInstance();
			$oColors->
			styleDefaultAllBgColor()->
			bgBlueLight($sHeader)->
			styleDefaultAllBgColor("\n")->
			textPrint();
			while (true) {
				$aLines = $this->getAfrCronLogSharedLogBuffer()->readLog($bFlushAfterRead);
				foreach ($aLines as $line) {
					$aLineFormatters = $this->getLineInfo($line);
					$line = trim(substr($line, 1));
					foreach ($aLineFormatters as $sLineFormatter) {
						$bArgx = null;
						if(strpos($sLineFormatter, '.') !== false) {
							[$sLineFormatter,$bArgx] = explode('.',$sLineFormatter);
						}
						if($bArgx!==null) {
							$oColors->$sLineFormatter((bool)$bArgx);
						}
						else{
							$oColors->$sLineFormatter();
						}
					}
					$oColors->textAppend($line)->styleDefaultAllBgColor("\n")->textPrint();
				}
				sleep($iReadTimeout ?? 5);
			}
		} else {
			AfrHttpImplicitFlush::getInstance()->setHttpImplicitFlush();
			echo '<meta http-equiv="refresh" content="' . ($iReadTimeout ?? 29) . '">';
			echo '<style>html,body{background-color: #1d1d1d; font-size: 12px; font-family: "Lucida Console", "Courier New", monospace;color:#eee;}</style>';
			echo "\n<h1>"(htmlentities($sHeader)) . "</h1>\n";
			$aLines = $this->getAfrCronLogSharedLogBuffer()->readLog(false);
			foreach (array_reverse($aLines) as $line) {
				$bSuccess = (bool)substr($line, 0, 1);
				echo '<p ' . (!$bSuccess ? 'style="color:red;"' : '') . '>';
				echo htmlentities(substr($line, 1));
				echo "</p>\n";
			}
		}


	}

	protected array $aFormatters = [
		'x' => ['styleDefaultAllBgColor', 'styleItalic.1'],//other
		'd' => ['styleDefaultAllBgColor', 'styleBold.1'],//daemon
		'w' => ['styleDefaultAllBgColor', 'colorBlack'],//worker
		'e' => ['colorRed'],//error
		's' => ['colorGreen'],//success
		'bg' => ['bgYellow', 'bgBlue', 'bgMagenta', 'bgCyan', 'bgGrayLight', 'bgYellowLight', 'bgBlueLight', 'bgMagentaLight', 'bgCyanLight', 'bgWhite',],//worker bg list
	];

	protected function getLineInfo(string $line): array
	{
		$d = '[DAEMON.';
		$w = '[WORKER.';

		$bSuccess = (bool)substr($line, 0, 1);
		$bDaemon = strpos($line, $d) !== false;

		$sId = explode($bDaemon ? $d : $w, $line)[1] ?? 'x';
		$sId = explode(']', $sId)[0];

		if (!isset($this->aFormatters[$sId])) { //daemon / worker
			if ($bDaemon) {
				return array_merge($this->aFormatters['d'], $this->aFormatters[$bSuccess ? 's' : 'e']);
			}
			$aFormatters = $this->aFormatters['w'];
			$iBgCount = count($this->aFormatters['bg']);
			$aFormatters[] = $this->aFormatters['bg'][(count($this->aFormatters) - 6) % $iBgCount];
			$this->aFormatters[$sId] = $aFormatters;
		}
		return array_merge($this->aFormatters[$sId], $bSuccess ? [] : $this->aFormatters['e']);

	}

}
