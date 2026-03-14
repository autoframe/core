<?php

namespace Autoframe\Core\CliTools;

use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;
use Autoframe\Core\Exception\AfrException;
use Autoframe\Core\Http\Request\AfrRequestInterface;

class AfrGetOpt extends AfrSingletonAbstractClass
{
	const REQUIRED = 'required';
	const OPTIONAL = 'optional';
	const NONE = 'none';
	protected ?array $argv = null;
	protected array $aDetectCache = [];

	/**
	 * Tokenize cli line input.
	 * @param string $input = 'test.php -f \'value for f\' --required value --optional="optional value"';
	 * @return array = [ 'test.php','-f','value for f','--required','value','--optional=optional value' ];
	 */
	public function tokenizeCliLineInput(string $input, bool $bSetArgv): array
	{
		$tokens = [];
		$length = strlen($input);
		$current = '';
		$inQuotes = false;
		$quoteChar = '';

		for ($i = 0; $i < $length; $i++) {
			$char = $input[$i];

			if ($inQuotes) {
				if ($char === $quoteChar) {
					$inQuotes = false;
				} else {
					$current .= $char;
				}
			} else {
				if ($char === '"' || $char === "'") {
					$inQuotes = true;
					$quoteChar = $char;
				} elseif ($char === ' ') {
					if ($current !== '') {
						$tokens[] = $current;
						$current = '';
					}
				} else {
					$current .= $char;
				}
			}
		}

		if ($current !== '') {
			$tokens[] = $current;
		}

		// Merge arguments with = inside them
		foreach ($tokens as $key => $token) {
			if (strpos($token, '=') !== false) {
				$parts = explode('=', $token, 2);
				if (isset($parts[1]) && ($parts[1][0] === '"' || $parts[1][0] === "'")) {
					$parts[1] = trim($parts[1], "'\"");
				}
				$tokens[$key] = $parts[0] . '=' . $parts[1];
			}
		}
		if ($bSetArgv) {
			$this->setArgvFromArray($tokens);
		}

		return $tokens;
	}


	/**
	 * Set argv from array.
	 */
	public function setArgvFromArray(array $argv = null): self
	{
		$this->argv = $argv;
		return $this;
	}

	/**
	 * Set argv from request.
	 * @param AfrRequestInterface $oRequest
	 * @return $this
	 * @throws AfrException
	 */
	public function setArgvFromRequest(AfrRequestInterface $oRequest): self
	{
		if (!$oRequest->isCli()) {
			throw new AfrException('The request must be cli in order to read the argv data');
		}
		$this->argv = $oRequest->getServerParam('argv');
		return $this;
	}

	/**
	 * Getopt.
	 * @param string $short_options
	 * @param array $long_options
	 * @param int|null $rest_index
	 * @param array $remaining_args
	 * @return array
	 * @throws AfrException
	 */
	public function getopt(string $short_options, array $long_options = [], int &$rest_index = null, array &$remaining_args = []): array
	{
		if (!isset($this->argv)) {
			throw new AfrException('The arguments must be set before calling getopt() using setArgvFromRequest() or setArgvFromArray() or tokenizeCliLineInput(). The first argument is like `path/script.php`');
		}
		if (empty($this->argv) || count($this->argv) < 2) {
			return [];
		}
		$args = array_slice($this->argv, 1);
		$options = [];
		$remaining_args = [];

		$short_opts = $this->parseShortOptions($short_options);
		$long_opts = $this->parseLongOptions($long_options);

		for ($i = 0; $i < count($args); $i++) {
			$arg = $args[$i];
			$argLen = strlen($arg);

			if (substr($arg, 0, 2) === '--' && $argLen > 2) { // Long option
				list($i, $options) = $this->processLongOption($arg, $long_opts, $args, $i, $options);
			} elseif (substr($arg, 0, 1) === '-' && $argLen > 1) { // Short option
				list($i, $options) = $this->processShortOption($arg, $short_opts, $args, $i, $options);
			} else {
				$remaining_args[] = $arg;
			}
		}

		if ($rest_index !== null) {
			$rest_index = count($this->argv) - count($remaining_args);
		}

		return $options;
	}

