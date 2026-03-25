<?php

namespace Autoframe\Core\Tenant;

use Autoframe\Core\Afr\Afr;
use Autoframe\Core\Http\Request\AfrCliInvokeRoute;

class AfrPrintTenantNameInCli implements AfrCliInvokeRoute
{

	public function cliInvoke()
	{
		echo implode(
			PHP_EOL, array_merge(
				['Afr::app()::getAllTenants()'], array_keys(Afr::app()::getAllTenants()), [PHP_EOL]
			)
		);
	}
}