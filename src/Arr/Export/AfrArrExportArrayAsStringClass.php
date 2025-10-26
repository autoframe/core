<?php
declare(strict_types=1);

namespace Autoframe\Core\Arr\Export;

use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;
use Autoframe\Core\String\Obj\AfrClosureToStr;

class AfrArrExportArrayAsStringClass extends AfrSingletonAbstractClass implements AfrArrExportArrayAsStringInterface
{
	/**
	 * @param array $aData
	 * @param string $sQuot
	 * @param string $sEndOfLine
	 * @param string $sPointComa
	 * @param string $sVarName
	 * @param int $iTab
	 * @return string
	 * @throws \ReflectionException
	 */
	public function exportPhpArrayAsString(
		array  $aData,
		string $sQuot = "'",
		string $sEndOfLine = "\n",
		string $sPointComa = ';',
		string $sVarName = '$aData',
		int    $iTab = 0
	): string
	{
		$sOut = '';
		foreach ($aData as $mk => $mVal) {
			$sKType = gettype($mk);
			$sVType = gettype($mVal);
			$sOut .= $iTab ? str_repeat("\t", $iTab) : '';
			$this->exportPhpArrayAsStringFormatKV($sKType, $mk, $sOut, $sQuot);
			$sOut .= '=>';
			if ($sVType === 'array') {
				$sOut .= $this->exportPhpArrayAsString($mVal, $sQuot, $sEndOfLine, '', '');
			} else {
				$this->exportPhpArrayAsStringFormatKV($sVType, $mVal, $sOut, $sQuot);
			}
			$sOut .= ',' . $sEndOfLine;
		}
		if ($sVarName) {
			if (substr($sVarName, 0, 1) !== '$') {
				$sVarName = '$' . $sVarName;
			}
			$sVarName .= '=';
		}
		return str_repeat("\t", max($iTab - 1, 0)) . $sVarName . '[' . $sEndOfLine . $sOut . ']' . $sPointComa . $sEndOfLine;

	}

	/**
	 * @param string $sVType
	 * @param $mVal
	 * @param string $sOut
	 * @param string $sQuot
	 * @throws \ReflectionException
	 */
	protected function exportPhpArrayAsStringFormatKV(string $sVType, $mVal, string &$sOut, string $sQuot)
	{
		if ($sVType === 'integer') {
			$sOut .= $mVal;
		} elseif ($sVType === 'boolean') {
			$sOut .= $mVal ? 'true' : 'false';
		} elseif ($sVType === 'double') {
			$mVal = (string)$mVal;
			if (strpos($mVal, '.') === false) {
				$mVal .= '.';
			}
			$sOut .= $mVal;
		} elseif ($sVType === 'NULL') {
			$sOut .= 'NULL';
		} elseif ($mVal instanceof \Closure) {
			$sOut .= AfrClosureToStr::dump($mVal);
		} else {
			if ($sVType !== 'string') {
				$mVal = serialize($mVal);
			}
			$sOut .= $sQuot . str_replace(['\\', $sQuot], ['\\\\', '\\' . $sQuot], $mVal) . $sQuot;
		}
	}

}