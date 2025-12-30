<?php
declare(strict_types=1);

namespace Autoframe\Core\ModuleBox;

/**
 * Marker/base interface for module functionalities.
 * Concrete functionality interfaces should extend this.
 * Generic marker/base interface
 */


interface AfrFunctionalityInterface extends AfrModuleConstantsInterface
{
	// generic marker/base interface
	// No members here; used for type grouping.

	//todo stocare in cheie
	public function attachParentModuleFQCN(string $sModuleFQCN):self;

	/**
	 * Can have one or more parents in case of singletons
	 * @return array
	 */
	public function getAttachedParentModulesFQCN():array;

	public function attachParentModuleInstance(AfrModuleInterface $oModule);

	/**
	 * @param string $sModuleFQCN
	 * @return mixed
	 * Aici daca am mai multe instante ma va afecta? Instante ale modulului si ale functionalitatilor
	 * Daca vorbesc de login-uri diferite, asta inseamna ca trebuie sa am mai multe config-uri de module sau de functionalitati?
	 *
	 * Cand construiesc modulul, inseamna ca trebuie sa atasez toate functionalitatile?
	 * - Cel mai probabil ca nu, ca nu este lazy!
	 *
	 * Functionalitatea trebuie sa stie despre modul?
	 * - Da si Nu
	 * - Da, ca sa aiba path de file
	 * - Da, CONTEXT
	 * - Da, pentru ca config-ul de functionalitate trebuie aplicat / facut available din modul
	 * - Nu, pentru ca modului ofera paths si array cu config
	 * ---------------
	 *
	 * Func sa aiba swager
	 * RESOLVE FN SA FIE: SAME CONTEXT(CA SI MODULUL DE ORIGINE) => FALLBACK => RESTUL
	 * RESOLVE FN SA FIE CONTERXT BASED CU NUME DE CONTEXT OBLIGATORIU SAU *
	 *
	 * RESOLVE FN SA INTOARCA ARRAY
	 *
	 * SAP:
	 * BAdI lookups instead of hardcoding behavior
	 * RAP handlers injected as dependencies / RAP extension points / Subclassing with REDEFINITION
	 *
	 */
	public function attachFunctionalityEffectiveRunTimeParameters(array $aEffectiveConfigs);


}
