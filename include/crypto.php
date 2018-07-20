<?php /** @file */

require_once('library/ASNValue.class.php');
require_once('library/asn1.php');

function rsa_sign($data,$key,$alg = 'sha256') {
	if(! $key)
		return 'no key';
	$sig = '';
	if(intval(OPENSSL_ALGO_SHA256) && $alg === 'sha256')
		$alg = OPENSSL_ALGO_SHA256;
	openssl_sign($data,$sig,$key,$alg);
	return $sig;
}

function rsa_verify($data,$sig,$key,$alg = 'sha256') {

	if(! $key)
		return false;

	if(intval(OPENSSL_ALGO_SHA256) && $alg === 'sha256')
		$alg = OPENSSL_ALGO_SHA256;
	$verify = @openssl_verify($data,$sig,$key,$alg);

	if($verify === (-1)) {
		while($msg = openssl_error_string())
			logger('openssl_verify: ' . $msg,LOGGER_NORMAL,LOG_ERR);
		btlogger('openssl_verify: key: ' . $key, LOGGER_DEBUG, LOG_ERR); 
	}

	return (($verify > 0) ? true : false);
}


function AES256CBC_encrypt($data,$key,$iv) {

	return openssl_encrypt($data,'aes-256-cbc',str_pad($key,32,"\0"),OPENSSL_RAW_DATA,str_pad($iv,16,"\0"));
}

function AES256CBC_decrypt($data,$key,$iv) {

	return openssl_decrypt($data,'aes-256-cbc',str_pad($key,32,"\0"),OPENSSL_RAW_DATA,str_pad($iv,16,"\0"));
}


function AES128CBC_encrypt($data,$key,$iv) {
	$key = substr($key,0,16);
	$iv = substr($iv,0,16);
	return openssl_encrypt($data,'aes-128-cbc',str_pad($key,16,"\0"),OPENSSL_RAW_DATA,str_pad($iv,16,"\0"));
}

function AES128CBC_decrypt($data,$key,$iv) {
	$key = substr($key,0,16);
	$iv = substr($iv,0,16);
	return openssl_decrypt($data,'aes-128-cbc',str_pad($key,16,"\0"),OPENSSL_RAW_DATA,str_pad($iv,16,"\0"));
}


function AES256CTR_encrypt($data,$key,$iv) {
	$key = substr($key,0,32);
	$iv = substr($iv,0,16);
	return openssl_encrypt($data,'aes-256-ctr',str_pad($key,32,"\0"),OPENSSL_RAW_DATA,str_pad($iv,16,"\0"));
}

function AES256CTR_decrypt($data,$key,$iv) {
	$key = substr($key,0,32);
	$iv = substr($iv,0,16);
	return openssl_decrypt($data,'aes-256-ctr',str_pad($key,32,"\0"),OPENSSL_RAW_DATA,str_pad($iv,16,"\0"));
}


function CAMELLIA256CFB_encrypt($data,$key,$iv) {
	$key = substr($key,0,32);
	$iv = substr($iv,0,16);
	return openssl_encrypt($data,'camellia-256-cfb',str_pad($key,32,"\0"),OPENSSL_RAW_DATA,str_pad($iv,16,"\0"));
}

function CAMELLIA256CFB_decrypt($data,$key,$iv) {
	$key = substr($key,0,32);
	$iv = substr($iv,0,16);
	return openssl_decrypt($data,'camellia-256-cfb',str_pad($key,32,"\0"),OPENSSL_RAW_DATA,str_pad($iv,16,"\0"));
}


function CAST5CBC_encrypt($data,$key,$iv) {
	$key = substr($key,0,16);
	$iv = substr($iv,0,8);
	return openssl_encrypt($data,'cast5-cbc',str_pad($key,16,"\0"),OPENSSL_RAW_DATA,str_pad($iv,8,"\0"));
}

function CAST5CBC_decrypt($data,$key,$iv) {
	$key = substr($key,0,16);
	$iv = substr($iv,0,8);
	return openssl_decrypt($data,'cast5-cbc',str_pad($key,16,"\0"),OPENSSL_RAW_DATA,str_pad($iv,8,"\0"));
}

