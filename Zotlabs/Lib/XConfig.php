<?php

namespace Zotlabs\Lib;

use App;

/**
 * @brief Class for handling observer's config.
 *
 * <b>XConfig</b> is comparable to <i>PConfig</i>, except that it uses <i>xchan</i>
 * (an observer hash) as an identifier.
 *
 * <b>XConfig</b> is used for observer specific configurations and takes a
 * <i>xchan</i> as identifier.
 * The storage is of size MEDIUMTEXT.
 *
 * @code{.php}$var = Zotlabs\Lib\XConfig::Get('xchan', 'category', 'key');
 * // with default value for non existent key
 * $var = Zotlabs\Lib\XConfig::Get('xchan', 'category', 'unsetkey', 'defaultvalue');@endcode
 *
 * The old (deprecated?) way to access a XConfig value is:
 * @code{.php}$observer = App::get_observer_hash();
 * if ($observer) {
 *     $var = get_xconfig($observer, 'category', 'key');
 * }@endcode
 */
class XConfig {

	/**
	 * @brief Loads a full xchan's configuration into a cached storage.
	 *
	 * All configuration values of the given observer hash are stored in global
	 * cache which is available under the global variable App::$config[$xchan].
	 *
	 * @param string $xchan
	 *  The observer's hash
	 * @return void|false Returns false if xchan is not set
	 */
	public static function Load($xchan) {

		if(! $xchan)
			return false;

		if(! array_key_exists($xchan, App::$config))
			App::$config[$xchan] = [];

		$r = q("SELECT * FROM xconfig WHERE xchan = '%s'",
			dbesc($xchan)
		);

		if($r) {
			foreach($r as $rr) {
				$k = $rr['k'];
				$c = $rr['cat'];
				if(! array_key_exists($c, App::$config[$xchan])) {
					App::$config[$xchan][$c] = [];
					App::$config[$xchan][$c]['config_loaded'] = true;
				}
				App::$config[$xchan][$c][$k] = $rr['v'];
			}
		}
	}

	/**
	 * @brief Get a particular observer's config variable given the category
	 * name ($family) and a key.
	 *
	 * Get a particular observer's config value from the given category ($family)
	 * and the $key from a cached storage in App::$config[$xchan].
	 *
	 * Returns false if not set.
	 *
	 * @param string $xchan
	 *  The observer's hash
	 * @param string $family
	 *  The category of the configuration value
	 * @param string $key
	 *  The configuration key to query
	 * @param bool $default (optional) default false
	 * @return mixed Stored $value or false if it does not exist
	 */
	public static function Get($xchan, $family, $key, $default = false) {

		if(! $xchan)
			return $default;

		if(! array_key_exists($xchan, App::$config))
			load_xconfig($xchan);

		if((! array_key_exists($family, App::$config[$xchan])) || (! array_key_exists($key, App::$config[$xchan][$family])))
			return $default;

		return unserialise(App::$config[$xchan][$family][$key]);
	}

	/**
	 * @brief Sets a configuration value for an observer.
	 *
	 * Stores a config value ($value) in the category ($family) under the key ($key)
	 * for the observer's $xchan hash.
	 *
	 * @param string $xchan
	 *  The observer's hash
	 * @param string $family
	 *  The category of the configuration value
	 * @param string $key
	 *  The configuration key to set
	 * @param string $value
	 *  The value to store
	 * @return mixed Stored $value or false
	 */
	public static function Set($xchan, $family, $key, $value) {

		// manage array value
		$dbvalue = ((is_array($value))  ? serialise($value) : $value);
		$dbvalue = ((is_bool($dbvalue)) ? intval($dbvalue)  : $dbvalue);

		if(self::Get($xchan, $family, $key) === false) {
			if(! array_key_exists($xchan, App::$config))
				App::$config[$xchan] = [];
			if(! array_key_exists($family, App::$config[$xchan]))
				App::$config[$xchan][$family] = [];

			$ret = q("INSERT INTO xconfig ( xchan, cat, k, v ) VALUES ( '%s', '%s', '%s', '%s' )",
				dbesc($xchan),
				dbesc($family),
				dbesc($key),
				dbesc($dbvalue)
			);
		}
		else {
			$ret = q("UPDATE xconfig SET v = '%s' WHERE xchan = '%s' and cat = '%s' AND k = '%s'",
				dbesc($dbvalue),
				dbesc($xchan),
				dbesc($family),
				dbesc($key)
			);
		}

		App::$config[$xchan][$family][$key] = $value;

		if($ret)
			return $value;

		return $ret;
	}

	/**
	 * @brief Deletes the given key from the observer's config.
	 *
	 * Removes the configured value from the stored cache in App::$config[$xchan]
	 * and removes it from the database.
	 *
	 * @param string $xchan
	 *  The observer's hash
	 * @param string $family
	 *  The category of the configuration value
	 * @param string $key
	 *  The configuration key to delete
	 * @return mixed
	 */
	public static function Delete($xchan, $family, $key) {

		if(isset(App::$config[$xchan]) && isset(App::$config[$xchan][$family]) && isset(App::$config[$xchan][$family][$key]))
			unset(App::$config[$xchan][$family][$key]);

		$ret = q("DELETE FROM xconfig WHERE xchan = '%s' AND cat = '%s' AND k = '%s'",
			dbesc($xchan),
			dbesc($family),
			dbesc($key)
		);

		return $ret;
	}

}
