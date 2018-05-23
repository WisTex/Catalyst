<?php /** @file */

namespace Zotlabs\Lib;

/**
 * Apps
 *
 */

require_once('include/plugin.php');
require_once('include/channel.php');


class Apps {

	static public $installed_system_apps = null;

	static public function get_system_apps($translate = true) {

		$ret = array();
		if(is_dir('apps'))
			$files = glob('apps/*.apd');
		else
			$files = glob('app/*.apd');
		if($files) {
			foreach($files as $f) {
				$x = self::parse_app_description($f,$translate);
				if($x) {
					$ret[] = $x;
				}
			}
		}
		$files = glob('addon/*/*.apd');
		if($files) {
			foreach($files as $f) {
				$path = explode('/',$f);
				$plugin = trim($path[1]);
				if(plugin_is_installed($plugin)) {
					$x = self::parse_app_description($f,$translate);
					if($x) {
						$x['plugin'] = $plugin;
						$ret[] = $x;
					}
				}
			}
		}

		call_hooks('get_system_apps',$ret);

		return $ret;

	}


	static public function import_system_apps() {
		if(! local_channel())
			return;
		$apps = self::get_system_apps(false);

		self::$installed_system_apps = q("select * from app where app_system = 1 and app_channel = %d",
			intval(local_channel())
		);

		if($apps) {
			foreach($apps as $app) {
				$id = self::check_install_system_app($app);
				// $id will be boolean true or false to install an app, or an integer id to update an existing app
				if($id === false)
					continue;
				if($id !== true) {
					// if we already installed this app, but it changed, preserve any categories we created
					$s = '';
					$r = q("select * from term where otype = %d and oid = %d",
						intval(TERM_OBJ_APP),
						intval($id)
					);
					if($r) {
						foreach($r as $t) {
							if($s)
								$s .= ',';
							$s .= $t['term'];
						}
						$app['categories'] = $s;
					}
				}
				$app['uid'] = local_channel();
				$app['guid'] = hash('whirlpool',$app['name']);
				$app['system'] = 1;
				self::app_install(local_channel(),$app);
			}
		}					
	}

	/**
	 * Install the system app if no system apps have been installed, or if a new system app 
	 * is discovered, or if the version of a system app changes.
	 */

	static public function check_install_system_app($app) {
		if((! is_array(self::$installed_system_apps)) || (! count(self::$installed_system_apps))) {
			return true;
		}
		$notfound = true;
		foreach(self::$installed_system_apps as $iapp) {
			if($iapp['app_id'] == hash('whirlpool',$app['name'])) {
				$notfound = false;
				if(($iapp['app_version'] != $app['version'])
					|| ($app['plugin'] && (! $iapp['app_plugin']))) {
					return intval($iapp['app_id']);
				}
			}
		}

		return $notfound;
	}


	static public function app_name_compare($a,$b) {
		return strcasecmp($a['name'],$b['name']);
	}


	static public function parse_app_description($f,$translate = true) {

		$ret = array();

		$baseurl = z_root();
		$channel = \App::get_channel();
		$address = (($channel) ? $channel['channel_address'] : '');
		
		//future expansion

		$observer = \App::get_observer();
	

		$lines = @file($f);
		if($lines) {
			foreach($lines as $x) {
				if(preg_match('/^([a-zA-Z].*?):(.*?)$/ism',$x,$matches)) {
					$ret[$matches[1]] = trim(str_replace(array('$baseurl','$nick'),array($baseurl,$address),$matches[2]));
				}
			}
		}	


		if(! $ret['photo'])
			$ret['photo'] = $baseurl . '/' . get_default_profile_photo(80);

		$ret['type'] = 'system';

		foreach($ret as $k => $v) {
			if(strpos($v,'http') === 0) {
				if(! (local_channel() && strpos($v,z_root()) === 0)) {
					$ret[$k] = zid($v);
				}
			}
		}

		if(array_key_exists('desc',$ret))
			$ret['desc'] = str_replace(array('\'','"'),array('&#39;','&dquot;'),$ret['desc']);

		if(array_key_exists('target',$ret))
			$ret['target'] = str_replace(array('\'','"'),array('&#39;','&dquot;'),$ret['target']);

		if(array_key_exists('version',$ret))
			$ret['version'] = str_replace(array('\'','"'),array('&#39;','&dquot;'),$ret['version']);

		if(array_key_exists('categories',$ret))
			$ret['categories'] = str_replace(array('\'','"'),array('&#39;','&dquot;'),$ret['categories']);

		if(array_key_exists('requires',$ret)) {
			$requires = explode(',',$ret['requires']);
			foreach($requires as $require) {
				$require = trim(strtolower($require));
				$config = false;

				if(substr($require, 0, 7) == 'config:') {
					$config = true;
					$require = ltrim($require, 'config:');
					$require = explode('=', $require);
				}

				switch($require) {
					case 'nologin':
						if(local_channel())
							unset($ret);
						break;
					case 'admin':
						if(! is_site_admin())
							unset($ret);
						break;
					case 'local_channel':
						if(! local_channel())
							unset($ret);
						break;
					case 'public_profile':
						if(! is_public_profile())
							unset($ret);
						break;
					case 'public_stream':
						if(! can_view_public_stream())
							unset($ret);
						break;
					case 'observer':
						if(! $observer)
							unset($ret);
						break;
					default:
						if($config)
							$unset = ((get_config('system', $require[0]) == $require[1]) ? false : true);
						else
							$unset = ((local_channel() && feature_enabled(local_channel(),$require)) ? false : true);
						if($unset)
							unset($ret);
						break;
				}
			}
		}
		if($ret) {
			if($translate)
				self::translate_system_apps($ret);
			return $ret;
		}
		return false;
	}	


