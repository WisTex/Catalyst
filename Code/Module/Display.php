<?php

namespace Code\Module;

use App;
use Code\Lib\PermissionDescription;
use Code\Lib\System;
use Code\Lib\PConfig;
use Code\Web\Controller;
use Code\Lib\Channel;
use Code\Lib\Libacl;
use Code\Lib\Head;
use Code\Extend\Hook;
use Code\Render\Theme;


require_once("include/bbcode.php");
require_once('include/security.php');
require_once('include/conversation.php');


class Display extends Controller
{


    // State passed in from the Update module.

    public $profile_uid = 0;
    public $loading = 0;
    public $updating = 0;


    public function get()
    {

        $noscript_content = (get_config('system', 'noscript_content', '1') && (!$this->updating));

        $module_format = $_REQUEST['module_format'];
        $distance = 0;
        $distance_from = '';

        if (!in_array($module_format, ['atom', 'nomad', 'json'])) {
            $module_format = 'html';
        }

        if ($this->loading) {
            $_SESSION['loadtime_display'] = datetime_convert();
        }

        if (argc() > 1) {
            $item_hash = argv(1);
        }

        if ($_REQUEST['mid']) {
            $item_hash = $_REQUEST['mid'];
        }
        if (!$item_hash) {
            App::$error = 404;
            notice(t('Item not found.') . EOL);
            return;
        }

        $observer_is_owner = false;
        $updateable = false;

        if (local_channel() && (!$this->updating)) {
            $channel = App::get_channel();

            $channel_acl = [
                'allow_cid' => $channel['channel_allow_cid'],
                'allow_gid' => $channel['channel_allow_gid'],
                'deny_cid' => $channel['channel_deny_cid'],
                'deny_gid' => $channel['channel_deny_gid']
            ];

            $x = [
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
            ];

            $o = '<div id="jot-popup">';
            $o .= status_editor($x);
            $o .= '</div>';
        }

        // This page can be viewed by anybody so the query could be complicated
        // First we'll see if there is a copy of the item which is owned by us - if we're logged in locally.
        // If that fails (or we aren't logged in locally),
        // query an item in which the observer (if logged in remotely) has cid or gid rights
        // and if that fails, look for a copy of the post that has no privacy restrictions.
        // If we find the post, but we don't find a copy that we're allowed to look at, this fact needs to be reported.

        // find a copy of the item somewhere

        $target_item = null;

        $item_hash = unpack_link_id($item_hash);

        $r = q(
            "select id, uid, mid, parent_mid, thr_parent, verb, item_type, item_deleted, author_xchan, item_blocked from item where mid like '%s' limit 1",
            dbesc($item_hash . '%')
        );

        if ($r) {
            $target_item = $r[0];
        }

        $x = q(
            "select * from xchan where xchan_hash = '%s' limit 1",
            dbesc($target_item['author_xchan'])
        );
        if ($x) {
// not yet ready for prime time
//          \App::$poi = $x[0];
        }

        // if the item is to be moderated redirect to /moderate
        if ($target_item['item_blocked'] == ITEM_MODERATED) {
            goaway(z_root() . '/moderate/?mid=' . gen_link_id($target_item['mid']));
        }

        $r = null;

        if ($target_item['item_type'] == ITEM_TYPE_WEBPAGE) {
            $x = q(
                "select * from channel where channel_id = %d limit 1",
                intval($target_item['uid'])
            );
            $y = q(
                "select * from iconfig left join item on iconfig.iid = item.id 
				where item.uid = %d and iconfig.cat = 'system' and iconfig.k = 'WEBPAGE' and item.id = %d limit 1",
                intval($target_item['uid']),
                intval($target_item['parent'])
            );
            if ($x && $y) {
                goaway(z_root() . '/page/' . $x[0]['channel_address'] . '/' . $y[0]['v']);
            } else {
                notice(t('Page not found.') . EOL);
                return '';
            }
        }
        if ($target_item['item_type'] == ITEM_TYPE_ARTICLE) {
            $x = q(
                "select * from channel where channel_id = %d limit 1",
                intval($target_item['uid'])
            );
            $y = q(
                "select * from iconfig left join item on iconfig.iid = item.id 
				where item.uid = %d and iconfig.cat = 'system' and iconfig.k = 'ARTICLE' and item.id = %d limit 1",
                intval($target_item['uid']),
                intval($target_item['parent'])
            );
            if ($x && $y) {
                goaway(z_root() . '/articles/' . $x[0]['channel_address'] . '/' . $y[0]['v']);
            } else {
                notice(t('Page not found.') . EOL);
                return '';
            }
        }
        if ($target_item['item_type'] == ITEM_TYPE_CARD) {
            $x = q(
                "select * from channel where channel_id = %d limit 1",
                intval($target_item['uid'])
            );
            $y = q(
                "select * from iconfig left join item on iconfig.iid = item.id 
				where item.uid = %d and iconfig.cat = 'system' and iconfig.k = 'CARD' and item.id = %d limit 1",
                intval($target_item['uid']),
                intval($target_item['parent'])
            );
            if ($x && $y) {
                goaway(z_root() . '/cards/' . $x[0]['channel_address'] . '/' . $y[0]['v']);
            } else {
                notice(t('Page not found.') . EOL);
                return '';
            }
        }
        if ($target_item['item_type'] == ITEM_TYPE_CUSTOM) {
            Hook::call('item_custom_display', $target_item);
            notice(t('Page not found.') . EOL);
            return '';
        }


        $static = ((array_key_exists('static', $_REQUEST)) ? intval($_REQUEST['static']) : 0);


        $simple_update = (($this->updating) ? " AND item_unseen = 1 " : '');

        if ($this->updating && $_SESSION['loadtime_display']) {
            $simple_update = " AND item.changed > '" . datetime_convert('UTC', 'UTC', $_SESSION['loadtime_display']) . "' ";
        }
        if ($this->loading) {
            $simple_update = '';
        }

        if ($static && $simple_update) {
            $simple_update .= " and item_thread_top = 0 and author_xchan = '" . protect_sprintf(get_observer_hash()) . "' ";
        }

        if ((!$this->updating) && (!$this->loading)) {
            $static = ((local_channel()) ? Channel::manual_conv_update(local_channel()) : 1);

            // if the target item is not a post (eg a like) we want to address its thread parent

            $mid = ((($target_item['verb'] == ACTIVITY_LIKE) || ($target_item['verb'] == ACTIVITY_DISLIKE)) ? $target_item['thr_parent'] : $target_item['mid']);

            // if we received a decoded hash originally we must encode it again before handing to javascript

            $mid = gen_link_id($mid);

            $o .= '<div id="live-display"></div>' . "\r\n";
            $o .= "<script> let profile_uid = " . ((intval(local_channel())) ? local_channel() : (-1))
                . "; let netargs = '?f='; let profile_page = " . App::$pager['page'] . "; </script>\r\n";

            App::$page['htmlhead'] .= replace_macros(Theme::get_template("build_query.tpl"), [
                '$baseurl' => z_root(),
                '$pgtype' => 'display',
                '$uid' => '0',
                '$gid' => '0',
                '$cid' => '0',
                '$cmin' => '(-1)',
                '$cmax' => '(-1)',
                '$star' => '0',
                '$liked' => '0',
                '$conv' => '0',
                '$spam' => '0',
                '$fh' => '0',
                '$dm' => '0',
                '$nouveau' => '0',
                '$wall' => '0',
                '$draft' => '0',
                '$static' => $static,
                '$page' => ((App::$pager['page'] != 1) ? App::$pager['page'] : 1),
                '$list' => ((x($_REQUEST, 'list')) ? intval($_REQUEST['list']) : 0),
                '$search' => '',
                '$xchan' => '',
                '$order' => '',
                '$file' => '',
                '$cats' => '',
                '$tags' => '',
                '$dend' => '',
                '$dbegin' => '',
                '$verb' => '',
                '$net' => '',
                '$mid' => (($mid) ? urlencode($mid) : ''),
                '$pf' => '0',
                '$distance' => '0',
                '$distance_from' => '',
            ]);

            Head::add_link([
                'rel' => 'alternate',
                'type' => 'application/json+oembed',
                'href' => z_root() . '/oep?f=&url=' . urlencode(z_root() . '/' . App::$query_string),
                'title' => 'oembed'
            ]);
        }

        $observer_hash = get_observer_hash();
        $item_normal = item_normal();
        $item_normal_update = item_normal_update();

        $sql_extra = ((local_channel()) ? EMPTY_STR : item_permissions_sql(0, $observer_hash));

        if ($noscript_content || $this->loading) {
            $r = null;

            $sys = Channel::get_system();
            $sysid = $sys['channel_id'];

            if (local_channel()) {
                $r = q(
                    "SELECT item.id as item_id from item WHERE uid = %d and mid = '%s' $item_normal limit 1",
                    intval(local_channel()),
                    dbesc($target_item['parent_mid'])
                );
                if ($r) {
                    $updateable = true;
                }
            }

            if (!(is_array($r) && count($r))) {
                $r = q(
                    "SELECT item.id as item_id from item WHERE mid = '%s' $sql_extra $item_normal limit 1",
                    dbesc($target_item['parent_mid'])
                );
            }
        } elseif ($this->updating && !$this->loading) {
            $r = null;

            $sys = Channel::get_system();
            $sysid = $sys['channel_id'];

            if (local_channel()) {
                $r = q(
                    "SELECT item.parent AS item_id from item WHERE uid = %d and parent_mid = '%s' $item_normal_update $simple_update limit 1",
                    intval(local_channel()),
                    dbesc($target_item['parent_mid'])
                );
                if ($r) {
                    $updateable = true;
                }
            }

            if (!$r) {
                $r = q(
                    "SELECT item.parent AS item_id from item WHERE parent_mid = '%s' $sql_extra $item_normal_update $simple_update limit 1",
                    dbesc($target_item['parent_mid'])
                );
            }
        } else {
            $r = [];
        }

        if ($r) {
            $parents_str = ids_to_querystr($r, 'item_id');
            if ($parents_str) {
                $items = q(
                    "SELECT item.*, item.id AS item_id 
					FROM item
					WHERE parent in ( %s ) $item_normal $sql_extra ",
                    dbesc($parents_str)
                );
                xchan_query($items);
                $items = fetch_post_tags($items);
                $items = conv_sort($items, 'created');
            }
        } else {
            $items = [];
        }

        // see if the top-level post owner chose to block search engines

        if ($items && get_pconfig($items[0]['uid'], 'system', 'noindex')) {
            App::$meta->set('robots', 'noindex, noarchive');
        }

        foreach ($items as $item) {
            if ($item['mid'] === $item_hash) {
                if (preg_match("/\[[zi]mg(.*?)]([^\[]+)/is", $items[0]['body'], $matches)) {
                    $ogimage = $matches[2];
                    //  Will we use og:image:type someday? We keep this just in case
                    //  $ogimagetype = guess_image_type($ogimage);
                }

                // some work on post content to generate a description
                // almost fully based on work done on Hubzilla by Max Kostikov
                $ogdesc = $item['body'];

                $ogdesc = bbcode($ogdesc, ['export' => true]);
                $ogdesc = trim(html2plain($ogdesc, 0, true));
                $ogdesc = html_entity_decode($ogdesc, ENT_QUOTES, 'UTF-8');

                // remove all URLs
                $ogdesc = preg_replace("/https?:\/\/[a-zA-Z0-9:\/\-?&;.=_~#%\$!+,@]+/", "", $ogdesc);

                // shorten description
                $ogdesc = substr($ogdesc, 0, 300);
                $ogdesc = str_replace("\n", " ", $ogdesc);
                while (str_contains($ogdesc, "  ")) {
                    $ogdesc = str_replace("  ", " ", $ogdesc);
                }
                $ogdesc = (strlen($ogdesc) < 298 ? $ogdesc : rtrim(substr($ogdesc, 0, strrpos($ogdesc, " ")), "?.,:;!-") . "...");

                $ogsite = (System::get_site_name()) ? escape_tags(System::get_site_name()) : System::get_platform_name();

                // we can now start loading content
                if ($item['mid'] == $item['parent_mid']) {
                    App::$meta->set('og:title', ($items[0]['title']
                        ? sprintf(t('"%1$s", shared by %2$s with %3$s'), $items[0]['title'], $item['author']['xchan_name'], $ogsite)
                        : sprintf(t('%1$s shared this post with %2$s'), $item['author']['xchan_name'], $ogsite)));
                    App::$meta->set('og:image', (isset($ogimage) ? $ogimage : System::get_site_icon()));
                    App::$meta->set('og:type', 'article');
                    App::$meta->set('og:url:secure_url', $item['llink']);
                    App::$meta->set('og:description', ($ogdesc ? $ogdesc : sprintf(t('Not much to read, click to see the post.'))));
                } else {
                    if (($target_item['verb'] == ACTIVITY_LIKE) || ($target_item['verb'] == ACTIVITY_DISLIKE)) {
                        App::$meta->set('og:title', ($items[0]['title']
                            ? sprintf(t('%1$s shared a reaction to "%2$s"'), $item['author']['xchan_name'], $items[0]['title'])
                            : sprintf(t('%s shared a reaction to this post/conversation'), $item['author']['xchan_name'])));
                        App::$meta->set('og:image', (isset($ogimage) ? $ogimage : System::get_site_icon()));
                        App::$meta->set('og:type', 'article');
                        App::$meta->set('og:url:secure_url', $item['llink']);
                        App::$meta->set('og:description', $ogdesc);
                    } else {
                        App::$meta->set('og:title', ($items[0]['title']
                            ? sprintf(t('%1$s commented "%2$s"'), $item['author']['xchan_name'], $items[0]['title'])
                            : sprintf(t('%s shared a comment of this post/conversation'), $item['author']['xchan_name'])));
                        App::$meta->set('og:image', (isset($ogimage) ? $ogimage : System::get_site_icon()));
                        App::$meta->set('og:type', 'article');
                        App::$meta->set('og:url:secure_url', $item['llink']);
                        App::$meta->set('og:description', sprintf(t('%1$s wrote this: "%2$s"'), $item['author']['xchan_name'], $ogdesc));
                    }
                }
            }
        }

        if (local_channel() && $items) {
            $ids = ids_to_array($items, 'item_id');
            $seen = $_SESSION['seen_items'];
            if (!$seen) {
                $seen = [];
            }
            $seen = array_values(array_unique(array_merge($ids, $seen)));
            $_SESSION['seen_items'] = $seen;
            PConfig::Set(local_channel(), 'system', 'seen_items', $seen);
        }

        switch ($module_format) {
            case 'html':
                if ($this->updating) {
                    $o .= conversation($items, 'display', $this->updating, 'client');
                } else {
                    $o .= '<noscript>';
                    if ($noscript_content) {
                        $o .= conversation($items, 'display', $this->updating, 'traditional');
                    } else {
                        $o .= '<div class="section-content-warning-wrapper">' . t('You must enable javascript for your browser to be able to view this content.') . '</div>';
                    }
                    $o .= '</noscript>';

                    App::$page['title'] = (($items[0]['title']) ? $items[0]['title'] . " - " . App::$page['title'] : App::$page['title']);

                    $o .= conversation($items, 'display', $this->updating, 'client');
                }

                break;

            case 'atom':
                $atom = replace_macros(Theme::get_template('atom_feed.tpl'), [
                    '$version' => xmlify(System::get_project_version()),
                    '$generator' => xmlify(System::get_project_name()),
                    '$generator_uri' => z_root(),
                    '$feed_id' => xmlify(App::$cmd),
                    '$feed_title' => xmlify(t('Article')),
                    '$feed_updated' => xmlify(datetime_convert('UTC', 'UTC', 'now', ATOM_TIME)),
                    '$author' => '',
                    '$owner' => '',
                    '$profile_page' => xmlify(z_root() . '/display/?mid=' . $target_item['mid']),
                ]);

                $x = ['xml' => $atom, 'channel' => $channel, 'observer_hash' => $observer_hash, 'params' => []];
                Hook::call('atom_feed_top', $x);

                $atom = $x['xml'];

                // a much simpler interface
                Hook::call('atom_feed', $atom);


                if ($items) {
                    $type = 'html';
                    foreach ($items as $item) {
                        if ($item['item_private']) {
                            continue;
                        }
                        $atom .= atom_entry($item, $type, [], [], true, '');
                    }
                }

                Hook::call('atom_feed_end', $atom);

                $atom .= '</feed>' . "\r\n";

                header('Content-type: application/atom+xml');
                echo $atom;
                killme();
        }

        if ($updateable) {
            $x = q(
                "UPDATE item SET item_unseen = 0 where item_unseen = 1 AND uid = %d and parent = %d ",
                intval(local_channel()),
                intval($r[0]['item_id'])
            );
        }

        $o .= '<div id="content-complete"></div>';

        if ((($this->updating && $this->loading) || $noscript_content) && (!$items)) {
            $r = q(
                "SELECT id, item_deleted FROM item WHERE mid = '%s' LIMIT 1",
                dbesc($item_hash)
            );

            if ($r) {
                if (intval($r[0]['item_deleted'])) {
                    notice(t('Item has been removed.') . EOL);
                } else {
                    notice(t('Permission denied.') . EOL);
                }
            } else {
                notice(t('Item not found.') . EOL);
            }
        }

        return $o;
    }
}
