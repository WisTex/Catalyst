<?php

namespace Zotlabs\Module\Admin;


class Site {

	/**
	 * @brief POST handler for Admin Site Page.
	 *
	 */
	function post(){
		if (!x($_POST, 'page_site')) {
			return;
		}

		check_form_security_token_redirectOnErr('/admin/site', 'admin_site');

		$sitename 			=	((x($_POST,'sitename'))			? notags(trim($_POST['sitename']))			: '');

		$banner				=	((x($_POST,'banner'))			? trim($_POST['banner'])				: false);

		$admininfo			=	((x($_POST,'admininfo'))		? trim($_POST['admininfo'])				: false);
		$siteinfo			=	((x($_POST,'siteinfo'))		    ? trim($_POST['siteinfo'])				: '');
		$language			=	((x($_POST,'language'))			? notags(trim($_POST['language']))			: '');
		$theme				=	((x($_POST,'theme'))			? notags(trim($_POST['theme']))				: '');
//		$theme_mobile			=	((x($_POST,'theme_mobile'))		? notags(trim($_POST['theme_mobile']))			: '');
//		$site_channel			=	((x($_POST,'site_channel'))	? notags(trim($_POST['site_channel']))				: '');
		$maximagesize		=	((x($_POST,'maximagesize'))		? intval(trim($_POST['maximagesize']))				:  0);

		$register_policy	=	((x($_POST,'register_policy'))	? intval(trim($_POST['register_policy']))	:  0);
		$minimum_age           = ((x($_POST,'minimum_age'))          ? intval(trim($_POST['minimum_age']))    : 13);
		$access_policy	=	((x($_POST,'access_policy'))	? intval(trim($_POST['access_policy']))	:  0);
		$invite_only	= ((x($_POST,'invite_only'))		? True	: False);
		$abandon_days	=	((x($_POST,'abandon_days'))	    ? intval(trim($_POST['abandon_days']))	    :  0);

		$register_text		=	((x($_POST,'register_text'))	? notags(trim($_POST['register_text']))		: '');
		$site_sellpage		=	((x($_POST,'site_sellpage'))	? notags(trim($_POST['site_sellpage']))		: '');
		$site_location		=	((x($_POST,'site_location'))	? notags(trim($_POST['site_location']))		: '');
		$frontpage			=	((x($_POST,'frontpage'))	? notags(trim($_POST['frontpage']))		: '');
		$firstpage		    =	((x($_POST,'firstpage'))	? notags(trim($_POST['firstpage']))		: 'profiles');
		$first_page		    =	((x($_POST,'first_page'))	? notags(trim($_POST['first_page']))		: 'profiles');
		// check value after trim
		if(! $first_page) {
			$first_page = 'profiles';
		}
		$mirror_frontpage	=	((x($_POST,'mirror_frontpage'))	? intval(trim($_POST['mirror_frontpage']))		: 0);
		$directory_server	=	((x($_POST,'directory_server')) ? trim($_POST['directory_server']) : '');
		$allowed_sites		=	((x($_POST,'allowed_sites'))	? notags(trim($_POST['allowed_sites']))		: '');
		$force_publish		=	((x($_POST,'publish_all'))		? True	: False);
		$disable_discover_tab =	((x($_POST,'disable_discover_tab'))		? False	:	True);
		$site_firehose      =   ((x($_POST,'site_firehose')) ? True : False);
		$open_pubstream     =   ((x($_POST,'open_pubstream')) ? True : False);
		$login_on_homepage	=	((x($_POST,'login_on_homepage'))		? True	:	False);
		$enable_context_help = ((x($_POST,'enable_context_help'))		? True	:	False);
		$global_directory     = ((x($_POST,'directory_submit_url'))	? notags(trim($_POST['directory_submit_url']))	: '');
		$no_community_page    = !((x($_POST,'no_community_page'))	? True	:	False);
		$default_expire_days  = ((array_key_exists('default_expire_days',$_POST)) ? intval($_POST['default_expire_days']) : 0);
		$active_expire_days  = ((array_key_exists('active_expire_days',$_POST)) ? intval($_POST['active_expire_days']) : 7);

		$reply_address      = ((array_key_exists('reply_address',$_POST) && trim($_POST['reply_address'])) ? trim($_POST['reply_address']) : 'noreply@' . \App::get_hostname());
		$from_email         = ((array_key_exists('from_email',$_POST) && trim($_POST['from_email'])) ? trim($_POST['from_email']) : 'Administrator@' . \App::get_hostname());
		$from_email_name    = ((array_key_exists('from_email_name',$_POST) && trim($_POST['from_email_name'])) ? trim($_POST['from_email_name']) : \Zotlabs\Lib\System::get_site_name());

		$verifyssl         = ((x($_POST,'verifyssl'))        ? True : False);
		$proxyuser         = ((x($_POST,'proxyuser'))        ? notags(trim($_POST['proxyuser']))  : '');
		$proxy             = ((x($_POST,'proxy'))            ? notags(trim($_POST['proxy']))      : '');
		$timeout           = ((x($_POST,'timeout'))          ? intval(trim($_POST['timeout']))    : 60);
		$delivery_interval = ((x($_POST,'delivery_interval'))? intval(trim($_POST['delivery_interval'])) : 0);
		$delivery_batch_count = ((x($_POST,'delivery_batch_count') && $_POST['delivery_batch_count'] > 0)? intval(trim($_POST['delivery_batch_count'])) : 1);
		$poll_interval     = ((x($_POST,'poll_interval'))    ? intval(trim($_POST['poll_interval'])) : 0);
		$maxloadavg        = ((x($_POST,'maxloadavg'))       ? intval(trim($_POST['maxloadavg'])) : 50);
		$feed_contacts     = ((x($_POST,'feed_contacts'))    ? intval($_POST['feed_contacts'])    : 0);
		$verify_email      = ((x($_POST,'verify_email'))     ? 1 : 0);
		$techlevel_lock    = ((x($_POST,'techlock'))         ? intval($_POST['techlock'])   : 0);
		$imagick_path      = ((x($_POST,'imagick_path'))     ? trim($_POST['imagick_path'])   : '');
		$thumbnail_security  = ((x($_POST,'thumbnail_security'))     ? intval($_POST['thumbnail_security'])   : 0);
		$force_queue       = ((intval($_POST['force_queue']) > 0) ? intval($_POST['force_queue'])   : 3000);
		$pub_incl = escape_tags(trim($_POST['pub_incl']));
		$pub_excl = escape_tags(trim($_POST['pub_excl']));

		$permissions_role = escape_tags(trim($_POST['permissions_role']));

		$techlevel         = null;
		if(array_key_exists('techlevel', $_POST))
			$techlevel = intval($_POST['techlevel']);

		set_config('system', 'feed_contacts', $feed_contacts);
		set_config('system', 'delivery_interval', $delivery_interval);
		set_config('system', 'delivery_batch_count', $delivery_batch_count);
		set_config('system', 'poll_interval', $poll_interval);
		set_config('system', 'maxloadavg', $maxloadavg);
		set_config('system', 'frontpage', $frontpage);
		set_config('system', 'sellpage', $site_sellpage);
		set_config('system', 'workflow_channel_next', $first_page);
		set_config('system', 'site_location', $site_location);
		set_config('system', 'mirror_frontpage', $mirror_frontpage);
		set_config('system', 'sitename', $sitename);
		set_config('system', 'login_on_homepage', $login_on_homepage);
		set_config('system', 'enable_context_help', $enable_context_help);
		set_config('system', 'verify_email', $verify_email);
		set_config('system', 'default_expire_days', $default_expire_days);
		set_config('system', 'active_expire_days', $active_expire_days);
		set_config('system', 'reply_address', $reply_address);
		set_config('system', 'from_email', $from_email);
		set_config('system', 'from_email_name' , $from_email_name);
		set_config('system', 'imagick_convert_path' , $imagick_path);
		set_config('system', 'thumbnail_security' , $thumbnail_security);
		set_config('system', 'default_permissions_role', $permissions_role);
		set_config('system', 'pubstream_incl',$pub_incl);
		set_config('system', 'pubstream_excl',$pub_excl);

		set_config('system', 'techlevel_lock', $techlevel_lock);



		if(! is_null($techlevel))
			set_config('system', 'techlevel', $techlevel);

		if($directory_server)
			set_config('system','directory_server',$directory_server);

		if ($banner == '') {
			del_config('system', 'banner');
		} else {
			set_config('system', 'banner', $banner);
		}

		if ($admininfo == ''){
			del_config('system', 'admininfo');
		} else {
			require_once('include/text.php');
			linkify_tags($a, $admininfo, local_channel());
			set_config('system', 'admininfo', $admininfo);
		}
		set_config('system','siteinfo',$siteinfo);
		set_config('system', 'language', $language);
		set_config('system', 'theme', $theme);
//		if ( $theme_mobile === '---' ) {
//			del_config('system', 'mobile_theme');
//		} else {
//			set_config('system', 'mobile_theme', $theme_mobile);
//		}
	//	set_config('system','site_channel', $site_channel);
		set_config('system','maximagesize', $maximagesize);

		set_config('system','register_policy', $register_policy);
		set_config('system','minimum_age', $minimum_age);
		set_config('system','invitation_only', $invite_only);
		set_config('system','access_policy', $access_policy);
		set_config('system','account_abandon_days', $abandon_days);
		set_config('system','register_text', $register_text);
		set_config('system','allowed_sites', $allowed_sites);
		set_config('system','publish_all', $force_publish);
		set_config('system','disable_discover_tab', $disable_discover_tab);
		set_config('system','site_firehose', $site_firehose);
		set_config('system','open_pubstream', $open_pubstream);
		set_config('system','force_queue_threshold', $force_queue);
		if ($global_directory == '') {
			del_config('system', 'directory_submit_url');
		} else {
			set_config('system', 'directory_submit_url', $global_directory);
		}

		set_config('system','no_community_page', $no_community_page);
		set_config('system','no_utf', $no_utf);
		set_config('system','verifyssl', $verifyssl);
		set_config('system','proxyuser', $proxyuser);
		set_config('system','proxy', $proxy);
		set_config('system','curl_timeout', $timeout);

		info( t('Site settings updated.') . EOL);
		goaway(z_root() . '/admin/site' );
	}

