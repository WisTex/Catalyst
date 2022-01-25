<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;
use Zotlabs\Access\AccessControl;
use Zotlabs\Lib\PermissionDescription;
use Zotlabs\Lib\Libzot;
use Zotlabs\Lib\Navbar;

require_once('include/acl_selectors.php');
require_once('include/items.php');
require_once('include/taxonomy.php');
require_once('include/conversation.php');
require_once('include/attach.php');

/**
 * remote post
 *
 * https://yoursite/rpost?f=&title=&body=&remote_return=
 *
 * This can be called via either GET or POST, use POST for long body content as suhosin often limits GET parameter length
 *
 * f= placeholder, often required
 * title= Title of post
 * body= Body of post
 * url= URL which will be parsed and the results appended to the body
 * source= Source application
 * post_id= post_id of post to 'share' (local use only)
 * remote_return= absolute URL to return after posting is finished
 * type= choices are 'html' or 'bbcode', default is 'bbcode'
 *
 */
class Rpost extends Controller
{

    public function get()
    {

        $o = '';

        if (!local_channel()) {
            if (remote_channel()) {
                // redirect to your own site.
                $url = Libzot::get_rpost_path(App::get_observer());
                // make sure we're not looping to our own hub
                if (($url) && (!stristr($url, App::get_hostname()))) {
                    foreach ($_GET as $key => $arg) {
                        if ($key === 'req') {
                            continue;
                        }
                        $url .= '&' . $key . '=' . $arg;
                    }
                    goaway($url);
                }
            }

            // The login procedure is going to bugger our $_REQUEST variables
            // so save them in the session.

            if (array_key_exists('body', $_REQUEST)) {
                $_SESSION['rpost'] = $_REQUEST;
            }
            return login();
        }

        Navbar::set_selected('Post');

        if (local_channel() && array_key_exists('userfile', $_FILES)) {
            $channel = App::get_channel();
            $observer = App::get_observer();

            $def_album = get_pconfig($channel['channel_id'], 'system', 'photo_path');
            $def_attach = get_pconfig($channel['channel_id'], 'system', 'attach_path');

            $r = attach_store($channel, (($observer) ? $observer['xchan_hash'] : ''), '', [
                'source' => 'editor',
                'visible' => 0,
                'album' => $def_album,
                'directory' => $def_attach,
                'flags' => 1, // indicates temporary permissions are created
                'allow_cid' => '<' . $channel['channel_hash'] . '>'
            ]);

            if (!$r['success']) {
                notice($r['message'] . EOL);
            }

            $s = EMPTY_STR;

            if (intval($r['data']['is_photo'])) {
                $s .= "\n\n" . $r['body'] . "\n\n";
            }

            $url = z_root() . '/cloud/' . $channel['channel_address'] . '/' . $r['data']['display_path'];

            if (strpos($r['data']['filetype'], 'video') === 0) {
                for ($n = 0; $n < 15; $n++) {
                    $thumb = Linkinfo::get_video_poster($url);
                    if ($thumb) {
                        break;
                    }
                    sleep(1);
                    continue;
                }

                if ($thumb) {
                    $s .= "\n\n" . '[zvideo poster=\'' . $thumb . '\']' . $url . '[/zvideo]' . "\n\n";
                } else {
                    $s .= "\n\n" . '[zvideo]' . $url . '[/zvideo]' . "\n\n";
                }
            }
            if (strpos($r['data']['filetype'], 'audio') === 0) {
                $s .= "\n\n" . '[zaudio]' . $url . '[/zaudio]' . "\n\n";
            }
            if ($r['data']['filetype'] === 'image/svg+xml') {
                $x = @file_get_contents('store/' . $channel['channel_address'] . '/' . $r['data']['os_path']);
                if ($x) {
                    $bb = svg2bb($x);
                    if ($bb) {
                        $s .= "\n\n" . $bb;
                    } else {
                        logger('empty return from svgbb');
                    }
                } else {
                    logger('unable to read svg data file: ' . 'store/' . $channel['channel_address'] . '/' . $r['data']['os_path']);
                }
            }
            if ($r['data']['filetype'] === 'text/vnd.abc' && addon_is_installed('abc')) {
                $x = @file_get_contents('store/' . $channel['channel_address'] . '/' . $r['data']['os_path']);
                if ($x) {
                    $s .= "\n\n" . '[abc]' . $x . '[/abc]';
                } else {
                    logger('unable to read ABC data file: ' . 'store/' . $channel['channel_address'] . '/' . $r['data']['os_path']);
                }
            }
            if ($r['data']['filetype'] === 'text/calendar') {
                $content = @file_get_contents('store/' . $channel['channel_address'] . '/' . $r['data']['os_path']);
                if ($content) {
                    $ev = ical_to_ev($content);
                    if ($ev) {
                        $s .= "\n\n" . format_event_bbcode($ev[0]) . "\n\n";
                    }
                }
            }

            $s .= "\n\n" . '[attachment]' . $r['data']['hash'] . ',' . $r['data']['revision'] . '[/attachment]' . "\n";
            $_REQUEST['body'] = ((array_key_exists('body', $_REQUEST)) ? $_REQUEST['body'] . $s : $s);
        }

        // If we have saved rpost session variables, but nothing in the current $_REQUEST, recover the saved variables

        if ((!array_key_exists('body', $_REQUEST)) && (array_key_exists('rpost', $_SESSION))) {
            $_REQUEST = $_SESSION['rpost'];
            unset($_SESSION['rpost']);
        }

        if (array_key_exists('channel', $_REQUEST)) {
            $r = q(
                "select channel_id from channel where channel_account_id = %d and channel_address = '%s' limit 1",
                intval(get_account_id()),
                dbesc($_REQUEST['channel'])
            );
            if ($r) {
                require_once('include/security.php');
                $change = change_channel($r[0]['channel_id']);
            }
        }

        if ($_REQUEST['remote_return']) {
            $_SESSION['remote_return'] = $_REQUEST['remote_return'];
        }

        if (argc() > 1 && argv(1) === 'return') {
            if ($_SESSION['remote_return']) {
                goaway($_SESSION['remote_return']);
            }
            goaway(z_root() . '/stream');
        }

        $plaintext = true;

        if (array_key_exists('type', $_REQUEST) && $_REQUEST['type'] === 'html' && isset($_REQUEST['body'])) {
            require_once('include/html2bbcode.php');
            $_REQUEST['body'] = html2bbcode($_REQUEST['body']);
        }

        $channel = App::get_channel();

        $acl = new AccessControl($channel);

        if (array_key_exists('to', $_REQUEST) && $_REQUEST['to']) {
            $acl->set(['allow_cid' => '<' . $_REQUEST['to'] . '>',
                    'allow_gid' => EMPTY_STR,
                    'deny_cid' => EMPTY_STR,
                    'deny_gid' => EMPTY_STR]);
			if (! (isset($_REQUEST['body']) && $_REQUEST['body'])) {
				$xchan = q("select * from xchan where xchan_hash = '%s'",
					dbesc($_REQUEST['to'])
				);
			
				if ($xchan) {
					$_REQUEST['body'] .= '@!{' . (($xchan[0]['xchan_addr']) ? $xchan[0]['xchan_addr'] : $xchan[0]['xchan_url']) . '} ' ;
				}
			}
        }

        $channel_acl = $acl->get();

        if ($_REQUEST['url']) {
            $x = z_fetch_url(z_root() . '/linkinfo?f=&url=' . urlencode($_REQUEST['url']) . '&oembed=1&zotobj=1');
            if ($x['success']) {
                $_REQUEST['body'] = $_REQUEST['body'] . $x['body'];
            }
        }

        if ($_REQUEST['post_id']) {
            $_REQUEST['body'] .= '[share=' . intval($_REQUEST['post_id']) . '][/share]';
        }

        $x = [
            'is_owner' => true,
            'allow_location' => ((intval(get_pconfig($channel['channel_id'], 'system', 'use_browser_location'))) ? '1' : ''),
            'default_location' => $channel['channel_location'],
            'nickname' => $channel['channel_address'],
            'lockstate' => (($acl->is_private()) ? 'lock' : 'unlock'),
            'acl' => populate_acl($channel_acl, true, PermissionDescription::fromGlobalPermission('view_stream'), get_post_aclDialogDescription(), 'acl_dialog_post'),
            'permissions' => $channel_acl,
            'bang' => '',
            'visitor' => true,
            'profile_uid' => local_channel(),
            'title' => $_REQUEST['title'],
            'body' => $_REQUEST['body'],
            'attachment' => $_REQUEST['attachment'],
            'source' => ((x($_REQUEST, 'source')) ? strip_tags($_REQUEST['source']) : ''),
            'return_path' => 'rpost/return',
            'bbco_autocomplete' => 'bbcode',
            'editor_autocomplete' => true,
            'bbcode' => true,
            'jotnets' => true,
            'reset' => t('Reset form')
        ];

        $editor = status_editor($x);

        $o .= replace_macros(get_markup_template('edpost_head.tpl'), [
            '$title' => t('Edit post'),
            '$cancel' => '',
            '$editor' => $editor
        ]);

        return $o;
    }
}
