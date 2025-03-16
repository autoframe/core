<?php

namespace Autoframe\Core\Module;

use Autoframe\Core\DesignPatterns\ClosureBind\AfrBindAndCallClosureInterface;
use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonInterface;

interface AfrModuleInterface extends AfrBindAndCallClosureInterface,AfrSingletonInterface{
	public function registerModule(array $aImplementingInterfacesFilter = null): array;
	public function getModuleFQCN(): string;
	public function getModuleNameSpace(): string;
	public function getModuleName(): string;
	public function getModuleClassFilePath(): string;
	public function getModuleDirPath(): string;
}