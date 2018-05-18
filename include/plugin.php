<?php
/**
 * @file include/plugin.php
 *
 * @brief Some functions to handle addons and themes.
 */


/**
 * @brief Handle errors in plugin calls
 *
 * @param string $plugin name of the addon
 * @param string $error_text text of error
 * @param bool $uninstall uninstall plugin
 */
function handleerrors_plugin($plugin,$notice,$log,$uninstall=false){
        logger("Addons: [" . $plugin . "] Error: ".$log, LOGGER_ERROR);
        if ($notice != '') {
                notice("[" . $plugin . "] Error: ".$notice, LOGGER_ERROR);
        }

        if ($uninstall) {
                $idx = array_search($plugin, \App::$plugins);
                unset(\App::$plugins[$idx]);
                uninstall_plugin($plugin);
                set_config("system","addon", implode(", ",\App::$plugins));
        }
}

/**
 * @brief Unloads an addon.
 *
 * @param string $plugin name of the addon
 */
function unload_plugin($plugin){
	logger("Addons: unloading " . $plugin, LOGGER_DEBUG);

	@include_once('addon/' . $plugin . '/' . $plugin . '.php');
	if(function_exists($plugin . '_unload')) {
		$func = $plugin . '_unload';
		try {
			$func();
		} catch (Exception $e) {
			handleerrors_plugin($plugin,"Unable to unload.",$e->getMessage());
		}
	}
}

/**
 * @brief Uninstalls an addon.
 *
 * @param string $plugin name of the addon
 * @return boolean
 */
function uninstall_plugin($plugin) {

	unload_plugin($plugin);

	if(! file_exists('addon/' . $plugin . '/' . $plugin . '.php')) {
		q("DELETE FROM addon WHERE aname = '%s' ",
			dbesc($plugin)
		);
		return false;
	}

	logger("Addons: uninstalling " . $plugin);
	//$t = @filemtime('addon/' . $plugin . '/' . $plugin . '.php');
	@include_once('addon/' . $plugin . '/' . $plugin . '.php');
	if(function_exists($plugin . '_uninstall')) {
		$func = $plugin . '_uninstall';
		try {
			$func();
		} catch (Exception $e) {
			handleerrors_plugin($plugin,"Unable to uninstall.","Unable to run _uninstall : ".$e->getMessage());
		}
	}

	q("DELETE FROM addon WHERE aname = '%s' ",
		dbesc($plugin)
	);

}

/**
 * @brief Installs an addon.
 *
 * This function is called once to install the addon (either from the cli or via
 * the web admin). This will also call load_plugin() once.
 *
 * @param string $plugin name of the addon
 * @return bool
 */
function install_plugin($plugin) {
	if(! file_exists('addon/' . $plugin . '/' . $plugin . '.php'))
		return false;

	logger("Addons: installing " . $plugin);
	$t = @filemtime('addon/' . $plugin . '/' . $plugin . '.php');
	@include_once('addon/' . $plugin . '/' . $plugin . '.php');
	if(function_exists($plugin . '_install')) {
		$func = $plugin . '_install';
		try {
			$func();
		} catch (Exception $e) {
			handleerrors_plugin($plugin,"Install failed.","Install failed : ".$e->getMessage());
			return;
		}
	}

	$plugin_admin = (function_exists($plugin . '_plugin_admin') ? 1 : 0);

	q("INSERT INTO addon (aname, installed, tstamp, plugin_admin) VALUES ( '%s', 1, %d , %d ) ",
		dbesc($plugin),
		intval($t),
		$plugin_admin
	);

	load_plugin($plugin);
}

/**
 * @brief loads an addon by it's name.
 *
 * @param string $plugin name of the addon
 * @return bool
 */
