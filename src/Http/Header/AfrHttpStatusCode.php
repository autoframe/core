<?php

namespace Autoframe\Core\Http\Header;

use Autoframe\Core\Afr\Afr;
use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;
use Autoframe\Core\Env\Exception\AfrEnvException;
use Autoframe\Core\Event\AfrEvent;
use Autoframe\Core\Event\Exception\AfrEventException;
use Autoframe\Core\Http\Header\Exception\AfrHttpHeaderException;

class AfrHttpStatusCode extends AfrSingletonAbstractClass implements AfrHttpStatusCodeInterface
{
	public static string $sTemplate = __DIR__ . DIRECTORY_SEPARATOR . '4xx.html';
	public static string $sTemplateAfr = __DIR__ . DIRECTORY_SEPARATOR . 'afr.html';

	/**
	 * H status html.
	 * @throws AfrEnvException
	 */
	public function hStatusHtml(
		int    $iStatus,
		string $sTitle,
		string $sTxt,
		string $sUrl = null,
		string $sPre = null
	): string
	{
		$sUrl ??= ($_SERVER['REQUEST_URI'] ?? null);
		if ($sUrl !== null) $sUrl = "<strong>$sUrl</strong><br>";
		if ($sPre === null && Afr::app() && Afr::app()->env()->isDevOrDebug()) {
			$sPre = print_r(array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 1), true);
			if (function_exists('opcache_get_status')) {
				$sPre .= "\n\nOPCACHE_GET_STATUS\n" . print_r(opcache_get_status(true), true);
			}
		}
		if ($sPre !== null) $sPre = "<pre>$sPre</pre>";
		$sTemplate = @file_get_contents(self::$sTemplate);
		return str_replace(
			['{{status}}', '{{title}}', '{{url}}', '{{txt}}', '{{pre}}'],
			[(string)$iStatus, $sTitle, $sUrl, $sTxt, $sPre],
			$sTemplate ?: '<hr> {{status}} {{title}} <hr> {{url}} <hr> {{txt}} <hr> {{pre}} <hr>'
		);
	}

	/**
	 * H500 config.
	 */
	public static function h500Config(string $sInfo = null, string $sH2 = null): void
	{
		AfrEvent::dispatchEvent();
		@http_response_code(418);
		die(str_replace(
			['{{V}}', '{{info}}', '{{sH2}}'],
			[Afr::V, $sInfo, $sH2 ?? 'We ❤️ to ⚙️ &amp; 🔨 with PHP',],
			file_get_contents(self::$sTemplateAfr)
		));
	}

	/**
	 * H status header and html.
	 * @throws AfrHttpHeaderException
	 * @throws AfrEventException
	 * @throws AfrEnvException
	 * @throws AfrContainerException
	 */
	public function hStatusHeaderAndHtml(
		int    $iStatus,
		string $sTitle = null,
		string $sTxt = null,
		string $sUrl = null,
		string $sPre = null
	): string
	{
		AfrHttpHeader::getInstance()->setHttpResponseCode($iStatus);
		$sTitle ??= self::STATUS[$iStatus] ?? 'Status ' . $iStatus;
		$sTxt ??= self::DESCRIPTION[$iStatus] ?? 'HTTP ' . $iStatus . ' CODE';
		return $this->hStatusHtml($iStatus, $sTitle, $sTxt, $sUrl, $sPre);
	}
}
