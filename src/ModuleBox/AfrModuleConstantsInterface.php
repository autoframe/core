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
	const sConcreteFQCN = 'sConcreteFQCN';
	const bSingletonFunctionalityWithMergedSettings = 'bSingletonFunctionalityWithMergedSettings';
	const bSingletonFunctionalityForceByModules = 'bSingletonSpawnForceByModules';
	const bExcludedFunctionality = 'bExcludedFunctionality';

	/**
	 * TODO:
	 * Set only for module extenders when a bridge is needed and both modules coexist.
	 * This may be used for multiple extenders of base class, when a mixin is needed.
	 * This is not a pure singleton.
	 * When parent is replaced, then we use the parent replacer functionality instance, we use the replacer key
	 * When parent is bDisabledModule || or functionality is excluded, we use name the key as the parent, regardless ??
	 * What do I do with the config? PARENT|Extender1|Extender2
	 * What do I do with the config? PARENT|Extender1|Extender2
	 * What do I do with the config? PARENT|Extender1|Extender2
	 * Parent seems to be the norm, so we can ignore the OFF flags?
	 */
	const bBridgeFunctionalityInstanceWithParent = 'bBridgeFunctionalityInstanceWithParent';
	const bDisabledModule = 'bDisabledModule';
	const iResolvableModule = 'iResolvableModule'; //0 = false; 1 = true resolvable by this module; 2 = resolvable by extender
	const snModuleReplaces = 'snModuleReplaces';
	const snModuleExtends = 'snModuleExtends';
	const aFunctionalities = 'aFunctionalities';
	const aFunctionalitiesModuleParents = 'aModuleParents';
}