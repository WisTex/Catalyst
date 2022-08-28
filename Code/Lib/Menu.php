<?php

namespace Code\Lib;

use App;
use Code\Render\Theme;


require_once('include/security.php');
require_once('include/bbcode.php');


class Menu
{
    public static function fetch($name, $uid, $observer_xchan)
    {

        $sql_options = permissions_sql($uid, $observer_xchan);

        $r = q(
            "select * from menu where menu_channel_id = %d and menu_name = '%s' limit 1",
            intval($uid),
            dbesc($name)
        );
        if ($r) {
            $x = q(
                "select * from menu_item where mitem_menu_id = %d and mitem_channel_id = %d
    			$sql_options 
    			order by mitem_order asc, mitem_desc asc",
                intval($r[0]['menu_id']),
                intval($uid)
            );
            return array('menu' => $r[0], 'items' => $x );
        }

        return null;
    }

    public static function element($channel, $menu)
    {

        $arr = [];
        $arr['type'] = 'menu';
        $arr['pagetitle'] = $menu['menu']['menu_name'];
        $arr['desc'] = $menu['menu']['menu_desc'];
        $arr['created'] = $menu['menu']['menu_created'];
        $arr['edited'] = $menu['menu']['menu_edited'];

        $arr['baseurl'] = z_root();
        if ($menu['menu']['menu_flags']) {
            $arr['flags'] = [];
            if ($menu['menu']['menu_flags'] & MENU_BOOKMARK) {
                $arr['flags'][] = 'bookmark';
            }
            if ($menu['menu']['menu_flags'] & MENU_SYSTEM) {
                $arr['flags'][] = 'system';
            }
        }
        if ($menu['items']) {
            $arr['items'] = [];
            foreach ($menu['items'] as $it) {
                $entry = [];

                $entry['link'] = str_replace(z_root() . '/channel/' . $channel['channel_address'], '[channelurl]', $it['mitem_link']);
                $entry['link'] = str_replace(z_root() . '/page/' . $channel['channel_address'], '[pageurl]', $it['mitem_link']);
                $entry['link'] = str_replace(z_root() . '/cloud/' . $channel['channel_address'], '[cloudurl]', $it['mitem_link']);
                $entry['link'] = str_replace(z_root(), '[baseurl]', $it['mitem_link']);

                $entry['desc'] = $it['mitem_desc'];
                $entry['order'] = $it['mitem_order'];
                if ($it['mitem_flags']) {
                    $entry['flags'] = [];
                    if ($it['mitem_flags'] & MENU_ITEM_ZID) {
                        $entry['flags'][] = 'zid';
                    }
                    if ($it['mitem_flags'] & MENU_ITEM_NEWWIN) {
                        $entry['flags'][] = 'new-window';
                    }
                    if ($it['mitem_flags'] & MENU_ITEM_CHATROOM) {
                        $entry['flags'][] = 'chatroom';
                    }
                }
                $arr['items'][] = $entry;
            }
        }

        return $arr;
    }



    public static function render($menu, $class = '', $edit = false, $var = [])
    {

        if (! $menu) {
            return '';
        }

        $channel_id = ((is_array(App::$profile)) ? App::$profile['profile_uid'] : 0);
        if ((! $channel_id) && (local_channel())) {
            $channel_id = local_channel();
        }

        $chan = Channel::from_id($channel_id);
        if (! $chan) {
            return '';
        }

        $menu_list = self::list($channel_id);
        $menu_names = [];

        foreach ($menu_list as $menus) {
            if ($menus['menu_name'] != $menu['menu']['menu_name']) {
                $menu_names[] = $menus['menu_name'];
            }
        }

        for ($x = 0; $x < count($menu['items']); $x++) {
            if (in_array($menu['items'][$x]['mitem_link'], $menu_names)) {
                $m = self::fetch($menu['items'][$x]['mitem_link'], $channel_id, get_observer_hash());
                $submenu = self::render($m, 'dropdown-menu', $edit = false, array('wrap' => 'none'));
                $menu['items'][$x]['submenu'] = $submenu;
            }

            if ($menu['items'][$x]['mitem_flags'] & MENU_ITEM_ZID) {
                $menu['items'][$x]['mitem_link'] = zid($menu['items'][$x]['mitem_link']);
            }

            if ($menu['items'][$x]['mitem_flags'] & MENU_ITEM_NEWWIN) {
                $menu['items'][$x]['newwin'] = '1';
            }

            $menu['items'][$x]['mitem_desc'] = zidify_links(smilies(bbcode($menu['items'][$x]['mitem_desc'])));
        }

        $wrap = (($var['wrap'] === 'none') ? false : true);

        $ret = replace_macros(Theme::get_template('usermenu.tpl'), array(
            '$menu' => $menu['menu'],
            '$class' => $class,
            '$nick' => $chan['channel_address'],
            '$edit' => (($edit) ? t("Edit") : ''),
            '$id' => $menu['menu']['menu_id'],
            '$items' => $menu['items'],
            '$wrap' => $wrap
        ));

        return $ret;
    }



    public static function fetch_id($menu_id, $channel_id)
    {

        $r = q(
            "select * from menu where menu_id = %d and menu_channel_id = %d limit 1",
            intval($menu_id),
            intval($channel_id)
        );

        return (($r) ? $r[0] : false);
    }



