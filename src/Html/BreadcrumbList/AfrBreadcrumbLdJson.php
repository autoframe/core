<?php

namespace Autoframe\Core\Html\BreadcrumbList;

//TODO SCHEMA_ORG: "https://github.com/spatie/schema-org",

class AfrBreadcrumbLdJson
{
	/**
	 * @param array $aNavInfo [ ['name'=>'Home','url'=>'/'], ['name'=>'Products','url'=>'...'], ]
	 * @param string $sSiteUrl https://example.com/
	 * @return string
	 */
	public static function getBreadcrumbJsonLdFromArray(
		array  $aNavInfo,
		string $sSiteUrl = ''
	): string
	{
		$aContent = [
			'@context' => 'https://schema.org',
			'@type' => 'BreadcrumbList',
			'itemListElement' => []
		];
		$iPosition = 0;
		foreach ($aNavInfo as $aNavInfoItem) {
			if (empty($aNavInfoItem['name']) || empty($aNavInfoItem['url'])) {
				continue;
			}
			$aItem = [
				'@type' => 'ListItem',
				'position' => ++$iPosition,
				'name' => $aNavInfoItem['name'],
				'item' => $aNavInfoItem['url']
			];
			if (substr($aItem['url'], 0, 4) !== 'http') {
				$aItem['item'] = rtrim($sSiteUrl, '/') . '/' . ltrim($aItem['item'], '/');
			}
			$aContent['itemListElement'][] = (object)$aItem;
		}
		$sJsonLd = '<script type="application/ld+json">';
		$sJsonLd .= json_encode((object)$aContent);
		$sJsonLd .= '</script>';
		return $sJsonLd;
	}
}