function load_plugin($plugin) {
	// silently fail if plugin was removed
	if(! file_exists('addon/' . $plugin . '/' . $plugin . '.php'))
		return false;

	logger("Addons: loading " . $plugin, LOGGER_DEBUG);
	//$t = @filemtime('addon/' . $plugin . '/' . $plugin . '.php');
	@include_once('addon/' . $plugin . '/' . $plugin . '.php');
	if(function_exists($plugin . '_load')) {
		$func = $plugin . '_load';
		try {
			$func();
		} catch (Exception $e) {
			handleerrors_plugin($plugin,"Unable to load.","FAILED loading : ".$e->getMessage(),true);
			return;
		}

		// we can add the following with the previous SQL
		// once most site tables have been updated.
		// This way the system won't fall over dead during the update.

		if(file_exists('addon/' . $plugin . '/.hidden')) {
			q("update addon set hidden = 1 where name = '%s'",
				dbesc($plugin)
			);
		}
		return true;
	}
	else {
		logger("Addons: FAILED loading " . $plugin . " (missing _load function)");
		return false;
	}
}


/**
 * @brief Check if addon is installed.
 *
 * @param string $name
 * @return boolean
 */
function plugin_is_installed($name) {
	$r = q("select aname from addon where aname = '%s' and installed = 1 limit 1",
		dbesc($name)
	);
	if($r)
		return true;

	return false;
}


/**
 * @brief Reload all updated plugins.
 */
function reload_plugins() {
	$plugins = get_config('system', 'addon');
	if(strlen($plugins)) {
		$r = q("SELECT * FROM addon WHERE installed = 1");
		if(count($r))
			$installed = $r;
		else
			$installed = array();

		$parr = explode(',', $plugins);

		if(count($parr)) {
			foreach($parr as $pl) {
				$pl = trim($pl);

				$fname = 'addon/' . $pl . '/' . $pl . '.php';

				if(file_exists($fname)) {
					$t = @filemtime($fname);
					foreach($installed as $i) {
						if(($i['aname'] == $pl) && ($i['tstamp'] != $t)) {
							logger('Reloading plugin: ' . $i['aname']);
							@include_once($fname);

							if(function_exists($pl . '_unload')) {
								$func = $pl . '_unload';
								try {
        								$func();
								} catch (Exception $e) {
									handleerrors_plugin($plugin,"","UNLOAD FAILED (uninstalling) : ".$e->getMessage(),true);
                                                                        continue;
								}
							}
							if(function_exists($pl . '_load')) {
								$func = $pl . '_load';
								try {
        								$func();
								} catch (Exception $e) {
									handleerrors_plugin($plugin,"","LOAD FAILED (uninstalling): ".$e->getMessage(),true);
                                                                        continue;
								}
							}
							q("UPDATE addon SET tstamp = %d WHERE id = %d",
								intval($t),
								intval($i['id'])
							);
						}
					}
				}
			}
		}
	}
}


function plugins_installed_list() {

	$r = q("select * from addon where installed = 1 order by aname asc");
	return(($r) ? ids_to_array($r,'aname') : []);
}


function plugins_sync() {

	/**
	 *
	 * Synchronise plugins:
	 *
	 * App::$config['system']['addon'] contains a comma-separated list of names
	 * of plugins/addons which are used on this system.
	 * Go through the database list of already installed addons, and if we have
	 * an entry, but it isn't in the config list, call the unload procedure
	 * and mark it uninstalled in the database (for now we'll remove it).
	 * Then go through the config list and if we have a plugin that isn't installed,
	 * call the install procedure and add it to the database.
	 *
	 */

	$installed = plugins_installed_list();

	$plugins = get_config('system', 'addon', '');

	$plugins_arr = explode(',', $plugins);

	// array_trim is in include/text.php

	if(! array_walk($plugins_arr,'array_trim'))
		return;

	App::$plugins = $plugins_arr;

	$installed_arr = [];

	if(count($installed)) {
		foreach($installed as $i) {
			if(! in_array($i, $plugins_arr)) {
				unload_plugin($i);
			}
			else {
				$installed_arr[] = $i;
			}
		}
	}

	if(count($plugins_arr)) {
		foreach($plugins_arr as $p) {
			if(! in_array($p, $installed_arr)) {
				load_plugin($p);
			}
		}
	}

}


