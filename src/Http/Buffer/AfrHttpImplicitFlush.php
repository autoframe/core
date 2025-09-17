<?php
namespace Autoframe\Core\Http\Buffer;

use Autoframe\Core\CliTools\AfrCliHttpDetect;
use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;

class AfrHttpImplicitFlush extends AfrSingletonAbstractClass
{
	protected ?bool $bSetHttpImplicitFlush = null;

	/**
	 * Enable streaming-friendly output.
	 *
	 * @param array{
	 *   contentType?: string,          // e.g. 'text/plain; charset=UTF-8' or 'text/event-stream'
	 *   sendNoCacheHeaders?: bool,     // default true
	 *   sendNoTransform?: bool,        // default true
	 *   disableProxyBufferHeader?: bool// send X-Accel-Buffering: no (helps only for NGINX proxy)
	 * } $opts
	 * @return bool|null Returns true if enabled, false if CLI, null if not set
	 */
	public function setHttpImplicitFlush(array $opts = []): bool
	{
		if ($this->bSetHttpImplicitFlush !== null) {
			return $this->bSetHttpImplicitFlush;
		}

		if (AfrCliHttpDetect::isCli()) {
			// Not an HTTP request; nothing to do.
			return $this->bSetHttpImplicitFlush = false;
		}

		$contentType            = $opts['contentType']            ?? 'text/plain; charset=UTF-8';
		$sendNoCacheHeaders     = $opts['sendNoCacheHeaders']     ?? true;
		$sendNoTransform        = $opts['sendNoTransform']        ?? true;
		$disableProxyBufferHdr  = $opts['disableProxyBufferHeader'] ?? true;

		// If any output already started, we cannot set headers/reliably change INI.
		if (headers_sent()) {
			// We can still try to end buffers/flush, but signal partial success.
			$this->tearDownPhpBuffers();
			ob_implicit_flush(true);
			$this->initialKick();
			return $this->bSetHttpImplicitFlush = true;
		}

		// Headers oriented for streaming
		header('Content-Type: ' . $contentType);
		if ($sendNoCacheHeaders) {
			header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
			header('Pragma: no-cache');
			header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
		}
		if ($sendNoTransform) {
			// Discourage intermediaries from recompressing/re-buffering
			header('Cache-Control: no-transform', false);
		}
		if ($disableProxyBufferHdr) {
			// Helps only when NGINX is acting as an HTTP proxy (NOT fastcgi)
			header('X-Accel-Buffering: no');
		}
		// Make sure no Content-Length survives (chunked encoding preferred)
		header_remove('Content-Length');

		// PHP runtime toggles (must be before any output)
		@ini_set('output_buffering', '0'); // 'off' also OK; ensure it's not a non-zero size
		@ini_set('zlib.output_compression', '0');

		// Tear down all PHP OB levels (may include gzip handlers)
		$this->tearDownPhpBuffers();

		// Enable implicit flush at engine level
		ob_implicit_flush(true);

		// Keep the script alive for long streams
		@set_time_limit(0);

		// Push an initial chunk to nudge proxies/clients
		$this->initialKick();

		return $this->bSetHttpImplicitFlush = true;
	}

	/**
	 * A pragmatic "are we set" indicator.
	 * Returns true if we have explicitly enabled implicit flush in this instance.
	 */
	public function readImplicitFlushState(): ?bool
	{
		return $this->bSetHttpImplicitFlush;
	}

	/**
	 * End all output buffers safely.
	 */
	protected function tearDownPhpBuffers(): void
	{
		while (ob_get_level() > 0) {
			// Some handlers may refuse; suppress warnings, and bail if we stop making progress
			$levelBefore = ob_get_level();
			@ob_end_flush();
			if (ob_get_level() >= $levelBefore) {
				// Try clean if flush failed
				@ob_end_clean();
				if (ob_get_level() >= $levelBefore) {
					break; // can't reduce further
				}
			}
		}
		// As belt-and-suspenders, also call flush()
		@flush();
	}

	/**
	 * Send a small padding to trigger chunked transfer to client.
	 */
	protected function initialKick(): void
	{
		// 8KB is a safe nudge for many proxies/browsers
		echo str_pad('', 8192);
		@flush();
	}
}
