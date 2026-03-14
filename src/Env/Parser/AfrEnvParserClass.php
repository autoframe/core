<?php
declare(strict_types=1);

namespace Autoframe\Core\Env\Parser;

use Autoframe\Core\Env\Exception\AfrEnvException;
use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;

class AfrEnvParserClass extends AfrSingletonAbstractClass implements AfrEnvParserInterface
{

	public bool $bDebugFlag = false; //debug flag
	protected array $aDecision = [];
	protected array $aLines = [];


	/**
	 * Parse str.
	 * @param string $sEnvLines
	 * @return array
	 */
	public function parseStr(string $sEnvLines): array
	{
		$this->aDecision = $this->aLines = [];
		$this->removeUtf8Bom($sEnvLines);

		$iLine = 0;
		$nxtChr = $chr = $chrChr = $prevChr = null;
		$sKey = $sVal = $sQuot = $sComment = $sLine = '';
		$bKey = $bVal = $bQuot = $bComment = false;


		$iEnvLinesLen = strlen($sEnvLines);
		for ($i = 0; $i < $iEnvLinesLen + 1; $i++) {
			$sDecision = '';
			$this->getChars($sEnvLines, $i, $iEnvLinesLen, $chr, $chrChr, $nxtChr, $prevChr);

			//first char or nothing started yet
			//a line may start with: space tab # char+notNumeric or continue if not in a quot
			if (!$bKey && !$bVal && !$bQuot && !$bComment) {
				//empty line
				if ($this->xEol($i, $sDecision, $chr, $chrChr, $iLine)) {
					//$sDecision = 'EOL #'.$i; //next line
				} elseif ($this->xComment($i, $sDecision, $chr, $bComment, $sComment)) {
					//you can have a #\n so threat it as it is
					//$sDecision = 'Comment start #'.$i;
				} elseif (strlen(trim($sKey)) > 0) { //key read is done
					if ($chr === ' ' || $chr === "\t") {
						$sDecision = 'Indent after key #' . $i; //indent / nothing
					} elseif ($chr === '"' || $chr === "'") {
						$sDecision = 'Quot start #' . $i;
						$bQuot = true;
						$sQuot = $chr;
						$bVal = true;
						$sVal = '';
					} else {
						$sDecision = 'Val start #' . $i;
						$bVal = true;
						$sVal = $chr;
					}
				} elseif ($chr === ' ' || $chr === "\t") {
					$sDecision = 'Indent #' . $i; //indent / nothing
				} elseif (is_numeric($chr)) {
					$sDecision = 'Numeric! #' . $i; //skip numeric chars in front of keys
				} else {
					$sDecision = 'Key start #' . $i;
					$bKey = true;
					$sKey = $chr;
				}
			}

			//general case EOL
			if (
				(!$sDecision && !$bQuot || $nxtChr === null)
				&& $this->xEol($i, $sDecision, $chr, $chrChr, $iLine)) {
				//$sDecision = 'EOL #'.$i; //next line
			}

			// comment chars
			if (!$sDecision && $bComment) {
				$sDecision = 'Append comment #' . $i;
				$sComment .= $chr;
			}
			// KEY
			if (!$sDecision && $bKey) {
				if ($chr === '=') {
					$sDecision = 'End Key #' . $i;
					$bKey = false;
				} elseif (
					($prevChr === ' ' || $prevChr === "\t") &&
					$this->xComment($i, $sDecision, $chr, $bComment, $sComment)
				) {
					//$sDecision = 'Comment start #' . $i;
					$bKey = false;
				} else {
					$sDecision = 'Concatenate Key #' . $i;
					$sKey .= $chr;
				}
			}

			//QUOT
			if (!$sDecision && $bQuot) {
				// end of quot
				if ($chr === $sQuot && $prevChr !== '\\') {
					$sDecision = 'Quot end #' . $i;
					$bQuot = false;
					$bVal = false;
				} elseif ($chr === $sQuot && $prevChr === '\\') {
					$sDecision = 'Fix escaped \quot #' . $i;
					$sVal = substr($sVal, 0, -1) . $chr; //remove escaped
				} else {
					$sDecision = 'Append quoted val #' . $i;
					$sVal .= $chr;
				}
			}

			//VAL
			if (!$sDecision && $bVal) {
				if (
					($prevChr === ' ' || $prevChr === "\t") &&
					$this->xComment($i, $sDecision, $chr, $bComment, $sComment)
				) {
					//$sDecision = 'Comment start #' . $i;
					$bVal = false;
				} else {
					$sDecision = 'Append val #' . $i;
					$sVal .= $chr;
				}
			}

			//PROCESS EOL
			if (substr($sDecision, 0, 3) == 'EOL') {
				$this->readNextLine(
					$sLine, $iLine,
					$sKey, $sVal, $sQuot, $sComment,
					$bKey, $bVal, $bQuot, $bComment
				);
			} else {
				$sLine .= $chr;
			}

			//DEBUG LOG
			if ($this->bDebugFlag) {
				$this->aDecision[$i] = $sDecision;
				if (!$sDecision) {
					echo "$i:blankDecision~$sLine\n";
				}

			}
		}
		$aReturn = $this->parseNested();

		if ($this->bDebugFlag) {
			print_r($aReturn);
			print_r($this->aLines);
			var_dump($this->aLines);
			print_r($this->aDecision);
		} else {
			$this->aDecision = $this->aLines = [];
		}
		return $aReturn;
	}

