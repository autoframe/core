<?php

namespace Autoframe\Core\String\Obj;

use Closure;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;

class AfrClosureToStr
{
	/**
	 * Dump.
	 */
	public static function dump(Closure $closure): string
	{
		$rf = new ReflectionFunction($closure);

		$file = $rf->getFileName();
		$startLine = $rf->getStartLine();
		$endLine = $rf->getEndLine();

		if ($file === false || $startLine === false || $endLine === false) {
			return 'Unable to retrieve closure source code.';
		}

		$lines = @file($file);
		if ($lines === false) {
			return 'Unable to read file containing closure.';
		}

		$snippet = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));
		$src = self::extractClosureSource($snippet);
		if ($src === '') {
			return 'Unable to parse closure source code.';
		}

		$body = self::extractBody($src);             // "{ ... }"
		$useClause = self::extractUseClause($src);   // "use (...)"
		$signature = self::buildSignature($rf, $useClause);

		return trim($signature . ' ' . $body);
	}

	/**
	 * Extracts "function (...) { ... }" from a larger snippet.
	 * Keeps original spacing/comments as much as possible inside the body.
	 */
	protected static function extractClosureSource(string $snippet): string
	{
		$tokens = token_get_all("<?php\n" . $snippet);

		$out = '';
		$in = false;
		$depth = 0;

		foreach ($tokens as $t) {
			if (is_array($t)) {
				// PHP 7.4: T_FUNCTION; PHP 8+: still T_FUNCTION (closures)
				if ($t[0] === T_FUNCTION) {
					$in = true;
				}
				if ($in) $out .= $t[1];
			} else {
				if ($in) {
					$out .= $t;
					if ($t === '{') $depth++;
					elseif ($t === '}') {
						$depth--;
						if ($depth === 0) break;
					}
				}
			}
		}

		return trim($out);
	}

	protected static function extractBody(string $closureSrc): string
	{
		$pos = strpos($closureSrc, '{');
		if ($pos === false) return '';
		return trim(substr($closureSrc, $pos));
	}

	protected static function extractUseClause(string $closureSrc): string
	{
		// Everything between ")" of params and "{" of body may contain "use (...)"
		// We keep it from the source to preserve "&$var" captures.
		$posClose = strpos($closureSrc, ')');
		$posOpenBrace = strpos($closureSrc, '{');
		if ($posClose === false || $posOpenBrace === false || $posOpenBrace <= $posClose) return '';

		$mid = trim(substr($closureSrc, $posClose + 1, $posOpenBrace - $posClose - 1));

		// Find the first "use(" occurrence
		$u = stripos($mid, 'use');
		if ($u === false) return '';

		$usePart = trim(substr($mid, $u));

		// Basic sanity check: must start with "use"
		return (stripos($usePart, 'use') === 0) ? $usePart : '';
	}

	protected static function buildSignature(ReflectionFunction $rf, string $useClause): string
	{
		$parts = [];

		if (method_exists($rf, 'isStatic') && $rf->isStatic()) {
			$parts[] = 'static';
		}

		$fn = 'function';
		if ($rf->returnsReference()) $fn .= ' &';
		$parts[] = $fn;

		$params = [];
		foreach ($rf->getParameters() as $p) {
			$params[] = self::formatParam($p);
		}

		$sig = implode(' ', $parts) . ' (' . implode(', ', $params) . ')';

		if ($useClause !== '') {
			// ensure single space separation
			$sig .= ' ' . trim($useClause);
		}

		$ret = self::formatType($rf->getReturnType(), false);
		if ($ret !== '') {
			$sig .= ': ' . $ret;
		}

		return $sig;
	}

	protected static function formatParam(ReflectionParameter $p): string
	{
		$s = '';

		$type = self::formatType($p->getType(), true);
		if ($type !== '') $s .= $type . ' ';

		if ($p->isPassedByReference()) $s .= '&';
		if ($p->isVariadic()) $s .= '...';

		$s .= '$' . $p->getName();

		// Default values not allowed for variadics
		if (!$p->isVariadic() && $p->isDefaultValueAvailable()) {
			if ($p->isDefaultValueConstant()) {
				$s .= ' = ' . $p->getDefaultValueConstantName();
			} else {
				$s .= ' = ' . var_export($p->getDefaultValue(), true);
			}
		}

		return $s;
	}

	/**
	 * Returns a PHP type string.
	 * - For non-builtin named types => prefixes "\" to force FQCN.
	 * - Handles union/intersection types when running on PHP 8+.
	 */
	protected static function formatType(?ReflectionType $type, bool $allowNullablePrefix): string
	{
		if ($type === null) return '';

		// PHP 8+: union types
		if (class_exists('ReflectionUnionType') && $type instanceof \ReflectionUnionType) {
			$parts = [];
			foreach ($type->getTypes() as $t) {
				$parts[] = self::formatNamedTypeNoNullable($t);
			}
			return implode('|', $parts);
		}

		// PHP 8.1+: intersection types
		if (class_exists('ReflectionIntersectionType') && $type instanceof \ReflectionIntersectionType) {
			$parts = [];
			foreach ($type->getTypes() as $t) {
				$parts[] = self::formatNamedTypeNoNullable($t);
			}
			return implode('&', $parts);
		}

		// Named type (PHP 7.4+)
		if ($type instanceof ReflectionNamedType) {
			$name = self::formatNamedTypeNoNullable($type);

			// In unions, null is explicit; here we can use "?T" for nullable named types.
			// Avoid "?mixed"/"?void"/"?never" etc.
			if ($allowNullablePrefix && $type->allowsNull()) {
				$lower = strtolower(ltrim($name, '\\'));
				if (!in_array($lower, ['mixed', 'void', 'never', 'null', 'false', 'true'], true)) {
					// if already contains "null" (won't here), skip; else prefix '?'
					return '?' . $name;
				}
			}

			return $name;
		}

		// Fallback (shouldn't happen often)
		return (string)$type;
	}

	protected static function formatNamedTypeNoNullable(ReflectionNamedType $t): string
	{
		$name = $t->getName();

		// For non-builtin class/interface names, force FQCN for portability.
		if (!$t->isBuiltin() && !in_array($name, ['self', 'parent', 'static'], true)) {
			$name = '\\' . ltrim($name, '\\');
		}

		return $name;
	}
}
