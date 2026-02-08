<?php
declare(strict_types=1);

namespace Autoframe\Core\ModuleBox\Example;

class AfrSmtpEmailSenderClass implements AfrEmailSenderInterface
{
	public function sendEmail(string $to, string $subject, string $body): void
	{
		// Example implementation – in real life you would inject a mailer here.
		// For now, just a stub.
		// mail($to, $subject, $body); // or use a library via DI.
	}

	public function attachFunctionalityEffectiveRunTimeParameters(array $aEffectiveConfigs)
	{
		// TODO: Implement attachFunctionalityEffectiveRunTimeParameters() method.
	}
}
