<?php

namespace Unit\ModuleBox;

use Autoframe\Core\ModuleBox\AfrModuleBoxClass;
use Autoframe\Core\ModuleBox\AfrModuleConstantsInterface;
use Autoframe\Core\ModuleBox\Exception\AfrModuleException;
use PHPUnit\Framework\TestCase;

class ModuleBoxTest extends TestCase
{
	public static array $aModulesRegX = [];
	public static array $aModulesReg = [
		//	ModuleBoxTest::class, //decomenteaza pentru eroare de clase care nu implementeaza mod interface
		TestAxR::class,
		//	TestAxEnd::class,
		TestBaseL0Ext3B::class,
		TestBaseL0ExtRep4A::class,
		TestBaseL0ExtRep4B::class,
		TestBaseL0Rep2A::class,
		TestBaseL0Rep2B::class,
		TestBaseL0Rep2x::class,
		//	TestBaseLoExt3A::class,
		//	TestBxEnd::class,
		TestChL0A::class,
		TestChL0B::class,
		TestChL1ExtA::class,
		TestChL2RepChL1::class,
		TestChL3ReplChL2ExtChL0B::class,
		TestExtL1Ext6A::class,
		TestExtL1Ext6B::class,
		TestExtL2Fin6B::class,
		TestRepL1Fin::class,
		TestRepL1Rep5AFin::class,
		TestRepL1Rep5B::class,
		TestRepL2Fin5A::class,
		TestRepL2Fin5N::class,
		TestReplL1RepExt7A4A4B::class,
		TestSelfL0::class,
		TestSelfL1RepExt::class,
		TestUnrefereedMod1::class,
	];

	/**
	 * @test
	 */
	public function testBuildGraphAutoRegisterMissingDependencyModules()
	{
		echo __CLASS__ . '->' . __FUNCTION__ . PHP_EOL;

		AfrModuleBoxClass::$bDebug = true;

		AfrModuleBoxClass::getInstance()->registerModuleFQCN(TestBxEnd::class);
		$aModulesToLoad = self::$aModulesReg;
		shuffle($aModulesToLoad);
		foreach ($aModulesToLoad as $module) {
			AfrModuleBoxClass::getInstance()->registerModuleFQCN($module);
		}


		$aList = AfrModuleBoxClass::getInstance()->getModulesEffectiveConfigsList();
		$this->assertSame(true, isset($aList[TestBaseLoExt3A::class]));
		$this->assertSame(true, isset($aList[TestAxEnd::class]));
		$this->assertSame(true, isset($aList[TestBxEnd::class]));
		AfrModuleBoxClass::$bDebug = false;

	}


	/**
	 * @test
	 */
	public function testModuleDoesNotImplementModInterface()
	{
		echo __CLASS__ . '->' . __FUNCTION__ . PHP_EOL;
		$oBox = AfrModuleBoxClass::getInstance();
		$oBox->registerModuleFQCN(TestBxEnd::class);

		try {
			$oBox->registerModuleFQCN(self::class);
		} catch (\Throwable $e) {
		}
		$this->assertSame(true, ($e ?? null) instanceof AfrModuleException);

	}

	/**
	 * @test
	 */
	public function testFunctionalityEatUnreferencedSimpleInstance()
	{
		echo __CLASS__ . '->' . __FUNCTION__ . PHP_EOL;
		AfrModuleBoxClass::$bDebug = false;
		$oBox = AfrModuleBoxClass::getInstance();
		$oBox->registerModuleFQCN(TestUnrefereedMod1::class);

		/** @var TestFniBridgeStuff $omTestFnx */
		$omTestFnx = $oBox->resolveFunctionalityByModuleFQCN(TestFniEat::class, TestUnrefereedMod1::class);
		//	print_r($oBox->getModulesEffectiveConfigsList()[TestUnrefereedMod1::class]);

		//	$this->assertSame('x', get_class($omTestFnxBridgeStuff));
		$this->assertSame(true, $omTestFnx instanceof TestFnxEat);
		$this->assertSame(TestFnxEat::class . '~eatSome:2;5;9', $omTestFnx->getTested());

	}


