<?php

namespace Autoframe\Core\Router\Contracts;

interface AfrRouterHttpInterface extends AfrRouterInterface {

	/**
	 * This will be populated from the executed route and used into the view
	 * @return array|null
	 */
	public function getViewArgs(): ?array;

}