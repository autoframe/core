<?php

namespace Autoframe\Core\Http\Url;

use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonAbstractClass;

/**
 * A PHP port of URLify.js from the Django project
 * (https://github.com/django/django/blob/master/django/contrib/admin/static/admin/js/urlify.js).
 * Handles symbols from Latin languages, Greek, Turkish, Bulgarian, Russian,
 * Ukrainian, Czech, Polish, Romanian, Latvian, Lithuanian, Vietnamese, Arabic,
 * Serbian, Azerbaijani and Kazakh. Symbols it cannot transliterate
 * it will simply omit.
 *
 * Usage:
 *    To generate slugs for URLs:
 *
 *     echo filter (' J\'étudie le français '); // "jetudie-le-francais"
 *     echo filter ('Lo siento, no hablo español.'); // "lo-siento-no-hablo-espanol"
 *
 *    To generate slugs for file names:
 *
 *     echo filter ('фото.jpg', 60, "", true); // "foto.jpg"
 *
 *    To simply transliterate characters:
 *
 *      echo transliterate ('J\'étudie le français'); // "J'etudie le francais"
 *      echo transliterate ('Lo siento, no hablo español.'); // "Lo siento, no hablo espanol."
 *
 *    To extend the character list:
 *
 *      add_chars (array ( * '¿' => '?', '®' => '(r)', '¼' => '1/4', * '½' => '1/2', '¾' => '3/4', '¶' => 'P' ));
 *      echo transliterate ('¿ ® ¼ ¼ ¾ ¶'); // "? (r) 1/2 1/2 3/4 P"
 *
 *    To extend the list of words to remove:
 *
 *      remove_words (array ('remove', 'these', 'too'));
 *
 *    To prioritize a certain language map:
 *
 *     echo filter (' Ägypten und Österreich besitzen wie üblich ein Übermaß an ähnlich öligen Attachés ',60,"de");
 *     // "aegypten-und-oesterreich-besitzen-wie-ueblich-ein-uebermass-aehnlich-oeligen-attaches"
 *
 *     echo filter ('Cağaloğlu, çalıştığı, müjde, lazım, mahkûm',60,"tr");
 *     // "cagaloglu-calistigi-mujde-lazim-mahkum"
 */
