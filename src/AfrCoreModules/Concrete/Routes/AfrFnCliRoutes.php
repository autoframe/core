<?php

namespace Autoframe\Core\AfrCoreModules\Concrete\Routes;

use Autoframe\Core\AfrCoreModules\Reusable\AfrCliRoutesHelper;
use Autoframe\Core\AfrCoreModules\FnContracts\AfrCliRoutesContract;


class AfrFnCliRoutes implements AfrCliRoutesContract
{
	use AfrCliRoutesHelper;
	const SELF_DIR = __DIR__;

}