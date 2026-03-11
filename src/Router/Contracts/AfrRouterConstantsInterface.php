<?php

namespace Autoframe\Core\Router\Contracts;

use Autoframe\Core\Http\Request\AfrCliConstantsInterface;
use Autoframe\Core\Http\Request\AfrHttpConstantsInterface;
use Autoframe\Core\Http\Request\AfrRequestInterface;

interface AfrRouterConstantsInterface extends AfrHttpConstantsInterface, AfrCliConstantsInterface {}