	static public function translate_system_apps(&$arr) {
		$apps = array(
			'Apps' => t('Apps'),
			'Articles' => t('Articles'),
			'Cards' => t('Cards'),
			'Admin' => t('Site Admin'),
			'Report Bug' => t('Report Bug'),
			'View Bookmarks' => t('View Bookmarks'),
			'My Chatrooms' => t('My Chatrooms'),
			'Connections' => t('Connections'),
			'Firefox Share' => t('Firefox Share'),
			'Remote Diagnostics' => t('Remote Diagnostics'),
			'Suggest Channels' => t('Suggest Channels'),
			'Login' => t('Login'),
			'Channel Manager' => t('Channel Manager'), 
			'Grid' => t('Activity'), 
			'Settings' => t('Settings'),
			'Files' => t('Files'),
			'Webpages' => t('Webpages'),
			'Wiki' => t('Wiki'),
			'Channel Home' => t('Channel Home'), 
			'View Profile' => t('View Profile'),
			'Photos' => t('Photos'), 
			'Events' => t('Events'), 
			'Directory' => t('Directory'), 
			'Help' => t('Help'),
			'Mail' => t('Mail'),
			'Mood' => t('Mood'),
			'Poke' => t('Poke'),
			'Chat' => t('Chat'),
			'Search' => t('Search'),
			'Probe' => t('Probe'),
			'Suggest' => t('Suggest'),
			'Random Channel' => t('Random Channel'),
			'Invite' => t('Invite'),
			'Features' => t('Features'),
			'Language' => t('Language'),
			'Post' => t('Post'),
			'Profile Photo' => t('Profile Photo')
		);

		if(array_key_exists('name',$arr)) {
			if(array_key_exists($arr['name'],$apps)) {
				$arr['name'] = $apps[$arr['name']];
			}
		}
		else {
			for($x = 0; $x < count($arr); $x++) {
				if(array_key_exists($arr[$x]['name'],$apps)) {
					$arr[$x]['name'] = $apps[$arr[$x]['name']];
				}
			}
		}
				
	}


	// papp is a portable app

