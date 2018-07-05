<?php

namespace Zotlabs\Lib;

/**
 * @brief ActivityStreams class.
 *
 * Parses an ActivityStream JSON string.
 */

class ActivityStreams {

	public $raw        = null;
	public $data       = null;
	public $valid      = false;
	public $id         = '';
	public $parent_id  = '';
	public $type       = '';
	public $actor      = null;
	public $obj        = null;
	public $tgt        = null;
	public $origin     = null;
	public $owner      = null;
	public $signer     = null;
	public $ldsig      = null;
	public $sigok      = false;
	public $recips     = null;
	public $raw_recips = null;

	/**
	 * @brief Constructor for ActivityStreams.
	 *
	 * Takes a JSON string as parameter, decodes it and sets up this object.
	 *
	 * @param string $string
	 */
	function __construct($string) {

		$this->raw  = $string;

		if(is_array($string)) {
			$this->data = $string;
		}
		else {
			$this->data = json_decode($string, true);
		}

		if($this->data) {

			// verify and unpack JSalmon signature if present
			
			if(is_array($this->data) && array_key_exists('signed',$this->data)) {
				$ret = JSalmon::verify($this->data);
				$tmp = JSalmon::unpack($this->data['data']);
				if($ret && $ret['success']) {
					if($ret['signer']) {
						$saved = json_encode($this->data,JSON_UNESCAPED_SLASHES);
						$this->data = $tmp;
						$this->data['signer'] = $ret['signer'];
						$this->data['signed_data'] = $saved;
						if($ret['hubloc']) {
							$this->data['hubloc'] = $ret['hubloc'];
						}
					}
				}
			}

			$this->valid = true;

		}

		if($this->is_valid()) {
			$this->id     = $this->get_property_obj('id');
			$this->type   = $this->get_primary_type();
			$this->actor  = $this->get_compound_property('actor','','',true);
			$this->obj    = $this->get_compound_property('object');
			$this->tgt    = $this->get_compound_property('target');
			$this->origin = $this->get_compound_property('origin');
			$this->recips = $this->collect_recips();

			$this->ldsig = $this->get_compound_property('signature');
			if($this->ldsig) {
				$this->signer = $this->get_compound_property('creator',$this->ldsig);
				if($this->signer && $this->signer['publicKey'] && $this->signer['publicKey']['publicKeyPem']) {
					$this->sigok = \Zotlabs\Lib\LDSignatures::verify($this->data,$this->signer['publicKey']['publicKeyPem']);
				}
			}

			if($this->obj && $this->obj['actor'])
				$this->obj['actor'] = $this->get_compound_property('actor',$this->obj,'',true);
			if($this->tgt && $this->tgt['actor'])
				$this->tgt['actor'] = $this->get_compound_property('actor',$this->tgt,'',true);



			if(($this->type === 'Note') && (! $this->obj)) {
				$this->obj = $this->data;
				$this->type = 'Create';
			}
			
			$this->parent_id = $this->get_property_obj('inReplyTo');
			if(! $this->parent_id) {
				$this->parent_id = $this->id;
			}

		}
	}

	/**
	 * @brief Return if instantiated ActivityStream is valid.
	 *
	 * @return boolean Return true if the JSON string could be decoded.
	 */
	function is_valid() {
		return $this->valid;
	}

	function set_recips($arr) {
		$this->saved_recips = $arr;
	}

	/**
	 * @brief Collects all recipients.
	 *
	 * @param string $base
	 * @param string $namespace (optional) default empty
	 * @return array
	 */
	function collect_recips($base = '', $namespace = '') {
		$x = [];
		$fields = [ 'to', 'cc', 'bto', 'bcc', 'audience'];
		foreach($fields as $f) {
			$y = $this->get_compound_property($f, $base, $namespace);
			if($y) {
				$x = array_merge($x, $y);
				if(! is_array($this->raw_recips))
					$this->raw_recips = [];

				$this->raw_recips[$f] = $x;
			}
		}
// not yet ready for prime time
//		$x = $this->expand($x,$base,$namespace);
		return $x;
	}

	function expand($arr,$base = '',$namespace = '') {
		$ret = [];

		// right now use a hardwired recursion depth of 5

		for($z = 0; $z < 5; $z ++) {
			if(is_array($arr) && $arr) {
				foreach($arr as $a) {
					if(is_array($a)) {
						$ret[] = $a;
					}
					else {
						$x = $this->get_compound_property($a,$base,$namespace);
						if($x) {
							$ret = array_merge($ret,$x);
						}
					}
				}
			}
		}

		/// @fixme de-duplicate

		return $ret;
	}

