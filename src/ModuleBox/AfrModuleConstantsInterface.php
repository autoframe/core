<?php

namespace Autoframe\Core\ModuleBox;

/**
 *    'bDisabledModule'       => bool,
 *    'snModuleReplaces'       => string|null,
 *    'snModuleExtends'        => string|null,
 *    'aFunctionalities'=> [ interfaceFqcn => [ 'class'=>..., 'singleton'=>..., 'excluded'=>... ] ],
 *
 *  Example Functionality list structure:
 *  [
 *      SomeFunctionalityInterface::class => [
 *          AfrModuleInterface::FQCN     => sConcreteClass::class,
 *          AfrModuleInterface::bSingletonSpawn => true,
 *          AfrModuleInterface::bExcludedFunctionality  => false,
 *      ],
 *  ]
 *
 *  These entries typically mirror what is defined in manifest/config files.
 * */
interface AfrModuleConstantsInterface
{
	//MODULES
	const bDisabledModule = 'bDisabledModule';
	const snModuleReplaces = 'snModuleReplaces';
	const snModuleExtends = 'snModuleExtends';
	const iResolvableModule = 'iResolvableModule'; //0 = false; 1 = true resolvable by this module; 2 = resolvable by extender
	const aFunctionalities = 'aFunctionalities';


	//MODULES[aFunctionalities][funcInterface]
	const aFunctionalitiesRunTimeParameters = 'aFunctionalitiesRunTimeParameters'; // aFunctionalitiesModuleParents
	const sFuncConcreteFQCN = 'sFuncConcreteFQCN';
	const bExcludedFunctionality = 'bExcludedFunctionality';
	const bSingletonFunctionalityWithMergedSettings = 'bSingletonFunctionalityWithMergedSettings';
	const aSingletonFunctionalityForceByModules = 'aSingletonFunctionalityForceByModules';
	const bBridgeFunctionalityInstanceWithParent = 'bBridgeFunctionalityInstanceWithParent';
	const aBridgeFunctionalityForceByModules = 'aBridgeFunctionalityForceByModules';


}