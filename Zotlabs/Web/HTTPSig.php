<?php

namespace Zotlabs\Web;

/**
 * @brief Implements HTTP Signatures per draft-cavage-http-signatures-07.
 *
 * @see https://tools.ietf.org/html/draft-cavage-http-signatures-07
 */
class HTTPSig {

	/**
	 * @brief RFC5843
	 *
	 * @see https://tools.ietf.org/html/rfc5843
	 *
	 * @param string $body The value to create the digest for
	 * @param boolean $set (optional, default true)
	 *   If set send a Digest HTTP header
	 * @return string The generated digest of $body
	 */
	static function generate_digest($body, $set = true) {
		$digest = base64_encode(hash('sha256', $body, true));

		if($set) {
			header('Digest: SHA-256=' . $digest);
		}
		return $digest;
	}

	// See draft-cavage-http-signatures-08

	static function verify($data,$key = '') {

		$body      = $data;
		$headers   = null;
		$spoofable = false;

		$result = [
			'signer'         => '',
			'header_signed'  => false,
			'header_valid'   => false,
			'content_signed' => false,
			'content_valid'  => false
		];

		// decide if $data arrived via controller submission or curl
		if(is_array($data) && $data['header']) {
			if(! $data['success'])
				return $result;

			$h = new \Zotlabs\Web\HTTPHeaders($data['header']);
			$headers = $h->fetcharr();
			$body = $data['body'];
		}

		else {
			$headers = [];
			$headers['(request-target)'] =
				strtolower($_SERVER['REQUEST_METHOD']) . ' ' .
				$_SERVER['REQUEST_URI'];
			$headers['content-type'] = $_SERVER['CONTENT_TYPE'];

			foreach($_SERVER as $k => $v) {
				if(strpos($k,'HTTP_') === 0) {
					$field = str_replace('_','-',strtolower(substr($k,5)));
					$headers[$field] = $v;
				}
			}
		}

		// logger('SERVER: ' . print_r($_SERVER,true), LOGGER_ALL);

		// logger('headers: ' . print_r($headers,true), LOGGER_ALL);

		$sig_block = null;

		if(array_key_exists('signature',$headers)) {
			$sig_block = self::parse_sigheader($headers['signature']);
		}
		elseif(array_key_exists('authorization',$headers)) {
			$sig_block = self::parse_sigheader($headers['authorization']);
		}

		if(! $sig_block) {
			logger('no signature provided.');
			return $result;
		}

		// Warning: This log statement includes binary data
		// logger('sig_block: ' . print_r($sig_block,true), LOGGER_DATA);

		$result['header_signed'] = true;

		$signed_headers = $sig_block['headers'];
		if(! $signed_headers)
			$signed_headers = [ 'date' ];

		$signed_data = '';
		foreach($signed_headers as $h) {
			if(array_key_exists($h,$headers)) {
				$signed_data .= $h . ': ' . $headers[$h] . "\n";
			}
			if(strpos($h,'.')) {
				$spoofable = true;
			}
		}
		$signed_data = rtrim($signed_data,"\n");

		$algorithm = null;
		if($sig_block['algorithm'] === 'rsa-sha256') {
			$algorithm = 'sha256';
		}
		if($sig_block['algorithm'] === 'rsa-sha512') {
			$algorithm = 'sha512';
		}

		if($key && function_exists($key)) {
			$result['signer'] = $sig_block['keyId'];
			$key = $key($sig_block['keyId']);
		}

		if(! $key) {
			$result['signer'] = $sig_block['keyId'];
			$key = self::get_activitypub_key($sig_block['keyId']);
		}

		if(! $key)
			return $result;

		$x = rsa_verify($signed_data,$sig_block['signature'],$key,$algorithm);

		logger('verified: ' . $x, LOGGER_DEBUG);

		if(! $x)
			return $result;

		if(! $spoofable)
			$result['header_valid'] = true;

		if(in_array('digest',$signed_headers)) {
			$result['content_signed'] = true;
			$digest = explode('=', $headers['digest']);
			if($digest[0] === 'SHA-256')
				$hashalg = 'sha256';
			if($digest[0] === 'SHA-512')
				$hashalg = 'sha512';

			// The explode operation will have stripped the '=' padding, so compare against unpadded base64
			if(rtrim(base64_encode(hash($hashalg,$body,true)),'=') === $digest[1]) {
				$result['content_valid'] = true;
			}
		}


		if(in_array('x-zot-digest',$signed_headers)) {
			$result['content_signed'] = true;
			$digest = explode('=', $headers['x-zot-digest']);
			if($digest[0] === 'SHA-256')
				$hashalg = 'sha256';
			if($digest[0] === 'SHA-512')
				$hashalg = 'sha512';

			// The explode operation will have stripped the '=' padding, so compare against unpadded base64
			if(rtrim(base64_encode(hash($hashalg,$_POST['data'],true)),'=') === $digest[1]) {
				$result['content_valid'] = true;
			}
		}

		logger('Content_Valid: ' . (($result['content_valid']) ? 'true' : 'false'));

		return $result;
	}

	/**
	 * @brief
	 *
	 * @param string $id
	 * @return boolean|string
	 *   false if no pub key found, otherwise return the pub key
	 */
	function get_activitypub_key($id) {

		if(strpos($id,'acct:') === 0) {
			$x = q("select xchan_pubkey from xchan left join hubloc on xchan_hash = hubloc_hash where hubloc_addr = '%s' limit 1",
				dbesc(str_replace('acct:','',$id))
			);
		}
		else {
			$x = q("select xchan_pubkey from xchan where xchan_hash = '%s' and xchan_network = 'activitypub' ",
				dbesc($id)
			);
		}

		if($x && $x[0]['xchan_pubkey']) {
			return ($x[0]['xchan_pubkey']);
		}

		if(function_exists('as_fetch'))
			$r = as_fetch($id);

		if($r) {
			$j = json_decode($r,true);

			if(array_key_exists('publicKey',$j) && array_key_exists('publicKeyPem',$j['publicKey'])) {
				if((array_key_exists('id',$j['publicKey']) && $j['publicKey']['id'] !== $id) && $j['id'] !== $id)
					return false;

				return($j['publicKey']['publicKeyPem']);
			}
		}

		return false;
	}