/**
 * @brief Get a list of non hidden addons.
 *
 * @return array
 */
function visible_plugin_list() {
	
	$r = q("select * from addon where hidden = 0 order by aname asc");
	$x = (($r) ? ids_to_array($r,'aname') : array());
	$y = [];
	if($x) {
		foreach($x as $xv) {
			if(is_dir('addon/' . $xv)) {
				$y[] = $xv;
			}
		}
	}			
	return $y;
}


/**
 * @brief Registers a hook.
 *
 * @see ::Zotlabs::Extend::Hook::register()
 *
 * @param string $hook the name of the hook
 * @param string $file the name of the file that hooks into
 * @param string $function the name of the function that the hook will call
 * @param int $priority A priority (defaults to 0)
 * @return mixed|bool
 */
function register_hook($hook, $file, $function, $priority = 0) {
	$r = q("SELECT * FROM hook WHERE hook = '%s' AND file = '%s' AND fn = '%s' LIMIT 1",
		dbesc($hook),
		dbesc($file),
		dbesc($function)
	);
	if($r)
		return true;

	$r = q("INSERT INTO hook (hook, file, fn, priority) VALUES ( '%s', '%s', '%s', '%s' )",
		dbesc($hook),
		dbesc($file),
		dbesc($function),
		dbesc($priority)
	);

	return $r;
}


/**
 * @brief unregisters a hook.
 *
 * @see ::Zotlabs::Extend::Hook::unregister
 *
 * @param string $hook the name of the hook
 * @param string $file the name of the file that hooks into
 * @param string $function the name of the function that the hook called
 * @return array
 */
function unregister_hook($hook, $file, $function) {
	$r = q("DELETE FROM hook WHERE hook = '%s' AND file = '%s' AND fn = '%s'",
		dbesc($hook),
		dbesc($file),
		dbesc($function)
	);

	return $r;
}


//
// It might not be obvious but themes can manually add hooks to the App::$hooks
// array in their theme_init() and use this to customise the app behaviour.
// UPDATE: use insert_hook($hookname,$function_name) to do this
//


function load_hooks() {

	App::$hooks = array();

	$r = q("SELECT * FROM hook WHERE true ORDER BY priority DESC");
	if($r) {
		foreach($r as $rr) {
			if(! array_key_exists($rr['hook'],App::$hooks))
				App::$hooks[$rr['hook']] = array();

			App::$hooks[$rr['hook']][] = array($rr['file'],$rr['fn'],$rr['priority'],$rr['hook_version']);
		}
	}
	//logger('hooks: ' . print_r(App::$hooks,true));
}

/**
 * @brief Inserts a hook into a page request.
 *
 * Insert a short-lived hook into the running page request.
 * Hooks are normally persistent so that they can be called
 * across asynchronous processes such as delivery and poll
 * processes.
 *
 * insert_hook lets you attach a hook callback immediately
 * which will not persist beyond the life of this page request
 * or the current process.
 *
 * @param string $hook
 *     name of hook to attach callback
 * @param string $fn
 *     function name of callback handler
 * @param int $version (optional) default 0
 * @param int $priority (optional) default 0
 */
function insert_hook($hook, $fn, $version = 0, $priority = 0) {

	if(! is_array(App::$hooks))
		App::$hooks = array();

	if(! array_key_exists($hook, App::$hooks))
		App::$hooks[$hook] = array();

	App::$hooks[$hook][] = array('', $fn, $priority, $version);
}

/**
 * @brief Calls a hook.
 *
 * Use this function when you want to be able to allow a hook to manipulate
 * the provided data.
 *
 * @param string $name of the hook to call
 * @param[in,out] string|array &$data to transmit to the callback handler
 */
