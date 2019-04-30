<?php

namespace Zotlabs\Module;

use Zotlabs\Lib\Libzot;
use Zotlabs\Lib\Activity;
use Zotlabs\Lib\Libprofile;
use Zotlabs\Lib\ActivityStreams;
use Zotlabs\Lib\LDSignatures;
use Zotlabs\Lib\Crypto;
use Zotlabs\Web\HTTPSig;

use App;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\PermissionDescription;

require_once('include/items.php');
require_once('include/security.php');
require_once('include/conversation.php');
require_once('include/acl_selectors.php');


/**
 * @brief Channel Controller
 *
 */

class Channel extends Controller {

	function init() {

		if(in_array(substr($_GET['search'],0,1),[ '@', '!', '?']))	
			goaway('search' . '?f=&search=' . $_GET['search']);

		$which = null;
		if(argc() > 1)
			$which = argv(1);
		if(! $which) {
			if(local_channel()) {
				$channel = App::get_channel();
				if($channel && $channel['channel_address']) {
					$which = $channel['channel_address'];
				}
			}
		}
		if(! $which) {
			notice( t('You must be logged in to see this page.') . EOL );
			return;
		}

		$profile = 0;
		$channel = App::get_channel();

		if((local_channel()) && (argc() > 2) && (argv(2) === 'view')) {
			$which = $channel['channel_address'];
			$profile = argv(1);
		}

		$channel = channelx_by_nick($which);
		if(! $channel) {
			http_status_exit(404, 'Not found');
		}

		head_add_link( [ 
			'rel'   => 'alternate', 
			'type'  => 'application/atom+xml',
			'title' => t('Posts and comments'),
			'href'  => z_root() . '/feed/' . $which
		]);

		head_add_link( [ 
			'rel'   => 'alternate', 
			'type'  => 'application/atom+xml',
			'title' => t('Only posts'),
			'href'  => z_root() . '/feed/' . $which . '?f=&top=1'
		]);

		// handle zot6 channel discovery 

		if(Libzot::is_zot_request()) {
	
			$sigdata = HTTPSig::verify(file_get_contents('php://input'));

			if($sigdata && $sigdata['signer'] && $sigdata['header_valid']) {
				$data = json_encode(Libzot::zotinfo([ 'address' => $channel['channel_address'], 'target_url' => $sigdata['signer'] ]));
				$s = q("select site_crypto, hubloc_sitekey from site left join hubloc on hubloc_url = site_url where hubloc_id_url = '%s' and hubloc_network = 'zot6' limit 1",
					dbesc($sigdata['signer'])
				);

				if($s) {
					$data = json_encode(Crypto::encapsulate($data,$s[0]['hubloc_sitekey'],Libzot::best_algorithm($s[0]['site_crypto'])));
				}
			}
			else {
				$data = json_encode(Libzot::zotinfo([ 'address' => $channel['channel_address'] ]));
			}

			$headers = [ 
				'Content-Type'     => 'application/x-zot+json', 
				'Digest'           => HTTPSig::generate_digest_header($data),
				'(request-target)' => strtolower($_SERVER['REQUEST_METHOD']) . ' ' . $_SERVER['REQUEST_URI']
			];
			$h = HTTPSig::create_sig($headers,$channel['channel_prvkey'],channel_url($channel));
			HTTPSig::set_headers($h);
			echo $data;
			killme();
		}

		if(ActivityStreams::is_as_request()) {

			$x = array_merge(['@context' => [
				ACTIVITYSTREAMS_JSONLD_REV,
				'https://w3id.org/security/v1'
			]], Activity::encode_person($channel,true,((defined('NOMADIC')) ? false : true)));

			$headers = [];
        	$headers['Content-Type'] = 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"' ;

        	$x['signature'] = LDSignatures::sign($x,$channel);
        	$ret = json_encode($x, JSON_UNESCAPED_SLASHES);
        	$headers['Digest'] = HTTPSig::generate_digest_header($ret);
			$headers['(request-target)'] = strtolower($_SERVER['REQUEST_METHOD']) . ' ' . $_SERVER['REQUEST_URI'];
        	$h = HTTPSig::create_sig($headers,$channel['channel_prvkey'],channel_url($channel));
        	HTTPSig::set_headers($h);

        	echo $ret;
	        killme();

		}

		// Run Libprofile::load() here to make sure the theme is set before
		// we start loading content

		Libprofile::load($which,$profile);

	}

