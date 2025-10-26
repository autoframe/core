<?php

use Autoframe\Core\InterfaceToConcrete\AfrToConcreteStrategiesInterface;
use Autoframe\Core\InterfaceToConcrete\AfrToConcreteStrategiesClass;
use Autoframe\Core\InterfaceToConcrete\AfrInterfaceToConcreteClass;

return function (AfrToConcreteStrategiesInterface $obj) {
	return;
	// MORE INFO IN CLASS
	// AfrToConcreteStrategiesClassTest

	$sPriRule = "newRule";
	$sCustomStrategy = "customStrategy";

	//strategies:
	$obj->addStrategy($sCustomStrategy, function (
		AfrToConcreteStrategiesInterface $oStrategiesInterface,
		array                            $aMap
	) {
		if ($oStrategiesInterface->getNotConcreteFQCN() === "extendClosureFn") {
			//fake match something
			if (isset($aMap["clFake1"])) unset($aMap["clFake1"]);
		} else {
			$aMap = [];
		}
		return $aMap;
	});
	$obj->addPriorityRules($sPriRule, [$sCustomStrategy])->setPriorityRule($sPriRule)->setContext("");

	/// custom priority:

	$obj->addPriorityRules($sPriRule, [
		AfrToConcreteStrategiesClass::StrategyFail,
	])->setPriorityRule($sPriRule);

	$obj->extendStrategyStrategyContextNamespaceFilterArr(
		"ns22\\",
		"sContext.a2"
	);

	$obj->setPriorityRule(AfrToConcreteStrategiesClass::PriorityRuleNeverFail);

	$obj->extendStrategyContextBound(
		$sNotConcrete = $sPriRule . "NeverFakeCB",
		$sConcrete = $sPriRule . "NeverConcreteFakeCB",
		$sContext = "context.something"
	);

	$obj->resolveInterfaceToConcrete(
		"notConcrete",
		AfrInterfaceToConcreteClass::getLatestInstance(),
		true
	);

	print_r($obj->getStrategies());

};