function call_hooks($name, &$data = null) {
	$a = 0;
	if((is_array(App::$hooks)) && (array_key_exists($name, App::$hooks))) {
		foreach(App::$hooks[$name] as $hook) {
			$origfn = $hook[1];
			if($hook[0])
				@include_once($hook[0]);
			if(preg_match('|^a:[0-9]+:{.*}$|s', $hook[1])) {
				$hook[1] = unserialize($hook[1]);
			}
			elseif(strpos($hook[1],'::')) {
				// We shouldn't need to do this, but it appears that PHP
				// isn't able to directly execute a string variable with a class
				// method in the manner we are attempting it, so we'll
				// turn it into an array.
				$hook[1] = explode('::',$hook[1]);
			}

			if(is_callable($hook[1])) {
				$func = $hook[1];
				if($hook[3])
					$func($data);
				else
					$func($a, $data);
			}
			else {

				// Don't do any DB write calls if we're currently logging a possibly failed DB call.
				if(! DBA::$logging) {
					// The hook should be removed so we don't process it.
					q("DELETE FROM hook WHERE hook = '%s' AND file = '%s' AND fn = '%s'",
						dbesc($name),
						dbesc($hook[0]),
						dbesc($origfn)
					);
				}
			}
		}
	}
}


/**
 * @brief Parse plugin comment in search of plugin infos.
 *
 * like
 * \code
 *   * Name: Plugin
 *   * Description: A plugin which plugs in
 *   * Version: 1.2.3
 *   * Author: John <profile url>
 *   * Author: Jane <email>
 *   *
 *\endcode
 * @param string $plugin the name of the plugin
 * @return array with the plugin information
 */
function get_plugin_info($plugin){
	$m = array();
	$info = array(
		'name' => $plugin,
		'description' => '',
		'author' => array(),
		'maintainer' => array(),
		'version' => '',
		'requires' => ''
	);

	if (!is_file("addon/$plugin/$plugin.php"))
		return $info;

	$f = file_get_contents("addon/$plugin/$plugin.php");
	$f = escape_tags($f);
	$r = preg_match("|/\*.*\*/|msU", $f, $m);

	if ($r){
		$ll = explode("\n", $m[0]);
		foreach( $ll as $l ) {
			$l = trim($l, "\t\n\r */");
			if ($l != ""){
				list($k, $v) = array_map("trim", explode(":", $l, 2));
				$k = strtolower($k);
				if ($k == 'author' || $k == 'maintainer'){
					$r = preg_match("|([^<]+)<([^>]+)>|", $v, $m);
					if ($r) {
						$info[$k][] = array('name' => $m[1], 'link' => $m[2]);
					} else {
						$info[$k][] = array('name' => $v);
					}
				}
				else {
					$info[$k] = $v;
				}
			}
		}
	}

	return $info;
}

/**
 * @brief Parse widget comment in search of widget info.
 *
 * like
 * \code
 *   * Name: MyWidget
 *   * Description: A widget
 *   * Version: 1.2.3
 *   * Author: John <profile url>
 *   * Author: Jane <email>
 *   *
 *\endcode
 * @param string $widget the name of the widget
 * @return array with the information
 */
function get_widget_info($widget){
	$m = array();
	$info = array(
		'name' => $widget,
		'description' => '',
		'author' => array(),
		'maintainer' => array(),
		'version' => '',
		'requires' => ''
	);

	$ucwidget = ucfirst($widget);

	$checkpaths = [
		"Zotlabs/SiteWidget/$ucwidget.php",
		"Zotlibs/Widget/$ucwidget.php",
		"addon/$ucwidget/$ucwidget.php",
		"addon/$widget.php"
	];

	$widget_found = false;

	foreach ($checkpaths as $path) {
		if (is_file($path)) {
			$widget_found = true;
			$f = file_get_contents($path);
			break;
		}
	}

	if(! ($widget_found && $f))		
		return $info;

	$f = escape_tags($f);
	$r = preg_match("|/\*.*\*/|msU", $f, $m);

	if ($r) {
		$ll = explode("\n", $m[0]);
		foreach( $ll as $l ) {
			$l = trim($l, "\t\n\r */");
			if ($l != ""){
				list($k, $v) = array_map("trim", explode(":", $l, 2));
				$k = strtolower($k);
				if ($k == 'author' || $k == 'maintainer'){
					$r = preg_match("|([^<]+)<([^>]+)>|", $v, $m);
					if ($r) {
						$info[$k][] = array('name' => $m[1], 'link' => $m[2]);
					} else {
						$info[$k][] = array('name' => $v);
					}
				}
				else {
					$info[$k] = $v;
				}
			}
		}
	}

	return $info;
}


