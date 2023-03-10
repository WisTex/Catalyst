<?php

/** @file */

namespace Code\Module;


use App;
use Code\Lib as Zlib;
use Code\Web\Controller;
use Code\Lib\Channel;
use Code\Extend\Hook;

require_once('include/security.php');

class Chatsvc extends Controller
{

    public function init()
    {

        //logger('chatsvc');

        $ret = ['success' => false];

        App::$data['chat']['room_id'] = intval($_REQUEST['room_id']);
        $x = q(
            "select cr_uid from chatroom where cr_id = %d and cr_id != 0 limit 1",
            intval(App::$data['chat']['room_id'])
        );
        if (!$x) {
            json_return_and_die($ret);
        }

        App::$data['chat']['uid'] = $x[0]['cr_uid'];

        if (!perm_is_allowed(App::$data['chat']['uid'], get_observer_hash(), 'chat')) {
            json_return_and_die($ret);
        }
    }

    public function post()
    {

        $ret = ['success' => false];

        $room_id = App::$data['chat']['room_id'];
        $text = escape_tags($_REQUEST['chat_text']);
        if (!$text) {
            return;
        }

        $sql_extra = permissions_sql(App::$data['chat']['uid']);

        $r = q(
            "select * from chatroom where cr_uid = %d and cr_id = %d $sql_extra",
            intval(App::$data['chat']['uid']),
            intval(App::$data['chat']['room_id'])
        );
        if (!$r) {
            json_return_and_die($ret);
        }

        $arr = [
            'chat_room' => App::$data['chat']['room_id'],
            'chat_xchan' => get_observer_hash(),
            'chat_text' => $text
        ];

        Hook::call('chat_post', $arr);

        $x = q(
            "insert into chat ( chat_room, chat_xchan, created, chat_text )
			values( %d, '%s', '%s', '%s' )",
            intval(App::$data['chat']['room_id']),
            dbesc(get_observer_hash()),
            dbesc(datetime_convert()),
            dbesc(str_rot47(base64url_encode($arr['chat_text'])))
        );

        $ret['success'] = true;
        json_return_and_die($ret);
    }

    public function get()
    {

        $status = strip_tags($_REQUEST['status']);
        $room_id = intval(App::$data['chat']['room_id']);
        $stopped = ((x($_REQUEST, 'stopped') && intval($_REQUEST['stopped'])) ? true : false);

        if ($status && $room_id) {
            $x = q(
                "select channel_address from channel where channel_id = %d limit 1",
                intval(App::$data['chat']['uid'])
            );

            $r = q(
                "update chatpresence set cp_status = '%s', cp_last = '%s' where cp_room = %d and cp_xchan = '%s' and cp_client = '%s'",
                dbesc($status),
                dbesc(datetime_convert()),
                intval($room_id),
                dbesc(get_observer_hash()),
                dbesc($_SERVER['REMOTE_ADDR'])
            );

            goaway(z_root() . '/chat/' . $x[0]['channel_address'] . '/' . $room_id);
        }

        if (!$stopped) {
            $lastseen = intval($_REQUEST['last']);

            $ret = ['success' => false];

            $sql_extra = permissions_sql(App::$data['chat']['uid']);

            $r = q(
                "select * from chatroom where cr_uid = %d and cr_id = %d $sql_extra",
                intval(App::$data['chat']['uid']),
                intval(App::$data['chat']['room_id'])
            );
            if (!$r) {
                json_return_and_die($ret);
            }

            $inroom = [];

            $r = q(
                "select * from chatpresence left join xchan on xchan_hash = cp_xchan where cp_room = %d order by xchan_name",
                intval(App::$data['chat']['room_id'])
            );
            if ($r) {
                foreach ($r as $rv) {
                    if (!$rv['xchan_name']) {
                        $rv['xchan_hash'] = $rv['cp_xchan'];
                        $rv['xchan_name'] = substr($rv['cp_xchan'], strrpos($rv['cp_xchan'], '.') + 1);
                        $rv['xchan_addr'] = '';
                        $rv['xchan_network'] = 'unknown';
                        $rv['xchan_url'] = z_root();
                        $rv['xchan_hidden'] = 1;
                        $rv['xchan_photo_mimetype'] = 'image/png';
                        $rv['xchan_photo_l'] = z_root() . '/' . Channel::get_default_profile_photo(300);
                        $rv['xchan_photo_m'] = z_root() . '/' . Channel::get_default_profile_photo(80);
                        $rv['xchan_photo_s'] = z_root() . '/' . Channel::get_default_profile_photo(48);
                    }

                    switch ($rv['cp_status']) {
                        case 'away':
                            $status = t('Away');
                            $status_class = 'away';
                            break;
                        case 'online':
                        default:
                            $status = t('Online');
                            $status_class = 'online';
                            break;
                    }

                    $inroom[] = ['img' => zid($rv['xchan_photo_m']), 'img_type' => $rv['xchan_photo_mimetype'], 'name' => $rv['xchan_name'], 'status' => $status, 'status_class' => $status_class];
                }
            }

            $chats = [];

            $r = q(
                "select * from chat left join xchan on chat_xchan = xchan_hash where chat_room = %d and chat_id > %d order by created",
                intval(App::$data['chat']['room_id']),
                intval($lastseen)
            );
            if ($r) {
                foreach ($r as $rr) {
                    $chats[] = [
                        'id' => $rr['chat_id'],
                        'img' => zid($rr['xchan_photo_m']),
                        'img_type' => $rr['xchan_photo_mimetype'],
                        'name' => $rr['xchan_name'],
                        'isotime' => datetime_convert('UTC', date_default_timezone_get(), $rr['created'], 'c'),
                        'localtime' => datetime_convert('UTC', date_default_timezone_get(), $rr['created'], 'r'),
                        'text' => zidify_links(smilies(bbcode(base64url_decode(str_rot47($rr['chat_text']))))),
                        'self' => ((get_observer_hash() == $rr['chat_xchan']) ? 'self' : '')
                    ];
                }
            }
        }

        $r = q(
            "update chatpresence set cp_last = '%s' where cp_room = %d and cp_xchan = '%s' and cp_client = '%s'",
            dbesc(datetime_convert()),
            intval(App::$data['chat']['room_id']),
            dbesc(get_observer_hash()),
            dbesc($_SERVER['REMOTE_ADDR'])
        );

        $ret['success'] = true;
        if (!$stopped) {
            $ret['inroom'] = $inroom;
            $ret['chats'] = $chats;
        }
        json_return_and_die($ret);
    }
}
