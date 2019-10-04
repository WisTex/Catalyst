<?php

namespace Zotlabs\Web;

use App;
use Zotlabs\Extend\Route;
use Zotlabs\Render\Theme;
use Exception;

/**
 *
 * We have already parsed the server path into App::$argc and App::$argv
 *
 * App::$argv[0] is our module name. Let's call it 'foo'. We will load the
 * Zotlabs/Module/Foo.php (object) or file mod/foo.php (procedural)
 * and use it for handling our URL request to 'https://ourgreatwebsite.something/foo' .
 * The module file contains a few functions that we call in various circumstances
 * and in the following order:
 * @code{.php}
 * Object:
 *    class Foo extends \Zotlabs\Web\Controller {
 *        function init() { init function }
 *        function post() { post function }
 *        function get()  { normal page function }
 *    }
 *
 * Procedual interface:
 *        foo_init()
 *        foo_post() (only called if there are $_POST variables)
 *        foo_content() - the string return of this function contains our page body
 * @endcode
 * Modules which emit other serialisations besides HTML (XML,JSON, etc.) should do
 * so within the module init and/or post functions and then invoke killme() to terminate
 * further processing.
 */
class Router {

	private $modname = '';
	private $controller = null;

	/**
	 * @brief Router constructor.
	 *
	 * @throws Exception module not found
	 */
	function __construct() {

		$module = App::$module;
		$modname = "Zotlabs\\Module\\" . ucfirst($module);

		if (strlen($module)) {

			/*
			 * We will always have a module name.
			 * First see if we have a plugin handling this route
			 */

			$routes = Route::get();
			if ($routes) {
				foreach ($routes as $route) {
					if (is_array($route) && strtolower($route[1]) === $module) {
						include_once($route[0]);
						if (class_exists($modname)) {
							$this->controller = new $modname;
							App::$module_loaded = true;
						}
					}
				}
			}


			/*
			 * If the site has a custom module to over-ride the standard module, use it.
			 * Otherwise, look for the standard program module
			 */

			if(! (App::$module_loaded)) {
				try {
					$filename = 'Zotlabs/SiteModule/'. ucfirst($module). '.php';
					if (file_exists($filename)) {
						// This won't be picked up by the autoloader, so load it explicitly
						require_once($filename);
						$this->controller = new $modname;
						App::$module_loaded = true;
					}
					else {
						$filename = 'Zotlabs/Module/'. ucfirst($module). '.php';
						if (file_exists($filename)) {
							$this->controller = new $modname;
							App::$module_loaded = true;
						}
					}
					if (! App::$module_loaded) {
						throw new Exception('Module not found');
					}
				}
				catch(Exception $e) {

				}
			}

			$x = [
				'module' => $module,
				'installed' => App::$module_loaded,
				'controller' => $this->controller
			];

			/**
			 * @hooks module_loaded
			 *   Called when a module has been successfully locate to server a URL request.
			 *   This provides a place for plugins to register module handlers which don't otherwise exist
			 *   on the system, or to completely over-ride an existing module.
			 *   If the plugin sets 'installed' to true we won't throw a 404 error for the specified module even if
			 *   there is no specific module file or matching plugin name.
			 *   The plugin should catch at least one of the module hooks for this URL.
			 *   * \e string \b module
			 *   * \e boolean \b installed
			 *   * \e mixed \b controller - The initialized module object
			 */
			call_hooks('module_loaded', $x);
			if ($x['installed']) {
				App::$module_loaded = true;
				$this->controller = $x['controller'];
			}

			/*
			 * The URL provided does not resolve to a valid module.
	 		 */

			if (! (App::$module_loaded)) {

				// undo the setting of a letsencrypt acme-challenge rewrite rule
				// which blocks access to our .well-known routes.
				// Also provide a config setting for sites that have a legitimate need
				// for a custom .htaccess in the .well-known directory; but they should
				// make the file read-only so letsencrypt doesn't modify it

				if (strpos($_SERVER['REQUEST_URI'],'/.well-known/') === 0) {
					if (file_exists('.well-known/.htaccess') && get_config('system','fix_apache_acme',true)) {
						rename('.well-known/.htaccess','.well-known/.htaccess.old');
					}
				}

				$x = [
					'module' => $module,
					'installed' => App::$module_loaded,
					'controller' => $this->controller
				];
				call_hooks('page_not_found',$x);

				// Stupid browser tried to pre-fetch our Javascript img template.
				// Don't log the event or return anything - just quietly exit.

				if ((x($_SERVER, 'QUERY_STRING')) && preg_match('/{[0-9]}/', $_SERVER['QUERY_STRING']) !== 0) {
					killme();
				}

				if (get_config('system','log_404',true)) {
					logger("Module {$module} not found.", LOGGER_DEBUG, LOG_WARNING);
					logger('index.php: page not found: ' . $_SERVER['REQUEST_URI']
						. ' ADDRESS: ' . $_SERVER['REMOTE_ADDR'] . ' QUERY: '
						. $_SERVER['QUERY_STRING'], LOGGER_DEBUG);
				}

				header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
				$tpl = get_markup_template('404.tpl');
				App::$page['content'] = replace_macros(get_markup_template('404.tpl'), [ '$message' => t('Page not found.') ]);

				// pretend this is a module so it will initialise the theme
				App::$module = '404';
				App::$module_loaded = true;
				App::$error = true;
			}
		}
	}