function CAST5CFB_encrypt($data,$key,$iv) {
	$key = substr($key,0,16);
	$iv = substr($iv,0,8);
	return openssl_encrypt($data,'cast5-cfb',str_pad($key,16,"\0"),OPENSSL_RAW_DATA,str_pad($iv,8,"\0"));
}

function CAST5CFB_decrypt($data,$key,$iv) {
	$key = substr($key,0,16);
	$iv = substr($iv,0,8);
	return openssl_decrypt($data,'cast5-cfb',str_pad($key,16,"\0"),OPENSSL_RAW_DATA,str_pad($iv,8,"\0"));
}



function crypto_encapsulate($data,$pubkey,$alg='') {
	if(! $alg) {
		return $data;
	}
	return other_encapsulate($data,$pubkey,$alg);
}

function other_encapsulate($data,$pubkey,$alg) {

	if(! $pubkey)
		logger('no key. data: ' . $data);

	// This default will change in the future. For now make it backward compatible.

	$padding = OPENSSL_PKCS1_PADDING;
	$base = $alg;

	$exts = explode('.',$alg);
	if(count($exts) > 1) {
		switch($exts[1]) {
			case 'oaep':
				$padding = OPENSSL_PKCS1_OAEP_PADDING;
				break;
		}
		$base = $exts[0];
	}


	$fn = strtoupper($base) . '_encrypt';
	if(function_exists($fn)) {

		// A bit hesitant to use openssl_random_pseudo_bytes() as we know
		// it has been historically targeted by US agencies for 'weakening'.
		// It is still arguably better than trying to come up with an
		// alternative cryptographically secure random generator.
		// There is little point in using the optional second arg to flag the
		// assurance of security since it is meaningless if the source algorithms
		// have been compromised. Also none of this matters if RSA has been
		// compromised by state actors and evidence is mounting that this has
		// already happened.   

		$result = [ 'encrypted' => true ];
		$key = openssl_random_pseudo_bytes(256);
		$iv  = openssl_random_pseudo_bytes(256);
		$result['data'] = base64url_encode($fn($data,$key,$iv),true);
		// log the offending call so we can track it down
		if(! openssl_public_encrypt($key,$k,$pubkey,$padding)) {
			$x = debug_backtrace();
			logger('RSA failed. ' . print_r($x[0],true));
		}

		$result['alg'] = $alg;
	 	$result['key'] = base64url_encode($k,true);
		openssl_public_encrypt($iv,$i,$pubkey,$padding);
		$result['iv'] = base64url_encode($i,true);
		return $result;
	}
	else {
		$x = [ 'data' => $data, 'pubkey' => $pubkey, 'alg' => $alg, 'result' => $data ];
		call_hooks('other_encapsulate', $x);
		return $x['result'];
	}
}

function crypto_methods() {

	// aes256cbc is provided for compatibility with earlier zot implementations which assume 32-byte key and 16-byte iv. 
	// other_encapsulate() now produces these longer keys/ivs by default so that it is difficult to guess a
	// particular implementation or choice of underlying implementations based on the key/iv length. 
	// The actual methods are responsible for deriving the actual key/iv from the provided parameters;
	// possibly by truncation or segmentation - though many other methods could be used.  

	$r = [ 'aes256ctr.oaep', 'camellia256cfb.oaep', 'cast5cfb.oaep' ];
	call_hooks('crypto_methods',$r);
	return $r;

}


function signing_methods() {


	$r = [ 'sha256' ];
	call_hooks('signing_methods',$r);
	return $r;

}


