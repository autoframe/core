<?php

namespace Autoframe\Core\Arr\Compare;

use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;

class AfrArrCompareClass extends AfrSingletonAbstractClass implements AfrArrCompareInterface
{
	/**
	 * Max recursion depth to protect against pathological/cyclic structures.
	 */
	protected int $maxDepth = 20;

	public function assertSameContents(
		$a,
		$b,
		bool $bIgnoreOrder = true,
		bool $bStrictObjectIdentity = true,
		bool $bStrictClosureIdentity = true,
		bool $bStrictScalars = true,
		int $iMaxDepth = 20
	): bool
	{
		$this->maxDepth = max($iMaxDepth, 1);
		$seen = [];
		return $this->deep_equal($a, $b, [
			'ignoreOrder' => $bIgnoreOrder,
			'strictClosureIdentity' => $bStrictClosureIdentity, // (=== / spl_object_id) or ReflectionFunction comparison
			'strictObjectIdentity' => $bStrictObjectIdentity,  // object $a === $b or compare public props recursively
			'strictScalars' => $bStrictScalars,
		], $seen);
	}


	/**
	 * Deep comparison for mixed values with special handling for arrays and closures.
	 *
	 * @param mixed $a
	 * @param mixed $b
	 * @param array $opt
	 *   - 'ignoreOrder' (bool) : if true, compares arrays as key/value maps (default true)
	 *   - 'strictClosureIdentity' (bool) : if true, compares closures equal only if same instance (=== / spl_object_id)
	 *        else compares best-effort signature via ReflectionFunction
	 *   - 'strictScalars' (bool) : if true uses === for scalars (default true)
	 *
	 *
	 * Practical recommendation:
	 *
	 * If closures are used as callbacks/handlers inside config arrays,
	 * identity comparison is the safest and most honest definition of equality.
	 *
	 * If you need caching keys or “mostly-equal” matching, use signature mode,
	 * but treat it as best-effort and document the limitations.
	 */
	protected function deep_equal($a, $b, array $opt = [], array &$seen = [], int $depth = 0): bool
	{
		// Depth guard
		if ($depth > $this->maxDepth) return false; // safest: "can't prove equal beyond this depth"


		$opt = array_merge([
			'ignoreOrder' => true,
			'strictClosureIdentity' => true, // (=== / spl_object_id) or ReflectionFunction comparison
			'strictObjectIdentity' => true,  // object $a === $b or compare public props recursively
			'strictScalars' => true,
		], $opt);

		// Fast path for identical zvals / same object
		if ($a === $b) return true;

		// Type mismatch quick reject (but allow non-strict scalar comparisons if configured)
		if (gettype($a) !== gettype($b)) {
			if (!$opt['strictScalars'] && is_scalar($a) && is_scalar($b)) {
				return $a == $b;
			}
			return false;
		}

		// Arrays
		if (is_array($a)) {
			if (count($a) !== count($b)) return false;

			if ($opt['ignoreOrder']) {
				// Compare as maps: same key set
				// Keys are already unique; order irrelevant.
				foreach ($a as $k => $va) {
					if (!array_key_exists($k, $b)) return false;
					if (!$this->deep_equal($va, $b[$k], $opt, $seen, $depth + 1)) return false;
				}
				return true;
			}

			// Order-sensitive comparison
			$keysA = array_keys($a);
			$keysB = array_keys($b);
			if ($keysA !== $keysB) return false;


			foreach ($keysA as $k) {
				if (!$this->deep_equal($a[$k], $b[$k], $opt, $seen, $depth + 1)) {
					return false;
				}
			}
			return true;
		}

		// Closures
		if ($a instanceof \Closure) {
			if (!($b instanceof \Closure)) return false;
			return $opt['strictClosureIdentity'] ?
				spl_object_id($a) === spl_object_id($b) :
				// Signature compare (best-effort)
				$this->getClosureSignature($a, $depth + 1) === $this->getClosureSignature($b, $depth + 1);
		}

		// Other objects
		if (is_object($a)) {
			if (get_class($a) !== get_class($b)) return false;

			// Prevent infinite loops on object graphs
			$oidA = spl_object_id($a);
			$oidB = spl_object_id($b);
			$pairKey = $oidA . ':' . $oidB;

			if (isset($seen['obj'][$pairKey])) return true;
			$seen['obj'][$pairKey] = true;

			if ($opt['strictObjectIdentity']) return $oidA === $oidB;
			// Compare public properties recursively
			return $this->deep_equal(get_object_vars($a), get_object_vars($b), $opt, $seen, $depth + 1);
		}

		// Resources
		if (is_resource($a)) {
			return get_resource_type($a) === get_resource_type($b) && (int)$a === (int)$b;
		}

		// Scalars / null
		return $opt['strictScalars'] ? ($a === $b) : ($a == $b);
	}

	/**
	 * Best-effort signature for closures.
	 * Note: Not a guarantee of semantic equivalence.
	 */
	protected function getClosureSignature(\Closure $c, int $depth = 0): array
	{
		// Stop signature recursion too (captured vars can be deep graphs)
		if ($depth > $this->maxDepth) return ['__depth_overflow__' => true];

		try {
			$rf = new \ReflectionFunction($c);
		} catch (\ReflectionException $e) {
			return ['__spl_object_id__' => spl_object_id($c)];
		}

		foreach ($rf->getParameters() as $p) {
			$params[] = [
				'name' => $p->getName(),
				'hasType' => $p->hasType(),
				'type' => $p->hasType() ? (string)$p->getType() : null,
				'isVariadic' => $p->isVariadic(),
				'isOptional' => $p->isOptional(),
			];
		}

		return [
			'file' => $rf->getFileName() ?: '',
			'start' => $rf->getStartLine() ?: 0,
			'end' => $rf->getEndLine() ?: 0,
			'static' => $this->normalize_for_signature($rf->getStaticVariables(), $depth + 1),
			'params' => $params ?? [],
			'scope' => $rf->getClosureScopeClass() ? $rf->getClosureScopeClass()->getName() : null,
			'this' => $rf->getClosureThis() ? spl_object_id($rf->getClosureThis()) : null,
		];
	}

	/**
	 * Normalizes values for inclusion in signatures.
	 * Closures/objects become stable-ish tokens; arrays recurse.
	 */
	protected function normalize_for_signature($v, int $depth = 0)
	{
		if ($depth > $this->maxDepth) return ['__depth_overflow__' => true];

		if ($v instanceof \Closure) return ['__closure__' => spl_object_id($v)];
		if (is_object($v)) return ['__object__' => get_class($v) . '#' . spl_object_id($v)];
		if (is_array($v)) {
			foreach ($v as $k => $vv) {
				$out[$k] = $this->normalize_for_signature($vv, $depth + 1);
			}
			return $out ?? [];
		}
		if (is_resource($v)) return ['__resource__' => get_resource_type($v) . '#' . (int)$v];
		return $v;
	}
}