function check_plugin_versions($info) {

	if(! is_array($info))
		return true;

	if(array_key_exists('minversion',$info)) {
		if(! version_compare(STD_VERSION,trim($info['minversion']), '>=')) {
			logger('minversion limit: ' . $info['name'],LOGGER_NORMAL,LOG_WARNING);
			return false;
		}
	}
	if(array_key_exists('maxversion',$info)) {
		if(version_compare(STD_VERSION,trim($info['maxversion']), '>')) {
			logger('maxversion limit: ' . $info['name'],LOGGER_NORMAL,LOG_WARNING);
			return false;
		}
	}
	if(array_key_exists('minphpversion',$info)) {
		if(! version_compare(PHP_VERSION,trim($info['minphpversion']), '>=')) {
			logger('minphpversion limit: ' . $info['name'],LOGGER_NORMAL,LOG_WARNING);
			return false;
		}
	}
	if(array_key_exists('serverroles',$info)) {
		$role = \Zotlabs\Lib\System::get_server_role();
		if(! (
			stristr($info['serverroles'],'*')
			|| stristr($info['serverroles'],'any')
			|| stristr($info['serverroles'],$role))) {
			logger('serverrole limit: ' . $info['name'],LOGGER_NORMAL,LOG_WARNING);

			return false;
		}
	}


	if(array_key_exists('requires',$info)) {
		$arr = explode(',',$info['requires']);
		$found = true;
		if($arr) {
			foreach($arr as $test) {
				$test = trim($test);
				if(! $test)
					continue;
				if(strpos($test,'.')) {
					$conf = explode('.',$test);
					if(get_config(trim($conf[0]),trim($conf[1])))
						return true;
					else
						return false;
				}
				if(! in_array($test,App::$plugins))
					$found = false;
			}
		}
		if(! $found)
			return false;
	}

	return true;
}


/**
 * @brief Parse theme comment in search of theme infos.
 *
 * like
 * \code
 *   * Name: My Theme
 *   * Description: My Cool Theme
 *   * Version: 1.2.3
 *   * Author: John <profile url>
 *   * Maintainer: Jane <profile url>
 *   * Compat: Friendica [(version)], Red [(version)]
 *   *
 * \endcode
 * @param string $theme the name of the theme
 * @return array
 */
function get_theme_info($theme){
	$m = array();
	$info = array(
		'name' => $theme,
		'description' => '',
		'author' => array(),
		'version' => '',
		'minversion' => '',
		'maxversion' => '',
		'compat' => '',
		'credits' => '',
		'maintainer' => array(),
		'experimental' => false,
		'unsupported' => false
	);

	if(file_exists("view/theme/$theme/experimental"))
		$info['experimental'] = true;

	if(file_exists("view/theme/$theme/unsupported"))
		$info['unsupported'] = true;

	if (!is_file("view/theme/$theme/php/theme.php"))
		return $info;

	$f = file_get_contents("view/theme/$theme/php/theme.php");
	$r = preg_match("|/\*.*\*/|msU", $f, $m);

	if ($r){
		$ll = explode("\n", $m[0]);
		foreach( $ll as $l ) {
			$l = trim($l, "\t\n\r */");
			if ($l != ""){
				list($k, $v) = array_map("trim", explode(":", $l, 2));
				$k = strtolower($k);
				if ($k == 'author'){
					$r = preg_match("|([^<]+)<([^>]+)>|", $v, $m);
					if ($r) {
						$info['author'][] = array('name' => $m[1], 'link' => $m[2]);
					} else {
						$info['author'][] = array('name' => $v);
					}
				}
				elseif ($k == 'maintainer'){
					$r = preg_match("|([^<]+)<([^>]+)>|", $v, $m);
					if ($r) {
						$info['maintainer'][] = array('name' => $m[1], 'link' => $m[2]);
					} else {
						$info['maintainer'][] = array('name' => $v);
					}
				} else {
					if (array_key_exists($k, $info)){
						$info[$k] = $v;
					}
				}
			}
		}
	}

	return $info;
}

