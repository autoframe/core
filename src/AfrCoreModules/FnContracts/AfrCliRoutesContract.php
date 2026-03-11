<?php

namespace Autoframe\Core\AfrCoreModules\FnContracts;

use Autoframe\Core\Http\Request\AfrCliConstantsInterface;
use Autoframe\Core\Http\Request\AfrRequestInterface;


interface AfrCliRoutesContract extends AfrCliConstantsInterface {
	//OPTIMISE LOADING: INCLUDE CLI_FILENAME that returns an array with 1-3 subtypes:

	public function registerCliRoutes(AfrRequestInterface $oRequest = null,array $aFilterOnly = []):int;
	public function getCliRoutes():?array;

}