	static public function app_render($papp,$mode = 'view') {

		/**
		 * modes:
		 *    view: normal mode for viewing an app via bbcode from a conversation or page
		 *       provides install/update button if you're logged in locally
		 *    list: normal mode for viewing an app on the app page
		 *       no buttons are shown
		 *    edit: viewing the app page in editing mode provides a delete button
		 *    nav: render apps for app-bin
		 */

		$installed = false;

		if(! $papp)
			return;

		if(! $papp['photo'])
			$papp['photo'] = z_root() . '/' . get_default_profile_photo(80);

		self::translate_system_apps($papp);

		if(trim($papp['plugin']) && (! plugin_is_installed(trim($papp['plugin']))))
			return '';

		$papp['papp'] = self::papp_encode($papp);

		if(! strstr($papp['url'],'://'))
			$papp['url'] = z_root() . ((strpos($papp['url'],'/') === 0) ? '' : '/') . $papp['url'];

		foreach($papp as $k => $v) {
			if(strpos($v,'http') === 0 && $k != 'papp') {
				if(! (local_channel() && strpos($v,z_root()) === 0)) {
					$papp[$k] = zid($v);
				}
			}
			if($k === 'desc')
				$papp['desc'] = str_replace(array('\'','"'),array('&#39;','&dquot;'),$papp['desc']);

			if($k === 'requires') {
				$requires = explode(',',$v);

				foreach($requires as $require) {
					$require = trim(strtolower($require));
					$config = false;

					if(substr($require, 0, 7) == 'config:') {
						$config = true;
						$require = ltrim($require, 'config:');
						$require = explode('=', $require);
					}

					switch($require) {
						case 'nologin':
							if(local_channel())
								return '';
							break;
						case 'admin':
							if(! is_site_admin())
								return '';
							break;
						case 'local_channel':
							if(! local_channel())
								return '';
							break;
						case 'public_profile':
							if(! is_public_profile())
								return '';
							break;
						case 'public_stream':
							if(! can_view_public_stream())
								return '';
							break;
						case 'observer':
							$observer = \App::get_observer();
							if(! $observer)
								return '';
							break;
						default:
							if($config)
								$unset = ((get_config('system', $require[0]) === $require[1]) ? false : true);
							else
								$unset = ((local_channel() && feature_enabled(local_channel(),$require)) ? false : true);
							if($unset)
								return '';
							break;
					}
				}
			}
		}

		$hosturl = '';

		if(local_channel()) {
			$installed = self::app_installed(local_channel(),$papp);
			$hosturl = z_root() . '/';
		}
		elseif(remote_channel()) {
			$observer = \App::get_observer();
			if($observer && $observer['xchan_network'] === 'zot') {
				// some folks might have xchan_url redirected offsite, use the connurl
				$x = parse_url($observer['xchan_connurl']);
				if($x) {
					$hosturl = $x['scheme'] . '://' . $x['host'] . '/';
				}
			} 
		}

		$install_action = (($installed) ? t('Update') : t('Install'));
		$icon = ((strpos($papp['photo'],'icon:') === 0) ? substr($papp['photo'],5) : '');

		if($mode === 'navbar') {
			return replace_macros(get_markup_template('app_nav.tpl'),array(
				'$app' => $papp,
				'$icon' => $icon,
			));
		}

		return replace_macros(get_markup_template('app.tpl'),array(
			'$app' => $papp,
			'$icon' => $icon,
			'$hosturl' => $hosturl,
			'$purchase' => (($papp['page'] && (! $installed)) ? t('Purchase') : ''),
			'$install' => (($hosturl && $mode == 'view') ? $install_action : ''),
			'$edit' => ((local_channel() && $installed && $mode == 'edit') ? t('Edit') : ''),
			'$delete' => ((local_channel() && $installed && $mode == 'edit') ? t('Delete') : ''),
			'$undelete' => ((local_channel() && $installed && $mode == 'edit') ? t('Undelete') : ''),
			'$deleted' => $papp['deleted'],
			'$feature' => (($papp['embed']) ? false : true),
			'$pin' => (($papp['embed']) ? false : true),
			'$featured' => ((strpos($papp['categories'], 'nav_featured_app') === false) ? false : true),
			'$pinned' => ((strpos($papp['categories'], 'nav_pinned_app') === false) ? false : true),
			'$navapps' => (($mode == 'nav') ? true : false),
			'$order' => (($mode == 'nav-order') ? true : false),
			'$add' => t('Add to app-tray'),
			'$remove' => t('Remove from app-tray'),
			'$add_nav' => t('Pin to navbar'),
			'$remove_nav' => t('Unpin from navbar')
		));
	}

	static public function app_install($uid,$app) {
		$app['uid'] = $uid;

		if(self::app_installed($uid,$app))
			$x = self::app_update($app);
		else
			$x = self::app_store($app);

		if($x['success']) {
			$r = q("select * from app where app_id = '%s' and app_channel = %d limit 1",
				dbesc($x['app_id']),
				intval($uid)
			);
			if($r) {
				if(! $r[0]['app_system']) {
					if($app['categories'] && (! $app['term'])) {
						$r[0]['term'] = q("select * from term where otype = %d and oid = %d",
							intval(TERM_OBJ_APP),
							intval($r[0]['id'])
						);
						build_sync_packet($uid,array('app' => $r[0]));
					}
				}
			}
			return $x['app_id'];
		}
		return false;
	}

