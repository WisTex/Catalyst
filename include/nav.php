<?php /** @file */

use \Zotlabs\Lib as Zlib;

require_once('include/security.php');
require_once('include/menu.php');


function nav($template = 'default') {

	/**
	 *
	 * Build page header and site navigation bars
	 *
	 */

	if(!(x(App::$page,'nav')))
		App::$page['nav'] = '';

	App::$page['htmlhead'] .= '<script>$(document).ready(function() { $("#nav-search-text").search_autocomplete(\'' . z_root() . '/acl' . '\');});</script>';

	$is_owner = (((local_channel()) && ((App::$profile_uid == local_channel()) || (App::$profile_uid == 0))) ? true : false);

	if(local_channel()) {
		$channel = App::get_channel();
		$observer = App::get_observer();
		$prof = q("select id from profile where uid = %d and is_default = 1",
			intval($channel['channel_id'])
		);

		if(! $_SESSION['delegate']) {
			$chans = q("select channel_name, channel_id from channel where channel_account_id = %d and channel_removed = 0 order by channel_name ",
				intval(get_account_id())
			);
		}
		$sitelocation = (($is_owner) ? '' : App::$profile['reddress']);
	}
	elseif(remote_channel()) {
		$observer = App::get_observer();
		$sitelocation = ((App::$profile['reddress']) ? App::$profile['reddress'] : '@' . App::get_hostname());
	}

	require_once('include/conversation.php');

	$channel_apps[] = channel_apps($is_owner, App::$profile['channel_address']);


	/**
	 *
	 * Provide a banner/logo/whatever
	 *
	 */

	$banner = get_config('system','banner');

	if($banner === false) 
		$banner = get_config('system','sitename');

	App::$page['header'] .= replace_macros(get_markup_template('hdr.tpl'), array(
		//we could additionally use this to display important system notifications e.g. for updates
	));

	$techlevel = get_account_techlevel();

	// nav links: array of array('href', 'text', 'extra css classes', 'title')
	$nav = [];

	if(can_view_public_stream())
		$nav['pubs'] = true;

	/**
	 * Display login or logout
	 */	

	$nav['usermenu'] = [];
	$userinfo = null;
	$nav['loginmenu'] = [];

	if($observer) {
		$userinfo = [
			'icon' => $observer['xchan_photo_m'],
			'name' => $observer['xchan_addr'],
		];
	}

	elseif(! $_SESSION['authenticated']) {
		$nav['remote_login'] = remote_login();
		$nav['loginmenu'][] = Array('rmagic',t('Remote authentication'),'',t('Click to authenticate to your home hub'),'rmagic_nav_btn');
	}

	if(local_channel()) {

 		if(! $_SESSION['delegate']) {
 			$nav['manage'] = array('manage', t('Channel Manager'), "", t('Manage Your Channels'),'manage_nav_btn');
 		}

 		$nav['settings'] = array('settings', t('Settings'),"", t('Account/Channel Settings'),'settings_nav_btn');

	
		if($chans && count($chans) > 1 && feature_enabled(local_channel(),'nav_channel_select'))
			$nav['channels'] = $chans;

		$nav['logout'] = ['logout',t('Logout'), "", t('End this session'),'logout_nav_btn'];
		
		// user menu
		$nav['usermenu'][] = ['profile/' . $channel['channel_address'], t('View Profile'), ((\App::$nav_sel['name'] == 'Profile') ? 'active' : ''), t('Your profile page'),'profile_nav_btn'];

		if(feature_enabled(local_channel(),'multi_profiles'))
			$nav['usermenu'][]   = ['profiles', t('Edit Profiles'), ((\App::$nav_sel['name'] == 'Profiles') ? 'active' : '') , t('Manage/Edit profiles'),'profiles_nav_btn'];
		else
			$nav['usermenu'][]   = ['profiles/' . $prof[0]['id'], t('Edit Profile'), ((\App::$nav_sel['name'] == 'Profiles') ? 'active' : ''), t('Edit your profile'),'profiles_nav_btn'];

	}
	else {
		if(! get_account_id())  {
			if(App::$module === 'channel') {
				$nav['login'] = login(true,'main-login',false,false);
				$nav['loginmenu'][] = ['login',t('Login'),'',t('Sign in'),''];
			}
			else {
				$nav['login'] = login(true,'main-login',false,false);
				$nav['loginmenu'][] = ['login',t('Login'),'',t('Sign in'),'login_nav_btn'];
				App::$page['content'] .= replace_macros(get_markup_template('nav_login.tpl'),
					[ 
						'$nav' => $nav,
						'userinfo' => $userinfo
					]
				);
			}
		}
		else
			$nav['alogout'] = ['logout',t('Logout'), "", t('End this session'),'logout_nav_btn'];


	}

	$my_url = get_my_url();
	if(! $my_url) {
		$observer = App::get_observer();
		$my_url = (($observer) ? $observer['xchan_url'] : '');
	}

	$homelink_arr = parse_url($my_url);
	$homelink = $homelink_arr['scheme'] . '://' . $homelink_arr['host'];

	if(! $is_owner) {
		$nav['rusermenu'] = array(
			$homelink,
			t('Take me home'),
			'logout',
			((local_channel()) ? t('Logout') : t('Log me out of this site'))
		);
	}

	if(((get_config('system','register_policy') == REGISTER_OPEN) || (get_config('system','register_policy') == REGISTER_APPROVE)) && (! $_SESSION['authenticated']))
		$nav['register'] = ['register',t('Register'), "", t('Create an account'),'register_nav_btn'];

	if(! get_config('system','hide_help')) {
		$help_url = z_root() . '/help?f=&cmd=' . App::$cmd;
		$context_help = '';
		$enable_context_help = ((intval(get_config('system','enable_context_help')) === 1 || get_config('system','enable_context_help') === false) ? true : false);
		if($enable_context_help === true) {
			require_once('include/help.php');
			$context_help = load_context_help();
			//point directly to /help if $context_help is empty - this can be removed once we have context help for all modules
			$enable_context_help = (($context_help) ? true : false);
		}
		$nav['help'] = [$help_url, t('Help'), "", t('Help and documentation'), 'help_nav_btn', $context_help, $enable_context_help];
	}

	$nav['search'] = ['search', t('Search'), "", t('Search site @name, !forum, #tag, ?docs, content')];


	/**
	 *
	 * The following nav links are only show to logged in users
	 *
	 */

	if(local_channel()) {
		if(! $_SESSION['delegate']) {
			$nav['manage'] = array('manage', t('Channel Manager'), "", t('Manage Your Channels'),'manage_nav_btn');
		}
		$nav['settings'] = array('settings', t('Settings'),"", t('Account/Channel Settings'),'settings_nav_btn');
	}

	/**
	 * Admin page
	 */
	 if (is_site_admin()) {
		 $nav['admin'] = array('admin/', t('Admin'), "", t('Site Setup and Configuration'),'admin_nav_btn');
	 }

	$x = array('nav' => $nav, 'usermenu' => $userinfo );

	call_hooks('nav', $x);

	// Not sure the best place to put this on the page. So I'm implementing it but leaving it 
	// turned off until somebody discovers this and figures out a good location for it. 
	$powered_by = '';

	if(App::$profile_uid && App::$nav_sel['raw_name']) {
		$active_app = q("SELECT app_url FROM app WHERE app_channel = %d AND app_name = '%s' LIMIT 1",
			intval(App::$profile_uid),
			dbesc(App::$nav_sel['raw_name'])
		);
	
		if($active_app) {
			$url = $active_app[0]['app_url'];
		}
	}

	//app bin
	if($is_owner) {
		if(get_pconfig(local_channel(), 'system','initial_import_system_apps') === false) {
			Zlib\Apps::import_system_apps();
			set_pconfig(local_channel(), 'system','initial_import_system_apps', 1);
		}

		$syslist = array();
		$list = Zlib\Apps::app_list(local_channel(), false, ['nav_featured_app', 'nav_pinned_app']);
		if($list) {
			foreach($list as $li) {
				$syslist[] = Zlib\Apps::app_encode($li);
			}
		}
		Zlib\Apps::translate_system_apps($syslist);
	}
	else {
		$syslist = Zlib\Apps::get_system_apps(true);
	}

	usort($syslist,'Zotlabs\\Lib\\Apps::app_name_compare');

	$syslist = Zlib\Apps::app_order(local_channel(),$syslist);

	foreach($syslist as $app) {
		if(\App::$nav_sel['name'] == $app['name'])
			$app['active'] = true;

		if($is_owner) {
			if(strpos($app['categories'],'nav_pinned_app') !== false) {
				$navbar_apps[] = Zlib\Apps::app_render($app,'navbar');
			}
			else {
				$nav_apps[] = Zlib\Apps::app_render($app,'nav');
			}
		}
		elseif(! $is_owner && strpos($app['requires'], 'local_channel') === false) {
			if(strpos($app['categories'],'nav_pinned_app') !== false) {
				$navbar_apps[] = Zlib\Apps::app_render($app,'navbar');
			}
			else {
				$nav_apps[] = Zlib\Apps::app_render($app,'nav');
			}
		}
	}

	$c = theme_include('navbar_' . purify_filename($template) . '.css');
	$tpl = get_markup_template('navbar_' . purify_filename($template) . '.tpl');

	if($c && $tpl) {
		head_add_css('navbar_' . $template . '.css');
	}

	if(! $tpl) {
		$tpl = get_markup_template('navbar_default.tpl');
	}

	App::$page['nav'] .= replace_macros($tpl, array(
		'$baseurl' => z_root(),
		'$fulldocs' => t('Help'),
		'$sitelocation' => $sitelocation,
		'$nav' => $x['nav'],
		'$banner' =>  $banner,
		'$emptynotifications' => t('Loading'),
		'$userinfo' => $x['usermenu'],
		'$localuser' => local_channel(),
		'$is_owner' => $is_owner,
		'$sel' => App::$nav_sel,
		'$powered_by' => $powered_by,
		'$help' => t('@name, !forum, #tag, ?doc, content'),
		'$pleasewait' => t('Please wait...'),
		'$nav_apps' => $nav_apps,
		'$navbar_apps' => $navbar_apps,
		'$channel_menu' => get_pconfig(App::$profile_uid,'system','channel_menu',get_config('system','channel_menu')),
		'$channel_thumb' => ((App::$profile) ? App::$profile['thumb'] : ''),
		'$channel_apps' => $channel_apps,
		'$addapps' => t('Add Apps'),
		'$orderapps' => t('Arrange Apps'),
		'$sysapps_toggle' => t('Toggle System Apps'),
		'$url' => (($url) ? $url : App::$cmd)
	));

	if(x($_SESSION, 'reload_avatar') && $observer) {
		// The avatar has been changed on the server but the browser doesn't know that, 
		// force the browser to reload the image from the server instead of its cache.
		$tpl = get_markup_template('force_image_reload.tpl');

		App::$page['nav'] .= replace_macros($tpl, array(
			'$imgUrl' => $observer['xchan_photo_m']
		));
		unset($_SESSION['reload_avatar']);
	}


	call_hooks('page_header', App::$page['nav']);
}