function aes_encapsulate($data,$pubkey) {
	if(! $pubkey)
		logger('aes_encapsulate: no key. data: ' . $data);
	$key = openssl_random_pseudo_bytes(32);
	$iv  = openssl_random_pseudo_bytes(16);

	$result = [ 'encrypted' => true ];

	$result['data'] = base64url_encode(AES256CBC_encrypt($data,$key,$iv),true);
	// log the offending call so we can track it down
	if(! openssl_public_encrypt($key,$k,$pubkey)) {
		$x = debug_backtrace();
		logger('aes_encapsulate: RSA failed. ' . print_r($x[0],true));
	}
	$result['alg'] = 'aes256cbc';
 	$result['key'] = base64url_encode($k,true);
	openssl_public_encrypt($iv,$i,$pubkey);
	$result['iv'] = base64url_encode($i,true);
	return $result;
}

function crypto_unencapsulate($data,$prvkey) {
	if(! $data)
		return;

	$alg = ((is_array($data) && array_key_exists('encrypted',$data)) ? $data['alg'] : '');
	if(! $alg) {
		return $data;
	}

	return other_unencapsulate($data,$prvkey,$alg);
}

function other_unencapsulate($data,$prvkey,$alg) {

	// This default will change in the future. For now make it backward compatible.

	$padding = OPENSSL_PKCS1_PADDING;
	$base = $alg;

	$exts = explode('.',$alg);
	if(count($exts) > 1) {
		switch($exts[1]) {
			case 'oaep':
				$padding = OPENSSL_PKCS1_OAEP_PADDING;
				break;
		}
		$base = $exts[0];
	}

	$fn = strtoupper($base) . '_decrypt';
	if(function_exists($fn)) {
		openssl_private_decrypt(base64url_decode($data['key']),$k,$prvkey,$padding);
		openssl_private_decrypt(base64url_decode($data['iv']),$i,$prvkey,$padding);
		return $fn(base64url_decode($data['data']),$k,$i);
	}
	else {
		$x = [ 'data' => $data, 'prvkey' => $prvkey, 'alg' => $alg, 'result' => $data ];
		call_hooks('other_unencapsulate',$x);
		return $x['result'];
	}
}


function aes_unencapsulate($data,$prvkey) {
	openssl_private_decrypt(base64url_decode($data['key']),$k,$prvkey);
	openssl_private_decrypt(base64url_decode($data['iv']),$i,$prvkey);
	return AES256CBC_decrypt(base64url_decode($data['data']),$k,$i);
}

function new_keypair($bits) {

	$openssl_options = array(
		'digest_alg'       => 'sha1',
		'private_key_bits' => $bits,
		'encrypt_key'      => false 
	);

	$conf = get_config('system','openssl_conf_file');
	if($conf)
		$openssl_options['config'] = $conf;
	
	$result = openssl_pkey_new($openssl_options);

	if(empty($result)) {
		logger('new_keypair: failed');
		return false;
	}

	// Get private key

	$response = array('prvkey' => '', 'pubkey' => '');

	openssl_pkey_export($result, $response['prvkey']);

	// Get public key
	$pkey = openssl_pkey_get_details($result);
	$response['pubkey'] = $pkey["key"];

	return $response;

}

function DerToPem($Der, $Private=false)
{
    //Encode:
    $Der = base64_encode($Der);
    //Split lines:
    $lines = str_split($Der, 65);
    $body = implode("\n", $lines);
    //Get title:
    $title = $Private? 'RSA PRIVATE KEY' : 'PUBLIC KEY';
    //Add wrapping:
    $result = "-----BEGIN {$title}-----\n";
    $result .= $body . "\n";
    $result .= "-----END {$title}-----\n";
 
    return $result;
}

function DerToRsa($Der)
{
    //Encode:
    $Der = base64_encode($Der);
    //Split lines:
    $lines = str_split($Der, 64);
    $body = implode("\n", $lines);
    //Get title:
    $title = 'RSA PUBLIC KEY';
    //Add wrapping:
    $result = "-----BEGIN {$title}-----\n";
    $result .= $body . "\n";
    $result .= "-----END {$title}-----\n";
 
    return $result;
}


