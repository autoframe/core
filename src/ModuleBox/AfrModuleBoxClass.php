<?php
declare(strict_types=1);

namespace Autoframe\Core\ModuleBox;

use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;


/**
 * Core Module Box implementation
 *
 * - Manages registration of modules and their configs.
 * - Applies disabled / replace / extend / excluded functionality rules.
 * - Resolves modules and functionalities.
 */
class AfrModuleBoxClass extends AfrSingletonAbstractClass implements AfrModuleBoxInterface
{
	use AfrMbhTrait;
}
