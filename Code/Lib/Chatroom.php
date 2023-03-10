<?php

namespace Code\Lib;

use Code\Extend\Hook;
    
/**
 * @brief A class with chatroom related static methods.
 */
class Chatroom
{
    /**
     * @brief Creates a chatroom.
     *
     * @param array $channel
     * @param array $arr
     * @return array An associative array containing:
     *   * \e boolean \b success - A boolean success status
     *   * \e string \b message - (optional) A string
     */
    public static function create($channel, $arr)
    {

        $ret = array('success' => false);

        $name = trim($arr['name']);
        if (!$name) {
            $ret['message'] = t('Missing room name');
            return $ret;
        }

        $r = q(
            "select cr_id from chatroom where cr_uid = %d and cr_name = '%s' limit 1",
            intval($channel['channel_id']),
            dbesc($name)
        );
        if ($r) {
            $ret['message'] = t('Duplicate room name');
            return $ret;
        }

        $r = q(
            "select count(cr_id) as total from chatroom where cr_aid = %d",
            intval($channel['channel_account_id'])
        );
        if ($r) {
            $limit = ServiceClass::fetch($channel['channel_id'], 'chatrooms');
        }

        if (($r) && ($limit !== false) && ($r[0]['total'] >= $limit)) {
            $ret['message'] = ServiceClass::upgrade_message();
            return $ret;
        }

        if (!array_key_exists('expire', $arr)) {
            $arr['expire'] = 120;  // minutes, e.g. 2 hours
        }

        $created = datetime_convert();

        $x = q(
            "insert into chatroom ( cr_aid, cr_uid, cr_name, cr_created, cr_edited, cr_expire, allow_cid, allow_gid, deny_cid, deny_gid )
			values ( %d, %d , '%s', '%s', '%s', %d, '%s', '%s', '%s', '%s' ) ",
            intval($channel['channel_account_id']),
            intval($channel['channel_id']),
            dbesc($name),
            dbesc($created),
            dbesc($created),
            intval($arr['expire']),
            dbesc($arr['allow_cid']),
            dbesc($arr['allow_gid']),
            dbesc($arr['deny_cid']),
            dbesc($arr['deny_gid'])
        );

        if ($x) {
            $ret['success'] = true;
        }

        return $ret;
    }


    public static function destroy($channel, $arr)
    {

        $ret = array('success' => false);

        if (intval($arr['cr_id'])) {
            $sql_extra = " and cr_id = " . intval($arr['cr_id']) . " ";
        } elseif (trim($arr['cr_name'])) {
            $sql_extra = " and cr_name = '" . protect_sprintf(dbesc(trim($arr['cr_name']))) . "' ";
        } else {
            $ret['message'] = t('Invalid room specifier.');
            return $ret;
        }

        $r = q(
            "select * from chatroom where cr_uid = %d $sql_extra limit 1",
            intval($channel['channel_id'])
        );
        if (!$r) {
            $ret['message'] = t('Invalid room specifier.');
            return $ret;
        }

        Libsync::build_sync_packet($channel['channel_id'], array('chatroom' => $r));

        q(
            "delete from chatroom where cr_id = %d",
            intval($r[0]['cr_id'])
        );
        if ($r[0]['cr_id']) {
            q(
                "delete from chatpresence where cp_room = %d",
                intval($r[0]['cr_id'])
            );
            q(
                "delete from chat where chat_room = %d",
                intval($r[0]['cr_id'])
            );
        }

        $ret['success'] = true;
        return $ret;
    }


