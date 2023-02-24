<?php

namespace Code\Module;

use App;
use Code\Web\Controller;
use Code\Lib\PermissionDescription;
use Code\Lib\PConfig;
use Code\Lib\Channel;
use Code\Lib\Navbar;
use Code\Lib\Libacl;
use Code\Render\Theme;

    
require_once('include/conversation.php');


class Pubstream extends Controller
{

    // State passed in from the Update module.

    public $profile_uid = 0;
    public $loading = 0;
    public $updating = 0;


    public function get()
    {

        $o = EMPTY_STR;
        $items = [];

        if (!intval(get_config('system', 'open_pubstream', 0))) {
            if (!local_channel()) {
                return login();
            }
        }
        $distance = 0;
        $distance_from = '';

        $public_stream_mode = intval(get_config('system', 'public_stream_mode', PUBLIC_STREAM_NONE));

        if (!$public_stream_mode) {
            return '';
        }

        $mid = ((x($_REQUEST, 'mid')) ? $_REQUEST['mid'] : '');
        $hashtags = ((x($_REQUEST, 'tag')) ? $_REQUEST['tag'] : '');
        $decoded = false;

        $mid = unpack_link_id($mid);

        $item_normal = item_normal();
        $item_normal_update = item_normal_update();

        $static = ((array_key_exists('static', $_REQUEST)) ? intval($_REQUEST['static']) : 0);
        $net = ((array_key_exists('net', $_REQUEST)) ? escape_tags($_REQUEST['net']) : '');


        if (local_channel() && (!$this->updating)) {
            $channel = App::get_channel();

            $channel_acl = array(
                'allow_cid' => $channel['channel_allow_cid'],
                'allow_gid' => $channel['channel_allow_gid'],
                'deny_cid' => $channel['channel_deny_cid'],
                'deny_gid' => $channel['channel_deny_gid']
            );

            $x = array(
                'is_owner' => true,
                'allow_location' => ((intval(get_pconfig($channel['channel_id'], 'system', 'use_browser_location'))) ? '1' : ''),
                'default_location' => $channel['channel_location'],
                'nickname' => $channel['channel_address'],
                'lockstate' => (($channel['channel_allow_cid'] || $channel['channel_allow_gid'] || $channel['channel_deny_cid'] || $channel['channel_deny_gid']) ? 'lock' : 'unlock'),
                'acl' => Libacl::populate($channel_acl, true, PermissionDescription::fromGlobalPermission('view_stream'), Libacl::get_post_aclDialogDescription(), 'acl_dialog_post'),
                'permissions' => $channel_acl,
                'bang' => '',
                'visitor' => true,
                'profile_uid' => local_channel(),
                'return_path' => 'channel/' . $channel['channel_address'],
                'expanded' => true,
                'editor_autocomplete' => true,
                'bbco_autocomplete' => 'bbcode',
                'bbcode' => true,
                'jotnets' => true,
                'reset' => t('Reset form')
            );

            $o = '<div id="jot-popup">';
            $o .= status_editor($x);
            $o .= '</div>';
        }

        if (!$this->updating && !$this->loading) {
            Navbar::set_selected(t('Public Stream'));

            if (!$mid) {
                $_SESSION['loadtime_pubstream'] = datetime_convert();
                if (local_channel()) {
                    PConfig::Set(local_channel(), 'system', 'loadtime_pubstream', $_SESSION['loadtime_pubstream']);
                }
            }

            $static = ((local_channel()) ? Channel::manual_conv_update(local_channel()) : 1);

            $maxheight = get_config('system', 'home_divmore_height');
            if (!$maxheight) {
                $maxheight = 400;
            }

            $o .= '<div id="live-pubstream"></div>' . "\r\n";
            $o .= "<script> var profile_uid = " . ((intval(local_channel())) ? local_channel() : (-1))
                . "; var profile_page = " . App::$pager['page']
                . "; divmore_height = " . intval($maxheight) . "; </script>\r\n";

            // if we got a decoded hash we must encode it again before handing to javascript
            $mid = gen_link_id($mid);

            App::$page['htmlhead'] .= replace_macros(Theme::get_template("build_query.tpl"), array(
                '$baseurl' => z_root(),
                '$pgtype' => 'pubstream',
                '$uid' => ((local_channel()) ? local_channel() : '0'),
                '$gid' => '0',
                '$cid' => '0',
                '$cmin' => '(-1)',
                '$cmax' => '(-1)',
                '$star' => '0',
                '$liked' => '0',
                '$conv' => '0',
                '$spam' => '0',
                '$fh' => '1',
                '$dm' => '0',
                '$nouveau' => '0',
                '$wall' => '0',
                '$draft' => '0',
                '$list' => '0',
                '$static' => $static,
                '$page' => ((App::$pager['page'] != 1) ? App::$pager['page'] : 1),
                '$search' => '',
                '$xchan' => '',
                '$order' => 'comment',
                '$file' => '',
                '$cats' => '',
                '$tags' => (($hashtags) ? urlencode($hashtags) : ''),
                '$dend' => '',
                '$mid' => (($mid) ? urlencode($mid) : ''),
                '$verb' => '',
                '$net' => (($net) ? urlencode($net) : ''),
                '$dbegin' => '',
                '$pf' => '0',
                '$distance' => (($distance) ? intval($distance) : '0'),
                '$distance_from' => (($distance_from) ? urlencode($distance_from) : ''),
            ));
        }

        if ($this->updating && !$this->loading) {
            // only setup pagination on initial page view
            $pager_sql = '';
        } else {
            $itemspage = ((local_channel()) ? get_pconfig(local_channel(), 'system', 'itemspage', 20) : 20);
            App::set_pager_itemspage($itemspage);
            $pager_sql = sprintf(" LIMIT %d OFFSET %d ", intval(App::$pager['itemspage']), intval(App::$pager['start']));
        }

        require_once('include/security.php');

        if ($public_stream_mode === PUBLIC_STREAM_SITE) {
            $uids = " and item_private = 0  and item_wall = 1 ";
        } else {
            $sys = Channel::get_system();
            $uids = " and item_private = 0 and item_wall = 0 and item.uid  = " . intval($sys['channel_id']) . " ";
            $sql_extra = item_permissions_sql($sys['channel_id']);
            App::$data['firehose'] = intval($sys['channel_id']);
        }

        if (get_config('system', 'public_list_mode')) {
            $page_mode = 'list';
        } else {
            $page_mode = 'client';
        }


        if (x($hashtags)) {
            $sql_extra .= protect_sprintf(term_query('item', $hashtags, TERM_HASHTAG, TERM_COMMUNITYTAG));
        }

        $net_query = (($net) ? " left join xchan on xchan_hash = author_xchan " : '');
        $net_query2 = (($net) ? " and xchan_network = '" . protect_sprintf(dbesc($net)) . "' " : '');

        if (isset(App::$profile) && isset(App::$profile['profile_uid'])) {
            $abook_uids = " and abook.abook_channel = " . intval(App::$profile['profile_uid']) . " ";
        }

        $simple_update = ((isset($_SESSION['loadtime_pubstream']) && $_SESSION['loadtime_pubstream']) ? " AND item.changed > '" . datetime_convert('UTC', 'UTC', $_SESSION['loadtime_pubstream']) . "' " : '');

        if ($this->loading) {
            $simple_update = '';
        }

        if ($static && $simple_update) {
            $simple_update .= " and author_xchan = '" . protect_sprintf(get_observer_hash()) . "' ";
        }

        //logger('update: ' . $this->updating . ' load: ' . $this->loading);

        if ($this->updating) {
            $ordering = "commented";

            if ($this->loading) {
                if ($mid) {
                    $r = q(
                        "SELECT parent AS item_id FROM item
						left join abook on item.author_xchan = abook.abook_xchan 
						$net_query
						WHERE mid like '%s' $uids $item_normal
						and (abook.abook_blocked = 0 or abook.abook_flags is null)
						$sql_extra $net_query2 LIMIT 1",
                        dbesc($mid . '%')
                    );
                } else {
                    // Fetch a page full of parent items for this page
                    $r = q("SELECT item.id AS item_id FROM item 
						left join abook on ( item.author_xchan = abook.abook_xchan $abook_uids )
						$net_query
						WHERE true $uids and item.item_thread_top = 1 $item_normal
						and (abook.abook_blocked = 0 or abook.abook_flags is null)
						$sql_extra $net_query2
						ORDER BY $ordering DESC $pager_sql ");
                }
            } elseif ($this->updating) {
                if ($mid) {
                    $r = q(
                        "SELECT parent AS item_id FROM item
						left join abook on item.author_xchan = abook.abook_xchan
						$net_query
						WHERE mid like '%s' $uids $item_normal_update $simple_update
						and (abook.abook_blocked = 0 or abook.abook_flags is null)
						$sql_extra $net_query2 LIMIT 1",
                        dbesc($mid . '%')
                    );
                } else {
                    $r = q("SELECT parent AS item_id FROM item
						left join abook on item.author_xchan = abook.abook_xchan
						$net_query
						WHERE true $uids $item_normal_update
						$simple_update
						and (abook.abook_blocked = 0 or abook.abook_flags is null)
						$sql_extra $net_query2");
                }
            }

            // Then fetch all the children of the parents that are on this page
            $parents_str = '';
            $update_unseen = '';

            if ($r) {
                $parents_str = ids_to_querystr($r, 'item_id');

                $items = q(
                    "SELECT item.*, item.id AS item_id FROM item
					WHERE true $uids $item_normal
					AND item.parent IN ( %s )
					$sql_extra ",
                    dbesc($parents_str)
                );

                // use effective_uid param of xchan_query to help sort out comment permission
                // for sys_channel owned items.

                xchan_query($items, true, (($sys) ? local_channel() : 0));
                $items = fetch_post_tags($items);
                $items = conv_sort($items, $ordering);
            } else {
                $items = [];
            }
        }

        if ($mid && local_channel()) {
            $ids = ids_to_array($items, 'item_id');
            $seen = $_SESSION['seen_items'];
            if (!$seen) {
                $seen = [];
            }
            $seen = array_values(array_unique(array_merge($ids, $seen)));
            $_SESSION['seen_items'] = $seen;
            PConfig::Set(local_channel(), 'system', 'seen_items', $seen);
        }

        // fake it
        $mode = ('pubstream');

        $o .= conversation($items, $mode, $this->updating, $page_mode);

        if ($mid) {
            $o .= '<div id="content-complete"></div>';
        }

        if (($items) && (!$this->updating)) {
            $o .= alt_pager(count($items));
        }

        return $o;
    }
}
