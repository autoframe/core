<?php

use Autoframe\Core\Event\AfrEvent;

//AfrEvent::$mMemoryUsage = 99; //there is a 1% chance to collect memory info for each dispatched event
//AfrEvent::$mMemoryUsage = true; //collect memory info for each dispatched event
AfrEvent::$mMemoryUsage = false; //do not collect memory info for each dispatched event

return [
	'Connection\AfrDbConnectionManagerClass::getInstance' => [AfrEvent::X_ARGS => false, AfrEvent::X_TRACE => 4],
	'Some.Last.Event' => [AfrEvent::X_ARGS => true, AfrEvent::X_TRACE => 99],
	'*Wildcard*' => [AfrEvent::X_ARGS => true, AfrEvent::X_TRACE => 0],
];
