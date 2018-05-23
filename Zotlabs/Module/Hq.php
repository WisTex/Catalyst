<?php
namespace Zotlabs\Module;

require_once("include/bbcode.php");
require_once('include/security.php');
require_once('include/conversation.php');
require_once('include/acl_selectors.php');
require_once('include/items.php');


class Hq extends \Zotlabs\Web\Controller {

	function init() {
		if(! local_channel())
			return;

		\App::$profile_uid = local_channel();
	}

	function post() {

		if(!local_channel())
			return;

		if($_REQUEST['notify_id']) {
			q("update notify set seen = 1 where id = %d and uid = %d",
				intval($_REQUEST['notify_id']),
				intval(local_channel())
			);
		}

		killme();

	}

	function get($update = 0, $load = false) {

		if(!local_channel())
			return;

		if($load)
			$_SESSION['loadtime'] = datetime_convert();
	
		if(argc() > 1 && argv(1) !== 'load') {
			$item_hash = argv(1);
		}
	
		if($_REQUEST['mid'])
			$item_hash = $_REQUEST['mid'];

		$item_normal = item_normal();
		$item_normal_update = item_normal_update();

		if(! $item_hash) {
			$r = q("SELECT mid FROM item 
				WHERE uid = %d $item_normal
				AND mid = parent_mid 
				ORDER BY created DESC LIMIT 1",
				intval(local_channel())
			);

			if($r[0]['mid']) {
				$item_hash = 'b64.' . base64url_encode($r[0]['mid']);
			}
		}

		if($item_hash) {

			if(strpos($item_hash,'b64.') === 0)
				$decoded = @base64url_decode(substr($item_hash,4));

			if($decoded)
				$item_hash = $decoded;

			$target_item = null;

			$r = q("select id, uid, mid, parent_mid, thr_parent, verb, item_type, item_deleted, item_blocked from item where mid like '%s' limit 1",
				dbesc($item_hash . '%')
			);
		
			if($r) {
				$target_item = $r[0];
			}

			//if the item is to be moderated redirect to /moderate
			if($target_item['item_blocked'] == ITEM_MODERATED) {
				goaway(z_root() . '/moderate/' . $target_item['id']);
			}
		
			$static = ((array_key_exists('static',$_REQUEST)) ? intval($_REQUEST['static']) : 0);

			$simple_update = (($update) ? " AND item_unseen = 1 " : '');
				
			if($update && $_SESSION['loadtime'])
				$simple_update = " AND (( item_unseen = 1 AND item.changed > '" . datetime_convert('UTC','UTC',$_SESSION['loadtime']) . "' )  OR item.changed > '" . datetime_convert('UTC','UTC',$_SESSION['loadtime']) . "' ) ";
		
			if($static && $simple_update)
				$simple_update .= " and item_thread_top = 0 and author_xchan = '" . protect_sprintf(get_observer_hash()) . "' ";

			$sys = get_sys_channel();
			$sql_extra = item_permissions_sql($sys['channel_id']);

			$sys_item = false;

		}
	
		if(! $update) {
			$channel = \App::get_channel();

			$channel_acl = [
				'allow_cid' => $channel['channel_allow_cid'], 
				'allow_gid' => $channel['channel_allow_gid'], 
				'deny_cid'  => $channel['channel_deny_cid'], 
				'deny_gid'  => $channel['channel_deny_gid']
			]; 

			$x = [
				'is_owner'            => true,
				'allow_location'      => ((intval(get_pconfig($channel['channel_id'],'system','use_browser_location'))) ? '1' : ''),
				'default_location'    => $channel['channel_location'],
				'nickname'            => $channel['channel_address'],
				'lockstate'           => (($group || $cid || $channel['channel_allow_cid'] || $channel['channel_allow_gid'] || $channel['channel_deny_cid'] || $channel['channel_deny_gid']) ? 'lock' : 'unlock'),
				'acl'                 => populate_acl($channel_acl,true, \Zotlabs\Lib\PermissionDescription::fromGlobalPermission('view_stream'), get_post_aclDialogDescription(), 'acl_dialog_post'),
				'permissions'         => $channel_acl,
				'bang'                => '',
				'visitor'             => true,
				'profile_uid'         => local_channel(),
				'return_path'         => 'hq',
				'expanded'            => true,
				'editor_autocomplete' => true,
				'bbco_autocomplete'   => 'bbcode',
				'bbcode'              => true,
				'jotnets'             => true,
				'reset'               => t('Reset form')
			];

			$o = replace_macros(get_markup_template("hq.tpl"),
				[
					'$no_messages' => (($target_item) ? false : true),
					'$no_messages_label' => [ t('Welcome to Hubzilla!'), t('You have got no unseen posts...') ],
					'$editor' => status_editor($a,$x)
				]
			);

		}

		if(! $update && ! $load) {

			nav_set_selected('HQ');

			$static  = ((local_channel()) ? channel_manual_conv_update(local_channel()) : 1);

			if($target_item) {
				// if the target item is not a post (eg a like) we want to address its thread parent
				$mid = ((($target_item['verb'] == ACTIVITY_LIKE) || ($target_item['verb'] == ACTIVITY_DISLIKE)) ? $target_item['thr_parent'] : $target_item['mid']);

				// if we got a decoded hash we must encode it again before handing to javascript 
				if($decoded)
					$mid = 'b64.' . base64url_encode($mid);
			}
			else {
				$mid = '';
			}

			$o .= '<div id="live-hq"></div>' . "\r\n";
			$o .= "<script> var profile_uid = " . local_channel()
				. "; var netargs = '?f='; var profile_page = " . \App::$pager['page'] . ";</script>\r\n";
	
			\App::$page['htmlhead'] .= replace_macros(get_markup_template("build_query.tpl"),[
				'$baseurl' => z_root(),
				'$pgtype'  => 'hq',
				'$uid'     => local_channel(),
				'$gid'     => '0',
				'$cid'     => '0',
				'$cmin'    => '0',
				'$cmax'    => '99',
				'$star'    => '0',
				'$liked'   => '0',
				'$conv'    => '0',
				'$spam'    => '0',
				'$fh'      => '0',
				'$nouveau' => '0',
				'$wall'    => '0',
				'$static'  => $static,
				'$page'    => 1,
				'$list'    => ((x($_REQUEST,'list')) ? intval($_REQUEST['list']) : 0),
				'$search'  => '',
				'$xchan'   => '',
				'$order'   => '',
				'$file'    => '',
				'$cats'    => '',
				'$tags'    => '',
				'$dend'    => '',
				'$dbegin'  => '',
				'$verb'    => '',
				'$net'     => '',
				'$mid'     => $mid
			]);
		}

		$updateable = false;

		if($load && $target_item) {
			$r = null;

			$r = q("SELECT item.id AS item_id FROM item
				WHERE uid = %d
				AND mid = '%s'
				$item_normal
				LIMIT 1",
				intval(local_channel()),
				dbesc($target_item['parent_mid'])
			);

			if($r) {
				$updateable = true;
			}

			if(!$r) {
				$sys_item = true;

				$r = q("SELECT item.id AS item_id FROM item
					LEFT JOIN abook ON item.author_xchan = abook.abook_xchan
					WHERE mid = '%s' AND item.uid = %d $item_normal
					AND (abook.abook_blocked = 0 or abook.abook_flags is null)
					$sql_extra LIMIT 1",
					dbesc($target_item['parent_mid']),
					intval($sys['channel_id'])
				);
			}
		}
		elseif($update && $target_item) {
			$r = null;

			$r = q("SELECT item.parent AS item_id FROM item
				WHERE uid = %d
				AND parent_mid = '%s'
				$item_normal_update
				$simple_update
				LIMIT 1",
				intval(local_channel()),
				dbesc($target_item['parent_mid'])
			);

			if($r) {
				$updateable = true;
			}

			if(!$r) {
				$sys_item = true;

				$r = q("SELECT item.parent AS item_id FROM item
					LEFT JOIN abook ON item.author_xchan = abook.abook_xchan
					WHERE mid = '%s' AND item.uid = %d $item_normal_update $simple_update
					AND (abook.abook_blocked = 0 or abook.abook_flags is null)
					$sql_extra LIMIT 1",
					dbesc($target_item['parent_mid']),
					intval($sys['channel_id'])
				);
			}

			$_SESSION['loadtime'] = datetime_convert();
		}
		else {
			$r = [];
		}
	
		if($r) {
			$items = q("SELECT item.*, item.id AS item_id 
				FROM item
				WHERE parent = '%s' $item_normal ",
				dbesc($r[0]['item_id'])
			);
	
			xchan_query($items,true,(($sys_item) ? local_channel() : 0));
			$items = fetch_post_tags($items,true);
			$items = conv_sort($items,'created');
		}
		else {
			$items = [];
		}

		$o .= conversation($items, 'hq', $update, 'client');

		if($updateable) {
			$x = q("UPDATE item SET item_unseen = 0 WHERE item_unseen = 1 AND uid = %d AND parent = %d ",
				intval(local_channel()),
				intval($r[0]['item_id'])
			);
		}

		$o .= '<div id="content-complete"></div>';

		return $o;

	}

}