	function get($update = 0, $load = false) {

		$noscript_content = get_config('system', 'noscript_content', '1');

		if($load)
			$_SESSION['loadtime'] = datetime_convert();

		$category = $datequery = $datequery2 = '';

		$mid = ((x($_REQUEST,'mid')) ? $_REQUEST['mid'] : '');
		if($mid && strpos($mid,'b64.') === 0) {
			$decoded = @base64url_decode(substr($mid,4));
			if($decoded) {
				$mid = $decoded;
			}
		}


		$datequery = ((x($_GET,'dend') && is_a_date_arg($_GET['dend'])) ? notags($_GET['dend']) : '');
		$datequery2 = ((x($_GET,'dbegin') && is_a_date_arg($_GET['dbegin'])) ? notags($_GET['dbegin']) : '');

		if(observer_prohibited(true)) {
			return login();
		}

		$category = ((x($_REQUEST,'cat')) ? $_REQUEST['cat'] : '');
		$hashtags = ((x($_REQUEST,'tag')) ? $_REQUEST['tag'] : '');
		$order    = ((x($_GET,'order')) ? notags($_GET['order']) : 'post');
		$static   = ((array_key_exists('static',$_REQUEST)) ? intval($_REQUEST['static']) : 0);
		$search   = ((x($_GET,'search')) ? $_GET['search'] : EMPTY_STR);

		$groups = array();

		$o = '';

		if($update) {
			// Ensure we've got a profile owner if updating.
			App::$profile['profile_uid'] = App::$profile_uid = $update;
		}

		$is_owner = (((local_channel()) && (App::$profile['profile_uid'] == local_channel())) ? true : false);

		$channel = App::get_channel();
		$observer = App::get_observer();
		$ob_hash = (($observer) ? $observer['xchan_hash'] : '');

		$perms = get_all_perms(App::$profile['profile_uid'],$ob_hash);

		if(! $perms['view_stream']) {
			// We may want to make the target of this redirect configurable
			if($perms['view_profile']) {
				notice( t('Insufficient permissions.  Request redirected to profile page.') . EOL);
				goaway (z_root() . "/profile/" . App::$profile['channel_address']);
			}
			notice( t('Permission denied.') . EOL);
			return;
		}


		if(! $update) {

			nav_set_selected('Channel Home');

			$static = channel_manual_conv_update(App::$profile['profile_uid']);

			// search terms header
			if($search) {
				$o .= replace_macros(get_markup_template("section_title.tpl"),array(
					'$title' => t('Search Results For:') . ' ' . htmlspecialchars($search, ENT_COMPAT,'UTF-8')
				));
			}

			if($channel && $is_owner) {
				$channel_acl = array(
					'allow_cid' => $channel['channel_allow_cid'],
					'allow_gid' => $channel['channel_allow_gid'],
					'deny_cid' => $channel['channel_deny_cid'],
					'deny_gid' => $channel['channel_deny_gid']
				);
			}
			else {
				$channel_acl = [ 'allow_cid' => '', 'allow_gid' => '', 'deny_cid' => '', 'deny_gid' => '' ];
			}


			if($perms['post_wall']) {

				$x = array(
					'is_owner' => $is_owner,
					'allow_location' => ((($is_owner || $observer) && (intval(get_pconfig(App::$profile['profile_uid'],'system','use_browser_location')))) ? true : false),
					'default_location' => (($is_owner) ? App::$profile['channel_location'] : ''),
					'nickname' => App::$profile['channel_address'],
					'lockstate' => (((strlen(App::$profile['channel_allow_cid'])) || (strlen(App::$profile['channel_allow_gid'])) || (strlen(App::$profile['channel_deny_cid'])) || (strlen(App::$profile['channel_deny_gid']))) ? 'lock' : 'unlock'),
					'acl' => (($is_owner) ? populate_acl($channel_acl,true, PermissionDescription::fromGlobalPermission('view_stream'), get_post_aclDialogDescription(), 'acl_dialog_post') : ''),
					'permissions' => $channel_acl,
					'showacl' => (($is_owner) ? 'yes' : ''),
					'bang' => '',
					'visitor' => (($is_owner || $observer) ? true : false),
					'profile_uid' => App::$profile['profile_uid'],
					'editor_autocomplete' => true,
					'bbco_autocomplete' => 'bbcode',
					'bbcode' => true,
					'jotnets' => true,
					'reset' => t('Reset form')
				);

				$o .= status_editor($a,$x);
			}

		}


		/**
		 * Get permissions SQL - if $remote_contact is true, our remote user has been pre-verified and we already have fetched his/her groups
		 */

		$item_normal = item_normal();
		$item_normal_update = item_normal_update();
		$sql_extra = item_permissions_sql(App::$profile['profile_uid']);

		if(get_pconfig(App::$profile['profile_uid'],'system','channel_list_mode') && (! $mid))
			$page_mode = 'list';
		else
			$page_mode = 'client';

		$abook_uids = " and abook.abook_channel = " . intval(App::$profile['profile_uid']) . " ";

		$simple_update = (($update) ? " AND item_unseen = 1 " : '');

		if($search) {
			$search = escape_tags($search);
			if(strpos($search,'#') === 0) {
				$sql_extra .= term_query('item',substr($search,1),TERM_HASHTAG,TERM_COMMUNITYTAG);
			}
			else {
				$sql_extra .= sprintf(" AND (item.body like '%s' OR item.title like '%s') ",
					dbesc(protect_sprintf('%' . $search . '%')),
					dbesc(protect_sprintf('%' . $search . '%'))
				);
			}
		}


		head_add_link([ 
			'rel'   => 'alternate',
			'type'  => 'application/json+oembed',
			'href'  => z_root() . '/oep?f=&url=' . urlencode(z_root() . '/' . App::$query_string),
			'title' => 'oembed'
		]);

		if($update && $_SESSION['loadtime'])
			$simple_update = " AND (( item_unseen = 1 AND item.changed > '" . datetime_convert('UTC','UTC',$_SESSION['loadtime']) . "' )  OR item.changed > '" . datetime_convert('UTC','UTC',$_SESSION['loadtime']) . "' ) ";
		if($load)
			$simple_update = '';

		if($static && $simple_update)
			$simple_update .= " and author_xchan = '" . protect_sprintf(get_observer_hash()) . "' ";

		if(($update) && (! $load)) {

			if($mid) {
				$r = q("SELECT parent AS item_id from item where mid like '%s' and uid = %d $item_normal_update
					AND item_wall = 1 $simple_update $sql_extra limit 1",
					dbesc($mid . '%'),
					intval(App::$profile['profile_uid'])
				);
				$_SESSION['loadtime'] = datetime_convert();
			}
			else {
				$r = q("SELECT parent AS item_id from item
					left join abook on ( item.owner_xchan = abook.abook_xchan $abook_uids )
					WHERE uid = %d $item_normal_update
					AND item_wall = 1 $simple_update
					AND (abook.abook_blocked = 0 or abook.abook_flags is null)
					$sql_extra
					ORDER BY created DESC",
					intval(App::$profile['profile_uid'])
				);
				$_SESSION['loadtime'] = datetime_convert();
			}

		}
		else {

			if(x($category)) {
				$sql_extra2 .= protect_sprintf(term_item_parent_query(App::$profile['profile_uid'],'item', $category, TERM_CATEGORY));
			}
			if(x($hashtags)) {
				$sql_extra2 .= protect_sprintf(term_item_parent_query(App::$profile['profile_uid'],'item', $hashtags, TERM_HASHTAG, TERM_COMMUNITYTAG));
			}

			if($datequery) {
				$sql_extra2 .= protect_sprintf(sprintf(" AND item.created <= '%s' ", dbesc(datetime_convert(date_default_timezone_get(),'',$datequery))));
				$order = 'post';
			}
			if($datequery2) {
				$sql_extra2 .= protect_sprintf(sprintf(" AND item.created >= '%s' ", dbesc(datetime_convert(date_default_timezone_get(),'',$datequery2))));
			}

			if($datequery || $datequery2) {
				$sql_extra2 .= " and item.item_thread_top != 0 ";
			}

			if($order === 'post')
				$ordering = "created";
			else
				$ordering = "commented";


			$itemspage = get_pconfig(local_channel(),'system','itemspage');
			App::set_pager_itemspage(((intval($itemspage)) ? $itemspage : 20));
			$pager_sql = sprintf(" LIMIT %d OFFSET %d ", intval(App::$pager['itemspage']), intval(App::$pager['start']));

			if($noscript_content || $load) {
				if($mid) {
					$r = q("SELECT parent AS item_id from item where mid like '%s' and uid = %d $item_normal
						AND item_wall = 1 $sql_extra limit 1",
						dbesc($mid . '%'),
						intval(App::$profile['profile_uid'])
					);
					if (! $r) {
						notice( t('Permission denied.') . EOL);
					}
				}
				else {
					$r = q("SELECT DISTINCT item.parent AS item_id, $ordering FROM item 
						left join abook on ( item.author_xchan = abook.abook_xchan $abook_uids )
						WHERE true and item.uid = %d $item_normal
						AND (abook.abook_blocked = 0 or abook.abook_flags is null)
						AND item.item_wall = 1 AND item.item_thread_top = 1
						$sql_extra $sql_extra2 
						ORDER BY $ordering DESC $pager_sql ",
						intval(App::$profile['profile_uid'])
					);
				}
			}
			else {
				$r = array();
			}
		}
		if($r) {

			$parents_str = ids_to_querystr($r,'item_id');

			$items = q("SELECT item.*, item.id AS item_id
				FROM item
				WHERE item.uid = %d $item_normal
				AND item.parent IN ( %s )
				$sql_extra ",
				intval(App::$profile['profile_uid']),
				dbesc($parents_str)
			);

			xchan_query($items);
			$items = fetch_post_tags($items, true);
			$items = conv_sort($items,$ordering);

			if($load && $mid && (! count($items))) {
				// This will happen if we don't have sufficient permissions
				// to view the parent item (or the item itself if it is toplevel)
				notice( t('Permission denied.') . EOL);
			}

		} else {
			$items = array();
		}

		if((! $update) && (! $load)) {

			// This is ugly, but we can't pass the profile_uid through the session to the ajax updater,
			// because browser prefetching might change it on us. We have to deliver it with the page.

			$maxheight = get_pconfig(App::$profile['profile_uid'],'system','channel_divmore_height');
			if(! $maxheight)
				$maxheight = 400;

			$o .= '<div id="live-channel"></div>' . "\r\n";
			$o .= "<script> var profile_uid = " . App::$profile['profile_uid']
				. "; var netargs = '?f='; var profile_page = " . App::$pager['page']
				. "; divmore_height = " . intval($maxheight) . "; </script>\r\n";

			App::$page['htmlhead'] .= replace_macros(get_markup_template("build_query.tpl"),array(
				'$baseurl' => z_root(),
				'$pgtype' => 'channel',
				'$uid' => ((App::$profile['profile_uid']) ? App::$profile['profile_uid'] : '0'),
				'$gid' => '0',
				'$cid' => '0',
				'$cmin' => '(-1)',
				'$cmax' => '(-1)',
				'$star' => '0',
				'$liked' => '0',
				'$conv' => '0',
				'$spam' => '0',
				'$nouveau' => '0',
				'$wall' => '1',
				'$fh' => '0',
				'$static'  => $static,
				'$page' => ((App::$pager['page'] != 1) ? App::$pager['page'] : 1),
				'$search' => $search,
				'$xchan' => '',
				'$order' => (($order) ? urlencode($order) : ''),
				'$list' => ((x($_REQUEST,'list')) ? intval($_REQUEST['list']) : 0),
				'$file' => '',
				'$cats' => (($category) ? urlencode($category) : ''),
				'$tags' => (($hashtags) ? urlencode($hashtags) : ''),
				'$mid' => (($mid) ? urlencode($mid) : ''),
				'$verb' => '',
				'$net' => '',
				'$dend' => $datequery,
				'$dbegin' => $datequery2
			));

		}

		$update_unseen = '';

		if($page_mode === 'list') {

			/**
			 * in "list mode", only mark the parent item and any like activities as "seen".
			 * We won't distinguish between comment likes and post likes. The important thing
			 * is that the number of unseen comments will be accurate. The SQL to separate the
			 * comment likes could also get somewhat hairy.
			 */

			if($parents_str) {
				$update_unseen = " AND ( id IN ( " . dbesc($parents_str) . " )";
				$update_unseen .= " OR ( parent IN ( " . dbesc($parents_str) . " ) AND verb in ( '" . dbesc(ACTIVITY_LIKE) . "','" . dbesc(ACTIVITY_DISLIKE) . "' ))) ";
			}
		}
		else {
			if($parents_str) {
				$update_unseen = " AND parent IN ( " . dbesc($parents_str) . " )";
			}
		}

		if($is_owner && $update_unseen) {
			$x = [ 'channel_id' => local_channel(), 'update' => 'unset' ];
			call_hooks('update_unseen',$x);
			if($x['update'] === 'unset' || intval($x['update'])) {
				$r = q("UPDATE item SET item_unseen = 0 where item_unseen = 1 and item_wall = 1 AND uid = %d $update_unseen",
					intval(local_channel())
				);
			}
		}

		$mode = (($search) ? 'search' : 'channel');

		if($update) {
			$o .= conversation($items,$mode,$update,$page_mode);
		}
		else {

			$o .= '<noscript>';
			if($noscript_content) {
				$o .= conversation($items,$mode,$update,'traditional');
				$o .= alt_pager(count($items));
			}
			else {
				$o .= '<div class="section-content-warning-wrapper">' . t('You must enable javascript for your browser to be able to view this content.') . '</div>';
			}
			$o .= '</noscript>';

			$o .= conversation($items,$mode,$update,$page_mode);

			if ($mid && $items[0]['title'])
				App::$page['title'] = $items[0]['title'] . " - " . App::$page['title'];

		}

		if($mid)
			$o .= '<div id="content-complete"></div>';

		return $o;
	}
}