class AfrURLify extends AfrSingletonAbstractClass
{
	protected array $maps = [
		'de' => [ /* German */
			'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
			'ẞ' => 'SS'
		],
		'latin' => [
			'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Ă' => 'A', 'Æ' => 'AE', 'Ç' =>
				'C', 'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I',
			'Ï' => 'I', 'Ð' => 'D', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' =>
				'O', 'Ő' => 'O', 'Ø' => 'O', 'Œ' => 'OE', 'Ș' => 'S', 'Ț' => 'T', 'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ű' => 'U',
			'Ý' => 'Y', 'Þ' => 'TH', 'ß' => 'ss', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' =>
				'a', 'å' => 'a', 'ă' => 'a', 'æ' => 'ae', 'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
			'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ð' => 'd', 'ñ' => 'n', 'ò' => 'o', 'ó' =>
				'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ő' => 'o', 'ø' => 'o', 'œ' => 'oe', 'ș' => 's', 'ț' => 't', 'ù' => 'u', 'ú' => 'u',
			'û' => 'u', 'ü' => 'u', 'ű' => 'u', 'ý' => 'y', 'þ' => 'th', 'ÿ' => 'y'
		],
		'latin_symbols' => [
			'©' => '(c)'
		],
		'el' => [ /* Greek */
			'α' => 'a', 'β' => 'b', 'γ' => 'g', 'δ' => 'd', 'ε' => 'e', 'ζ' => 'z', 'η' => 'h', 'θ' => '8',
			'ι' => 'i', 'κ' => 'k', 'λ' => 'l', 'μ' => 'm', 'ν' => 'n', 'ξ' => '3', 'ο' => 'o', 'π' => 'p',
			'ρ' => 'r', 'σ' => 's', 'τ' => 't', 'υ' => 'y', 'φ' => 'f', 'χ' => 'x', 'ψ' => 'ps', 'ω' => 'w',
			'ά' => 'a', 'έ' => 'e', 'ί' => 'i', 'ό' => 'o', 'ύ' => 'y', 'ή' => 'h', 'ώ' => 'w', 'ς' => 's',
			'ϊ' => 'i', 'ΰ' => 'y', 'ϋ' => 'y', 'ΐ' => 'i',
			'Α' => 'A', 'Β' => 'B', 'Γ' => 'G', 'Δ' => 'D', 'Ε' => 'E', 'Ζ' => 'Z', 'Η' => 'H', 'Θ' => '8',
			'Ι' => 'I', 'Κ' => 'K', 'Λ' => 'L', 'Μ' => 'M', 'Ν' => 'N', 'Ξ' => '3', 'Ο' => 'O', 'Π' => 'P',
			'Ρ' => 'R', 'Σ' => 'S', 'Τ' => 'T', 'Υ' => 'Y', 'Φ' => 'F', 'Χ' => 'X', 'Ψ' => 'PS', 'Ω' => 'W',
			'Ά' => 'A', 'Έ' => 'E', 'Ί' => 'I', 'Ό' => 'O', 'Ύ' => 'Y', 'Ή' => 'H', 'Ώ' => 'W', 'Ϊ' => 'I',
			'Ϋ' => 'Y'
		],
		'tr' => [ /* Turkish */
			'ş' => 's', 'Ş' => 'S', 'ı' => 'i', 'İ' => 'I', 'ç' => 'c', 'Ç' => 'C', 'ü' => 'u', 'Ü' => 'U',
			'ö' => 'o', 'Ö' => 'O', 'ğ' => 'g', 'Ğ' => 'G'
		],
		'bg' => [ /* Bulgarian */
			'Щ' => 'Sht', 'Ш' => 'Sh', 'Ч' => 'Ch', 'Ц' => 'C', 'Ю' => 'Yu', 'Я' => 'Ya',
			'Ж' => 'J', 'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D',
			'Е' => 'E', 'З' => 'Z', 'И' => 'I', 'Й' => 'Y', 'К' => 'K', 'Л' => 'L',
			'М' => 'M', 'Н' => 'N', 'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S',
			'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ь' => '', 'Ъ' => 'A',
			'щ' => 'sht', 'ш' => 'sh', 'ч' => 'ch', 'ц' => 'c', 'ю' => 'yu', 'я' => 'ya',
			'ж' => 'j', 'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
			'е' => 'e', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l',
			'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's',
			'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ь' => '', 'ъ' => 'a'
		],
		'ru' => [ /* Russian */
			'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo', 'ж' => 'zh',
			'з' => 'z', 'и' => 'i', 'й' => 'i', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o',
			'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c',
			'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sh', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu',
			'я' => 'ya',
			'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ё' => 'Yo', 'Ж' => 'Zh',
			'З' => 'Z', 'И' => 'I', 'Й' => 'I', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O',
			'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'C',
			'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Sh', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '', 'Э' => 'E', 'Ю' => 'Yu',
			'Я' => 'Ya',
			'№' => ''
		],
		'uk' => [ /* Ukrainian */
			'Є' => 'Ye', 'І' => 'I', 'Ї' => 'Yi', 'Ґ' => 'G', 'є' => 'ye', 'і' => 'i', 'ї' => 'yi', 'ґ' => 'g'
		],
		'kk' => [ /* Kazakh */
			'Ә' => 'A', 'Ғ' => 'G', 'Қ' => 'Q', 'Ң' => 'N', 'Ө' => 'O', 'Ұ' => 'U', 'Ү' => 'U', 'Һ' => 'H',
			'ә' => 'a', 'ғ' => 'g', 'қ' => 'q', 'ң' => 'n', 'ө' => 'o', 'ұ' => 'u', 'ү' => 'u', 'һ' => 'h',
		],
		'cs' => [ /* Czech */
			'č' => 'c', 'ď' => 'd', 'ě' => 'e', 'ň' => 'n', 'ř' => 'r', 'š' => 's', 'ť' => 't', 'ů' => 'u',
			'ž' => 'z', 'Č' => 'C', 'Ď' => 'D', 'Ě' => 'E', 'Ň' => 'N', 'Ř' => 'R', 'Š' => 'S', 'Ť' => 'T',
			'Ů' => 'U', 'Ž' => 'Z'
		],
		'pl' => [ /* Polish */
			'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n', 'ó' => 'o', 'ś' => 's', 'ź' => 'z',
			'ż' => 'z', 'Ą' => 'A', 'Ć' => 'C', 'Ę' => 'e', 'Ł' => 'L', 'Ń' => 'N', 'Ó' => 'O', 'Ś' => 'S',
			'Ź' => 'Z', 'Ż' => 'Z'
		],
		'ro' => [ /* Romanian */
			'ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ț' => 't', 'Ţ' => 'T', 'ţ' => 't'
		],
		'lv' => [ /* Latvian */
			'ā' => 'a', 'č' => 'c', 'ē' => 'e', 'ģ' => 'g', 'ī' => 'i', 'ķ' => 'k', 'ļ' => 'l', 'ņ' => 'n',
			'š' => 's', 'ū' => 'u', 'ž' => 'z', 'Ā' => 'A', 'Č' => 'C', 'Ē' => 'E', 'Ģ' => 'G', 'Ī' => 'i',
			'Ķ' => 'k', 'Ļ' => 'L', 'Ņ' => 'N', 'Š' => 'S', 'Ū' => 'u', 'Ž' => 'Z'
		],
		'lt' => [ /* Lithuanian */
			'ą' => 'a', 'č' => 'c', 'ę' => 'e', 'ė' => 'e', 'į' => 'i', 'š' => 's', 'ų' => 'u', 'ū' => 'u', 'ž' => 'z',
			'Ą' => 'A', 'Č' => 'C', 'Ę' => 'E', 'Ė' => 'E', 'Į' => 'I', 'Š' => 'S', 'Ų' => 'U', 'Ū' => 'U', 'Ž' => 'Z'
		],
		'vn' => [ /* Vietnamese */
			'Á' => 'A', 'À' => 'A', 'Ả' => 'A', 'Ã' => 'A', 'Ạ' => 'A', 'Ă' => 'A', 'Ắ' => 'A', 'Ằ' => 'A', 'Ẳ' => 'A', 'Ẵ' => 'A', 'Ặ' => 'A', 'Â' => 'A', 'Ấ' => 'A', 'Ầ' => 'A', 'Ẩ' => 'A', 'Ẫ' => 'A', 'Ậ' => 'A',
			'á' => 'a', 'à' => 'a', 'ả' => 'a', 'ã' => 'a', 'ạ' => 'a', 'ă' => 'a', 'ắ' => 'a', 'ằ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a', 'ặ' => 'a', 'â' => 'a', 'ấ' => 'a', 'ầ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a', 'ậ' => 'a',
			'É' => 'E', 'È' => 'E', 'Ẻ' => 'E', 'Ẽ' => 'E', 'Ẹ' => 'E', 'Ê' => 'E', 'Ế' => 'E', 'Ề' => 'E', 'Ể' => 'E', 'Ễ' => 'E', 'Ệ' => 'E',
			'é' => 'e', 'è' => 'e', 'ẻ' => 'e', 'ẽ' => 'e', 'ẹ' => 'e', 'ê' => 'e', 'ế' => 'e', 'ề' => 'e', 'ể' => 'e', 'ễ' => 'e', 'ệ' => 'e',
			'Í' => 'I', 'Ì' => 'I', 'Ỉ' => 'I', 'Ĩ' => 'I', 'Ị' => 'I', 'í' => 'i', 'ì' => 'i', 'ỉ' => 'i', 'ĩ' => 'i', 'ị' => 'i',
			'Ó' => 'O', 'Ò' => 'O', 'Ỏ' => 'O', 'Õ' => 'O', 'Ọ' => 'O', 'Ô' => 'O', 'Ố' => 'O', 'Ồ' => 'O', 'Ổ' => 'O', 'Ỗ' => 'O', 'Ộ' => 'O', 'Ơ' => 'O', 'Ớ' => 'O', 'Ờ' => 'O', 'Ở' => 'O', 'Ỡ' => 'O', 'Ợ' => 'O',
			'ó' => 'o', 'ò' => 'o', 'ỏ' => 'o', 'õ' => 'o', 'ọ' => 'o', 'ô' => 'o', 'ố' => 'o', 'ồ' => 'o', 'ổ' => 'o', 'ỗ' => 'o', 'ộ' => 'o', 'ơ' => 'o', 'ớ' => 'o', 'ờ' => 'o', 'ở' => 'o', 'ỡ' => 'o', 'ợ' => 'o',
			'Ú' => 'U', 'Ù' => 'U', 'Ủ' => 'U', 'Ũ' => 'U', 'Ụ' => 'U', 'Ư' => 'U', 'Ứ' => 'U', 'Ừ' => 'U', 'Ử' => 'U', 'Ữ' => 'U', 'Ự' => 'U',
			'ú' => 'u', 'ù' => 'u', 'ủ' => 'u', 'ũ' => 'u', 'ụ' => 'u', 'ư' => 'u', 'ứ' => 'u', 'ừ' => 'u', 'ử' => 'u', 'ữ' => 'u', 'ự' => 'u',
			'Ý' => 'Y', 'Ỳ' => 'Y', 'Ỷ' => 'Y', 'Ỹ' => 'Y', 'Ỵ' => 'Y', 'ý' => 'y', 'ỳ' => 'y', 'ỷ' => 'y', 'ỹ' => 'y', 'ỵ' => 'y',
			'Đ' => 'D', 'đ' => 'd'
		],
		'ar' => [ /* Arabic */
			'أ' => 'a', 'ب' => 'b', 'ت' => 't', 'ث' => 'th', 'ج' => 'g', 'ح' => 'h', 'خ' => 'kh', 'د' => 'd',
			'ذ' => 'th', 'ر' => 'r', 'ز' => 'z', 'س' => 's', 'ش' => 'sh', 'ص' => 's', 'ض' => 'd', 'ط' => 't',
			'ظ' => 'th', 'ع' => 'aa', 'غ' => 'gh', 'ف' => 'f', 'ق' => 'k', 'ك' => 'k', 'ل' => 'l', 'م' => 'm',
			'ن' => 'n', 'ه' => 'h', 'و' => 'o', 'ي' => 'y',
			'ا' => 'a', 'إ' => 'a', 'آ' => 'a', 'ؤ' => 'o', 'ئ' => 'y', 'ء' => 'aa',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
		],
		'fa' => [ /* Persian */
			'گ' => 'g', 'ژ' => 'j', 'پ' => 'p', 'چ' => 'ch', 'ی' => 'y', 'ک' => 'k',
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
		],
		'sr' => [ /* Serbian */
			'ђ' => 'dj', 'ј' => 'j', 'љ' => 'lj', 'њ' => 'nj', 'ћ' => 'c', 'џ' => 'dz', 'đ' => 'dj',
			'Ђ' => 'Dj', 'Ј' => 'j', 'Љ' => 'Lj', 'Њ' => 'Nj', 'Ћ' => 'C', 'Џ' => 'Dz', 'Đ' => 'Dj'
		],
		'az' => [ /* Azerbaijani */
			'ç' => 'c', 'ə' => 'e', 'ğ' => 'g', 'ı' => 'i', 'ö' => 'o', 'ş' => 's', 'ü' => 'u',
			'Ç' => 'C', 'Ə' => 'E', 'Ğ' => 'G', 'İ' => 'I', 'Ö' => 'O', 'Ş' => 'S', 'Ü' => 'U'
		],
	];