	protected function parseShortOptions(string $short_options): array
	{
		$options = [];
		$length = strlen($short_options);

		for ($i = 0; $i < $length; $i++) {
			$char = $short_options[$i];
			if (!ctype_alnum($char)) {
				continue;
			}
			if ($i + 1 < $length && $short_options[$i + 1] === ':') {
				if ($i + 2 < $length && $short_options[$i + 2] === ':') {
					$options[$char] = static::OPTIONAL;
					$i += 2;
				} else {
					$options[$char] = static::REQUIRED;
					$i++;
				}
			} else {
				$options[$char] = static::NONE;
			}
		}

		return $options;
	}

	protected function parseLongOptions(array $long_options): array
	{
		$options = [];
		foreach ($long_options as $opt) {
			if (substr($opt, -2) === '::') {
				$options[substr($opt, 0, -2)] = static::OPTIONAL;
			} elseif (substr($opt, -1) === ':') {
				$options[substr($opt, 0, -1)] = static::REQUIRED;
			} else {
				$options[$opt] = static::NONE;
			}
		}

		return $options;
	}

	/**
	 * @param string $arg
	 * @param array $short_opts
	 * @param array $args
	 * @param int $i
	 * @param array $options
	 * @return array
	 */
	protected function processShortOption(string $arg, array $short_opts, array $args, int $i, array $options): array
	{
		$opt = substr($arg, 1);
		$optLen = strlen($opt);

		for ($j = 0; $j < $optLen; $j++) {
			$char = substr($opt, $j, 1);
			if (!isset($short_opts[$char])) {//ignored
				continue;
			}

			if ($short_opts[$char] === static::NONE) {
				$value = false;
			} else { // $short_opts[$char] === static::REQUIRED || $short_opts[$char] === static::OPTIONAL
				if ($j + 1 < $optLen) { //the value is embedded into $opt string
					$value = substr($opt, $j + 1);
				} else {
					//$value = $args[++$i] ?? null;
					if ($short_opts[$char] === static::OPTIONAL) {
						$value = isset($args[$i + 1]) && substr($args[$i + 1], 0, 1) !== '-' ?
							$args[++$i] : false;
					} else { //REQUIRED
						if (isset($args[$i + 1])) {
							$value = $args[++$i];
						} else {
							unset($value);
						}
					}
				}
				$j = $optLen;//break optional|required
			}
			if (!isset($value)) {
				continue;
			}
			$options = $this->pushOption($options, $char, $value);
		}
		return [$i, $options];
	}

	/**
	 * @param array $options
	 * @param $key
	 * @param $value
	 * @return array
	 */
	protected function pushOption(array $options, $key, $value): array
	{
		$value = substr((string)$value, 0, 1) === '=' ? substr($value, 1) : $value;
		if (isset($options[$key])) {
			if (!is_array($options[$key])) {
				$options[$key] = [$options[$key]];
			}
			$options[$key][] = $value;
		} else {
			$options[$key] = $value;
		}
		return $options;
	}

	/**
	 * @param string $arg
	 * @param array $long_opts
	 * @param array $args
	 * @param int $i
	 * @param array $options
	 * @return array
	 */
	protected function processLongOption(string $arg, array $long_opts, array $args, int $i, array $options): array
	{
		$opt = substr($arg, 2);
		$value = '';
		if (strpos($opt, '=') !== false) {
			list($optEq, $valueEq) = explode('=', $opt, 2);
			if (strlen($valueEq) > 0) {
				$opt = $optEq;
				$value = $valueEq;
			}
		}

		if (isset($long_opts[$opt])) {
			if ($long_opts[$opt] === static::NONE) {
				$value = false;
			} elseif ($long_opts[$opt] === static::REQUIRED) {
				if (strlen($value) < 1) {
					$value = $args[++$i] ?? '';
				}
				if (strlen($value) < 1) {
					unset($value);
				}
			} elseif ($long_opts[$opt] === static::OPTIONAL) {

				if (strlen($value) < 1) {
					if (isset($args[$i + 1]) && substr($args[$i + 1], 0, 1) !== '-') {
						$value = $args[++$i];
					} else {
						$value = false;
					}
				}

			}

			if (isset($value)) {
				$options = $this->pushOption($options, $opt, $value);
			}
		}
		return [$i, $options];
	}