function pkcs8_encode($Modulus,$PublicExponent) {
	//Encode key sequence
	$modulus = new ASNValue(ASNValue::TAG_INTEGER);
	$modulus->SetIntBuffer($Modulus);
	$publicExponent = new ASNValue(ASNValue::TAG_INTEGER);
	$publicExponent->SetIntBuffer($PublicExponent);
	$keySequenceItems = array($modulus, $publicExponent);
	$keySequence = new ASNValue(ASNValue::TAG_SEQUENCE);
	$keySequence->SetSequence($keySequenceItems);
	//Encode bit string
	$bitStringValue = $keySequence->Encode();
	$bitStringValue = chr(0x00) . $bitStringValue; //Add unused bits byte
	$bitString = new ASNValue(ASNValue::TAG_BITSTRING);
	$bitString->Value = $bitStringValue;
	//Encode body
	$bodyValue = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00" . $bitString->Encode();
	$body = new ASNValue(ASNValue::TAG_SEQUENCE);
	$body->Value = $bodyValue;
	//Get DER encoded public key:
	$PublicDER = $body->Encode();
	return $PublicDER;
}


function pkcs1_encode($Modulus,$PublicExponent) {
	//Encode key sequence
	$modulus = new ASNValue(ASNValue::TAG_INTEGER);
	$modulus->SetIntBuffer($Modulus);
	$publicExponent = new ASNValue(ASNValue::TAG_INTEGER);
	$publicExponent->SetIntBuffer($PublicExponent);
	$keySequenceItems = array($modulus, $publicExponent);
	$keySequence = new ASNValue(ASNValue::TAG_SEQUENCE);
	$keySequence->SetSequence($keySequenceItems);
	//Encode bit string
	$bitStringValue = $keySequence->Encode();
	return $bitStringValue;
}


// http://stackoverflow.com/questions/27568570/how-to-convert-raw-modulus-exponent-to-rsa-public-key-pem-format
function metopem($m,$e) {
	$der = pkcs8_encode($m,$e);
	$key = DerToPem($der,false);
	return $key;
}	


function pubrsatome($key,&$m,&$e) {
	require_once('library/asn1.php');

	$lines = explode("\n",$key);
	unset($lines[0]);
	unset($lines[count($lines)]);
	$x = base64_decode(implode('',$lines));

	$r = ASN_BASE::parseASNString($x);

	$m = base64url_decode($r[0]->asnData[0]->asnData);
	$e = base64url_decode($r[0]->asnData[1]->asnData);
}


function rsatopem($key) {
	pubrsatome($key,$m,$e);
	return(metopem($m,$e));
}

function pemtorsa($key) {
	pemtome($key,$m,$e);
	return(metorsa($m,$e));
}

function pemtome($key,&$m,&$e) {
	$lines = explode("\n",$key);
	unset($lines[0]);
	unset($lines[count($lines)]);
	$x = base64_decode(implode('',$lines));

	$r = ASN_BASE::parseASNString($x);

	$m = base64url_decode($r[0]->asnData[1]->asnData[0]->asnData[0]->asnData);
	$e = base64url_decode($r[0]->asnData[1]->asnData[0]->asnData[1]->asnData);
}

function metorsa($m,$e) {
	$der = pkcs1_encode($m,$e);
	$key = DerToRsa($der);
	return $key;
}	



function salmon_key($pubkey) {
	pemtome($pubkey,$m,$e);
	return 'RSA' . '.' . base64url_encode($m,true) . '.' . base64url_encode($e,true) ;
}


function convert_salmon_key($key) {

	if(strstr($key,','))
		$rawkey = substr($key,strpos($key,',')+1);
	else
		$rawkey = substr($key,5);

	$key_info = explode('.',$rawkey);

	$m = base64url_decode($key_info[1]);
	$e = base64url_decode($key_info[2]);

	logger('key details: ' . print_r($key_info,true), LOGGER_DATA);
	$salmon_key = metopem($m,$e);
	return $salmon_key;

}


function z_obscure($s) {
	$algs = crypto_methods();
	return json_encode(crypto_encapsulate($s,get_config('system','pubkey'),
		(($algs) ? $algs[0] : '')));
}

function z_unobscure($s) {
	return crypto_unencapsulate(json_decode($s,true),get_config('system','prvkey'));
}