	/**
	 * List of words to remove from URLs.
	 */
	protected array $aRemoveWords = [/*		'a', 'an', 'as', 'at', 'before', 'but', 'by', 'for', 'from',
				'is', 'in', 'into', 'like', 'of', 'off', 'on', 'onto', 'per',
				'since', 'than', 'the', 'this', 'that', 'to', 'up', 'via',
				'with'*/
	];

	/**
	 * The character map.
	 */
	protected array $aCharMap = [];

	/**
	 * The character list as a string.
	 */
	protected string $sChars = "";


	/**
	 * The current language
	 */
	protected string $sCurrentLanguage = '';

	/**
	 * Initializes the character map.
	 * @param string $language
	 */
	public function setPriorityLanguage(string $language = ""): void
	{
		if (count($this->aCharMap) > 0 && (($language == "") || ($language == $this->sCurrentLanguage))) {
			return;
		}

		/* Is a specific map associated with $language ? */
		if (isset($this->maps[$language]) && is_array($this->maps[$language])) {
			/* Move this map to end. This means it will have priority over others */
			$m = $this->maps[$language];
			unset($this->maps[$language]);
			$this->maps[$language] = $m;
		}
		/* Reset vars */
		$this->sCurrentLanguage = $language;
		$this->aCharMap = [];
		$this->sChars = '';

		foreach ($this->maps as $map) {
			foreach ($map as $orig => $conv) {
				$this->aCharMap[$orig] = $conv;
				$this->sChars .= $orig;
			}
		}

	}