/*
 * Set a menu item in navbar as selected
 * 
 */
function nav_set_selected($item){
	App::$nav_sel['raw_name'] = $item;
	$item = ['name' => $item];
	Zlib\Apps::translate_system_apps($item);
	App::$nav_sel['name'] = $item['name'];
}



function channel_apps($is_owner = false, $nickname = null) {

	// Don't provide any channel apps if we're running as the sys channel

	if(App::$is_sys)
		return '';

	if(! get_pconfig($uid, 'system', 'channelapps','1'))
		return '';

	$channel = App::get_channel();

	if($channel && is_null($nickname))
		$nickname = $channel['channel_address'];

	$uid = ((App::$profile['profile_uid']) ? App::$profile['profile_uid'] : local_channel());
	$account_id = ((App::$profile['profile_uid']) ? App::$profile['channel_account_id'] : App::$channel['channel_account_id']);

	if($uid == local_channel()) {
		return;
	}
	else {
		$cal_link = '/cal/' . $nickname;
	}

	$sql_options = item_permissions_sql($uid);

	$r = q("select item.* from item left join iconfig on item.id = iconfig.iid
		where item.uid = %d and iconfig.cat = 'system' and iconfig.v = '%s' 
		and item.item_delayed = 0 and item.item_deleted = 0 
		and ( iconfig.k = 'WEBPAGE' and item_type = %d ) 
		$sql_options limit 1",
		intval($uid),
		dbesc('home'),
		intval(ITEM_TYPE_WEBPAGE)
	);

	$has_webpages = (($r) ? true : false);

	if(x($_GET, 'tab'))
		$tab = notags(trim($_GET['tab']));

	$url = z_root() . '/channel/' . $nickname;
	$pr  = z_root() . '/profile/' . $nickname;

	$tabs = [
		[
			'label' => t('Channel'),
			'url'   => $url,
			'sel'   => ((argv(0) == 'channel') ? 'active' : ''),
			'title' => t('Status Messages and Posts'),
			'id'    => 'status-tab',
			'icon'  => 'home'
		],
	];

	$p = get_all_perms($uid,get_observer_hash());

	if ($p['view_profile']) {
		$tabs[] = [
			'label' => t('About'),
			'url'   => $pr,
			'sel'   => ((argv(0) == 'profile') ? 'active' : ''),
			'title' => t('Profile Details'),
			'id'    => 'profile-tab',
			'icon'  => 'user'
		];
	}
	if ($p['view_storage']) {
		$tabs[] = [
			'label' => t('Photos'),
			'url'   => z_root() . '/photos/' . $nickname,
			'sel'   => ((argv(0) == 'photos') ? 'active' : ''),
			'title' => t('Photo Albums'),
			'id'    => 'photo-tab',
			'icon'  => 'photo'
		];
		$tabs[] = [
			'label' => t('Files'),
			'url'   => z_root() . '/cloud/' . $nickname,
			'sel'   => ((argv(0) == 'cloud' || argv(0) == 'sharedwithme') ? 'active' : ''),
			'title' => t('Files and Storage'),
			'id'    => 'files-tab',
			'icon'  => 'folder-open'
		];
	}

	if($p['view_stream'] && $cal_link) {
		$tabs[] = [
			'label' => t('Calendar'),
			'url'   => z_root() . $cal_link,
			'sel'   => ((argv(0) == 'cal') ? 'active' : ''),
			'title' => t('Calendar'),
			'id'    => 'event-tab',
			'icon'  => 'calendar'
		];
	}


	if ($p['chat'] && feature_enabled($uid,'ajaxchat')) {
		$has_chats = ZLib\Chatroom::list_count($uid);
		if ($has_chats) {
			$tabs[] = [
				'label' => t('Chatrooms'),
				'url'   => z_root() . '/chat/' . $nickname,
				'sel'   => ((argv(0) == 'chat') ? 'active' : '' ),
				'title' => t('Chatrooms'),
				'id'    => 'chat-tab',
				'icon'  => 'comments-o'
			];
		}
	}

	$has_bookmarks = menu_list_count(local_channel(),'',MENU_BOOKMARK) + menu_list_count(local_channel(),'',MENU_SYSTEM|MENU_BOOKMARK);
	if ($is_owner && $has_bookmarks) {
		$tabs[] = [
			'label' => t('Bookmarks'),
			'url'   => z_root() . '/bookmarks',
			'sel'   => ((argv(0) == 'bookmarks') ? 'active' : ''),
			'title' => t('Saved Bookmarks'),
			'id'    => 'bookmarks-tab',
			'icon'  => 'bookmark'
		];
	}

	if($p['view_pages'] && feature_enabled($uid,'cards')) {
		$tabs[] = [
			'label' => t('Cards'),
			'url'   => z_root() . '/cards/' . $nickname ,
			'sel'   => ((argv(0) == 'cards') ? 'active' : ''),
			'title' => t('View Cards'),
			'id'    => 'cards-tab',
			'icon'  => 'list'
		];
	}

	if($p['view_pages'] && feature_enabled($uid,'articles')) {
		$tabs[] = [
			'label' => t('Articles'),
			'url'   => z_root() . '/articles/' . $nickname ,
			'sel'   => ((argv(0) == 'articles') ? 'active' : ''),
			'title' => t('View Articles'),
			'id'    => 'articles-tab',
			'icon'  => 'file-text-o'
		];
	}


	if($has_webpages && feature_enabled($uid,'webpages')) {
		$tabs[] = [
			'label' => t('Webpages'),
			'url'   => z_root() . '/page/' . $nickname . '/home',
			'sel'   => ((argv(0) == 'webpages') ? 'active' : ''),
			'title' => t('View Webpages'),
			'id'    => 'webpages-tab',
			'icon'  => 'newspaper-o'
		];
	}
 

	if ($p['view_wiki']) {
		if(feature_enabled($uid,'wiki') && (get_account_techlevel($account_id) > 3)) {
			$tabs[] = [
				'label' => t('Wikis'),
				'url'   => z_root() . '/wiki/' . $nickname,
				'sel'   => ((argv(0) == 'wiki') ? 'active' : ''),
				'title' => t('Wiki'),
				'id'    => 'wiki-tab',
				'icon'  => 'pencil-square-o'
			];
		}
	}

	$arr = array('is_owner' => $is_owner, 'nickname' => $nickname, 'tab' => (($tab) ? $tab : false), 'tabs' => $tabs);
	call_hooks('profile_tabs', $arr);
	call_hooks('channel_apps', $arr);	

	return replace_macros(get_markup_template('profile_tabs.tpl'), 
		[
			'$tabs'  => $arr['tabs'],
			'$name'  => App::$profile['channel_name'],
			'$thumb' => App::$profile['thumb'],
		]
	);
}
