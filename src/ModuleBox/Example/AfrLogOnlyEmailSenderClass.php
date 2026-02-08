<?php
declare(strict_types=1);

namespace Autoframe\Core\ModuleBox\Example;

class AfrLogOnlyEmailSenderClass implements AfrEmailSenderInterface
{
	public function sendEmail(string $to, string $subject, string $body): void
	{
		// Example: log-only sender used by a replacement or extender.
		// In real usage, you would log to a PSR-3 logger.
		// error_log("[Mail] TO=$to SUBJECT=$subject BODY=$body");
	}

	public function attachFunctionalityEffectiveRunTimeParameters(array $aEffectiveConfigs)
	{
		// TODO: Implement attachFunctionalityEffectiveRunTimeParameters() method.
	}
}
