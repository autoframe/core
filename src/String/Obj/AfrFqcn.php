<?php

namespace Autoframe\Core\String\Obj;

final class AfrFqcn
{

	public static function getClassBaseNameFromInstance(object $sInstance): ?string
	{
		$sFQCN = get_class($sInstance);
		return $sFQCN ? self::getClassBaseNameFromFQCN($sFQCN) : null;
	}

	public static function getClassBaseNameFromFQCN(string $sFQCN): ?string
	{
		if(empty($sFQCN = trim($sFQCN))) return null;
		$sFQCN = array_slice(explode('\\', strtr($sFQCN, '/', '\\')), -1, 1)[0];
		$sFQCN = preg_replace('/[^A-Za-z0-9_-]/', '_', $sFQCN);
		return empty($sFQCN) ? null : $sFQCN;
	}

	/**
	 * @param string|object $soClass
	 * @return string|null
	 */
	public static function getClassBaseNameFromObjectOrFQCN($soClass): ?string
	{
		if(is_object($soClass)) return self::getClassBaseNameFromInstance($soClass);
		if(is_string($soClass)) return self::getClassBaseNameFromFQCN($soClass);
		return null;
	}




}