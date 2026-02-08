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



	/**
	 * Dumps the source of a Closure / arrow function (fn) as a string.
	 *
	 * Limitations (inherent to Reflection):
	 * - If the closure was created via eval() or internal code, file/lines may be unavailable.
	 * - If the underlying file changed since runtime, extracted code may mismatch.
	 *
	 * @throws \ReflectionException
	 */
	public static function dump8x(Closure $closure): string
	{
		$ref = new ReflectionFunction($closure);

		$file = $ref->getFileName();
		$startLine = $ref->getStartLine();
		$endLine = $ref->getEndLine();

		if (!\is_string($file) || $file === '' || !\is_int($startLine) || !\is_int($endLine) || $startLine < 1 || $endLine < $startLine) {
			return 'Unable to retrieve closure source code.';
		}

		$snippet = self::readFileLines($file, $startLine, $endLine);
		if ($snippet === null || $snippet === '') {
			return 'Unable to read file containing closure.';
		}

		$code = self::extractClosureFromSnippet($snippet);

		return $code !== '' ? $code : 'Unable to parse closure source code.';
	}

	/**
	 * Reads a file line range (1-based inclusive) without loading the whole file.
	 */
	protected static function readFileLines(string $file, int $startLine, int $endLine): ?string
	{
		try {
			$fh = new \SplFileObject($file, 'r');
		} catch (\Throwable $e) {
			return null;
		}

		// SplFileObject::seek() is 0-based line index
		$fh->seek($startLine - 1);

		$out = '';
		for ($line = $startLine; $line <= $endLine && !$fh->eof(); $line++) {
			$out .= (string)$fh->current();
			$fh->next();
		}

		return $out;
	}

	/**
	 * Extracts the first closure/arrow-function expression from a snippet.
	 * The snippet should already contain the reflected line range.
	 */
	protected static function extractClosureFromSnippet(string $snippet): string
	{
		// Prefix with PHP tag so token_get_all treats it as code.
		$tokens = token_get_all("<?php\n" . $snippet);

		$tFn = \defined('T_FN') ? T_FN : -1;

		$start = null;
		$mode = null; // 'closure' | 'arrow'

		// Find first T_FUNCTION or T_FN in the snippet.
		$count = \count($tokens);
		for ($i = 0; $i < $count; $i++) {
			$tok = $tokens[$i];
			if (\is_array($tok)) {
				if ($tok[0] === T_FUNCTION) {
					$start = $i;
					$mode = 'closure';
					break;
				}
				if ($tok[0] === $tFn) {
					$start = $i;
					$mode = 'arrow';
					break;
				}
			}
		}

		if ($start === null || $mode === null) {
			return '';
		}

		// If immediately preceded by "static", include it.
		$prev = self::prevNonTrivialTokenIndex($tokens, $start);
		if ($prev !== null && \is_array($tokens[$prev]) && $tokens[$prev][0] === T_STATIC) {
			$start = $prev;
		}

		$out = '';

		if ($mode === 'closure') {
			$depth = 0;
			$seenBody = false;

			for ($i = $start; $i < $count; $i++) {
				$tok = $tokens[$i];
				$out .= \is_array($tok) ? $tok[1] : $tok;

				if (!\is_array($tok)) {
					if ($tok === '{') {
						$depth++;
						$seenBody = true;
					} elseif ($tok === '}') {
						$depth--;
						if ($seenBody && $depth === 0) {
							break; // end of closure body
						}
					}
				}
			}

			return \trim($out);
		}

		// Arrow function: collect tokens until the expression ends in the outer context.
		// Stop BEFORE the outer delimiter token (e.g., ',', ')', ';', ']', '}') when not nested.
		$paren = 0;
		$brack = 0;
		$curly = 0;

		for ($i = $start; $i < $count; $i++) {
			$tok = $tokens[$i];

			if (!\is_array($tok)) {
				// If we're not nested, and we hit a delimiter that belongs to the surrounding code,
				// stop before consuming it.
				if ($paren === 0 && $brack === 0 && $curly === 0) {
					if ($tok === ';' || $tok === ',' || $tok === ')' || $tok === ']' || $tok === '}') {
						break;
					}
				}

				// Update nesting after deciding delimiter stop.
				if ($tok === '(') $paren++;
				elseif ($tok === ')') $paren = \max(0, $paren - 1);
				elseif ($tok === '[') $brack++;
				elseif ($tok === ']') $brack = \max(0, $brack - 1);
				elseif ($tok === '{') $curly++;
				elseif ($tok === '}') $curly = \max(0, $curly - 1);

				$out .= $tok;
			} else {
				$out .= $tok[1];
			}
		}

		return \trim($out);
	}

	/**
	 * Returns the previous token index that is not whitespace/comment.
	 */
	protected static function prevNonTrivialTokenIndex(array $tokens, int $from): ?int
	{
		for ($i = $from - 1; $i >= 0; $i--) {
			$tok = $tokens[$i];
			if (!\is_array($tok)) {
				// single-char tokens matter
				return $i;
			}
			$id = $tok[0];
			if ($id === T_WHITESPACE || $id === T_COMMENT || $id === T_DOC_COMMENT) {
				continue;
			}
			return $i;
		}
		return null;
	}

}