	static public function app_destroy($uid,$app) {


		if($uid && $app['guid']) {

			$x = q("select * from app where app_id = '%s' and app_channel = %d limit 1",
				dbesc($app['guid']),
				intval($uid)
			);
			if($x) {
				if(! intval($x[0]['app_deleted'])) {
					$x[0]['app_deleted'] = 1;
					q("delete from term where otype = %d and oid = %d",
						intval(TERM_OBJ_APP),
						intval($x[0]['id'])
					);
					if($x[0]['app_system']) {
						$r = q("update app set app_deleted = 1 where app_id = '%s' and app_channel = %d",
							dbesc($app['guid']),
							intval($uid)
						);
					}
					else {
						$r = q("delete from app where app_id = '%s' and app_channel = %d",
							dbesc($app['guid']),
							intval($uid)
						);

						// we don't sync system apps - they may be completely different on the other system
						build_sync_packet($uid,array('app' => $x));
					}
				}
				else {
					self::app_undestroy($uid,$app);
				}
			}
		}
	}

	static public function app_undestroy($uid,$app) {

		// undelete a system app
		
		if($uid && $app['guid']) {

			$x = q("select * from app where app_id = '%s' and app_channel = %d limit 1",
				dbesc($app['guid']),
				intval($uid)
			);
			if($x) {
				if($x[0]['app_system']) {
					$r = q("update app set app_deleted = 0 where app_id = '%s' and app_channel = %d",
						dbesc($app['guid']),
						intval($uid)
					);
				}
			}
		}
	}

	static public function app_feature($uid,$app,$term) {
		$r = q("select id from app where app_id = '%s' and app_channel = %d limit 1",
			dbesc($app['guid']),
			intval($uid)
		);

		$x = q("select * from term where otype = %d and oid = %d and term = '%s' limit 1",
			intval(TERM_OBJ_APP),
			intval($r[0]['id']),
			dbesc($term)
		);

		if($x) {
			q("delete from term where otype = %d and oid = %d and term = '%s'",
				intval(TERM_OBJ_APP),
				intval($x[0]['oid']),
				dbesc($term)
			);
		}
		else {
			store_item_tag($uid, $r[0]['id'], TERM_OBJ_APP, TERM_CATEGORY, $term, escape_tags(z_root() . '/apps/?f=&cat=' . $term));
		}
	}

	static public function app_installed($uid,$app) {

		$r = q("select id from app where app_id = '%s' and app_channel = %d limit 1",
			dbesc((array_key_exists('guid',$app)) ? $app['guid'] : ''), 
			intval($uid)
		);
		return(($r) ? true : false);

	}


	static public function app_list($uid, $deleted = false, $cats = []) {
		if($deleted) 
			$sql_extra = "";
		else
			$sql_extra = " and app_deleted = 0 ";

		if($cats) {

			$cat_sql_extra = " and ( ";

			foreach($cats as $cat) {
				if(strpos($cat_sql_extra, 'term'))
					$cat_sql_extra .= "or ";

				$cat_sql_extra .= "term = '" . dbesc($cat) . "' ";
			}

			$cat_sql_extra .=  ") ";

			$r = q("select oid from term where otype = %d $cat_sql_extra",
				intval(TERM_OBJ_APP)
			);
			if(! $r)
				return $r;
			$sql_extra .= " and app.id in ( ";
			$s = '';
			foreach($r as $rr) {
				if($s)
					$s .= ',';
				$s .= intval($rr['oid']);
			}
			$sql_extra .= $s . ') ';
		}

		$r = q("select * from app where app_channel = %d $sql_extra order by app_name asc",
			intval($uid)
		);

		if($r) {
			for($x = 0; $x < count($r); $x ++) {
				if(! $r[$x]['app_system'])
					$r[$x]['type'] = 'personal';
				$r[$x]['term'] = q("select * from term where otype = %d and oid = %d",
					intval(TERM_OBJ_APP),
					intval($r[$x]['id'])
				);
			}
		}

		return($r);
	}

