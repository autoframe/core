<?php

namespace Autoframe\Core\InterfaceToConcrete;


use Autoframe\Core\ClassDependency\AfrClassDependencyException;
use Autoframe\Core\InterfaceToConcrete\Exception\AfrInterfaceToConcreteException;

/**
 * Copyright BSD-3-Clause / Nistor Alexadru Marius / Auroframe SRL Romania / https://github.com/autoframe
 * This will make a configuration object that contains the paths to be wired:
 *
 * $oAfrConfigWiredPaths = new AfrConfigWiredPaths(['src','vendor']);
 * AfrMultiClassMapper::setAfrConfigWiredPaths($oAfrConfigWiredPaths);
 * AfrMultiClassMapper::xetRegenerateAll(true/false);
 * register_shutdown_function(function(){ print_r(AfrClassDependency::getDependencyInfo());});
 * $aMaps = AfrMultiClassMapper::getInterfaceToConcrete();
 */
interface AfrInterfaceToConcreteInterface
{
    /**
     * Get class interface to concrete.
     * @param string|null $sFilterFQCN
	 * @return array
     * @throws AfrInterfaceToConcreteException
     * @throws AfrClassDependencyException
     */
    public function getClassInterfaceToConcrete(string $sFilterFQCN = null): array;

    /**
     * Get latest instance.
     * @return AfrInterfaceToConcreteInterface|null
     */
    public static function getLatestInstance(): ?AfrInterfaceToConcreteInterface;


	/**
     * Get settings.
	 * @param string|null $sType
	 * @return array|mixed
	 */
    public function getSettings(string $sType = null);

    /**
     * Hash v.
     * @param string $s
     * @return string
     */
    public function hashV(string $s): string;

    /**
     * Get paths.
     * @return array
     */
    public function getPaths(): array;

    /**
     * Get afr to concrete strategies.
     * @return AfrToConcreteStrategiesInterface
     */
    public function getAfrToConcreteStrategies(): AfrToConcreteStrategiesInterface;

    /**
     * Set afr to concrete strategies.
     * @param AfrToConcreteStrategiesInterface $oAfrToConcreteStrategies
     * @return AfrToConcreteStrategiesInterface
     */
    public function setAfrToConcreteStrategies(AfrToConcreteStrategiesInterface $oAfrToConcreteStrategies): AfrToConcreteStrategiesInterface;

    /**
     * Returns: 1|FQCN for instantiable; 2|FQCN for singleton; 0|notConcreteFQCN for fail
     * @param string $sNotConcreteFQCN
     * @param bool $bUseCache
     * @param string|null $sTemporaryContextOverwrite
     * @param string|null $sTemporaryPriorityRuleOverwrite
     * @return string 1|FQCN for instantiable; 2|FQCN for singleton; 0|notConcreteFQCN for fail
     * @throws AfrClassDependencyException
     * @throws AfrInterfaceToConcreteException
     */
    public function resolve(
        string $sNotConcreteFQCN,
        bool   $bUseCache = true,
        string $sTemporaryContextOverwrite = null,
        string $sTemporaryPriorityRuleOverwrite = null
    ): string;

}
