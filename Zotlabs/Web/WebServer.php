<?php

namespace Zotlabs\Web;

use App;

class WebServer {

	public function run() {


		/*
		 * Bootstrap the application, load configuration, load modules, load theme, etc.
		 */

		require_once('boot.php');

		if (file_exists('maintenance_lock') || file_exists('cache/maintenance_lock')) {
			http_status_exit(503,'System unavailable');
		}
		
		sys_boot();


		App::$language = get_best_language();
		load_translation_table(App::$language,App::$install);


		/**
		 *
		 * Important stuff we always need to do.
		 *
		 * The order of these may be important so use caution if you think they're all
		 * intertwingled with no logical order and decide to sort it out. Some of the
		 * dependencies have changed, but at least at one time in the recent past - the
		 * order was critical to everything working properly
		 *
		 */

		if (App::$session) {
			App::$session->start();
	  	}
  		else {
			session_start();
			register_shutdown_function('session_write_close');
  		}

		/**
		 * Language was set earlier, but we can over-ride it in the session.
		 * We have to do it here because the session was just now opened.
		 */

		if (array_key_exists('system_language',$_REQUEST)) {
			if (strlen($_REQUEST['system_language'])) {
				$_SESSION['language'] = $_REQUEST['system_language'];
			}
			else {
				unset($_SESSION['language']);
			}
		}
		if ((x($_SESSION, 'language')) && ($_SESSION['language'] !== $lang)) {
			App::$language = $_SESSION['language'];
			load_translation_table(App::$language);
		}

		if ((x($_GET,'zid')) && (! App::$install)) {
			App::$query_string = strip_zids(App::$query_string);
			if (! local_channel()) {
				if ($_SESSION['my_address'] !== $_GET['zid']) {
					$_SESSION['my_address'] = $_GET['zid'];
					$_SESSION['authenticated'] = 0;
				}
				zid_init();
			}
		}

		if ((x($_GET,'zat')) && (! App::$install)) {
			App::$query_string = strip_zats(App::$query_string);
			if (! local_channel()) {
				zat_init();
			}
		}

		if ((x($_REQUEST,'owt')) && (! App::$install)) {
			$token = $_REQUEST['owt'];
			App::$query_string = strip_query_param(App::$query_string,'owt');
			owt_init($token);
		}

		if ((x($_SESSION, 'authenticated')) || (x($_POST, 'auth-params')) || (App::$module === 'login')) {
			require('include/auth.php');
		}

		if (! x($_SESSION, 'sysmsg')) {
			$_SESSION['sysmsg'] = [];
		}

		if (! x($_SESSION, 'sysmsg_info')) {
			$_SESSION['sysmsg_info'] = [];
		}


		if (App::$install) {
			/* Allow an exception for the view module so that pcss will be interpreted during installation */
			if (App::$module !== 'view')
				App::$module = 'setup';
		}
		else {

			/*
			 * check_config() is responsible for running update scripts. These automatically
			 * update the DB schema whenever we push a new one out. It also checks to see if
			 * any plugins have been added or removed and reacts accordingly.
			 */

			check_config();
		}

		$this->create_channel_links();

		$Router = new Router();

		$this->initialise_content();

		$Router->Dispatch();

		$this->set_homebase();

		// now that we've been through the module content, see if the page reported
		// a permission problem and if so, a 403 response would seem to be in order.

		if (is_array($_SESSION['sysmsg']) && stristr(implode("", $_SESSION['sysmsg']), t('Permission denied'))) {
			header($_SERVER['SERVER_PROTOCOL'] . ' 403 ' . t('Permission denied.'));
		}

		call_hooks('page_end', App::$page['content']);

		construct_page();

		killme();
	}


	private function initialise_content() {

		/* initialise content region */

		if (! x(App::$page, 'content')) {
			App::$page['content'] = EMPTY_STR;
		}

		call_hooks('page_content_top', App::$page['content']);
	}

	private function create_channel_links() {

		/* Initialise the Link: response header if this is a channel page. 
		 * This cannot be done inside the channel module because some protocol
		 * addons over-ride the module functions and these links are common 
		 * to all protocol drivers; thus doing it here avoids duplication.
		 */

		if (( App::$module === 'channel' ) && argc() > 1) {
			App::$channel_links = [
				[
					'rel'  => 'jrd',
					'type' => 'application/jrd+json',
					'url'  => z_root() . '/.well-known/webfinger?f=&resource=acct%3A' . argv(1) . '%40' . App::get_hostname()
				],

				[
					'rel'  => 'zot',
					'type' => 'application/x-zot+json',
					'url'  => z_root() . '/channel/' . argv(1)
				],

				[
					'rel'  => 'self',
					'type' => 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"',
					'href' => z_root() . '/channel/' . argv(1)
				],

				[
					'rel'  => 'self',
					'type' => 'application/activity+json',
					'href' => z_root() . '/channel/' . argv(1)
 				]
			];

			$x = [ 'channel_address' => argv(1), 'channel_links' => App::$channel_links ]; 
			call_hooks('channel_links', $x );
			App::$channel_links = $x['channel_links'];
			header('Link: ' . App::get_channel_links());
		}
	}

	private function set_homebase() {

		// If you're just visiting, let javascript take you home

		if (x($_SESSION, 'visitor_home')) {
			$homebase = $_SESSION['visitor_home'];
		}
		elseif (local_channel()) {
			$homebase = z_root() . '/channel/' . App::$channel['channel_address'];
		}

		if (isset($homebase)) {
			App::$page['content'] .= '<script>var homebase = "' . $homebase . '";</script>';
		}
	}
}
