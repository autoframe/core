<?php
declare(strict_types=1);

namespace Autoframe\Core\ModuleBox\Example;

use Autoframe\Core\ModuleBox\AfrFunctionalityInterface;

interface AfrEmailSenderInterface extends AfrFunctionalityInterface
{
	public function sendEmail(string $to, string $subject, string $body): void;
}
