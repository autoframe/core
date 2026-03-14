<?php

// see Autoframe\Core\String\AfrStr


class thfStrinGGGGGGGGGGGGGGGGGGGGGGGGGGGg
{

	/**
	 * @var int bitwise
	 */
	public static $iFlagsHtmlentities = ENT_QUOTES | ENT_SUBSTITUTE;

	/**
	 * @var int bitwise
	 */
	public static $iFlagsHtmlEntityDecode = ENT_QUOTES | ENT_SUBSTITUTE;

	/**
	 * @var string
	 */
	protected static $sHtmlEntitiesEncoding = 'UTF-8';

	/**
	 * @param string $str
	 * @param string $quot
	 * @return string
	 */
	function q(string $str, string $quot = "'"): string
	{
		$str = str_replace(chr(92), chr(92) . chr(92), $str); //chr(92)=\
		$str = str_replace($quot, $quot . $quot, $str);
		return $str;
	}

	/**
	 * @param string $sType UTF-8|ISO-8859-15|...
	 */
	static function setHtmlEntitiesEncoding($sType = '')
	{
		if (!$sType) {
			$sType = ini_get("default_charset");
		}
		self::$sHtmlEntitiesEncoding = $sType;
	}

	/**
	 * @return string
	 */
	static function getHtmlEntitiesEncoding(): string
	{
		if (!self::$sHtmlEntitiesEncoding) {
			self::setHtmlEntitiesEncoding();
		}
		return self::$sHtmlEntitiesEncoding;
	}

	/**
	 * @param string|array $saData
	 * @param string $sEncoding
	 * @return array|string
	 */
	static function h($saData, string $sEncoding = '')
	{
		if (!$sEncoding) {
			$sEncoding = self::getHtmlEntitiesEncoding();
		}
		if (is_array($saData)) {
			foreach ($saData as &$val) {
				$val = self::h($val, $sEncoding);
			}
		} elseif (is_string($saData)) {
			$saData = htmlentities($saData, self::$iFlagsHtmlentities, $sEncoding);
		}
		return $saData;
	}

	/**
	 * @param string $sValueStr
	 * @return string
	 */
	static function hXml(string $sValueStr): string
	{
		return str_replace(
			array('&', '<', '>', '"', "'",),
			array('&amp;', '&lt;', '&gt;', '&quot;', '&apos;',),
			$sValueStr);
	}

	/**
	 * Uh.
	 * @param string $sHtml
	 * @param string $sEncoding
	 * @return string
	 */
	public static function uh(string $sHtml, $sEncoding = ''): string
	{
		if (!$sEncoding) {
			$sEncoding = self::getHtmlEntitiesEncoding();
		}
		return html_entity_decode($sHtml, self::$iFlagsHtmlEntityDecode, $sEncoding);
	}

	/**
	 * Scurtez txt.
	 * @param string $txt
	 * @param int $len
	 * @param array $aIgnore
	 * @return string
	 */
	public static function scurtezTxt(string $txt, int $len, array $aIgnore = []): string
	{
		if (mb_strlen($txt) > $len && !in_array($txt, $aIgnore)) {
			$txt = mb_substr($txt, 0, $len - 3);
			for ($i = 0; $i < 3; $i++) {
				$sCheckText = htmlentities($txt);
				if (strlen($sCheckText) > 1) {
					break;
				}
				$txt = substr($txt, 0, -1);
			}
			$txt .= '...';
		}
		return $txt;
	}

	/**
	 * Prea.
	 */
	public static function prea($mixed): string
	{
		echo '<pre>' . print_r(self::h($mixed), true) . '</pre>';
	}

	/**
	 * Extract between.
	 */
	public static function extract_between($str = NULL, $start_char = NULL, $end_char = NULL)
	{ //extrag o expresie din interiorul unui string
		if ($str != NULL && $start_char !== NULL && $start_char != '') {
			if ($end_char === NULL || $end_char == '') {
				$end_char = $start_char;
			}
		} else {
			return array(NULL);
		}
		$out = array();
		$str = explode($start_char, $str);
		$parts = count($str);
		if ($parts < 2) {
			return array(NULL);
		}
		if ($start_char == $end_char) {
			$i = 0;
			while ($i < $parts) {
				$out[] = $str[$i + 1];
				$i += 2;
			}
		} else {
			unset($str[0]);
			foreach ($str as &$val) {
				$val = explode($end_char, $val);
				$val = $val[0];
				$out[] = $val;
			}
		}
		return $out;
	}

