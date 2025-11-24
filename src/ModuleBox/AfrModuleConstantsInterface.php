<?php

namespace Autoframe\Core\ModuleBox;

/**
 * 'disabled'       => bool,
 *    'replaces'       => string|null,
 *    'extends'        => string|null,
 *    'functionalities'=> [ interfaceFqcn => [ 'class'=>..., 'singleton'=>..., 'excluded'=>... ] ],
 *
 *  Example Functionality list structure:
 *  [
 *      SomeInterface::class => [
 *          AfrModuleInterface::FQCN     => ConcreteClass::class,
 *          AfrModuleInterface::bSingleton => true,
 *          AfrModuleInterface::bExcludedFunctionality  => false,
 *      ],
 *  ]
 *
 *  These entries typically mirror what is defined in manifest/config files.
 * */
interface AfrModuleConstantsInterface
{
	const FQCN = 'sClass';
	const bSingleton = 'bSingleton';
	const bExcludedFunctionality = 'bExcludedFunctionality';
	const bDisabledModule = 'bDisabledModule';
	const snModuleReplaces = 'snModuleReplaces';
	const snModuleExtends = 'snModuleExtends';
	const aFunctionalities = 'aFunctionalities';
}