    public static function create($arr)
    {
        $menu_name = trim(escape_tags($arr['menu_name']));
        $menu_desc = trim(escape_tags($arr['menu_desc']));
        $menu_flags = intval($arr['menu_flags']);

        //allow menu_desc (title) to be empty
        //if(! $menu_desc)
        //  $menu_desc = $menu_name;

        if (! $menu_name) {
            return false;
        }

        if (! $menu_flags) {
            $menu_flags = 0;
        }


        $menu_channel_id = intval($arr['menu_channel_id']);

        $r = q(
            "select * from menu where menu_name = '%s' and menu_channel_id = %d limit 1",
            dbesc($menu_name),
            intval($menu_channel_id)
        );

        if ($r) {
            return false;
        }

        $t = datetime_convert();

        $r = q(
            "insert into menu ( menu_name, menu_desc, menu_flags, menu_channel_id, menu_created, menu_edited ) 
    		values( '%s', '%s', %d, %d, '%s', '%s' )",
            dbesc($menu_name),
            dbesc($menu_desc),
            intval($menu_flags),
            intval($menu_channel_id),
            dbesc(datetime_convert('UTC', 'UTC', (($arr['menu_created']) ?: $t))),
            dbesc(datetime_convert('UTC', 'UTC', (($arr['menu_edited']) ?: $t)))
        );
        if (! $r) {
            return false;
        }

        $r = q(
            "select menu_id from menu where menu_name = '%s' and menu_channel_id = %d limit 1",
            dbesc($menu_name),
            intval($menu_channel_id)
        );
        if ($r) {
            return $r[0]['menu_id'];
        }
        return false;
    }

    /**
     * If $flags is present, check that all the bits in $flags are set
     * so that MENU_SYSTEM|MENU_BOOKMARK will return entries with both
     * bits set. We will use this to find system generated bookmarks.
     */

    public static function list($channel_id, $name = '', $flags = 0)
    {

        $sel_options = '';
        $sel_options .= (($name) ? " and menu_name = '" . protect_sprintf(dbesc($name)) . "' " : '');
        $sel_options .= (($flags) ? " and menu_flags = " . intval($flags) . " " : '');

        $r = q(
            "select * from menu where menu_channel_id = %d $sel_options order by menu_desc",
            intval($channel_id)
        );
        return $r;
    }

    public static function list_count($channel_id, $name = '', $flags = 0)
    {

        $sel_options = '';
        $sel_options .= (($name) ? " and menu_name = '" . protect_sprintf(dbesc($name)) . "' " : '');
        $sel_options .= (($flags) ? " and menu_flags = " . intval($flags) . " " : '');

        $r = q(
            "select count(*) as total from menu where menu_channel_id = %d $sel_options",
            intval($channel_id)
        );
        return $r[0]['total'];
    }

    public static function edit($arr)
    {

        $menu_id   = intval($arr['menu_id']);

        $menu_name = trim(escape_tags($arr['menu_name']));
        $menu_desc = trim(escape_tags($arr['menu_desc']));
        $menu_flags = intval($arr['menu_flags']);

        //allow menu_desc (title) to be empty
        //if(! $menu_desc)
        //  $menu_desc = $menu_name;

        if (! $menu_name) {
            return false;
        }

        if (! $menu_flags) {
            $menu_flags = 0;
        }


        $menu_channel_id = intval($arr['menu_channel_id']);

        $r = q(
            "select menu_id from menu where menu_name = '%s' and menu_channel_id = %d limit 1",
            dbesc($menu_name),
            intval($menu_channel_id)
        );
        if (($r) && ($r[0]['menu_id'] != $menu_id)) {
            logger('menu_edit: duplicate menu name for channel ' . $menu_channel_id);
            return false;
        }

        $r = q(
            "select * from menu where menu_id = %d and menu_channel_id = %d limit 1",
            intval($menu_id),
            intval($menu_channel_id)
        );
        if (! $r) {
            logger('menu_edit: not found: ' . print_r($arr, true));
            return false;
        }

        return q(
            "update menu set menu_name = '%s', menu_desc = '%s', menu_flags = %d, menu_edited = '%s'
    		where menu_id = %d and menu_channel_id = %d",
            dbesc($menu_name),
            dbesc($menu_desc),
            intval($menu_flags),
            dbesc(datetime_convert()),
            intval($menu_id),
            intval($menu_channel_id)
        );
    }

    public static function delete($menu_name, $uid)
    {
        $r = q(
            "select menu_id from menu where menu_name = '%s' and menu_channel_id = %d limit 1",
            dbesc($menu_name),
            intval($uid)
        );

        if ($r) {
            return self::delete_id($r[0]['menu_id'], $uid);
        }
        return false;
    }

    public static function delete_id($menu_id, $uid)
    {
        $r = q(
            "select menu_id from menu where menu_id = %d and menu_channel_id = %d limit 1",
            intval($menu_id),
            intval($uid)
        );
        if ($r) {
            $x = q(
                "delete from menu_item where mitem_menu_id = %d and mitem_channel_id = %d",
                intval($menu_id),
                intval($uid)
            );
            return q(
                "delete from menu where menu_id = %d and menu_channel_id = %d",
                intval($menu_id),
                intval($uid)
            );
        }
        return false;
    }

    public static function sync_packet($uid, $observer_hash, $menu_id, $delete = false)
    {
        $r = self::fetch_id($menu_id, $uid);
        $c = Channel::from_id($uid);
        if ($r) {
            $m = self::fetch($r['menu_name'], $uid, $observer_hash);
            if ($m) {
                if ($delete) {
                    $m['menu_delete'] = 1;
                }
                Libsync::build_sync_packet($uid, array('menu' => array(self::element($c, $m))));
            }
        }
    }

}
