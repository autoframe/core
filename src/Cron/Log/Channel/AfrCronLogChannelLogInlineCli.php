<?php

namespace Autoframe\Core\Cron\Log\Channel;

use Autoframe\Core\CliTools\AfrCliHttpDetect;
use Autoframe\Core\CliTools\AfrCliTextColors;
use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;
use Autoframe\Core\Cron\Log\AfrCronLoggerInterface;
use Autoframe\Core\Http\Buffer\AfrHttpImplicitFlush;
use Autoframe\Core\Tenant\AfrTenant;

class AfrCronLogChannelLogInlineCli extends AfrSingletonAbstractClass implements AfrCronLogChannelInterface
{
	public function log(AfrCronLoggerInterface $oData): void
	{
		$this->somethingPrintedHead($oData);
		$timestamp = date('Y-m-d H:i:sO');
		$type = $oData->isWorker() ? '[WORKER.' . $oData->getHash() . ']' : '[DAEMON.' . $oData->getHash() . ']';
		$logLevel = $oData->isError() ? '[ERROR]' : '[INFO]';
		$message = $oData->getMessage();
		$sText = "》$timestamp $type $logLevel $message\n";

		if (AfrCliHttpDetect::isCli()) {
			if ($oData->isError()) {
				echo chr(7); //ascii bell
			}
			$sMethod = $oData->isError() ? 'colorRed' : 'colorGreen';
			AfrCliTextColors::getInstance()->$sMethod($sText)->styleDefaultAllBgColor()->textPrint();
		} else {
			echo '<div'.($oData->isError() ? ' style="color: red;"':'').'>';
			echo nl2br(htmlentities($sText));
			echo '</div>';
		}
	}
	protected bool $bSomethingPrinted = false;
	protected function somethingPrintedHead(AfrCronLoggerInterface $oData)
	{
		if ($this->bSomethingPrinted) return;
		$this->bSomethingPrinted = true;
		AfrHttpImplicitFlush::getInstance()->setHttpImplicitFlush();

		$sHeader = "\n*** AFR CRON ".($oData->isWorker() ? 'WORKER':'DAEMON')." [".$oData->getHash()."] ***" ;
		$sTenant = "\t\t@" .($oData->getTenant() ?? AfrTenant::AFR_NO_TENANT) . "\n\n" ;
		if(AfrCliHttpDetect::isCli()) {
			AfrCliTextColors::getInstance()->
			styleDefaultAllBgColor()->
			bgBlueLight($sHeader)->
			bgDefault($sTenant)->
			textPrint();
		}
		else{
			echo '<style>html,body{background-color: #1d1d1d; font-size: 12px; font-family: "Lucida Console", "Courier New", monospace;color:#eee;}</style>';
			echo "\n<h1>"(htmlentities($sHeader))."</h1>\n";
			echo '<h2>'(htmlentities($sTenant))."</h2>\n";
		}
	}

	public function cleanupGcOlderThan_N_Months(float $fMonths = null, bool $bForce = false): ?bool
	{
		return true;
	}
}