/**
 * @brief Returns the theme's screenshot.
 *
 * The screenshot is expected as view/theme/$theme/img/screenshot.[png|jpg].
 *
 * @param string $theme The name of the theme
 * @return string
 */
function get_theme_screenshot($theme) {

	$exts = array('.png', '.jpg');
	foreach($exts as $ext) {
		if(file_exists('view/theme/' . $theme . '/img/screenshot' . $ext))
			return(z_root() . '/view/theme/' . $theme . '/img/screenshot' . $ext);
	}

	return(z_root() . '/images/blank.png');
}

/**
 * @brief add CSS to \<head\>
 *
 * @param string $src
 * @param string $media change media attribute (default to 'screen')
 */
function head_add_css($src, $media = 'screen') {
	App::$css_sources[] = array($src, $media);
}

function head_remove_css($src, $media = 'screen') {

	$index = array_search(array($src, $media), App::$css_sources);
	if($index !== false)
		unset(App::$css_sources[$index]);
}

function head_get_css() {
	$str = '';
	$sources = App::$css_sources;
	if(count($sources)) {
		foreach($sources as $source)
			$str .= format_css_if_exists($source);
	}

	return $str;
}

function head_add_link($arr) {
	if($arr) {
		App::$linkrel[] = $arr;
	}
}

function head_get_links() {
	$str = '';
	$sources = App::$linkrel;
	if(count($sources)) {
		foreach($sources as $source) {
			if(is_array($source) && count($source)) {
				$str .= '<link';
				foreach($source as $k => $v) {
					$str .= ' ' . $k . '="' . $v . '"';
				}
				$str .= ' />' . "\r\n";

			}
		}
	}

	return $str;
}


function format_css_if_exists($source) {

	// script_path() returns https://yoursite.tld

	$path_prefix = script_path();

	$script = $source[0];

	if(strpos($script, '/') !== false) {
		// The script is a path relative to the server root
		$path = $script;
		// If the url starts with // then it's an absolute URL
		if(substr($script,0,2) === '//') {
			$path_prefix = '';
		}
	} else {
		// It's a file from the theme
		$path = '/' . theme_include($script);
	}

	if($path) {
		$qstring = ((parse_url($path, PHP_URL_QUERY)) ? '&' : '?') . 'v=' . STD_VERSION;
		return '<link rel="stylesheet" href="' . $path_prefix . $path . $qstring . '" type="text/css" media="' . $source[1] . '">' . "\r\n";
	}
}

/**
 * This basically calculates the baseurl. We have other functions to do that, but
 * there was an issue with script paths and mixed-content whose details are arcane
 * and perhaps lost in the message archives. The short answer is that we're ignoring
 * the URL which we are "supposed" to use, and generating script paths relative to
 * the URL which we are currently using; in order to ensure they are found and aren't
 * blocked due to mixed content issues.
 *
 * @return string
 */
