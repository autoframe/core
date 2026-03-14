<?php

namespace Autoframe\Core\Html\Har;

use Autoframe\Core\Exception\AfrException;

class AfrHarChrome
{
	protected array $aHarJson;
	protected string $sDestinationPath;
	protected string $sCrawledHost = '';
	protected array $aList = [];

	/**
	 * Create a new instance.
	 * @throws AfrException
	 */
	public function __construct(
		string $sLocalDestinationPath,
		string $sHarJsonContents = '{}',
		string $sCrawledHost = ''
	)
	{
		$this->aHarJson = json_decode($sHarJsonContents, true);
		if (empty($this->aHarJson['log']['entries'])) {
			throw new AfrException("Log entry HAR is empty");
		}
		$this->setCrawledHost($sCrawledHost);
		$this->initDestinationPath($sLocalDestinationPath);
	}


	/**
	 * @param string $sCrawledHost
	 * @return void
	 */
	protected function setCrawledHost(string $sCrawledHost): void
	{
		if (empty($sCrawledHost) && substr($this->aHarJson['log']['pages'][0]['title'] ?? '', 0, 4) === 'http') {
			$aPathInfo = parse_url($this->aHarJson['log']['pages'][0]['title']);
			$sCrawledHost = $aPathInfo['host']??'';
		}
		$this->sCrawledHost = $sCrawledHost;
	}

	/**
	 * @param string $sDestinationPath
	 * @return void
	 * @throws AfrException
	 */
	protected function initDestinationPath(string $sDestinationPath): void
	{
		$sDestinationPath = rtrim($sDestinationPath, '\/');
		$this->sDestinationPath = $sDestinationPath . ($this->sCrawledHost ? DIRECTORY_SEPARATOR . $this->sCrawledHost : '');
		if (!is_dir($this->sDestinationPath) && !mkdir($this->sDestinationPath, 0777, true)) {
			throw new AfrException("Unable to create directory $this->sDestinationPath");
		}
	}

	/**
	 * Prepare list.
	 */
	public function prepareList(): bool
	{
		foreach ($this->aHarJson['log']['entries'] as $logEntry) {
			if (empty($logEntry['request']['url']) || ($logEntry['request']['method']??'')  !== 'GET') {
				continue;
			}
			if (substr($logEntry['request']['url'], 0, 4) !== 'http') {
				continue;
			}
			$this->aList[] = $logEntry['request']['url'];

		}
		return count($this->aList) > 0;
	}

	/**
	 * Get list.
	 */
	public function getList(): array
	{
		return $this->aList;
	}

	/**
	 * Download list.
	 */
	public function downloadList(): array
	{
		if(empty($this->aList)) {
			$this->prepareList();
		}

		$aResult = [];
		foreach ($this->aList as $sUrl) {
			$aUrlInfo = parse_url($sUrl);
			if($aUrlInfo['host']!==$this->sCrawledHost) {
				continue;
			}
			$sStoreInto = $sStoreInto2 = $this->sDestinationPath . DIRECTORY_SEPARATOR . $aUrlInfo['host'] . $aUrlInfo['path'];
			if (!empty($aUrlInfo['query'])) {
				$sStoreInto2 .= '-_-' . urldecode($aUrlInfo['query']);
			}
			$sDirPath = substr($sStoreInto, 0, -strlen(basename($sStoreInto)));

			$bStoreInto = is_file($sStoreInto);
			$bStoreInto2 = $sStoreInto === $sStoreInto2 ? $bStoreInto : is_file($sStoreInto2);
			if ($bStoreInto && $bStoreInto2) {
				continue;
			}
			if(!$bStoreInto || !$bStoreInto2) {
				if(!is_dir($sDirPath)){
					mkdir($sDirPath, 0755, true);
				}
				set_time_limit(9999);
				sleep(1);
				$sUrlData = file_get_contents($sUrl);
				if(!$bStoreInto){
					$aResult[]=[$sUrl,$sStoreInto];
					$x = file_put_contents($sStoreInto, $sUrlData);
					echo "$sUrl ~~~~ $sStoreInto  ~~~~ $x<hr>\n";

				}
				if(!$bStoreInto2 && $sStoreInto !== $sStoreInto2){
					$aResult[]=[$sUrl,$sStoreInto2];
					$x = file_put_contents($sStoreInto2, $sUrlData);
					echo "$sUrl ~~~~ $sStoreInto2 ~~~~ $x<hr>\n";

				}
			}
		}
		return $aResult;
	}

}