	/** @test */
	public function testFunctionalityBridgingOverAReplaceModule()
	{
		echo __CLASS__ . '->' . __FUNCTION__ . PHP_EOL;
		$oBox = AfrModuleBoxClass::getInstance();
		$oBox->registerModuleFQCN(TestBxEnd::class);//loads most part of the tested chain

		// TestAxR repalces TestAxEnd
		/** @var TestFniBridgeStuff $oRqRepalced */
		$oRqOriginalButGotRepalced = $oBox->resolveFunctionalityByModuleFQCN(TestFniBridgeStuff::class, TestAxEnd::class);
		$oRqRepalced = $oBox->resolveFunctionalityByModuleFQCN(TestFniBridgeStuff::class, TestAxR::class);
		$oExtended = $oBox->resolveFunctionalityByModuleFQCN(TestFniBridgeStuff::class, TestBxEnd::class);
		$this->assertSame($oRqOriginalButGotRepalced, $oRqRepalced);
		$this->assertSame(TestFnxBridgeStuff::class . '~bridgeAction:TestAxR;ReplacerOfTestAxEnd', $oRqOriginalButGotRepalced->getTested());
		$this->assertSame(false, spl_object_id($oRqOriginalButGotRepalced) === spl_object_id($oExtended));
		//Ax is repalced by AxR and extended BxEnd
	//	print_r($oBox->getFunctionalityEffectiveConfig($oExtended));
		$this->assertSame(TestFnxBridgeStuff::class . '~bridgeAction:TestAx&TestAxEnd', $oExtended->getTested());



		//forcing a new wrap key and mergind the run time settings here:
		$oBox->registerModuleFQCN(TestAxR::class, [AfrModuleConstantsInterface::aFunctionalities => [ //augument settings:
			TestFniBridgeStuff::class => [
				AfrModuleConstantsInterface::sBridgeFunctionalityOnCommonInstanceKey => 'BridgeStuffAxR_' . __FUNCTION__,//force new wrap key
				AfrModuleConstantsInterface::anFunctionalitySettings => ['RL99' => 'Augumented'],
			]
		]]);
		$nRepalcedAugumented = $oBox->resolveFunctionalityByModuleFQCN(TestFniBridgeStuff::class, TestAxEnd::class);
		$this->assertSame(false, spl_object_id($oRqOriginalButGotRepalced) === spl_object_id($nRepalcedAugumented));
		$this->assertSame(TestFnxBridgeStuff::class . '~bridgeAction:TestAxR;Augumented', $nRepalcedAugumented->getTested());


		$oBox->registerModuleFQCN(TestExtL1Ext6A::class);
		$oBox->registerModuleFQCN(TestUnrefereedMod1::class);
		$oBridgeExt1 = $oBox->resolveFunctionalityByModuleFQCN(TestFniBridgeStuff::class, TestExtL1Ext6A::class);
		$oBridgeUnr = $oBox->resolveFunctionalityByModuleFQCN(TestFniBridgeStuff::class, TestUnrefereedMod1::class);
	//	print_r($oBox->getFunctionalityEffectiveConfig($oBridgeUCh_));
		$this->assertSame($oBridgeExt1, $oBridgeUnr);
		$this->assertSame(TestFnxBridgeStuffTwo::class . '~bridgeAction:Ext-L16A_ExtL16A_UnrefereedMod1', $oBridgeExt1->getTested());
		//$this->assertSame($oBridgeUCh_, $oBridgeUCh__);
	}



	/** @test */
	public function testFunctionalityMergeSettings()
	{
		echo __CLASS__ . '->' . __FUNCTION__ . PHP_EOL;
		$oBox = AfrModuleBoxClass::getInstance();
		$oBox->registerModuleFQCN(TestExtL2Fin6B::class);

		/** @var TestFniEat $oL2 */
		$oL2 = $oBox->resolveFunctionalityByModuleFQCN(TestFniEat::class, TestExtL2Fin6B::class);
		$oL0 = $oBox->resolveFunctionalityByModuleFQCN(TestFniEat::class, TestBaseLoExt3A::class);
//			print_r($oBox->getFunctionalityEffectiveConfig($oL2));print_r($oBox->getFunctionalityEffectiveConfig($oL1));

		$aS2 = $oBox->getFunctionalityEffectiveConfig($oL2)[AfrModuleConstantsInterface::anFunctionalitySettings];
		$aS0 = $oBox->getFunctionalityEffectiveConfig($oL0)[AfrModuleConstantsInterface::anFunctionalitySettings];
		$this->assertSame('A,7,8,D,E', implode(',',$aS2));
		$this->assertSame('6,7,8,9', implode(',',$aS0));

		$oBox->registerModuleFQCN(TestExtL2Fin6B::class, [AfrModuleConstantsInterface::aFunctionalities => [ //augument settings:
			TestFniEat::class => [
				AfrModuleConstantsInterface::bMergeFunctionalityIntKeys => true,

			]
		]]);
		$oL22 = $oBox->resolveFunctionalityByModuleFQCN(TestFniEat::class, TestExtL2Fin6B::class);
		$oL0 = $oBox->resolveFunctionalityByModuleFQCN(TestFniEat::class, TestBaseLoExt3A::class);

		$aS2 = $oBox->getFunctionalityEffectiveConfig($oL22)[AfrModuleConstantsInterface::anFunctionalitySettings];
		$aS0 = $oBox->getFunctionalityEffectiveConfig($oL0)[AfrModuleConstantsInterface::anFunctionalitySettings];
		//print_r($oBox->getFunctionalityEffectiveConfig($oL22));print_r($oBox->getFunctionalityEffectiveConfig($oL1));

		$this->assertSame('6,7,8,9,A,D,E', implode(',',$aS2));
		$this->assertSame('6,7,8,9', implode(',',$aS0));



	}