	/**
	 * @brief
	 *
	 */
	function Dispatch() {

		/**
		 * Call module functions
		 */

		if (App::$module_loaded) {

			App::$page['page_title'] = App::$module;
			$placeholder = '';

			/*
			 * No theme has been specified when calling the module_init functions
			 * For this reason, please restrict the use of templates to those which
			 * do not provide any presentation details - as themes will not be able
			 * to over-ride them.
			 */

			$arr = [ 'init' => true, 'replace' => false ];
			call_hooks(App::$module . '_mod_init', $arr);
			if (! $arr['replace']) {
				if ($this->controller && method_exists($this->controller,'init')) {
					$this->controller->init();
				}
			}

			/*
			 * Do all theme initialisation here before calling any additional module functions.
			 * The module_init function may have changed the theme.
			 * Additionally any page with a Comanche template may alter the theme.
			 * So we'll check for those now.
			 */


			/*
			 * In case a page has overloaded a module, see if we already have a layout defined
			 * otherwise, if a PDL file exists for this module, use it
			 * The member may have also created a customised PDL that's stored in the config
			 */

			load_pdl();

			/*
		 	 * load current theme info
		 	 */

			$current_theme = Theme::current();

			$theme_info_file = 'view/theme/' . $current_theme[0] . '/php/theme.php';
			if (file_exists($theme_info_file)) {
				require_once($theme_info_file);
			}

			if (function_exists(str_replace('-', '_', $current_theme[0]) . '_init')) {
				$func = str_replace('-', '_', $current_theme[0]) . '_init';
				$func($a);
			}
			elseif (x(App::$theme_info, 'extends') && file_exists('view/theme/' . App::$theme_info['extends'] . '/php/theme.php')) {
				require_once('view/theme/' . App::$theme_info['extends'] . '/php/theme.php');
				if (function_exists(str_replace('-', '_', App::$theme_info['extends']) . '_init')) {
					$func = str_replace('-', '_', App::$theme_info['extends']) . '_init';
					$func($a);
				}
			}

			if (($_SERVER['REQUEST_METHOD'] === 'POST') && (! App::$error) && (! x($_POST, 'auth-params'))) {
				call_hooks(App::$module . '_mod_post', $_POST);
				if ($this->controller && method_exists($this->controller,'post')) {
					$this->controller->post();
				}
			}

			if (! App::$error) {
				$arr = [ 'content' => \App::$page['content'], 'replace' => false ];
				call_hooks(App::$module . '_mod_content', $arr);

				if (! $arr['replace']) {
					if ($this->controller && method_exists($this->controller,'get')) {
						$arr = [ 'content' => $this->controller->get() ];
					}
				}
				call_hooks(App::$module . '_mod_aftercontent', $arr);
				App::$page['content'] = (($arr['replace']) ? $arr['content'] : App::$page['content'] . $arr['content']);
			}
		}
	}
}