	/**
	 * @brief Admin page site.
	 *
	 * @return string with HTML
	 */
	function get() {

		/* Installed langs */
		$lang_choices = array();
		$langs = glob('view/*/hstrings.php');

		if(is_array($langs) && count($langs)) {
			if(! in_array('view/en/hstrings.php',$langs))
				$langs[] = 'view/en/';
			asort($langs);
			foreach($langs as $l) {
				$t = explode("/",$l);
				$lang_choices[$t[1]] = $t[1];
			}
		}

		/* Installed themes */
		$theme_choices_mobile["---"] = t("Default");
		$theme_choices = array();
		$files = glob('view/theme/*');
		if($files) {
			foreach($files as $file) {
				$vars = '';
				$f = basename($file);

				$info = get_theme_info($f);
				$compatible = check_plugin_versions($info);
				if(!$compatible) {
					$theme_choices[$f] = $theme_choices_mobile[$f] = sprintf(t('%s - (Incompatible)'), $f);
					continue;
				}

				if (file_exists($file . '/library'))
					continue;
				if (file_exists($file . '/mobile'))
					$vars = t('mobile');
				if (file_exists($file . '/experimental'))
					$vars .= t('experimental');
				if (file_exists($file . '/unsupported'))
					$vars .= t('unsupported');
				if ($vars) {
					$theme_choices[$f] = $f . ' (' . $vars . ')';
					$theme_choices_mobile[$f] = $f . ' (' . $vars . ')';
				}
				else {
					$theme_choices[$f] = $f;
					$theme_choices_mobile[$f] = $f;
				}
			}
		}

		$dir_choices = null;
		$dirmode = get_config('system','directory_mode');
		$realm = get_directory_realm();

		// directory server should not be set or settable unless we are a directory client
		// avoid older redmatrix servers which don't have modern encryption

		if($dirmode == DIRECTORY_MODE_NORMAL) {
			$x = q("select site_url from site where site_flags in (%d,%d) and site_realm = '%s' and site_dead = 0 and site_project != 'redmatrix'",
				intval(DIRECTORY_MODE_SECONDARY),
				intval(DIRECTORY_MODE_PRIMARY),
				dbesc($realm)
			);
			if($x) {
				$dir_choices = array();
				foreach($x as $xx) {
					$dir_choices[$xx['site_url']] = $xx['site_url'];
				}
			}
		}

		/* Banner */

		$banner = get_config('system', 'banner');
		if($banner === false)
			$banner = get_config('system','sitename');

		$banner = htmlspecialchars($banner);

		/* Admin Info */
		$admininfo = get_config('system', 'admininfo');

		/* Register policy */
		$register_choices = Array(
			REGISTER_CLOSED  => t("No"),
			REGISTER_APPROVE => t("Yes - with approval"),
			REGISTER_OPEN    => t("Yes")
		);

		/* Acess policy */
		$access_choices = Array(
			ACCESS_PRIVATE => t("My site is not a public server"),
			ACCESS_PAID => t("My site has paid access only"),
			ACCESS_FREE => t("My site has free access only"),
			ACCESS_TIERED => t("My site offers free accounts with optional paid upgrades")
		);

		$discover_tab = get_config('system','disable_discover_tab');

		// $disable public streams by default
		if($discover_tab === false)
			$discover_tab = 1;
		// now invert the logic for the setting.
		$discover_tab = (1 - $discover_tab);

		$techlevels = [
			'0' => t('Beginner/Basic'),
			'1' => t('Novice - not skilled but willing to learn'),
			'2' => t('Intermediate - somewhat comfortable'),
			'3' => t('Advanced - very comfortable'),
			'4' => t('Expert - I can write computer code'),
			'5' => t('Wizard - I probably know more than you do')
		];

		$perm_roles = \Zotlabs\Access\PermissionRoles::roles();
		$default_role = get_config('system','default_permissions_role','social');

		$role = array('permissions_role' , t('Default permission role for new accounts'), $default_role, t('This role will be used for the first channel created after registration.'),$perm_roles);


		$homelogin = get_config('system','login_on_homepage');
		$enable_context_help = get_config('system','enable_context_help');

		$t = get_markup_template("admin_site.tpl");
		return replace_macros($t, array(
			'$title' => t('Administration'),
			'$page' => t('Site'),
			'$submit' => t('Submit'),
			'$registration' => t('Registration'),
			'$upload' => t('File upload'),
			'$corporate' => t('Policies'),
			'$advanced' => t('Advanced'),

			'$baseurl' => z_root(),
			// name, label, value, help string, extra data...
			'$sitename' 		=> array('sitename', t("Site name"), htmlspecialchars(get_config('system','sitename'), ENT_QUOTES, 'UTF-8'),''),

			'$techlevel' => [ 'techlevel', t('Site default technical skill level'), get_config('system','techlevel'), t('Used to provide a member experience matched to technical comfort level'), $techlevels ],

			'$techlock' => [ 'techlock', t('Lock the technical skill level setting'), get_config('system','techlevel_lock'), t('Members can set their own technical comfort level by default') ],

			'$banner'			=> array('banner', t("Banner/Logo"), $banner, t('Unfiltered HTML/CSS/JS is allowed')),
			'$admininfo'		=> array('admininfo', t("Administrator Information"), $admininfo, t("Contact information for site administrators.  Displayed on siteinfo page.  BBCode can be used here")),
			'$siteinfo'		=> array('siteinfo', t('Site Information'), get_config('system','siteinfo'), t("Publicly visible description of this site.  Displayed on siteinfo page.  BBCode can be used here")),
			'$language' 		=> array('language', t("System language"), get_config('system','language'), "", $lang_choices),
			'$theme' 			=> array('theme', t("System theme"), get_config('system','theme'), t("Default system theme - may be over-ridden by user profiles - <a href='#' id='cnftheme'>change theme settings</a>"), $theme_choices),
//			'$theme_mobile' 	=> array('theme_mobile', t("Mobile system theme"), get_config('system','mobile_theme'), t("Theme for mobile devices"), $theme_choices_mobile),
//			'$site_channel' 	=> array('site_channel', t("Channel to use for this website's static pages"), get_config('system','site_channel'), t("Site Channel")),
			'$feed_contacts'    => array('feed_contacts', t('Allow Feeds as Connections'),get_config('system','feed_contacts'),t('(Heavy system resource usage)')),
			'$maximagesize'		=> array('maximagesize', t("Maximum image size"), intval(get_config('system','maximagesize')), t("Maximum size in bytes of uploaded images. Default is 0, which means no limits.")),
			'$register_policy'	=> array('register_policy', t("Does this site allow new member registration?"), get_config('system','register_policy'), "", $register_choices),
			'$invite_only'		=> array('invite_only', t("Invitation only"), get_config('system','invitation_only'), t("Only allow new member registrations with an invitation code. Above register policy must be set to Yes.")),
			'$minimum_age'		=> array('minimum_age', t("Minimum age"), (x(get_config('system','minimum_age'))?get_config('system','minimum_age'):13), t("Minimum age (in years) for who may register on this site.")),
			'$access_policy'	=> array('access_policy', t("Which best describes the types of account offered by this hub?"), get_config('system','access_policy'), "This is displayed on the public server site list.", $access_choices),
			'$register_text'	=> array('register_text', t("Register text"), htmlspecialchars(get_config('system','register_text'), ENT_QUOTES, 'UTF-8'), t("Will be displayed prominently on the registration page.")),
			'$role'         => $role,
			'$frontpage'	=> array('frontpage', t("Site homepage to show visitors (default: login box)"), get_config('system','frontpage'), t("example: 'public' to show public stream, 'page/sys/home' to show a system webpage called 'home' or 'include:home.html' to include a file.")),
			'$mirror_frontpage'	=> array('mirror_frontpage', t("Preserve site homepage URL"), get_config('system','mirror_frontpage'), t('Present the site homepage in a frame at the original location instead of redirecting')),
			'$abandon_days'     => array('abandon_days', t('Accounts abandoned after x days'), get_config('system','account_abandon_days'), t('Will not waste system resources polling external sites for abandonded accounts. Enter 0 for no time limit.')),
			'$allowed_sites'	=> array('allowed_sites', t("Allowed friend domains"), get_config('system','allowed_sites'), t("Comma separated list of domains which are allowed to establish friendships with this site. Wildcards are accepted. Empty to allow any domains")),
			'$verify_email'		=> array('verify_email', t("Verify Email Addresses"), get_config('system','verify_email'), t("Check to verify email addresses used in account registration (recommended).")),
			'$force_publish'	=> array('publish_all', t("Force publish"), get_config('system','publish_all'), t("Check to force all profiles on this site to be listed in the site directory.")),
			'$disable_discover_tab'	=> array('disable_discover_tab', t('Import Public Streams'), $discover_tab, t('Import and allow access to public content pulled from other sites. Warning: this content is unmoderated.')),
			'$site_firehose'	=> array('site_firehose', t('Site only Public Streams'), get_config('system','site_firehose'), t('Allow access to public content originating only from this site if Imported Public Streams are disabled.')),
			'$open_pubstream'	=> array('open_pubstream', t('Allow anybody on the internet to access the Public streams'), get_config('system','open_pubstream',1), t('Disable to require authentication before viewing. Warning: this content is unmoderated.')),
			'$incl'           => array('pub_incl',t('Only import Public stream posts with this text'), get_config('system','pubstream_incl'),t('words one per line or #tags or /patterns/ or lang=xx, leave blank to import all posts')),
			'$excl'           => array('pub_excl',t('Do not import Public stream posts with this text'), get_config('system','pubstream_excl'),t('words one per line or #tags or /patterns/ or lang=xx, leave blank to import all posts')),


			'$login_on_homepage'	=> array('login_on_homepage', t("Login on Homepage"),((intval($homelogin) || $homelogin === false) ? 1 : '') , t("Present a login box to visitors on the home page if no other content has been configured.")),
			'$enable_context_help'	=> array('enable_context_help', t("Enable context help"),((intval($enable_context_help) === 1 || $enable_context_help === false) ? 1 : 0) , t("Display contextual help for the current page when the help button is pressed.")),

			'$reply_address' => [ 'reply_address', t('Reply-to email address for system generated email.'), get_config('system','reply_address','noreply@' . \App::get_hostname()),'' ],
			'$from_email' => [ 'from_email', t('Sender (From) email address for system generated email.'), get_config('system','from_email','Administrator@' . \App::get_hostname()),'' ],
			'$from_email_name' => [ 'from_email_name', t('Name of email sender for system generated email.'), get_config('system','from_email_name',\Zotlabs\Lib\System::get_site_name()),'' ],

			'$directory_server' => (($dir_choices) ? array('directory_server', t("Directory Server URL"), get_config('system','directory_server'), t("Default directory server"), $dir_choices) : null),

			'$proxyuser'		=> array('proxyuser', t("Proxy user"), get_config('system','proxyuser'), ""),
			'$proxy'			=> array('proxy', t("Proxy URL"), get_config('system','proxy'), ""),
			'$timeout'			=> array('timeout', t("Network timeout"), (x(get_config('system','curl_timeout'))?get_config('system','curl_timeout'):60), t("Value is in seconds. Set to 0 for unlimited (not recommended).")),
			'$delivery_interval'			=> array('delivery_interval', t("Delivery interval"), (x(get_config('system','delivery_interval'))?get_config('system','delivery_interval'):2), t("Delay background delivery processes by this many seconds to reduce system load. Recommend: 4-5 for shared hosts, 2-3 for virtual private servers. 0-1 for large dedicated servers.")),
			'$delivery_batch_count' => array('delivery_batch_count', t('Deliveries per process'),(x(get_config('system','delivery_batch_count'))?get_config('system','delivery_batch_count'):1), t("Number of deliveries to attempt in a single operating system process. Adjust if necessary to tune system performance. Recommend: 1-5.")),
			'$force_queue'			=> array('force_queue', t("Queue Threshold"), get_config('system','force_queue_threshold',3000), t("Always defer immediate delivery if queue contains more than this number of entries.")),
			'$poll_interval'			=> array('poll_interval', t("Poll interval"), (x(get_config('system','poll_interval'))?get_config('system','poll_interval'):2), t("Delay background polling processes by this many seconds to reduce system load. If 0, use delivery interval.")),
			'$imagick_path'			=> array('imagick_path', t("Path to ImageMagick convert program"), get_config('system','imagick_convert_path'), t("If set, use this program to generate photo thumbnails for huge images ( > 4000 pixels in either dimension), otherwise memory exhaustion may occur. Example: /usr/bin/convert")),
			'$thumbnail_security'			=> array('thumbnail_security', t("Allow SVG thumbnails in file browser"), get_config('system','thumbnail_security',0), t("WARNING: SVG images may contain malicious code.")),
			'$maxloadavg'			=> array('maxloadavg', t("Maximum Load Average"), ((intval(get_config('system','maxloadavg')) > 0)?get_config('system','maxloadavg'):50), t("Maximum system load before delivery and poll processes are deferred - default 50.")),
			'$default_expire_days' => array('default_expire_days', t('Expiration period in days for imported (grid/network) content'), intval(get_config('system','default_expire_days')), t('0 for no expiration of imported content')),
			'$active_expire_days' => array('active_expire_days', t('Do not expire any posts which have comments less than this many days ago'), intval(get_config('system','active_expire_days',7)), ''),

			'$sellpage' => array('site_sellpage', t('Public servers: Optional landing (marketing) webpage for new registrants'), get_config('system','sellpage',''), sprintf( t('Create this page first. Default is %s/register'),z_root())),
			'$first_page' => array('first_page', t('Page to display after creating a new channel'), get_config('system','workflow_channel_next','profiles'), t('Default: profiles')),

			'$location' => array('site_location', t('Optional: site location'), get_config('system','site_location',''), t('Region or country')),


			'$form_security_token' => get_form_security_token("admin_site"),
		));
	}

}
