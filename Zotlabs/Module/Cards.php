<?php
namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Lib\Libprofile;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\PermissionDescription;

require_once('include/channel.php');
require_once('include/conversation.php');
require_once('include/acl_selectors.php');

class Cards extends Controller {

	function init() {

		if(argc() > 1)
			$which = argv(1);
		else
			return;

		Libprofile::load($which);

	}

	/**
	 * {@inheritDoc}
	 * @see \Zotlabs\Web\Controller::get()
	 */
	function get($update = 0, $load = false) {

		if(observer_prohibited(true)) {
			return login();
		}

		if(! App::$profile) {
			notice( t('Requested profile is not available.') . EOL );
			App::$error = 404;
			return;
		}

		if(! Apps::system_app_installed(App::$profile_uid, 'Cards')) {
			//Do not display any associated widgets at this point
			App::$pdl = '';

			$o = '<b>Cards App (Not Installed):</b><br>';
			$o .= t('Create personal planning cards');
			return $o;
		}

		nav_set_selected('Cards');

		head_add_link([
			'rel'   => 'alternate',
			'type'  => 'application/json+oembed',
			'href'  => z_root() . '/oep?f=&url=' . urlencode(z_root() . '/' . App::$query_string),
			'title' => 'oembed'
		]);


		$category = (($_REQUEST['cat']) ? escape_tags(trim($_REQUEST['cat'])) : '');

		if($category) {
			$sql_extra2 .= protect_sprintf(term_item_parent_query(App::$profile['profile_uid'], 'item', $category, TERM_CATEGORY));
		}


		$which = argv(1);

		$selected_card = ((argc() > 2) ? argv(2) : '');

		$_SESSION['return_url'] = App::$query_string;

		$uid      = local_channel();
		$owner    = App::$profile_uid;
		$observer = App::get_observer();

		$ob_hash = (($observer) ? $observer['xchan_hash'] : '');

		if(! perm_is_allowed($owner, $ob_hash, 'view_pages')) {
			notice( t('Permission denied.') . EOL);
			return;
		}

		$is_owner = ($uid && $uid == $owner);

		$channel = channelx_by_n($owner);

		if($channel) {
			$channel_acl = [
				'allow_cid' => $channel['channel_allow_cid'],
				'allow_gid' => $channel['channel_allow_gid'],
				'deny_cid'  => $channel['channel_deny_cid'],
				'deny_gid'  => $channel['channel_deny_gid']
			];
		}
		else {
			$channel_acl = [ 'allow_cid' => '', 'allow_gid' => '', 'deny_cid' => '', 'deny_gid' => '' ];
		}



		if(perm_is_allowed($owner, $ob_hash, 'write_pages')) {

			$x = [
				'webpage'           => ITEM_TYPE_CARD,
				'is_owner'          => true,
				'content_label'     => t('Add Card'),
				'button'            => t('Create'),
				'nickname'          => $channel['channel_address'],
				'lockstate'         => (($channel['channel_allow_cid'] || $channel['channel_allow_gid']
					|| $channel['channel_deny_cid'] || $channel['channel_deny_gid']) ? 'lock' : 'unlock'),
				'acl'               => (($is_owner) ? populate_acl($channel_acl, false, 
					PermissionDescription::fromGlobalPermission('view_pages')) : ''),
				'permissions'       => $channel_acl,
				'showacl'           => (($is_owner) ? true : false),
				'visitor'           => true,
				'hide_location'     => false,
				'hide_voting'       => false,
				'profile_uid'       => intval($owner),
				'mimetype'          => 'text/bbcode',
				'mimeselect'        => false,
				'layoutselect'      => false,
				'expanded'          => false,
				'novoting'          => false,
				'catsenabled'       => Apps::system_app_installed($owner, 'Categories'),
				'bbco_autocomplete' => 'bbcode',
				'bbcode'            => true
			];

			if($_REQUEST['title'])
				$x['title'] = $_REQUEST['title'];
			if($_REQUEST['body'])
				$x['body'] = $_REQUEST['body'];

			$editor = status_editor($x);
		}
		else {
			$editor = '';
		}


		$itemspage = get_pconfig(local_channel(),'system','itemspage');
		App::set_pager_itemspage(((intval($itemspage)) ? $itemspage : 20));
		$pager_sql = sprintf(" LIMIT %d OFFSET %d ", intval(App::$pager['itemspage']), intval(App::$pager['start']));


		$sql_extra = item_permissions_sql($owner);
		$sql_item = '';

		if($selected_card) {
			$r = q("select * from iconfig where iconfig.cat = 'system' and iconfig.k = 'CARD' and iconfig.v = '%s' limit 1",
				dbesc($selected_card)
			);
			if($r) {
				$sql_item = "and item.id = " . intval($r[0]['iid']) . " ";
			}
		}

		$r = q("select * from item
			where uid = %d and item_type = %d
			$sql_extra $sql_item order by item.created desc $pager_sql",
			intval($owner),
			intval(ITEM_TYPE_CARD)
		);

		$item_normal = " and item.item_hidden = 0 and item.item_type in (0,6) and item.item_deleted = 0
			and item.item_unpublished = 0 and item.item_delayed = 0 and item.item_pending_remove = 0
			and item.item_blocked = 0 ";

		$items_result = [];
		if($r) {

			$pager_total = count($r);

			$parents_str = ids_to_querystr($r, 'id');

			$items = q("SELECT item.*, item.id AS item_id
				FROM item
				WHERE item.uid = %d $item_normal
				AND item.parent IN ( %s )
				$sql_extra $sql_extra2 ",
				intval(App::$profile['profile_uid']),
				dbesc($parents_str)
			);
			if($items) {
				xchan_query($items);
				$items = fetch_post_tags($items, true);
				$items_result = conv_sort($items, 'updated');
			}
		}

		$mode = 'cards';

		if(get_pconfig(local_channel(),'system','articles_list_mode') && (! $selected_card))
			$page_mode = 'pager_list';
		else
			$page_mode = 'traditional';

		$content = conversation($items_result, $mode, false, $page_mode);

		$o = replace_macros(get_markup_template('cards.tpl'), [
			'$title' => t('Cards'),
			'$editor' => $editor,
			'$content' => $content,
			'$pager' => alt_pager($pager_total)
		]);

		return $o;
	}

}