	static public function app_order($uid,$apps) {

		if(! $apps)
			return $apps;

		$x = (($uid) ? get_pconfig($uid,'system','app_order') : get_config('system','app_order'));
		if(($x) && (! is_array($x))) {
			$y = explode(',',$x);
			$y = array_map('trim',$y);
			$x = $y;
		}

		if(! (is_array($x) && ($x)))
			return $apps;

		$ret = [];
		foreach($x as $xx) {
			$y = self::find_app_in_array($xx,$apps);
			if($y) {
				$ret[] = $y;
			}
		}
		foreach($apps as $ap) {
			if(! self::find_app_in_array($ap['name'],$ret)) {
				$ret[] = $ap;
			}
		}
		return $ret;

	}

	static function find_app_in_array($name,$arr) {
		if(! $arr)
			return false;
		foreach($arr as $x) {
			if($x['name'] === $name) {
					return $x;
			}
		}
		return false;
	}

	static function moveup($uid,$guid) {
		$syslist = array();
		$list = self::app_list($uid, false, ['nav_featured_app', 'nav_pinned_app']);
		if($list) {
			foreach($list as $li) {
				$syslist[] = self::app_encode($li);
			}
		}
		self::translate_system_apps($syslist);

		usort($syslist,'self::app_name_compare');

		$syslist = self::app_order($uid,$syslist);

		if(! $syslist)
			return;

		$newlist = [];

		foreach($syslist as $k => $li) {
			if($li['guid'] === $guid) {
				$position = $k;
				break;
			}
		}
		if(! $position)
			return;
		$dest_position = $position - 1;
		$saved = $syslist[$dest_position];
		$syslist[$dest_position] = $syslist[$position];
		$syslist[$position] = $saved;

		$narr = [];
		foreach($syslist as $x) {
			$narr[] = $x['name'];
		}

		set_pconfig($uid,'system','app_order',implode(',',$narr));

	}

	static function movedown($uid,$guid) {
		$syslist = array();
		$list = self::app_list($uid, false, ['nav_featured_app', 'nav_pinned_app']);
		if($list) {
			foreach($list as $li) {
				$syslist[] = self::app_encode($li);
			}
		}
		self::translate_system_apps($syslist);

		usort($syslist,'self::app_name_compare');

		$syslist = self::app_order($uid,$syslist);

		if(! $syslist)
			return;

		$newlist = [];

		foreach($syslist as $k => $li) {
			if($li['guid'] === $guid) {
				$position = $k;
				break;
			}
		}
		if($position >= count($syslist) - 1)
			return;
		$dest_position = $position + 1;
		$saved = $syslist[$dest_position];
		$syslist[$dest_position] = $syslist[$position];
		$syslist[$position] = $saved;

		$narr = [];
		foreach($syslist as $x) {
			$narr[] = $x['name'];
		}

		set_pconfig($uid,'system','app_order',implode(',',$narr));

	}

	static public function app_decode($s) {
		$x = base64_decode(str_replace(array('<br />',"\r","\n",' '),array('','','',''),$s));
		return json_decode($x,true);
	}