	/**
	 * Getopt detect all args.
	 * @param array|null $arguments
	 * @param bool $bWildcardAnyArg
	 * @return array
	 * @throws AfrException
	 */
	public function getoptDetectAllArgs(array $arguments = null, bool $bWildcardAnyArg = false): array
	{
		if ($arguments !== null) {
			$this->setArgvFromArray($arguments);
		} elseif (!isset($this->argv)) {
			$this->setArgvFromArray(
				AfrCliHttpDetect::isCli() ? ($_SERVER['argv'] ?? ['']) : ['']
			);
		}

		// Remove the script name (first argument)
		$arguments = array_slice($this->argv, 1);
		if (empty($arguments)) {
			return [];
		}

		$sDetectCacheKey = md5(serialize($arguments));
		if (!isset($this->aDetectCache[$sDetectCacheKey])) {
			$this->aDetectCache[$sDetectCacheKey] = $this->doDetectAllArgs($arguments);
		}
		$aOpt = $this->aDetectCache[$sDetectCacheKey];

		if ($bWildcardAnyArg) {
			foreach ($arguments as $arg) {
				if (!$arg || substr($arg, 0, 1) == '-' || is_numeric($arg)) {
					continue; //skip standard args
				}
				$sDetectVal = false;
				if (($iEqPos = strpos($arg, '=')) !== false) {
					$sDetectVal = substr($arg, $iEqPos + 1);
					$arg = substr($arg, 0, $iEqPos);
				}
				$aOpt = $this->pushOption($aOpt, $arg, $sDetectVal);
			}
		}
		return $aOpt;
	}

	protected function addParsedUniqueOption(string $flagMix, array &$aOptionStack): void
	{
		$key = rtrim($flagMix, ':');
		if (empty($aOptionStack[$key])) {
			$aOptionStack[$key] = $flagMix;
			return;
		}
		$aOptionStack[$key] = strlen($aOptionStack[$key]) < strlen($flagMix) ? $flagMix : $aOptionStack[$key];
	}

	/**
	 * @param array $arguments
	 * @return array
	 * @throws AfrException
	 */
	protected function doDetectAllArgs(array $arguments): array
	{
		$aShortOptions = [];
		$longOptions = [];
		foreach ($arguments as $index => $arg) {
			if (preg_match('/^-([a-zA-Z0-9])$/', $arg, $matches)) {
				// Short option without value
				$this->addParsedUniqueOption($matches[1], $aShortOptions);
			} elseif (preg_match('/^-([a-zA-Z0-9])\s*(.+)?$/', $arg, $matches)) {
				// Short option with required value
				//$aShortOptions .= $matches[1] . '::';
				if (substr($matches[2], 0, 1) === '=') {
					$this->addParsedUniqueOption($matches[1] . '::', $aShortOptions);
				} else {
					//more than one short option
					$aShortOptionsValue = explode('=', substr($arg, 1));
					$sShortOptionList = $aShortOptionsValue[0];
					$iShortOptionListLen = strlen($sShortOptionList);
					$sShortOptionValue = $aShortOptionsValue[1] ?? '';
					for ($j = 0; $j < $iShortOptionListLen; $j++) {
						if ($j + 1 == $iShortOptionListLen) {
							if (
								strlen($sShortOptionValue) > 0 ||  //last short list value followed by equal
								isset($arguments[$index + 1]) && substr($arguments[$index + 1], 0, 1) !== '-'
							) {
								$this->addParsedUniqueOption($sShortOptionList[$j] . '::', $aShortOptions);
								continue;
							}
						}
						// short value without any value
						$this->addParsedUniqueOption($sShortOptionList[$j], $aShortOptions);
					}
				}
			} elseif (preg_match('/^--([a-zA-Z][a-zA-Z0-9-_]*)$/', $arg, $matches)) {
				// Long option without value
				//$longOptions[] = $matches[1];
				$this->addParsedUniqueOption($matches[1], $longOptions);
			} elseif (preg_match('/^--([a-zA-Z][a-zA-Z0-9-_]*)=(.+)$/', $arg, $matches)) {
				// Long option with required value
				//$longOptions[] = $matches[1] . '::';
				$this->addParsedUniqueOption($matches[1] . '::', $longOptions);
			} elseif ($index > 0 && substr($previousArg = $arguments[$index - 1], 0, 1) == '-' && strpos($previousArg, '=') === false) {
				if (substr($previousArg, 0, 2) == '--') {
					//$longOptions[] = ltrim($previousArg, '-') . '::';
					$this->addParsedUniqueOption(ltrim($previousArg, '-') . '::', $longOptions);
				} elseif (substr($previousArg, 0, 1) == '-') {
					$this->addParsedUniqueOption(substr($previousArg, -1, 1) . '::', $aShortOptions);
				}
			}
		}
		return $this->getopt(implode('', $aShortOptions), array_values($longOptions));
	}

}
