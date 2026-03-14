*PHP Interface to concrete class mapper, composer vendor path,  Autoframe Framework*

Namespace:
- Autoframe\Core\InterfaceToConcrete

Classes:
- class AfrInterfaceToConcreteClass implements AfrInterfaceToConcreteInterface
  - configurator and caller for AfrMultiClassMapper
- class AfrMultiClassMapper
  - magic
- class AfrVendorPath 
  - will detect the vendor dir and read the psr4, psr0 and class-maps

---

# AfrInterfaceToConcreteClass
```php
	$oAfrConfigWiredPaths = new AfrInterfaceToConcreteClass(
	  $sEnv, //'DEV'/ 'PRODUCTION'/ 'STAGING'/ 'DEBUG'
	  $aEnvSettings [], //overwrite profile settings
	  $aExtraPaths = [] //all compose paths are covered
	  );
	$oAfrConfigWiredPaths->getClassInterfaceToConcrete();
	//OR STATIC CALL AFTER INSTANTIATING: 
	AfrInterfaceToConcreteClass::$oInstance->getClassInterfaceToConcrete();
```

---

# AfrToConcreteStrategiesClass
```php
    $sRuleName = 'CustomRule';
    $obj = \Autoframe\Core\InterfaceToConcrete\AfrToConcreteStrategiesClass::getLatestInstance();
    //add a custom strategy
    $sCustomStrategy = 'customStrategy';
    $obj->addStrategy($sCustomStrategy, function (
        AfrToConcreteStrategiesInterface $oStrategiesInterface,
        array                            $aMap
    ) {
        //$aMap = ['ns1\\concreteInstantiable' => true, 'ns2\\concreteSingleton' => 2];
        if ($oStrategiesInterface->getNotConcreteFQCN() === 'extendClosureFn') {
            //match something
            if (isset($aMap['concreteSingleton'])) unset($aMap['concreteSingleton']);
        } else {
            $aMap = [];
        }
        return $aMap;
    });
    //set some custom rules
    $obj->addPriorityRules($sRuleName, [
        $sCustomStrategy, //custom
        AfrToConcreteStrategiesClass::StrategyClosureFn, //multiple
        AfrToConcreteStrategiesClass::StrategyContextBound, //single
        AfrToConcreteStrategiesClass::StrategyContextNamespaceFilterArr, //multiple
        AfrToConcreteStrategiesClass::StrategyContextHttpRequestUriRegex, //multiple
        AfrToConcreteStrategiesClass::StrategyGetDeclaredClasses, //multiple
        AfrToConcreteStrategiesClass::StrategyOtherNamespaceThanNotInstantiable, //multiple
        AfrToConcreteStrategiesClass::StrategyProjectComposerPsrNamespaces, //multiple
        AfrToConcreteStrategiesClass::StrategyFirstFoundWithWarning,  //single
        //    AfrToConcreteStrategiesClass::StrategyFirstFoundWithoutWarning,  //single
        //    AfrToConcreteStrategiesClass::StrategyShuffle,  //single
        //    AfrToConcreteStrategiesClass::StrategyFail,  //empty
    ])->setPriorityRule($sRuleName);

    $aMap = ['ns1\\concreteInstantiable' => true, 'ns2\\concreteSingleton' => 2];
    $sResplvedClass = $obj->resolveMap(
        $aMap,
        'ns\\notConcrete',
        $bCache = false
    ); 

    // OR
    $sResplvedClass = $obj->resolveInterfaceToConcrete(
        'ns\\notConcrete',
         $oAfrInterfaceToConcreteInterface, // \Autoframe\Core\InterfaceToConcrete\AfrInterfaceToConcreteInterface
    $bCache = false
    );
    $sResplvedClass = '1|ns1\\concreteInstantiable'; // new ns1\\concreteInstantiable(...);
    $sResplvedClass = '2|ns2\\concreteSingleton'; //singleton::getInstance();
    $sResplvedClass = '0|ns\\notConcrete'; //fail
```