	/**
	 * Parse file.
	 * @param string $sPath
	 * @return array
	 * @throws AfrEnvException
	 */
	public function parseFile(string $sPath): array
	{
		if (!is_file($sPath)) {
			throw new AfrEnvException('Env file not found: ' . $sPath);
		}
		return $this->parseStr(
			(string)file_get_contents($sPath)
		);
	}

	/**
	 * @param string $sEnvLines
	 * @return void
	 */
	protected function removeUtf8Bom(string &$sEnvLines): void
	{
		$bom = substr($sEnvLines, 0, 3);
		if (ord($bom[0]) === 239 && ord($bom[1]) === 187 && ord($bom[2]) === 191) {
			$sEnvLines = substr($sEnvLines, 3);
		}
	}

	/**
	 * @param string $value
	 * @return bool
	 */
	protected function parseNull(string $value): bool
	{
		return strtolower($value) === 'null';
	}

	/**
	 * @param string $value
	 * @return bool
	 */
	protected function parseTrue(string $value): bool
	{
		$value = strtolower($value);
		return $value === 'true' || $value === 'yes' || $value === 'on';
	}

	/**
	 * @param string $value
	 * @return bool
	 */
	protected function parseFalse(string $value): bool
	{
		$value = strtolower($value);
		return $value === 'false' || $value === 'no' || $value === 'off';
	}

	/**
	 * @param string $value
	 * @return bool
	 */
	protected function parseNumeric(string $value): bool
	{
		return
			preg_match('@^(\+?-?\d+(?:\.\d*)?)$@', $value)
			&& $value <= PHP_INT_MAX
			&& $value >= PHP_INT_MIN;
	}

	/**
	 * @param string $sEnvLines
	 * @param int $i
	 * @param int $iEnvLinesLen
	 * @param $chr
	 * @param $chrChr
	 * @param $nxtChr
	 * @param $prevChr
	 * @return void
	 */
	protected function getChars(
		string $sEnvLines,
		int    $i,
		int    $iEnvLinesLen,
		       &$chr,
		       &$chrChr,
		       &$nxtChr,
		       &$prevChr
	): void
	{
		//get current char and next char
		$chr = substr($sEnvLines, $i, 1);
		if ($i + 1 === $iEnvLinesLen) { //end
			$nxtChr = null;
			$chrChr = $chr;
		} else {
			$nxtChr = substr($sEnvLines, $i + 1, 1);
			$chrChr = $chr . $nxtChr;
		}
		$prevChr = $i === 0 ? null : substr($sEnvLines, $i - 1, 1);
	}


