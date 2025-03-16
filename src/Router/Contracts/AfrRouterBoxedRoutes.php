<?php

namespace Autoframe\Core\Router\Contracts;

interface AfrRouterBoxedRoutes  extends AfrRouterInterface {
	public function xetRoutesBox(AfrRoutesBoxInterface $oBox = null): ?AfrRoutesBoxInterface;

}