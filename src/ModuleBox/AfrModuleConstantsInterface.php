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
	const sInterfaceFQCN = 'sInterfaceFQCN';
	const bSingletonSpawn = 'bSingletonSpawn';
	const bExcludedFunctionality = 'bExcludedFunctionality';
	const bDisabledModule = 'bDisabledModule';
	const snModuleReplaces = 'snModuleReplaces';
	const snModuleExtends = 'snModuleExtends';
	const aFunctionalities = 'aFunctionalities';
	const aModuleParents = 'aModuleParents';
}