	/** @test */
	public function testFunctionalitySingletonSleepAndWrapReverseEngineerInterface()
	{
		echo __CLASS__ . '->' . __FUNCTION__ . PHP_EOL;
		$oBox = AfrModuleBoxClass::getInstance();
		$oBox->registerModuleFQCN(TestRepL2Fin5N::class);
		$oBox->registerModuleFQCN(TestSelfL1RepExt::class);
		$oBox->registerModuleFQCN(TestUnrefereedMod1::class);
		$oBox->registerModuleFQCN(TestChL0B::class);

		//getFunctionalityWrapReverseEngineerInterface on TestFniSleep with TestFnxSleep
		/** @var TestFnxSleep $oS0 */
		$oS0 = $oBox->resolveFunctionalityByModuleFQCN(TestFnxSleep::class, TestSelfL0::class);
		$oS1 = $oBox->resolveFunctionalityByModuleFQCN(TestFniSleep::class, TestSelfL1RepExt::class);
		$oRx = $oBox->resolveFunctionalityByModuleFQCN(TestFnxSleep::class, TestRepL2Fin5N::class);
		$oUx = $oBox->resolveFunctionalityByModuleFQCN(TestFniSleep::class, TestUnrefereedMod1::class);

		$oTwo = $oBox->resolveFunctionalityByModuleFQCN(TestFniSleep::class, TestChL0B::class);

		$sTested = TestFnxSleep::class.'~sleepMinutes:22';
		$sTested2 = TestFnxSleepTwo::class.'~sleepMinutes:27';
		$this->assertSame($oS0, $oS1);
		$this->assertSame($oS0, $oRx);
		$this->assertSame($oUx,$oS0);
	//	print_r($oBox->getFunctionalityEffectiveConfig($oUx));
	//	print_r($oBox->getModuleEffectiveConfigs(TestUnrefereedMod1::class,true));
		$this->assertSame(true,$oTwo instanceof TestFnxSleepTwo);
		//$this->assertNotSame($oUx,$oTwo);
		$this->assertSame( $sTested, $oS0->getTested());
		$this->assertSame( $sTested, $oS1->getTested());
		$this->assertSame( $sTested, $oRx->getTested());
		$this->assertSame( $sTested, $oUx->getTested());
		$this->assertSame( $sTested2, $oTwo->getTested());
	}


	/** @test */
	public function testMultipleInterfacesImplementTheSameConcreteInSameModule()
	{
		echo __CLASS__ . '->' . __FUNCTION__ . PHP_EOL;
		$oBox = AfrModuleBoxClass::getInstance();
		$oBox->registerModuleFQCN(TestReplL1RepExt7A4A4B::class);

		$oPie = $oBox->resolveFunctionalityByModuleFQCN(TestFniEatPie::class, TestReplL1RepExt7A4A4B::class);
		$oEat = $oBox->resolveFunctionalityByModuleFQCN(TestFniEat::class, TestReplL1RepExt7A4A4B::class);

		$this->assertSame(true,$oPie instanceof TestFniEat);
		$this->assertSame(true,$oEat instanceof TestFniEat);
		$this->assertSame($oPie, $oEat);
		$this->assertSame( TestFnxEatPie::class.'~eatSome:eatPie:Blue~Red', $oPie->getTested());

	}


}