	/**
	 * Add new characters to the list. `$map` should be a hash.
	 * @param array $map
	 * @param string $sLanguage
	 */
	public function extendCharMap(array $map, string $sLanguage = '')
	{
		if (strlen($sLanguage) > 0) {
			$this->maps[$sLanguage] = $map;
		} else {
			$this->maps[] = $map;
		}
		$this->aCharMap = array();
		$this->sChars = '';
	}

	public function getMaps(): array
	{
		return $this->maps;
	}

	/**
	 * Append words to the remove list. Accepts either single words
	 * or an array of words.
	 * @param mixed $words
	 */
	public function extendRemovedWords(array $words)
	{
		$this->aRemoveWords = array_merge($this->aRemoveWords, $words);
	}

	/**
	 * Transliterates characters to their ASCII equivalents.
	 * $language specifies a priority for a specific language.
	 * The latter is useful if languages have different rules for the same character.
	 * @param string $text
	 * @param string $language
	 * @return string
	 */
	public function transliterate(string $text, string $language = ""): string
	{
		$this->setPriorityLanguage($language);
		$sMatch = '/[' . $this->sChars . ']/u';
		if (preg_match_all($sMatch, $text, $matches)) {
			for ($i = 0; $i < count($matches[0]); $i++) {
				$char = $matches[0][$i];
				if (isset($this->aCharMap[$char])) {
					$text = str_replace($char, $this->aCharMap[$char], $text);
				}
			}
		}
		return $text;
	}