function script_path() {
	if(x($_SERVER,'HTTPS') && $_SERVER['HTTPS'])
		$scheme = 'https';
	elseif(x($_SERVER,'SERVER_PORT') && (intval($_SERVER['SERVER_PORT']) == 443))
		$scheme = 'https';
	elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on')
		$scheme = 'https';
	else
		$scheme = 'http';

	// Some proxy setups may require using http_host

	if(intval(App::$config['system']['script_path_use_http_host']))
		$server_var = 'HTTP_HOST';
	else
		$server_var = 'SERVER_NAME';


	if(x($_SERVER,$server_var)) {
		$hostname = $_SERVER[$server_var];
	}
	else {
		return z_root();
	}

	return $scheme . '://' . $hostname;
}

function head_add_js($src, $priority = 0) {
	if(! is_array(App::$js_sources[$priority]))
		App::$js_sources[$priority] = array();
	App::$js_sources[$priority][] = $src;
}

function head_remove_js($src, $priority = 0) {

	$index = array_search($src, App::$js_sources[$priority]);
	if($index !== false)
		unset(App::$js_sources[$priority][$index]);
}

/**
 * We should probably try to register main.js with a high priority, but currently
 * we handle it separately and put it at the end of the html head block in case
 * any other javascript is added outside the head_add_js construct.
 *
 * @return string
 */
function head_get_js() {

	$str = '';
	if(App::$js_sources) {
		ksort(App::$js_sources,SORT_NUMERIC);
		foreach(App::$js_sources as $sources) {
			if(count($sources)) {
				foreach($sources as $source) {
					if($source === 'main.js')
						continue;
					$str .= format_js_if_exists($source);
				}
			}
		}
	}

	return $str;
}

function head_get_main_js() {
	$str = '';
	$sources = array('main.js');
	if(count($sources))
		foreach($sources as $source)
			$str .= format_js_if_exists($source,true);

	return $str;
}

function format_js_if_exists($source) {
	$path_prefix = script_path();

	if(strpos($source,'/') !== false) {
		// The source is a known path on the system
		$path = $source;
		// If the url starts with // then it's an absolute URL
		if(substr($source,0,2) === '//') {
			$path_prefix = '';
		}
	}
	else {
		// It's a file from the theme
		$path = '/' . theme_include($source);
	}
	if($path) {
		$qstring = ((parse_url($path, PHP_URL_QUERY)) ? '&' : '?') . 'v=' . STD_VERSION;
		return '<script src="' . $path_prefix . $path . $qstring . '" ></script>' . "\r\n" ;
	}
}


function theme_include($file, $root = '') {

	// Make sure $root ends with a slash / if it's not blank
	if($root !== '' && $root[strlen($root)-1] !== '/')
		$root = $root . '/';

	$theme_info = App::$theme_info;

	if(array_key_exists('extends',$theme_info))
		$parent = $theme_info['extends'];
	else
		$parent = 'NOPATH';

	$theme = Zotlabs\Render\Theme::current();
	$thname = $theme[0];

	$ext = substr($file,strrpos($file,'.')+1);

	$paths = array(
		"{$root}view/theme/$thname/$ext/$file",
		"{$root}view/theme/$parent/$ext/$file",
		"{$root}view/site/$ext/$file",
		"{$root}view/$ext/$file",
	);

	foreach($paths as $p) {
		// strpos() is faster than strstr when checking if one string is in another (http://php.net/manual/en/function.strstr.php)
		if(strpos($p,'NOPATH') !== false)
			continue;
		if(file_exists($p))
			return $p;
	}

	return '';
}


function get_intltext_template($s, $root = '') {

	$t = App::template_engine();

	$template = $t->get_intltext_template($s, $root);
	return $template;
}


function get_markup_template($s, $root = '') {

	$t = App::template_engine();
	$template = $t->get_markup_template($s, $root);
	return $template;
}

/**
 * @brief
 *
 * @param string $folder
 * @return boolean|string
 */
function folder_exists($folder) {
	// Get canonicalized absolute pathname
	$path = realpath($folder);

	// If it exist, check if it's a directory
	return (($path !== false) && is_dir($path)) ? $path : false;
}