	/**
	 * @brief
	 *
	 * @param array $base
	 * @param string $namespace if not set return empty string
	 * @return string|NULL
	 */
	function get_namespace($base, $namespace) {

		if(! $namespace)
			return '';

		$key = null;

		foreach( [ $this->data, $base ] as $b ) {
			if(! $b)
				continue;

			if(array_key_exists('@context', $b)) {
				if(is_array($b['@context'])) {
					foreach($b['@context'] as $ns) {
						if(is_array($ns)) {
							foreach($ns as $k => $v) {
								if($namespace === $v)
									$key = $k;
							}
						}
						else {
							if($namespace === $ns) {
								$key = '';
							}
						}
					}
				}
				else {
					if($namespace === $b['@context']) {
						$key = '';
					}
				}
			}
		}

		return $key;
	}

	/**
	 * @brief
	 *
	 * @param string $property
	 * @param array $base (optional)
	 * @param string $namespace (optional) default empty
	 * @return NULL|mixed
	 */
	function get_property_obj($property, $base = '', $namespace = '') {
		$prefix = $this->get_namespace($base, $namespace);
		if($prefix === null)
			return null;

		$base = (($base) ? $base : $this->data);
		$propname = (($prefix) ? $prefix . ':' : '') . $property;

		return ((array_key_exists($propname, $base)) ? $base[$propname] : null);
	}

	/**
	 * @brief Fetches a property from an URL.
	 *
	 * @param string $url
	 * @return NULL|mixed
	 */

	function fetch_property($url) {
		$redirects = 0;
		if(! check_siteallowed($url)) {
			logger('blacklisted: ' . $url);
			return null;
		}

		$x = z_fetch_url($url, true, $redirects,
			['headers' => [ 'Accept: application/activity+json, application/ld+json; profile="https://www.w3.org/ns/activitystreams"' ]]);
		if($x['success'])
			return json_decode($x['body'], true);

		return null;
	}

	/**
	 * @brief
	 *
	 * @param string $property
	 * @param array $base
	 * @param string $namespace (optional) default empty
	 * @param boolean $first (optional) default false, if true and result is a sequential array return only the first element
	 * @return NULL|mixed
	 */
	function get_compound_property($property, $base = '', $namespace = '', $first = false) {
		$x = $this->get_property_obj($property, $base, $namespace);
		if($this->is_url($x)) {
			$x = $this->fetch_property($x);
		}

		// verify and unpack JSalmon signature if present
			
		if(is_array($x) && array_key_exists('signed',$x)) {
			$ret = JSalmon::verify($x);
			$tmp = JSalmon::unpack($x['data']);
			if($ret && $ret['success']) {
				if($ret['signer']) {
					$saved = json_encode($x,JSON_UNESCAPED_SLASHES);
					$x = $tmp;
					$x['signer'] = $ret['signer'];
					$x['signed_data'] = $saved;
					if($ret['hubloc']) {
						$x['hubloc'] = $ret['hubloc'];
					}
				}
			}
		}
		if($first && array_key_exists(0,$x)) {
			return $x[0];
		}

		return $x;
	}

	/**
	 * @brief Check if string starts with http.
	 *
	 * @param string $url
	 * @return boolean
	 */
	function is_url($url) {
		if(($url) && (! is_array($url)) && (strpos($url, 'http') === 0)) {
			return true;
		}

		return false;
	}

	/**
	 * @brief Gets the type property.
	 *
	 * @param array $base
	 * @param string $namespace (optional) default empty
	 * @return NULL|mixed
	 */
	function get_primary_type($base = '', $namespace = '') {
		if(! $base)
			$base = $this->data;

		$x = $this->get_property_obj('type', $base, $namespace);
		if(is_array($x)) {
			foreach($x as $y) {
				if(strpos($y, ':') === false) {
					return $y;
				}
			}
		}

		return $x;
	}

	function debug() {
		$x = var_export($this, true);
		return $x;
	}


	static function is_as_request() {

		$x = getBestSupportedMimeType([
			'application/ld+json;profile="https://www.w3.org/ns/activitystreams"',
			'application/activity+json',
			'application/ld+json;profile="http://www.w3.org/ns/activitystreams"'
		]);

		return(($x) ? true : false);

	}


}