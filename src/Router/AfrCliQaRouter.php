<?php

namespace Autoframe\Core\Router;

use Autoframe\Core\Afr\Afr;
use Autoframe\Core\CliTools\AfrCliHttpDetect;
use Autoframe\Core\CliTools\AfrCliPromptMenu;
use Autoframe\Core\CliTools\AfrCliTextColors;
use Autoframe\Core\Container\Exception\AfrContainerException;
use Autoframe\Core\Event\AfrEvent;
use Autoframe\Core\Event\Exception\AfrEventException;
use Autoframe\Core\Exception\AfrException;
use \Closure;

class AfrCliQaRouter
{

	const UP_STACK = '🡅';
	public static int $iCliQaDepth = 0;
	public static int $iCliQaHandled = 0;

	protected static array $aActions = [];

	/**
	 * @param string $sTitle
	 * @param array|Closure $aoClosureOrArray
	 * @param bool $bMergeWithExisting
	 * @return void
	 * @throws AfrException
	 */
	public static function addActionGroup(
		string $sTitle,
		       $aoClosureOrArray,
		bool   $bMergeWithExisting = true
	): int
	{
		$bNewIsArr = is_array($aoClosureOrArray);
		$bNewIsClosure = $aoClosureOrArray instanceof Closure;
		$iRegistered = $bNewIsArr ? count($aoClosureOrArray) : 1;
		if (!$bNewIsArr && !$bNewIsClosure) {
			throw new AfrException(
				"Invalid stack parameter type for " . __FUNCTION__ .
				".\n Expected array of closures OR Closure that returns array of closures"
			);
		}
		if (!$bMergeWithExisting || empty(self::$aActions[$sTitle])) {
			self::$aActions[$sTitle] = $aoClosureOrArray;
		}
		elseif (is_array(self::$aActions[$sTitle]) && $bNewIsArr) {
			self::$aActions[$sTitle] = array_merge(self::$aActions[$sTitle], $aoClosureOrArray);
		} else {
			$mExisting = self::$aActions[$sTitle];
			self::$aActions[$sTitle] = function () use ($aoClosureOrArray, $mExisting) {
				return array_merge( //todo: test la 2,3,4 merge ca sa vad daca se pierd din valori
					is_array($mExisting) ? $mExisting : $mExisting(),
					is_array($aoClosureOrArray) ? $aoClosureOrArray : $aoClosureOrArray()
				);
			};
		}
		return $iRegistered;
	}

	/**
	 * @param string|null $sQaIndexStack
	 * @return int
	 * @throws AfrException
	 */
	public static function run(string $sQaIndexStack = null): int
	{
		$aOptions = [];
		foreach (static::$aActions as $sOption => $mStack) {
			$aOptions[$sOption] = function () use ($sOption, $mStack) {
				return self::handleCliQaStack($sOption, $mStack);
			};
		}

		if ($sQaIndexStack !== null) {
			if (($aOptions[$sQaIndexStack] ?? false)) {
				self::handleCliQaStack($sQaIndexStack, $aOptions[$sQaIndexStack]);
				return self::$iCliQaHandled;
			}
			AfrCliTextColors::getInstance()
				->colorRed("Invalid QA stack: `$sQaIndexStack` Switching to default QA stack:\n")
				->styleDefaultAllBgColor()
				->textPrint();
		}


		self::handleCliQaStack(__CLASS__ . '@' . __FUNCTION__, $aOptions);
		return self::$iCliQaHandled;
	}

	/**
	 * @param string $actionStackTitleKey
	 * @param array|Closure $oaDynamicOptions Contains array stack of closures
	 * @return void
	 * @throws AfrException
	 */
	public static function handleCliQaStack(string $actionStackTitleKey, $oaDynamicOptions): string
	{
		if (!AfrCliHttpDetect::isCli() && Afr::app()->request()->isHttp()) {
			throw new AfrException("Cli QA actions are not possible over HTTP requests.");
		}
		$bClosure = $oaDynamicOptions instanceof Closure;
		if (!$bClosure && !is_array($oaDynamicOptions)) {
			throw new AfrException("Stack options not set for title '$actionStackTitleKey'");
		}
		while (self::$iCliQaHandled < 150) {//reasonable limit
			//dynamic option list
			$aQuestionClosure = $bClosure ? $oaDynamicOptions() : $oaDynamicOptions;
			if ($aQuestionClosure === static::UP_STACK || static::dispatchCliQA($aQuestionClosure, $actionStackTitleKey) === null) {
				break;
			}
		}
		return static::UP_STACK;
	}

	/**
	 * @param array $aQuestionClosure
	 * @param string $sTitleInfo
	 * @return bool|mixed|string|null
	 * @throws AfrContainerException
	 * @throws AfrEventException
	 */
	protected static function dispatchCliQA(array $aQuestionClosure, string $sTitleInfo = '')
	{
		AfrEvent::dispatchEvent();
		self::$iCliQaDepth++;
		$sReturnInfo = empty($aQuestionClosure) ? "`$sTitleInfo` has no stack actions defined! Return" : 'Return';
		$aOptions = array_merge([$sReturnInfo => null,], $aQuestionClosure);
		$oTxt = AfrCliTextColors::getInstance()
			->styleItalic(true)
			->textAppend("\n#" . self::$iCliQaDepth . ' ' . $sTitleInfo)
			->styleItalic(false)
			->textPrint();
		$chosenKey = AfrCliPromptMenu::promptMenu(
			"What to execute?",
			array_keys($aOptions),
			array_key_first($aOptions)
		);
		$bIsClassAtMethod = is_string($aOptions[$chosenKey]) && strpos($aOptions[$chosenKey], '@');
		if (is_callable($aOptions[$chosenKey]) || $bIsClassAtMethod) {
			self::$iCliQaHandled++;
			if ($bIsClassAtMethod) {
				[$sClassOrContainerAliasBind, $sMethod] = explode('@', $aOptions[$chosenKey]);
				$r = Afr::app()->container()->get($sClassOrContainerAliasBind)->$sMethod();
			} else {
				$r = $aOptions[$chosenKey](); //call
			}
			$oTxt->textAppend("\n\t");
			if ($r === true) {
				$oTxt->colorGreen("Success: $chosenKey");
			} elseif ($r === false) {
				$oTxt->colorRed("Error: $chosenKey");
			} elseif (is_array($r)) {
				$oTxt->styleItalic(true)->textAppend(implode("\n", $r))->styleItalic(false);
			} elseif ($r === self::UP_STACK) {
				$oTxt->textDiscardBuffer();
			} elseif (!empty((string)$r)) {
				$oTxt->styleItalic(true)->textAppend((string)$r)->styleItalic(false);
			}
			$oTxt->styleDefaultAllBgColor()->textAppend("\n")->textPrint();
			self::$iCliQaDepth--;
			return $r;
		}
		self::$iCliQaDepth--;
		if ($aOptions[$chosenKey] === null) {
			return null;
		}
		return false;
	}


}