	static public function app_store($arr) {

		//logger('app_store: ' . print_r($arr,true));

		$darray = array();
		$ret = array('success' => false);

		$darray['app_url']     = ((x($arr,'url')) ? $arr['url'] : '');
		$darray['app_channel'] = ((x($arr,'uid')) ? $arr['uid'] : 0);

		if((! $darray['app_url']) || (! $darray['app_channel']))
			return $ret;

		if($arr['photo'] && (strpos($arr['photo'],'icon:') !== 0) && (! strstr($arr['photo'],z_root()))) {
			$x = import_xchan_photo($arr['photo'],get_observer_hash(),true);
			$arr['photo'] = $x[1];
		}


		$darray['app_id']       = ((x($arr,'guid'))     ? $arr['guid'] : random_string(). '.' . \App::get_hostname());
		$darray['app_sig']      = ((x($arr,'sig'))      ? $arr['sig'] : '');
		$darray['app_author']   = ((x($arr,'author'))   ? $arr['author'] : get_observer_hash());
		$darray['app_name']     = ((x($arr,'name'))     ? escape_tags($arr['name']) : t('Unknown'));
		$darray['app_desc']     = ((x($arr,'desc'))     ? escape_tags($arr['desc']) : '');
		$darray['app_photo']    = ((x($arr,'photo'))    ? $arr['photo'] : z_root() . '/' . get_default_profile_photo(80));
		$darray['app_version']  = ((x($arr,'version'))  ? escape_tags($arr['version']) : '');
		$darray['app_addr']     = ((x($arr,'addr'))     ? escape_tags($arr['addr']) : '');
		$darray['app_price']    = ((x($arr,'price'))    ? escape_tags($arr['price']) : '');
		$darray['app_page']     = ((x($arr,'page'))     ? escape_tags($arr['page']) : '');
		$darray['app_plugin']   = ((x($arr,'plugin'))   ? escape_tags(trim($arr['plugin'])) : '');
		$darray['app_requires'] = ((x($arr,'requires')) ? escape_tags($arr['requires']) : '');
		$darray['app_system']   = ((x($arr,'system'))   ? intval($arr['system']) : 0);
		$darray['app_deleted']  = ((x($arr,'deleted'))  ? intval($arr['deleted']) : 0);

		$created = datetime_convert();

		$r = q("insert into app ( app_id, app_sig, app_author, app_name, app_desc, app_url, app_photo, app_version, app_channel, app_addr, app_price, app_page, app_requires, app_created, app_edited, app_system, app_plugin, app_deleted ) values ( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', %d, '%s', '%s', '%s', '%s', '%s', '%s', %d, '%s', %d )",
			dbesc($darray['app_id']),
			dbesc($darray['app_sig']),
			dbesc($darray['app_author']),
			dbesc($darray['app_name']),
			dbesc($darray['app_desc']),
			dbesc($darray['app_url']),
			dbesc($darray['app_photo']),
			dbesc($darray['app_version']),
			intval($darray['app_channel']),
			dbesc($darray['app_addr']),
			dbesc($darray['app_price']),
			dbesc($darray['app_page']),
			dbesc($darray['app_requires']),
			dbesc($created),
			dbesc($created),
			intval($darray['app_system']),
			dbesc($darray['app_plugin']),
			intval($darray['app_deleted'])
		);

		if($r) {
			$ret['success'] = true;
			$ret['app_id'] = $darray['app_id'];
		}
		if($arr['categories']) {
			$x = q("select id from app where app_id = '%s' and app_channel = %d limit 1",
				dbesc($darray['app_id']),
				intval($darray['app_channel'])
			);
			$y = explode(',',$arr['categories']);
			if($y) {
				foreach($y as $t) {
					$t = trim($t);
					if($t) {
						store_item_tag($darray['app_channel'],$x[0]['id'],TERM_OBJ_APP,TERM_CATEGORY,escape_tags($t),escape_tags(z_root() . '/apps/?f=&cat=' . escape_tags($t)));
					}
				}
			}
		}

		return $ret;
	}