	/**
	 * @param string $sLine
	 * @param int $iLine
	 * @param string $sKey
	 * @param string $sVal
	 * @param string $sQuot
	 * @param string $sComment
	 * @param bool $bKey
	 * @param bool $bVal
	 * @param bool $bQuot
	 * @param bool $bComment
	 * @return void
	 */
	protected function readNextLine(
		string &$sLine,
		int    $iLine,
		string &$sKey,
		string &$sVal,
		string &$sQuot,
		string &$sComment,
		bool   &$bKey,
		bool   &$bVal,
		bool   &$bQuot,
		bool   &$bComment
	): void
	{
		$aData = [
			'key' => str_replace(["\t", ' '], '_', trim($sKey)),
			'val' => $sQuot ? stripcslashes($sVal) : trim($sVal),
			'comment' => substr($sComment, 1)
		];
		if ($this->bDebugFlag) {
			$aData['line'] = $sLine;
		}

		if (strlen($aData['key']) && strlen($aData['val'])) {
			if ($this->parseTrue($aData['val'])) {
				$aData['val'] = true;
			} elseif ($this->parseFalse($aData['val'])) {
				$aData['val'] = false;
			} elseif ($this->parseNull($aData['val'])) {
				$aData['val'] = null;
			} elseif ($this->parseNumeric($aData['val'])) {
				if (strpos($aData['val'], '.') !== false) {
					$aData['val'] = (float)$aData['val'];
				} else {
					$aData['val'] = (int)$aData['val'];
				}
			}

		}
		$this->aLines[$iLine] = $aData;
		$sKey = $sVal = $sQuot = $sComment = $sLine = ''; //reset
		$bKey = $bVal = $bQuot = $bComment = false; //reset
	}

	/**
	 * @param $i
	 * @param $sDecision
	 * @param $chr
	 * @param $chrChr
	 * @param $iLine
	 * @return bool
	 */
	protected function xEol(&$i, &$sDecision, $chr, $chrChr, &$iLine): bool
	{
		if ($chrChr === "\r\n") {
			$i++; //skip next char
		}
		$bEol = $chr === "\n" || $chr === "\r" || $chrChr === "\r\n" || strlen($chrChr) < 1;//eof
		if ($bEol) {
			$sDecision = 'EOL #' . $i;
			$iLine++;
		}

		return $bEol;
	}

	/**
	 * @param $i
	 * @param $sDecision
	 * @param $chr
	 * @param $bComment
	 * @param $sComment
	 * @return bool
	 */
	protected function xComment($i, &$sDecision, $chr, &$bComment, &$sComment): bool
	{
		if ($chr === '#') {
			$sDecision = 'Comment start #' . $i;
			$bComment = true;
			$sComment = $chr;
			return true;
		}
		return false;
	}

	/**
	 * @param string $sStr
	 * @param string $startStr
	 * @param string $endStr
	 * @return array|null[]
	 */
	protected function extractNested(string $sStr, string $startStr, string $endStr = ''): array
	{
		if ($sStr && $startStr) {
			if (!$endStr) {
				$endStr = $startStr;
			}
		} else {
			return [];
		}
		$aMatch = [];
		$aStr = explode($startStr, $sStr);
		$iParts = count($aStr);
		if ($iParts < 2) {
			return [];
		}
		if ($startStr === $endStr) {
			$i = 0;
			while ($i < $iParts) {
				$aMatch[] = $aStr[$i + 1];
				$i += 2;
			}
		} else {
			unset($aStr[0]);
			foreach ($aStr as &$val) {
				$val = explode($endStr, $val)[0];
				$aMatch[] = $val;
			}
		}
		return $aMatch;
	}


	/**
	 * @return array
	 */
	protected function parseNested(): array
	{
		$aTmp = [];
		foreach ($this->aLines as &$aData) {
			if (strlen($aData['key']) < 1) {
				continue;
			}
			foreach (['${', '{$', '${', '{$'] as $sNestedStart) {
				if (is_string($aData['val']) && strpos($aData['val'], $sNestedStart) !== false) {

					foreach ($this->extractNested($aData['val'], $sNestedStart, '}') as $sNestedKey) {
						$sEscNested = '\\' . $sNestedStart;
						$bEscNested = strpos($aData['val'], $sEscNested) !== false;
						if ($bEscNested) {
							$aData['val'] = str_replace(
								$sEscNested,
								strtoupper(md5($sEscNested)),
								$aData['val']
							);
						}

						$aData['val'] = str_replace(
							$sNestedStart . $sNestedKey . '}',
							(string)($aTmp[$sNestedKey] ?? ($_ENV[$sNestedKey] ?? '')),
							$aData['val']
						);

						if ($bEscNested) {
							$aData['val'] = str_replace(
								strtoupper(md5($sEscNested)),
								$sEscNested,
								$aData['val']
							);
						}

					}
				}
			}
			$aTmp[$aData['key']] = $aData['val'];
		}
		return $aTmp;
	}

}