    public static function enter($observer_xchan, $room_id, $status, $client)
    {

        if (!$room_id || !$observer_xchan) {
            return;
        }

        $r = q(
            "select * from chatroom where cr_id = %d limit 1",
            intval($room_id)
        );
        if (!$r) {
            notice(t('Room not found.') . EOL);
            return false;
        }
        require_once('include/security.php');
        $sql_extra = permissions_sql($r[0]['cr_uid']);

        $x = q(
            "select * from chatroom where cr_id = %d and cr_uid = %d $sql_extra limit 1",
            intval($room_id),
            intval($r[0]['cr_uid'])
        );
        if (!$x) {
            notice(t('Permission denied.') . EOL);
            return false;
        }

        $limit = ServiceClass::fetch($r[0]['cr_uid'], 'chatters_inroom');
        if ($limit !== false) {
            $y = q(
                "select count(*) as total from chatpresence where cp_room = %d",
                intval($room_id)
            );
            if ($y && $y[0]['total'] > $limit) {
                notice(t('Room is full') . EOL);
                return false;
            }
        }

        if (intval($x[0]['cr_expire'])) {
            $r = q(
                "delete from chat where created < %s - INTERVAL %s and chat_room = %d",
                db_utcnow(),
                db_quoteinterval(intval($x[0]['cr_expire']) . ' MINUTE'),
                intval($x[0]['cr_id'])
            );
        }

        $r = q(
            "select * from chatpresence where cp_xchan = '%s' and cp_room = %d limit 1",
            dbesc($observer_xchan),
            intval($room_id)
        );
        if ($r) {
            q(
                "update chatpresence set cp_last = '%s' where cp_id = %d and cp_client = '%s'",
                dbesc(datetime_convert()),
                intval($r[0]['cp_id']),
                dbesc($client)
            );
            return true;
        }

        $r = q(
            "insert into chatpresence ( cp_room, cp_xchan, cp_last, cp_status, cp_client )
			values ( %d, '%s', '%s', '%s', '%s' )",
            intval($room_id),
            dbesc($observer_xchan),
            dbesc(datetime_convert()),
            dbesc($status),
            dbesc($client)
        );

        return $r;
    }


    public function leave($observer_xchan, $room_id, $client)
    {
        if (!$room_id || !$observer_xchan) {
            return;
        }

        $r = q(
            "select * from chatpresence where cp_xchan = '%s' and cp_room = %d and cp_client = '%s' limit 1",
            dbesc($observer_xchan),
            intval($room_id),
            dbesc($client)
        );
        if ($r) {
            q(
                "delete from chatpresence where cp_id = %d",
                intval($r[0]['cp_id'])
            );
        }

        return true;
    }


    public static function roomlist($uid)
    {
        require_once('include/security.php');
        $sql_extra = permissions_sql($uid);

        $r = q(
            "select allow_cid, allow_gid, deny_cid, deny_gid, cr_name, cr_expire, cr_id, count(cp_id) as cr_inroom from chatroom left join chatpresence on cr_id = cp_room where cr_uid = %d $sql_extra group by cr_name, cr_id order by cr_name",
            intval($uid)
        );

        return $r;
    }

    public static function list_count($uid)
    {
        require_once('include/security.php');
        $sql_extra = permissions_sql($uid);

        $r = q(
            "select count(*) as total from chatroom where cr_uid = %d $sql_extra",
            intval($uid)
        );

        return $r[0]['total'];
    }

    /**
     * @brief Create a chat message via API.
     *
     * It is the caller's responsibility to enter the room.
     *
     * @param int $uid
     * @param int $room_id
     * @param string $xchan
     * @param string $text
     * @return array
     */
    public static function message($uid, $room_id, $xchan, $text)
    {

        $ret = array('success' => false);

        if (!$text) {
            return $ret;
        }

        $sql_extra = permissions_sql($uid);

        $r = q(
            "select * from chatroom where cr_uid = %d and cr_id = %d $sql_extra",
            intval($uid),
            intval($room_id)
        );
        if (!$r) {
            return $ret;
        }

        $arr = [
            'chat_room' => $room_id,
            'chat_xchan' => $xchan,
            'chat_text' => $text
        ];
        /**
         * @hooks chat_message
         *   Called to create a chat message.
         *   * \e int \b chat_room
         *   * \e string \b chat_xchan
         *   * \e string \b chat_text
         */
        Hook::call('chat_message', $arr);

        $x = q(
            "insert into chat ( chat_room, chat_xchan, created, chat_text )
			values( %d, '%s', '%s', '%s' )",
            intval($room_id),
            dbesc($xchan),
            dbesc(datetime_convert()),
            dbesc(str_rot47(base64url_encode($arr['chat_text'])))
        );

        $ret['success'] = true;
        return $ret;
    }
}