	/**
	 * Attr class.
	 */
	public static function attr_class($classes = '', $quot = '"')
	{
		if (is_string($classes) && strlen(trim($classes))) {
			return ' class=' . $quot . $classes . $quot . ' ';
		} elseif (is_array($classes) && count($classes)) {
			$out = '';
			foreach ($classes as $cls) {
				$out = trim($out . ' ' . $cls);
			}
			return ' class=' . $quot . $out . $quot . ' ';
		}
		return NULL;
	}

	/**
	 * Parse url get params.
	 */
	public static function parse_url_get_params($url = 'www.youtube.com/watch?v=q1uVg13zDwM&gg=1')
	{
		$urlp = parse_url($url);
		if ($urlp['query'] != '') {
			parse_str($urlp['query'], $output);
			return $output;
		} else return NULL;
	} //inversul:  http_build_query($array);

	//http_build_query($array);		parse_str($sursa,$dest_array);		$dest=parse_url();	 // Parse a URL and return its components

	/**
	 * Base64url encode.
	 */
	public static function base64url_encode($data) { return rtrim(strtr(base64_encode($data), '+/', '-_'), '='); }

	/**
	 * Base64url decode.
	 */
	public static function base64url_decode($data) { return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT)); }

	/**
	 * Base64 encode image.
	 */
	public static function base64_encode_image($filename, $filetype)
	{
		$imgbinary = fread(fopen($filename, "r"), filesize($filename));
		return 'data:image/' . $filetype . ';base64,' . base64_encode($imgbinary);
	}

	/* CSS: .logo {background: url("<?php echo base64_encode_image ('img/logo.png','png'); ?>") no-repeat; }
	<img src="<?php echo base64_encode_image ('img/logo.png','png'); ?>"/> 	*/

	/**
	 * Make 8b key.
	 */
	public static function make_8b_key($password = '')
	{
		global $_th_sv;
		return substr(md5((strlen($password) > 1 ? $password : $_th_sv['unique_key'])), 0, 8);
	}

	/**
	 * Encrypt.
	 */
	public static function encrypt($str, $b64url_enc = 1, $password = '')
	{
		$block = mcrypt_get_block_size('des', 'ecb');    # Add PKCS7 padding.
		if (($pad = $block - (strlen($str) % $block)) < $block) {
			$str .= str_repeat(chr($pad), $pad);
		}
		$out = mcrypt_encrypt(MCRYPT_DES, make_8b_key($password), $str, MCRYPT_MODE_ECB);
		if ($b64url_enc == 1) {
			$out = base64url_encode($out);
		}
		return $out;
	}

	/**
	 * Decrypt.
	 */
	public static function decrypt($str, $b64url_dec = 1, $password = '')
	{
		if ($b64url_dec == 1) {
			$str = base64url_decode($str);
		}
		$str = mcrypt_decrypt(MCRYPT_DES, make_8b_key($password), $str, MCRYPT_MODE_ECB);
		$block = mcrypt_get_block_size('des', 'ecb');    # Strip padding out.
		$pad = ord($str[($len = strlen($str)) - 1]);
		if ($pad && $pad < $block && preg_match('/' . chr($pad) . '{' . $pad . '}$/', $str)) {
			return substr($str, 0, strlen($str) - $pad);
		}
		return $str;
	}

	/**
	 * Array add.
	 */
	public static function array_add($arr = array(), $attributes = array(), $overwrite = 1)
	{
		if (is_array($attributes) && is_array($arr)) {
			foreach ($attributes as $key => $val) {
				if ($overwrite) {
					$arr[$key] = $val;
				} else {
					if (!isset($arr[$key])) {
						$arr[$key] = $val;
					}
				}//do not overwrite
			}
		}
		return $arr;
	}


	/**
	 * Crypt apr1 md5.
	 */
	public static function crypt_apr1_md5($plainpasswd)
	{
		/* .htaccess
		AuthName "Protected Area. Use Admin Credentials"
		AuthType Basic
		AuthUserFile C:/xampp/.htpasswd
		Require valid-user
		Require ip 192.168.10. 192.168.40

		#############
		#Deny from 192.168.10.1
		#AuthName "Protected Area. Use Admin Credentials"
		#AuthType Basic
		#AuthUserFile C:/xampp/.htpasswd
		#Require valid-user
		#Satisfy Any
		#ErrorDocument 401     /401.html

		.htpasswd
		USERNAME:$apr1$....

		 */
		$salt = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz0123456789"), 0, 8);
		//	$salt='eI9V5izx';
		$len = strlen($plainpasswd);
		$text = $plainpasswd . '$apr1$' . $salt;
		$bin = pack("H32", md5($plainpasswd . $salt . $plainpasswd));
		for ($i = $len; $i > 0; $i -= 16) {
			$text .= substr($bin, 0, min(16, $i));
		}
		for ($i = $len; $i > 0; $i >>= 1) {
			$text .= ($i & 1) ? chr(0) : $plainpasswd[0];
		}
		$bin = pack("H32", md5($text));
		for ($i = 0; $i < 1000; $i++) {
			$new = ($i & 1) ? $plainpasswd : $bin;
			if ($i % 3) $new .= $salt;
			if ($i % 7) $new .= $plainpasswd;
			$new .= ($i & 1) ? $bin : $plainpasswd;
			$bin = pack("H32", md5($new));
		}
		$tmp = null;
		for ($i = 0; $i < 5; $i++) {
			$k = $i + 6;
			$j = $i + 12;
			if ($j == 16) $j = 5;
			$tmp = $bin[$i] . $bin[$k] . $bin[$j] . $tmp;
		}
		$tmp = chr(0) . chr(0) . $bin[11] . $tmp;
		$tmp = strtr(strrev(substr(base64_encode($tmp), 2)),
			"ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/",
			"./0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz");

		return "$" . "apr1" . "$" . $salt . "$" . $tmp;
	}


	/**
	 * Write htpasswd file.
	 */
	public static function write_htpasswd_file($user_pass = array())
	{
		$pw = '';
		foreach ($user_pass as $user => $pass) {
			$pw .= $user . ':' . crypt_apr1_md5($pass) . "\r\n";
		}
		if (!$pw || count($user_pass) < 1) {
			die('NO PASSWORD SET!');
		}
		file_put_contents('./.htpasswd', $pw);

		$htaccess_add = '
	#Deny from 192.168.10.1		#not required. use only for internal network gateway
	AuthName "Protected Area. Use Admin Credentials"
	AuthType Basic
	AuthUserFile ' . dirname($_SERVER['SCRIPT_FILENAME']) . '/.htpasswd
	Require valid-user
	#Require ip 192.168.10. 192.168.40 #allow ip range without password
	#Satisfy Any		#not required
	ErrorDocument 401 "<h1>Use Admin Credentials to autentificate!</h1><br />If this problem persists, contact the administrator!"
	<Files ~ "^\.(htaccess|htpasswd)$">
	deny from all
	</Files>
	';
		//create once the htaccess file
		if (!is_file('./.htaccess')) {
			file_put_contents('./.htaccess', $htaccess_add);
		}
		return 1;
	}

	/**
	 * Array remove.
	 */
	public static function array_remove($arr = array(), $exclude = NULL)
	{ //$exclude='$key3'; OR  $exclude=array('key1','key2');
		$new_arr = array();
		if (is_array($arr)) {
			if (is_array($exclude)) {
				foreach ($exclude as $val) {
					$tmp[$val] = 1;
				}
				foreach ($arr as $key => $val) {
					if (!isset($tmp[$key])) {
						$new_arr[$key] = $val;
					}
				}
			} elseif ((is_string($exclude) || is_numeric($exclude)) && strlen($exclude) > 0) {
				foreach ($arr as $key => $val) {
					if ($key != $exclude) {
						$new_arr[$key] = $val;
					}
				}
			} else {
				$new_arr = $arr;
			}
		}
		unset($arr);
		return $new_arr;
	}

	/**
	 * Construct get.
	 */
	public static function construct_get($arr = array(), $initial_link = '', $urlencode = 1)
	{    //http_build_query
		$initial_link = explode('?', $initial_link);
		$initial_link = $initial_link[0];
		if (count($arr) > 0) {
			$i = 0;
			foreach ($arr as $key => $val) {
				if ($val != '') {
					$initial_link .= ($i == 0 ? '?' : '&');
					$initial_link .= $key . '=';
					if ($urlencode) {
						$initial_link .= urlencode($val);
					} else {
						$initial_link .= $val;
					}
					$i++;
				}
			}
		}
		return $initial_link;
	}

	/**
	 * Num2excel.
	 */
	public static function num2excel($n)
	{
		for ($r = ""; $n >= 0; $n = intval($n / 26) - 1) $r = chr($n % 26 + 0x41) . $r;
		return $r;
	}//excel nr to A,B,C


	/**
	 * Deduce line number.
	 */
	public static function deduce_line_number($str, $chars_per_line, $nl = '<br />')
	{ //line counter
		$l = 0;
		$str = explode($nl, $str);
		foreach ($str as $s) {
			$line = ceil(strlen($s) / $chars_per_line);
			if ($line) {
				$l += $line;
			} else {
				$l += 1;
			}
		}
		return $l;
	}


	//SEO:
	/**
	 * Titlu.
	 */
	public static function titlu($str, $max = 80) { return scurtez_txt($str, $max); } //nu e nevoie sa i folosesti

	/**
	 * Descriere.
	 */
	public static function descriere($str, $max = 160) { return str_replace('  ', ' ', scurtez_txt($str, $max)); }

	/**
	 * Keywords.
	 */
	public static function keywords($str, $max_keywords = 35, $mai_mari = 1)
	{//mai mari=lungimea minima a cuvintelor;
		$max_duplicates_exceprion = 10;
		$str = str_replace(array('.', ',', ';', ':', '!', '?', '"', "'", '(', ')', '[', ']', '', '{', '}', '  ', '   ', '	'), ' ', $str);
		$str = explode(' ', $str, $max_keywords + $max_duplicates_exceprion);
		$str[$max_keywords - 1 + $max_duplicates_exceprion] = '';
		$out = '';
		$duplicate = array();
		$k = 0;
		foreach ($str as $val) {
			if ($max_keywords > $k && strlen($val) >= $mai_mari && $duplicate[s2($val)] != 'k') {
				$out .= $val . ', ';
				$duplicate[s2($val)] = 'k';
				$k++;
			}
		}
		return substr($out, 0, -2);
	}
	// http://www.google.com/support/webmasters/bin/answer.py?answer=185417
	//$breadcrumb[]=array('/','OnBreak.ro','alt title'); // link nume link descriere
	/**
	 * Breadcrumb.
	 */
	public static function breadcrumb($array, $last_element_is_link = 1)
	{//v2.0
		global $pagina_generala_produse, $pagina_produs;
		if (isset($pagina_generala_produse) && $pagina_generala_produse == 'da' || isset($pagina_produs) && $pagina_produs == 'da') {
			$stoc_picture = "background:url('/css/img/stoc_real.png') no-repeat 99% center;";
		} else {
			$stoc_picture = '';
		}
		if (!is_array($array)) {
			return 0;
		}
		echo '<div xmlns:v="http://rdf.data-vocabulary.org/#" style="font-size:11px;' . $stoc_picture . '">' . PHP_EOL;
		$nivele = count($array);
		for ($i = 0; $i < $nivele; $i++) {
			if (($array[$i][2] == '')) {
				$array[$i][2] = $array[$i][1];
			}
			if ($i + 1 < $nivele + $last_element_is_link) {
				echo str_repeat('	', $i) . '<span typeof="v:Breadcrumb">' . PHP_EOL;
				echo str_repeat('	', $i) . '<a href="' . h($array[$i][0]) . '" rel="v:url" property="v:title" title="' . h($array[$i][2]) . '" class="breadcrumb">';
				echo h($array[$i][1]);
				echo '</a> ' . PHP_EOL;
			} else {
				echo str_repeat('	', $i) . '<strong>' . h($array[$i][1]) . '</strong>';
			}//ultimul bredcrumb care nu este link
			if ($i + 1 < $nivele) {
				echo str_repeat('	', $i) . '&raquo; ';
			}
			if ($i + 2 - $last_element_is_link < $nivele) {
				echo '<span rel="v:child">' . PHP_EOL;
			}
		}
		echo str_repeat('</span>', ($i - 1) * 2 + ($last_element_is_link == 0 ? -1 : 1));
		echo '</div>' . PHP_EOL;
		return 1;
	}

	/**
	 * Round decimal.
	 */
	public static function round_decimal($float, $decimals = 2)
	{
		if (is_numeric($float)) {
			$tmp = explode('.', $float);
			if (isset($tmp[1])) {
				if (strlen($tmp[1]) < $decimals) {
					$tmp[1] .= str_repeat('0', ($decimals - strlen($tmp[1])));
				} elseif (strlen($tmp[1]) > $decimals) {
					$tmp[1] = substr($tmp[1], 0, $decimals);
				} elseif (strlen($tmp[1]) == $decimals) {
				} else {
					$tmp[1] = str_repeat('0', $decimals);
				}
			} else {
				$tmp[1] = str_repeat('0', $decimals);
			}
			$float = $tmp[0] . '.' . $tmp[1];
		}
		return $float;
	}

	/**
	 * Diacritice fix.
	 */
	public static function diacritice_fix($str)
	{
		$str = str_replace('ÅŸ', 'ş', $str);
		$str = str_replace('Å£', 'ţ', $str);
		$str = str_replace('Äƒ', 'ă', $str);
		$str = str_replace('Ã®', 'î', $str);
		$str = str_replace('Ã¢', 'â', $str);
		$str = str_replace('Åž', 'Ş', $str);
		$str = str_replace('Å¢', 'Ţ', $str);
		$str = str_replace('Ä‚', 'Ă', $str);
		$str = str_replace('ÃŽ', 'Î', $str);
		$str = str_replace('Ã‚', 'Â', $str);
		return $str;
	}

	/**
	 * Diacritice fix v2.
	 */
	public static function diacritice_fix_v2($str)
	{
		$str = str_replace('&Aring;&Yuml;', 'ş', $str);
		$str = str_replace('&Aring;&pound;', 'ţ', $str);
		$str = str_replace('&Auml;&fnof;', 'ă', $str);
		$str = str_replace('&Atilde;&reg;', 'î', $str);
		$str = str_replace('&Atilde;&cent;', 'â', $str);
		$str = str_replace('&Aring;ž', 'Ş', $str);
		$str = str_replace('&Aring;&cent;', 'Ţ', $str);
		$str = str_replace('&Auml;&sbquo;', 'Ă', $str);
		$str = str_replace('&Atilde;Ž', 'Î', $str);
		$str = str_replace('&Atilde;&sbquo;', 'Â', $str);
		return $str;
	}

	/**
	 * Substri count.
	 */
	public static function substri_count($haystack, $needle) { return substr_count(strtoupper($haystack), strtoupper($needle)); }

	/**
	 * Html table to array.
	 */
	public static function html_table_to_array($html)
	{// nu suporta nested tables (adica sa aiba copii tabel) si suporta numai tabelele cu nr constant de coloane
		$out = array();
		$tables = extract_between($html, '<table', '</table>');
		foreach ($tables as $ti => $table) {
			$rows = extract_between($table, '<tr', '</tr>');
			foreach ($rows as $ri => $row) {
				$row = str_replace(array('<th', '</th>', "\r", "\n", '&nbsp;'), array('<td', '</td>', NULL, ' ', ' '), $row);//merge header with rows
				$cels = extract_between($row, '<td', '</td>');
				foreach ($cels as $ci => $cell) {
					$s = trim(uh(strip_tags('<p' . $cell . '</p>'))); //fix invalid strip start point
					$out[$ti][$ri][$ci] = filter_var($s, FILTER_SANITIZE_STRING);
				}
			}
		}
		return $out;
	}


	/**
	 * Clear spaces.
	 */
	public static function clear_spaces($str)
	{
		$str = trim(str_replace(array("\r\n", "\r", "\n", '	'), array(' ', ' ', ' ', ' '), $str));
		return str_replace(array('     ', '    ', '   ', '  '), array(' ', ' ', ' ', ' '), $str);
	}



	//!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
	//http://www.portabilitate.ro/getnumber.aspx?lang=ro&number=0742601660
	//!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
	/**
	 * Is mobile.
	 */
	public static function is_mobile($tel)
	{
		if (strlen($tel) != 12) {
			$tel = '';
		}
		if (!is_numeric($tel)) {
			$tel = '';
		} //determin daca e nr de tel mobil din romania
		if (substr_count($tel, '+407') != 1) {
			$tel = '';
		}
		if ($tel == '') {
			return 0;
		} else {
			return 1;
		}
	}

	/**
	 * Is tel.
	 */
	public static function is_tel($tel)
	{
		if (strlen($tel) < 13) {
			$tel = '';
		}
		if (!is_numeric($tel)) {
			$tel = '';
		} //determin daca e  tel din romania
		if (substr_count($tel, '+40') != 1) {
			$tel = '';
		}
		if ($tel == '') {
			return 0;
		} else {
			return 1;
		}
	}

	/**
	 * Validate tel.
	 */
	public static function validate_tel($tel)
	{//prefixe 02 romtelecom, 03 upc, rds, rcs, zaptelfix, vdf acasa 07 mobil;
		if ($tel[0] == '4') {
			$tel = '+' . $tel;
		}//fix convertion to number
		if (strlen($tel) < 10) {
			return '';
		}
		$replace = '.,- \'"*%#`	~()[\]|<>?/';
		for ($i = 0; $i < strlen($replace); $i++) {
			$tel = str_replace($replace[$i], NULL, $tel);
		}
		if (!is_numeric($tel)) {
			return '';
		}
		if (strlen($tel) > 13) {
			return '';
		}

		if (strlen($tel) == 10) {//scurt fara +4
			if (substr($tel, 0, 2) == '07') {
				$tel = '+4' . $tel;
			} elseif (substr($tel, 0, 2) == '02') {
				$tel = '+4' . $tel;
			} elseif (substr($tel, 0, 2) == '03') {
				$tel = '+4' . $tel;
			} else {
				return '';
			}
		} elseif (strlen($tel) == 11) {
			if (substr($tel, 0, 2) == '02') {
				$tel = '+4' . $tel;
			} elseif (substr($tel, 0, 2) == '03') {
				$tel = '+4' . $tel;
			} else {
				return '';
			}
		} elseif (strlen($tel) == 12) {
			if (substr($tel, 0, 2) != '+4') {
				return '';
			}
		} elseif (strlen($tel) == 13) {
			if (substr($tel, 0, 4) == '+402') {
			} elseif (substr($tel, 0, 4) == '+403') {
			} else {
				return '';
			}
		}
		return $tel;
	}

	/**
	 * Tel spaces.
	 */
	public static function tel_spaces($nr)
	{
		$out = NULL;
		for ($i = strlen($start) - 1; $i > -1; $i--) {
			$out = $out . $nr[$i];
			if ($i == $start - 3 || $i == $start - 6) {
				$out .= ' ';
			}
		}
		return strrev($out);
	}


	/**
	 * Utf8 to entities.
	 */
	public static function UTF8ToEntities($string)
	{
		/* note: apply htmlspecialchars if desired /before/ applying this function
		/* Only do the slow convert if there are 8-bit characters */
		/* avoid using 0xA0 (\240) in ereg ranges. RH73 does not like that */
		if (!ereg("[\200-\237]", $string) and !ereg("[\241-\377]", $string)) return $string;
		// reject too-short sequences
		$string = preg_replace("/[\302-\375]([\001-\177])/", "&#65533;\\1", $string);
		$string = preg_replace("/[\340-\375].([\001-\177])/", "&#65533;\\1", $string);
		$string = preg_replace("/[\360-\375]..([\001-\177])/", "&#65533;\\1", $string);
		$string = preg_replace("/[\370-\375]...([\001-\177])/", "&#65533;\\1", $string);
		$string = preg_replace("/[\374-\375]....([\001-\177])/", "&#65533;\\1", $string);

		// reject illegal bytes & sequences
		$string = preg_replace("/[\300-\301]./", "&#65533;", $string);        // 2-byte characters in ASCII range
		$string = preg_replace("/\364[\220-\277]../", "&#65533;", $string);    // 4-byte illegal codepoints (RFC 3629)
		$string = preg_replace("/[\365-\367].../", "&#65533;", $string);    // 4-byte illegal codepoints (RFC 3629)
		$string = preg_replace("/[\370-\373]..../", "&#65533;", $string);    // 5-byte illegal codepoints (RFC 3629)
		$string = preg_replace("/[\374-\375]...../", "&#65533;", $string);    // 6-byte illegal codepoints (RFC 3629)
		$string = preg_replace("/[\376-\377]/", "&#65533;", $string);        // undefined bytes
		$string = preg_replace("/[\302-\364]{2,}/", "&#65533;", $string);    // reject consecutive start-bytes

		$string = preg_replace("/([\360-\364])([\200-\277])([\200-\277])([\200-\277])/e",
			"'&#'.((ord('\\1')&7)<<18 | (ord('\\2')&63)<<12 |" . " (ord('\\3')&63)<<6 | (ord('\\4')&63)).';'", $string);// decode four byte unicode chars

		$string = preg_replace("/([\340-\357])([\200-\277])([\200-\277])/e",
			"'&#'.((ord('\\1')&15)<<12 | (ord('\\2')&63)<<6 | (ord('\\3')&63)).';'", $string);// decode three byte unicode characters

		$string = preg_replace("/([\300-\337])([\200-\277])/e",
			"'&#'.((ord('\\1')&31)<<6 | (ord('\\2')&63)).';'", $string); // decode two byte unicode characters

		$string = preg_replace("/[\200-\277]/", "&#65533;", $string); // reject leftover continuation bytes
		return $string;
	}

	/**
	 * Get coordonates by address.
	 */
	public static function get_coordonates_by_address($adresa)
	{
		return json_decode(file_get_contents('https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($adresa)), true);
	}

	/**
	 * Get lang long by address.
	 */
	public static function get_lang_long_by_address($adresa)
	{
		$a = get_coordonates_by_address($adresa);
		return array('lat' => $a['results'][0]['geometry']['location']['lat'], 'lng' => $a['results'][0]['geometry']['location']['lng']);
	}

	/**
	 * Embed map by address.
	 */
	public static function embed_map_by_address($adresa, $api_key = '', $width = '100%', $height = '400px', $border = 'none', $fullscreen = 'allowfullscreen')
	{
		echo '<iframe style="border:' . $border . ';width:' . $width . ';height:' . $height . ';" src="https://www.google.com/maps/embed/v1/search?key=' . $api_key . '&q=' . urlencode($adresa) . '" ' . $fullscreen . '></iframe>';
		return $a;
	}

	/**
	 * Embed streetview by address.
	 */
	public static function embed_streetview_by_address($adresa, $api_key = '', $width = '100%', $height = '400px', $border = 'none', $fullscreen = 'allowfullscreen', $heading = 210, $pinch = 10, $fov = 35)
	{
		$a = get_lang_long_by_address($adresa);
		echo '<iframe style="border:' . $border . ';width:' . $width . ';height:' . $height . ';" src="https://www.google.com/maps/embed/v1/streetview?key=' . $api_key . '&location=' . $a['lat'] . ',' . $a['lng'] . '&heading=' . $heading . '&pitch=' . $pinch . '&fov=' . $fov . '" ' . $fullscreen . '></iframe>';
		return $a;
	}

	/**
	 * Distance.
	 */
	public static function distance($lat1, $lng1, $lat2, $lng2, $miles = false)
	{//distanta dintre 2 coordonate in km
		$pi80 = M_PI / 180;
		$lat1 *= $pi80;
		$lng1 *= $pi80;
		$lat2 *= $pi80;
		$lng2 *= $pi80;
		$r = 6372.797; // mean radius of Earth in km
		$dlat = $lat2 - $lat1;
		$dlng = $lng2 - $lng1;
		$a = sin($dlat / 2) * sin($dlat / 2) + cos($lat1) * cos($lat2) * sin($dlng / 2) * sin($dlng / 2);
		$c = 2 * atan2(sqrt($a), sqrt(1 - $a));
		$km = $r * $c;
		return ($miles ? ($km * 0.621371192) : $km);
	}

	/**
	 * Coordonate dec to grade.
	 */
	public static function coordonate_dec_to_grade($coord, $ce_intorc = 'string')
	{//bag coordonate 45.85634746757 scot 45 grade x min y sec ....
		$out = array();
		$func = ($coord < 0) ? 'ceil' : 'floor';
		$grade = $func($coord);
		$out[] = $grade;
		$min_aprox = ($coord - $grade) * 60;
		$min = $func($min_aprox);
		$out[] = $min;
		$sec_aprox = ($min_aprox - $min) * 60;
		$sec = $func($sec_aprox);
		$out[] = $sec;
		$ms_aprox = ($sec_aprox - $sec) * 60;
		$ms = $func($ms_aprox);
		$out[] = $ms;
		$mms_aprox = ($ms_aprox - $ms) * 60;
		$mms = $func($mms_aprox);
		$out[] = $mms;
		$mmms_aprox = ($mms_aprox - $mms) * 60;
		$mmms = $func($mmms_aprox);
		$out[] = $mmms;

		$new_coord = $grade . '.';
		for ($i = 1; $i < count($out); $i++) {
			if ($out[$i] < 0) {
				$out[$i] -= $out[$i] * 2;
			}
			$new_coord .= $out[$i];
		}
		if ($ce_intorc != 'string') {
			return $out;
		} else {
			return $new_coord;
		}
	}


}


