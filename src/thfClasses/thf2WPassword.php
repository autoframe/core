<?php
	class thf2WPassword {

		static function encrypt($key, $payload) {
			$iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
			$encrypted = openssl_encrypt($payload, 'aes-256-cbc', $key, 0, $iv);
			return base64_encode($encrypted . '::' . $iv);
		}
		

		static function decrypt($key, $garble) {
			list($encrypted_data, $iv) = explode('::', base64_decode($garble), 2);
			return openssl_decrypt($encrypted_data, 'aes-256-cbc', $key, 0, $iv);
		}
	}



class Openssl2WPassword {

	const SESS_CIPHER = 'aes-128-cbc';

	/**
	 * Encrypts the session ID and returns it as a base 64 encoded string.
	 *
	 * @param $session_id
	 * @return string
	 */
	public function encrypt($session_id) {
		// Get the MD5 hash salt as a key.
		$key = $this->_getSalt();
		// For an easy iv, MD5 the salt again.
		$iv = $this->_getIv();
		// Encrypt the session ID.
		$ciphertext = openssl_encrypt($session_id, self::SESS_CIPHER, $key, $options=OPENSSL_RAW_DATA, $iv);
		// Base 64 encode the encrypted session ID.
		$encryptedSessionId = base64_encode($ciphertext);
		// Return it.
		return $encryptedSessionId;
	}

	/**
	 * Decrypts a base 64 encoded encrypted session ID back to its original form.
	 *
	 * @param $encryptedSessionId
	 * @return string
	 */
	public function decrypt($encryptedSessionId) {
		// Get the Drupal hash salt as a key.
		$key = $this->_getSalt();
		// Get the iv.
		$iv = $this->_getIv();
		// Decode the encrypted session ID from base 64.
		$decoded = base64_decode($encryptedSessionId, TRUE);
		// Decrypt the string.
		$decryptedSessionId = openssl_decrypt($decoded, self::SESS_CIPHER, $key, $options=OPENSSL_RAW_DATA, $iv);
		// Trim the whitespace from the end.
		$session_id = rtrim($decryptedSessionId, '\0');
		// Return it.
		return $session_id;
	}

	public function _getIv() {
		$ivlen = openssl_cipher_iv_length(self::SESS_CIPHER);
		return substr(md5($this->_getSalt()), 0, $ivlen);
	}

	public function _getSalt() {
		return $this->drupal->drupalGetHashSalt();
	}

}