	/**
	 * @brief
	 *
	 * @param string $request
	 * @param array $head
	 * @param string $prvkey
	 * @param string $keyid (optional, default 'Key')
	 * @param boolean $send_headers (optional, default false)
	 *   If set send a HTTP header
	 * @param boolean $auth (optional, default false)
	 * @param string $alg (optional, default 'sha256')
	 * @param string $crypt_key (optional, default null)
	 * @param string $crypt_algo (optional, default 'aes256ctr')
	 * @return array
	 */
	static function create_sig($request, $head, $prvkey, $keyid = 'Key', $send_headers = false, $auth = false,
			$alg = 'sha256', $crypt_key = null, $crypt_algo = 'aes256ctr') {

		$return_headers = [];

		if($alg === 'sha256') {
			$algorithm = 'rsa-sha256';
		}
		if($alg === 'sha512') {
			$algorithm = 'rsa-sha512';
		}

		$x = self::sign($request,$head,$prvkey,$alg);

		$headerval = 'keyId="' . $keyid . '",algorithm="' . $algorithm
			. '",headers="' . $x['headers'] . '",signature="' . $x['signature'] . '"';

		if($crypt_key) {
			$x = crypto_encapsulate($headerval,$crypt_key,$crypt_algo);
			$headerval = 'iv="' . $x['iv'] . '",key="' . $x['key'] . '",alg="' . $x['alg'] . '",data="' . $x['data'] . '"';
		}

		if($auth) {
			$sighead = 'Authorization: Signature ' . $headerval;
		}
		else {
			$sighead = 'Signature: ' . $headerval;
		}

		if($head) {
			foreach($head as $k => $v) {
				if($send_headers) {
					header($k . ': ' . $v);
				}
				else {
					$return_headers[] = $k . ': ' . $v;
				}
			}
		}
		if($send_headers) {
			header($sighead);
		}
		else {
			$return_headers[] = $sighead;
		}

		return $return_headers;
	}

	/**
	 * @brief
	 *
	 * @param string $request
	 * @param array  $head
	 * @param string $prvkey
	 * @param string $alg (optional) default 'sha256'
	 * @return array
	 */
	static function sign($request, $head, $prvkey, $alg = 'sha256') {

		$ret = [];

		$headers = '';
		$fields  = '';
		if($request) {
			$headers = '(request-target)' . ': ' . trim($request) . "\n";
			$fields = '(request-target)';
		}

		if($head) {
			foreach($head as $k => $v) {
				$headers .= strtolower($k) . ': ' . trim($v) . "\n";
				if($fields)
					$fields .= ' ';

				$fields .= strtolower($k);
			}
			// strip the trailing linefeed
			$headers = rtrim($headers,"\n");
		}

		$sig = base64_encode(rsa_sign($headers,$prvkey,$alg));

		$ret['headers']   = $fields;
		$ret['signature'] = $sig;

		return $ret;
	}

	/**
	 * @brief
	 *
	 * @param string $header
	 * @return array associate array with
	 *   - \e string \b keyID
	 *   - \e string \b algorithm
	 *   - \e array  \b headers
	 *   - \e string \b signature
	 */
	static function parse_sigheader($header) {

		$ret = [];
		$matches = [];

		// if the header is encrypted, decrypt with (default) site private key and continue

		if(preg_match('/iv="(.*?)"/ism',$header,$matches))
			$header = self::decrypt_sigheader($header);

		if(preg_match('/keyId="(.*?)"/ism',$header,$matches))
			$ret['keyId'] = $matches[1];
		if(preg_match('/algorithm="(.*?)"/ism',$header,$matches))
			$ret['algorithm'] = $matches[1];
		if(preg_match('/headers="(.*?)"/ism',$header,$matches))
			$ret['headers'] = explode(' ', $matches[1]);
		if(preg_match('/signature="(.*?)"/ism',$header,$matches))
			$ret['signature'] = base64_decode(preg_replace('/\s+/','',$matches[1]));

		if(($ret['signature']) && ($ret['algorithm']) && (! $ret['headers']))
			$ret['headers'] = [ 'date' ];

 		return $ret;
	}


	/**
	 * @brief
	 *
	 * @param string $header
	 * @param string $prvkey (optional), if not set use site private key
	 * @return array|string associative array, empty string if failue
	 *   - \e string \b iv
	 *   - \e string \b key
	 *   - \e string \b alg
	 *   - \e string \b data
	 */
	static function decrypt_sigheader($header, $prvkey = null) {

		$iv = $key = $alg = $data = null;

		if(! $prvkey) {
			$prvkey = get_config('system', 'prvkey');
		}

		$matches = [];

		if(preg_match('/iv="(.*?)"/ism',$header,$matches))
			$iv = $matches[1];
		if(preg_match('/key="(.*?)"/ism',$header,$matches))
			$key = $matches[1];
		if(preg_match('/alg="(.*?)"/ism',$header,$matches))
			$alg = $matches[1];
		if(preg_match('/data="(.*?)"/ism',$header,$matches))
			$data = $matches[1];

		if($iv && $key && $alg && $data) {
			return crypto_unencapsulate([ 'iv' => $iv, 'key' => $key, 'alg' => $alg, 'data' => $data ] , $prvkey);
		}

		return '';
	}

}
