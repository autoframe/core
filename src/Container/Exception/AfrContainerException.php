<?php
declare(strict_types=1);

namespace Autoframe\Core\Container\Exception;
use Autoframe\Core\Exception\AfrException;
use Psr\Container\ContainerExceptionInterface;

class AfrContainerException extends AfrException implements ContainerExceptionInterface
{

}