	static public function app_update($arr) {

		//logger('app_update: ' . print_r($arr,true));
		$darray = array();
		$ret = array('success' => false);

		$darray['app_url']     = ((x($arr,'url')) ? $arr['url'] : '');
		$darray['app_channel'] = ((x($arr,'uid')) ? $arr['uid'] : 0);
		$darray['app_id']      = ((x($arr,'guid')) ? $arr['guid'] : 0);

		if((! $darray['app_url']) || (! $darray['app_channel']) || (! $darray['app_id']))
			return $ret;

		if($arr['photo'] && (strpos($arr['photo'],'icon:') !== 0) && (! strstr($arr['photo'],z_root()))) {
			$x = import_xchan_photo($arr['photo'],get_observer_hash(),true);
			$arr['photo'] = $x[1];
		}

		$darray['app_sig']      = ((x($arr,'sig')) ? $arr['sig'] : '');
		$darray['app_author']   = ((x($arr,'author')) ? $arr['author'] : get_observer_hash());
		$darray['app_name']     = ((x($arr,'name')) ? escape_tags($arr['name']) : t('Unknown'));
		$darray['app_desc']     = ((x($arr,'desc')) ? escape_tags($arr['desc']) : '');
		$darray['app_photo']    = ((x($arr,'photo')) ? $arr['photo'] : z_root() . '/' . get_default_profile_photo(80));
		$darray['app_version']  = ((x($arr,'version')) ? escape_tags($arr['version']) : '');
		$darray['app_addr']     = ((x($arr,'addr')) ? escape_tags($arr['addr']) : '');
		$darray['app_price']    = ((x($arr,'price')) ? escape_tags($arr['price']) : '');
		$darray['app_page']     = ((x($arr,'page')) ? escape_tags($arr['page']) : '');
		$darray['app_plugin']   = ((x($arr,'plugin')) ? escape_tags(trim($arr['plugin'])) : '');
		$darray['app_requires'] = ((x($arr,'requires')) ? escape_tags($arr['requires']) : '');
		$darray['app_system']   = ((x($arr,'system'))   ? intval($arr['system']) : 0);
		$darray['app_deleted']  = ((x($arr,'deleted'))  ? intval($arr['deleted']) : 0);

		$edited = datetime_convert();

		$r = q("update app set app_sig = '%s', app_author = '%s', app_name = '%s', app_desc = '%s', app_url = '%s', app_photo = '%s', app_version = '%s', app_addr = '%s', app_price = '%s', app_page = '%s', app_requires = '%s', app_edited = '%s', app_system = %d, app_plugin = '%s', app_deleted = %d where app_id = '%s' and app_channel = %d",
			dbesc($darray['app_sig']),
			dbesc($darray['app_author']),
			dbesc($darray['app_name']),
			dbesc($darray['app_desc']),
			dbesc($darray['app_url']),
			dbesc($darray['app_photo']),
			dbesc($darray['app_version']),
			dbesc($darray['app_addr']),
			dbesc($darray['app_price']),
			dbesc($darray['app_page']),
			dbesc($darray['app_requires']),
			dbesc($edited),
			intval($darray['app_system']),
			dbesc($darray['app_plugin']),
			intval($darray['app_deleted']),
			dbesc($darray['app_id']),
			intval($darray['app_channel'])
		);
		if($r) {
			$ret['success'] = true;
			$ret['app_id'] = $darray['app_id'];
		}

		$x = q("select id from app where app_id = '%s' and app_channel = %d limit 1",
			dbesc($darray['app_id']),
			intval($darray['app_channel'])
		);

		// if updating an embed app, don't mess with any existing categories.

		if(array_key_exists('embed',$arr) && intval($arr['embed']))
			return $ret;

		if($x) {
			q("delete from term where otype = %d and oid = %d",
				intval(TERM_OBJ_APP),
				intval($x[0]['id'])
			);
			if($arr['categories']) {
				$y = explode(',',$arr['categories']);
				if($y) {
					foreach($y as $t) {
						$t = trim($t);
						if($t) {
							store_item_tag($darray['app_channel'],$x[0]['id'],TERM_OBJ_APP,TERM_CATEGORY,escape_tags($t),escape_tags(z_root() . '/apps/?f=&cat=' . escape_tags($t)));
						}
					}
				}
			}
		}

		return $ret;

	}


	static public function app_encode($app,$embed = false) {

		$ret = array();

		$ret['type'] = 'personal';
	
		if($app['app_id'])
			$ret['guid'] = $app['app_id'];

		if($app['app_id'])
			$ret['guid'] = $app['app_id'];

		if($app['app_sig'])
			$ret['sig'] = $app['app_sig'];

		if($app['app_author'])
			$ret['author'] = $app['app_author'];

		if($app['app_name'])
			$ret['name'] = $app['app_name'];

		if($app['app_desc'])
			$ret['desc'] = $app['app_desc'];

		if($app['app_url'])
			$ret['url'] = $app['app_url'];

		if($app['app_photo'])
			$ret['photo'] = $app['app_photo'];

		if($app['app_icon'])
			$ret['icon'] = $app['app_icon'];

		if($app['app_version'])
			$ret['version'] = $app['app_version'];

		if($app['app_addr'])
			$ret['addr'] = $app['app_addr'];

		if($app['app_price'])
			$ret['price'] = $app['app_price'];
	
		if($app['app_page'])
			$ret['page'] = $app['app_page'];

		if($app['app_requires'])
			$ret['requires'] = $app['app_requires'];

		if($app['app_system'])
			$ret['system'] = $app['app_system'];

		if($app['app_plugin'])
			$ret['plugin'] = trim($app['app_plugin']);

		if($app['app_deleted'])
			$ret['deleted'] = $app['app_deleted'];

		if($app['term']) {
			$s = '';
			foreach($app['term'] as $t) {
				if($s)
					$s .= ',';
				$s .= $t['term'];
			}
			$ret['categories'] = $s;
		}


		if(! $embed)
			return $ret;

		$ret['embed'] = true;

		if(array_key_exists('categories',$ret))
			unset($ret['categories']);
	
		$j = json_encode($ret);
		return '[app]' . chunk_split(base64_encode($j),72,"\n") . '[/app]';

	}


	static public function papp_encode($papp) {
		return chunk_split(base64_encode(json_encode($papp)),72,"\n");

	}

}