	/**
	 * Filters a string, e.g., "Petty theft" to "petty-theft"
	 * @param string $text The text to return filtered
	 * @param int $maxLength The length (after filtering) of the string to be returned
	 * @param string $language The transliteration language, passed down to transliterate()
	 * @param bool $isFileName Whether there should be and additional filter considering this is a filename
	 * @param bool $use_remove_list Whether you want to remove specific elements previously set in $this->remove_list
	 * @param bool $lower_case Whether you want the filter to maintain casing or lowercase everything (default)
	 * @param bool $treat_underscore_as_space Treat underscore as space, so it will replaced with "-"
	 * @return string
	 */
	public function filter(
		string $text,
		int    $maxLength = 60,
		string $language = "",
		bool   $isFileName = false,
		bool   $use_remove_list = true,
		bool   $lower_case = true,
		bool   $treat_underscore_as_space = true
	): string
	{
		$text = $this->transliterate($text, $language);
		if ($use_remove_list) {
			// remove all these words from the string before urlifying
			$text = preg_replace('/\b(' . join('|', $this->aRemoveWords) . ')\b/i', '', $text);
		}
		$text = strtr("\n\r\t\v\0", '__---', $text);
		// if transliterate doesn't hit, the char will be stripped here
		$text = $isFileName ?
			preg_replace('/[^_\-.a-zA-Z0-9\s]/u', '', $text) :
			preg_replace('/[^\s_\-a-zA-Z0-9]/u', '', $text);
		if ($treat_underscore_as_space) {
			$text = str_replace('_', ' ', $text);             // treat underscores as spaces
		}
		$text = preg_replace('/^\s+|\s+$/u', '', $text);  // trim leading/trailing spaces
		$text = preg_replace('/[-\s]+/u', '-', $text);    // convert spaces to hyphens
		$text = preg_replace('/[.\-_]{2,}/', '-', $text); // Remove any consecutive dots or dashes or underscores
		if ($lower_case) {
			$text = strtolower($text);  // convert to lowercase
		}

		return trim(substr($text, 0, $maxLength), '-');     // trim to first $length chars
	}

	public function sanitizeFilename(
		string $text,
		int    $maxLength = 60,
		bool   $lower_case = true
	): string
	{
		return $this->filter($text, $maxLength, '', true, false, $lower_case, true);
	}

}
