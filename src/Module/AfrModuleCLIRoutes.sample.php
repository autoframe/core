<?php

use Autoframe\Core\CliTools\AfrCliTextColors;
use Autoframe\Core\Router\Contracts\AfrRouterConstantsInterface;

return [
	AfrRouterConstantsInterface::CLI_CRON_JOB_REQUEST => [],
	AfrRouterConstantsInterface::CLI_INLINE => [],
	AfrRouterConstantsInterface::CLI_QA_REQUEST => [
		'sample Dynamic Array Of Closures QA' => function () {
			return
				[
					'Item Dynamic#' . rand(1432, 6464) => function () {
						AfrCliTextColors::getInstance()
							->textAppend("\n\t")
							->colorRed("Some text:\n")
							->textPrint();
						return true;
					},
				];
		}],
	'sample Array Of Closures QA' => [
		'fixed sample' => function () {
			AfrCliTextColors::getInstance()
				->textAppend("\n\t")
				->colorMagenta("something...\n")
				->textPrint();
			return false;
		},
	],
];