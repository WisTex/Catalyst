<?php

namespace Code\Lib;

use App;

class Config
{

    /**
     * @brief Loads the hub's configuration from database to a cached storage.
     *
     * Retrieve a category ($family) of config variables from database to a cached
     * storage in the global App::$config[$family].
     *
     * @param string $family
     *  The category of the configuration value
     */
    public static function Load($family)
    {
        if (! array_key_exists($family, App::$config)) {
            App::$config[$family] = [];
        }

        if (! array_key_exists('config_loaded', App::$config[$family])) {
            $r = q("SELECT * FROM config WHERE cat = '%s'", dbesc($family));
            if ($r !== false) {
                if ($r) {
                    foreach ($r as $rr) {
                        $k = $rr['k'];
                        App::$config[$family][$k] = $rr['v'];
                    }
                }
                App::$config[$family]['config_loaded'] = true;
            }
        }
    }

    /**
     * @brief Sets a configuration value for the hub.
     *
     * Stores a config value ($value) in the category ($family) under the key ($key).
     *
     * @param string $family
     *  The category of the configuration value
     * @param string $key
     *  The configuration key to set
     * @param mixed $value
     *  The value to store in the configuration
     * @return mixed
     *  Return the set value, or false if the database update failed
     */
    public static function Set($family, $key, $value)
    {
        // manage array value
        $dbvalue = ((is_array($value))  ? serialise($value) : $value);
        $dbvalue = ((is_bool($dbvalue)) ? intval($dbvalue)  : $dbvalue);

        if (self::Get($family, $key) === false || (! self::get_from_storage($family, $key))) {
            $ret = q(
                "INSERT INTO config ( cat, k, v ) VALUES ( '%s', '%s', '%s' ) ",
                dbesc($family),
                dbesc($key),
                dbesc($dbvalue)
            );
            if ($ret) {
                App::$config[$family][$key] = $value;
                $ret = $value;
            }
            return $ret;
        }

        $ret = q(
            "UPDATE config SET v = '%s' WHERE cat = '%s' AND k = '%s'",
            dbesc($dbvalue),
            dbesc($family),
            dbesc($key)
        );

        if ($ret) {
            App::$config[$family][$key] = $value;
            $ret = $value;
        }

        return $ret;
    }

    /**
     * @brief Get a particular config variable given the category name ($family)
     * and a key.
     *
     * Get a particular config variable from the given category ($family) and the
     * $key from a cached storage in App::$config[$family]. If a key is found in the
     * DB but does not exist in local config cache, pull it into the cache so we
     * do not have to hit the DB again for this item.
     *
     * Returns false if not set.
     *
     * @param string $family
     *  The category of the configuration value
     * @param string $key
     *  The configuration key to query
     * @param string $default (optional) default false
     * @return mixed Return value or false on error or if not set
     */
    public static function Get($family, $key, $default = false)
    {
        if ((! array_key_exists($family, App::$config)) || (! array_key_exists('config_loaded', App::$config[$family]))) {
            self::Load($family);
        }

        if (array_key_exists('config_loaded', App::$config[$family])) {
            if (! array_key_exists($key, App::$config[$family])) {
                return $default;
            }
            return unserialise(App::$config[$family][$key]);
        }

        return $default;
    }

    /**
     * @brief Deletes the given key from the hub's configuration database.
     *
     * Removes the configured value from the stored cache in App::$config[$family]
     * and removes it from the database.
     *
     * @param string $family
     *  The category of the configuration value
     * @param string $key
     *  The configuration key to delete
     * @return mixed
     */
    public static function Delete($family, $key)
    {

        $ret = false;

        if (array_key_exists($family, App::$config) && array_key_exists($key, App::$config[$family])) {
            unset(App::$config[$family][$key]);
        }

        $ret = q(
            "DELETE FROM config WHERE cat = '%s' AND k = '%s'",
            dbesc($family),
            dbesc($key)
        );

        return $ret;
    }


    /**
     * @brief Returns a record directly from the database configuration storage.
     *
     * This function queries directly the database and bypasses the cached storage
     * from get_config($family, $key).
     *
     * @param string $family
     *  The category of the configuration value
     * @param string $key
     *  The configuration key to query
     * @return mixed
     */
    private static function get_from_storage($family, $key)
    {
        $ret = q(
            "SELECT * FROM config WHERE cat = '%s' AND k = '%s' LIMIT 1",
            dbesc($family),
            dbesc($key)
        );

        return $ret;
    }
}
