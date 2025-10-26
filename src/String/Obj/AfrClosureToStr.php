<?php

namespace Autoframe\Core\String\Obj;

use Closure;
use ReflectionFunction;

class AfrClosureToStr
{
	/**
	 * @param Closure $closure
	 * @return string
	 * @throws \ReflectionException
	 */
	public static function dump(Closure $closure): string
	{
		$reflection = new ReflectionFunction($closure);
		$file = $reflection->getFileName();
		$startLine = $reflection->getStartLine();
		$endLine = $reflection->getEndLine();

		if ($file === false || $startLine === false || $endLine === false) {
			return 'Unable to retrieve closure source code.';
		}

		// Read the file contents
		$fileLines = file($file);
		if ($fileLines === false) {
			return 'Unable to read file containing closure.';
		}

		// Extract relevant lines
		$closureCode = implode("", array_slice($fileLines, $startLine - 1, $endLine - $startLine + 1));

		// Use token_get_all to clean up the extracted closure
		$tokens = token_get_all("<?php\n" . $closureCode);
		$cleanedCode = '';
		$inClosure = false;
		$bracketCount = 0;

		foreach ($tokens as $token) {
			if (is_array($token)) {
				if ($token[0] === T_FUNCTION) {
					$inClosure = true;
				}
				if ($inClosure) {
					$cleanedCode .= $token[1];
				}
			} else {
				if ($inClosure) {
					$cleanedCode .= $token;
					if ($token === '{') {
						$bracketCount++;
					} elseif ($token === '}') {
						$bracketCount--;
						if ($bracketCount === 0) {
							break;
						}
					}
				}
			}
		}
		return trim($cleanedCode);
	}
}