<?php

namespace Autoframe\Core\Router\Contracts;

interface AfrRoutesBoxInterface extends AfrRouterConstantsInterface
{


	public function registerHttpRoutes(): int; //nbr of registered routes

	public function registerRestRoutes(): int; //nbr of registered routes

	public function registerCliRoutes(): int; //nbr of registered routes

	public function registerCronRoutes(): int; //nbr of registered routes

	public function getHttpRoutes($sForRequestMethod = 'ANY'): array;

	public function getRestRoutes($sForRequestMethod = 'ANY'): array;

	public function getCliRoutes(): array;
	public function getCliQaRoutes(): array;

	public function getCronRoutes(): array;

}