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

	const RESOLVE_NONE = 0;
	const RESOLVE_DIRECT = 1;
	const RESOLVE_REPLACED = 2;


	const aFunctionalities = 'aFunctionalities';

	//MODULES[aFunctionalities][funcInterface]
	const sFuncConcreteFQCN = 'sFuncConcreteFQCN';
	const bExcludedFunctionality = 'bExcludedFunctionality';
	const bSingletonFunctionalityWithMergedSettings = 'bSingletonFunctionalityWithMergedSettings';
	const bMergeFunctionalityIntKeys = 'bMerge⚠️Functionality❗Int❗Keys';
	const bMergeFunctionalityFlushOldConfig = 'bMergeFunctionalityFlushOldConfig';
	const sBridgeFunctionalityOnCommonInstanceKey = 'sBridgeFunctionalityOnCommonInstanceKey';
	const sFunctionalityWrapKey = 'sFunctionalityWrapKey';
	const anFuncSettings = 'anFuncSettings';
	const onFunctionalityApplySettingsClosure = 'onFunctionalityApplySettingsClosure';
	//DEPRECATED: 	const aFunctionalityParentsModInterface = 'aFunctionalityParentsModInterface';

	const AFR_CORE_CONFIG_FILE =
		__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR .
		'AfrCoreModules' . DIRECTORY_SEPARATOR . 'afr.core.modules.config.php';


}