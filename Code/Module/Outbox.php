<?php

namespace Code\Module;

use App;
use Code\Web\Controller;
use Code\Lib\ActivityStreams;
use Code\Lib\ThreadListener;
use Code\Web\HTTPSig;
use Code\Lib\Activity;
use Code\Lib\ActivityPub;
use Code\Lib\Config;
use Code\Lib\PConfig;
use Code\Lib\Channel;

require_once('include/api_auth.php');
require_once('include/api.php');

/**
 * Implements an ActivityPub outbox.
 */
class Outbox extends Controller
{

    public $item = null;

    public function init() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ! api_user()) {
            api_login();
        }
    }

    public function post()
    {
        if (argc() < 2) {
            killme();
        }

        if (! api_user()) {
            killme();
        }

        $channel = Channel::from_username(argv(1));
        if (!$channel) {
            killme();
        }

        if (intval($channel['channel_system'])) {
            killme();
        }

        $observer = App::get_observer();
        if (!$observer) {
            killme();
        }

        if ($observer['xchan_hash'] !== $channel['channel_hash']) {
            if (!perm_is_allowed($channel['channel_id'], $observer['xchan_hash'], 'post_wall')) {
                logger('outbox post permission denied to ' . $observer['xchan_name']);
                killme();
            }
        }

        $observer_hash = get_observer_hash();

        $data = file_get_contents('php://input');
        if (!$data) {
            return;
        }

        logger('outbox_activity: ' . jindent($data), LOGGER_DATA);

        // the third parameter signals to the parser that we are using C2S and that implied Create activities are supported
        $AS = new ActivityStreams($data, null, true);

        if (!$AS->is_valid()) {
            return;
        }

        if (!PConfig::Get($channel['channel_id'], 'system', 'activitypub', Config::Get('system', 'activitypub', ACTIVITYPUB_ENABLED))) {
            return;
        }

        // ensure the posted activity has required attributes

        $uuid = new_uuid();

        $AS->id = z_root() . '/activity/' . $uuid;

        if (isset($AS->obj) && (! isset($AS->obj['id']))) {
            $AS->obj['id'] = z_root() . '/item/' . $uuid;
        }

        if (! isset($AS->actor)) {
            $AS->actor = Channel::url($channel);
        }

        logger('outbox_channel: ' . $channel['channel_address'], LOGGER_DEBUG);

        switch ($AS->type) {
            case 'Follow':
                if (is_array($AS->obj) && array_key_exists('type', $AS->obj) && ActivityStreams::is_an_actor($AS->obj['type']) && isset($AS->obj['id'])) {
                    // do follow activity
                    Activity::follow($channel,$AS);
                }
                break;
            case 'Invite':
            case 'Join':
                if (is_array($AS->obj) && array_key_exists('type', $AS->obj) && $AS->obj['type'] === 'Group') {
                    // do follow activity
                    Activity::follow($channel,$AS);
                }
                break;
            case 'Accept':
                // Activitypub for wordpress sends lowercase 'follow' on accept.
                // https://github.com/pfefferle/wordpress-activitypub/issues/97
                // Mobilizon sends Accept/"Member" (not in vocabulary) in response to Join/Group
                if (is_array($AS->obj) && array_key_exists('type', $AS->obj) && in_array($AS->obj['type'], ['Follow','follow', 'Member'])) {
                    // do follow activity
                    Activity::follow($channel,$AS);
                }
                break;
            case 'Reject':
            default:
                break;

        }

        if (! $this->APDispatch($channel, $observer_hash, $AS)) {
            if (is_array($AS->obj)) {
                // The boolean flag enables html cache of the item
                $this->item = Activity::decode_note($AS, true);
            }
            else {
                logger('unresolved object: ' . print_r($AS->obj, true));
            }
        }


        if ($this->item) {
            // fixup some of the item fields when using C2S

            if (! (isset($this->item['parent_mid']) && $this->item['parent_mid'])) {
                $this->item['parent_mid'] = $this->item['mid'];
            }
            // map ActivityPub recipients to Nomad ACLs to the extent possible.
            if (isset($AS->recips)) {
                $this->item['item_private'] = ((in_array(ACTIVITY_PUBLIC_INBOX, $AS->recips)
                    || in_array('Public', $AS->recips)
                    || in_array('as:Public', $AS->recips))
                    ? 0
                    : 1
                );

                if ($this->item['item_private']) {
                    foreach ($AS->recips as $recip) {
                        if (strpos($recip,'/lists/')) {
                            $r = q("select * from pgrp where hash = '%s' and uid = %d",
                                dbesc(basename($recip)),
                                intval($channel['channel_id'])
                            );
                            if ($r) {
                                if (! isset($this->item['allow_gid'])) {
                                    $this->item['allow_gid'] = EMPTY_STR;
                                }
                                $this->item['allow_gid'] .= '<' . $r[0]['hash'] . '>';
                            }
                            continue;
                        }
                        if ($recip === z_root() . '/followers/' . $channel['channel_address']) {
                            // map to a virtual list/group even if the app isn't installed. This should do the right
                            // thing and create a followers-only post with the correct ACL as long as the public stream
                            // isn't addressed. And if it is, the post will still go to all your connections - so the ACL isn't
                            // necessary.
                            if (! isset($this->item['allow_gid'])) {
                                $this->item['allow_gid'] = EMPTY_STR;
                            }
                            $this->item['allow_gid'] .= '<connections:' . $channel['channel_hash'] . '>';
                            continue;
                        }
                        $r = q("select * from hubloc where hubloc_id_url = '%s' and hubloc_deleted = 0",
                            dbesc($recip)
                        );
                        if ($r) {
                            if (! isset($this->item['allow_cid'])) {
                                $this->item['allow_cid'] = EMPTY_STR;
                            }
                            $this->item['allow_cid'] .= '<' . $r[0]['hubloc_hash'] . '>';
                        }
                    }
                }
                // set the DM flag if needed
                if ($this->item['item_private'] && isset($this->item['allow_cid']) && ! isset($this->item['allow_gid'])
                    && in_array(substr_count($this->item['allow_cid'],'<'), [ 1, 2 ])) {
                    $this->item['item_private'] = 2;
                }
            }

            $this->item['item_wall'] = 1;

            logger('parsed_item: ' . print_r($this->item, true), LOGGER_DATA);
            Activity::store($channel, $observer_hash, $AS, $this->item);
            header('Location: ' . $this->item['mid']);
            http_status_exit(201, 'Created');
        }
        http_status_exit(200, 'OK');
    }


    public function get()
    {
        if (argc() < 2) {
            killme();
        }

        $channel = Channel::from_username(argv(1));
        if (!$channel) {
            killme();
        }

        if (ActivityStreams::is_as_request()) {
            $sigdata = HTTPSig::verify(($_SERVER['REQUEST_METHOD'] === 'POST') ? file_get_contents('php://input') : EMPTY_STR);
            if ($sigdata['portable_id'] && $sigdata['header_valid']) {
                $portable_id = $sigdata['portable_id'];
                if (!check_channelallowed($portable_id)) {
                    http_status_exit(403, 'Permission denied');
                }
                if (!check_siteallowed($sigdata['signer'])) {
                    http_status_exit(403, 'Permission denied');
                }
                observer_auth($portable_id);
            } elseif (Config::Get('system', 'require_authenticated_fetch', false)) {
                http_status_exit(403, 'Permission denied');
            }
            $observer_hash = get_observer_hash();

            $params = [];

            $params['begin'] = ((x($_REQUEST, 'date_begin')) ? $_REQUEST['date_begin'] : NULL_DATE);
            $params['end'] = ((x($_REQUEST, 'date_end')) ? $_REQUEST['date_end'] : '');
            $params['type'] = 'json';
            $params['pages'] = ((x($_REQUEST, 'pages')) ? intval($_REQUEST['pages']) : 0);
            $params['top'] = ((x($_REQUEST, 'top')) ? intval($_REQUEST['top']) : 0);
            $params['direction'] = ((x($_REQUEST, 'direction')) ? dbesc($_REQUEST['direction']) : 'desc'); // unimplemented
            $params['cat'] = ((x($_REQUEST, 'cat')) ? escape_tags($_REQUEST['cat']) : '');
            $params['compat'] = 1;


            $total = items_fetch(
                [
                    'total' => true,
                    'wall' => '1',
                    'datequery' => $params['end'],
                    'datequery2' => $params['begin'],
                    'direction' => dbesc($params['direction']),
                    'pages' => $params['pages'],
                    'order' => dbesc('post'),
                    'top' => $params['top'],
                    'cat' => $params['cat'],
                    'compat' => $params['compat']
                ],
                $channel,
                $observer_hash,
                CLIENT_MODE_NORMAL,
                App::$module
            );

            if ($total) {
                App::set_pager_total($total);
                App::set_pager_itemspage(100);
            }

            if (App::$pager['unset'] && $total > 100) {
                $ret = Activity::paged_collection_init($total, App::$query_string);
            } else {
                $items = items_fetch(
                    [
                        'wall' => '1',
                        'datequery' => $params['end'],
                        'datequery2' => $params['begin'],
                        'records' => intval(App::$pager['itemspage']),
                        'start' => intval(App::$pager['start']),
                        'direction' => dbesc($params['direction']),
                        'pages' => $params['pages'],
                        'order' => dbesc('post'),
                        'top' => $params['top'],
                        'cat' => $params['cat'],
                        'compat' => $params['compat']
                    ],
                    $channel,
                    $observer_hash,
                    CLIENT_MODE_NORMAL,
                    App::$module
                );

                if ($items && $observer_hash) {
                    // check to see if this observer is a connection. If not, register any items
                    // belonging to this channel for notification of deletion/expiration

                    $x = q(
                        "select abook_id from abook where abook_channel = %d and abook_xchan = '%s'",
                        intval($channel['channel_id']),
                        dbesc($observer_hash)
                    );
                    if (!$x) {
                        foreach ($items as $item) {
                            if (strpos($item['mid'], z_root()) === 0) {
                                ThreadListener::store($item['mid'], $observer_hash);
                            }
                        }
                    }
                }

                $ret = Activity::encode_item_collection($items, App::$query_string, 'OrderedCollection', true, $total);
            }

            as_return_and_die($ret, $channel);
        }
    }

    public function APDispatch($channel, $observer_hash, $AS)
    {
        if ($AS->type === 'Update') {
            if (is_array($AS->obj) && array_key_exists('type', $AS->obj) && ActivityStreams::is_an_actor($AS->obj['type'])) {
                Activity::actor_store($AS->obj['id'], $AS->obj, true /* force cache refresh */);
                return true;
            }
        }
        if ($AS->type === 'Undo') {
            if ($AS->obj && is_array($AS->obj) && array_key_exists('type', $AS->obj) && $AS->obj['type'] === 'Follow') {
                // do unfollow activity
                Activity::unfollow($channel, $AS);
                return true;
            }
        }
        if ($AS->type === 'Accept') {
            if (is_array($AS->obj) && array_key_exists('type', $AS->obj) && (ActivityStreams::is_an_actor($AS->obj['type']) || $AS->obj['type'] === 'Member')) {
                return true;
            }
        }
        if ($AS->type === 'Leave') {
            if ($AS->obj && is_array($AS->obj) && array_key_exists('type', $AS->obj) && $AS->obj['type'] === 'Group') {
                // do unfollow activity
                Activity::unfollow($channel, $AS);
                return true;
            }
        }
        if (in_array($AS->type, ['Tombstone', 'Delete'])) {
            Activity::drop($channel, $observer_hash, $AS);
            return true;
        }

        if ($AS->type === 'Copy') {
            if (
                $observer_hash && $observer_hash === $AS->actor
                && is_array($AS->obj) && array_key_exists('type', $AS->obj) && ActivityStreams::is_an_actor($AS->obj['type'])
                && is_array($AS->tgt) && array_key_exists('type', $AS->tgt) && ActivityStreams::is_an_actor($AS->tgt['type'])
            ) {
                ActivityPub::copy($AS->obj, $AS->tgt);
                return true;
            }
        }
        if ($AS->type === 'Move') {
            if (
                $observer_hash && $observer_hash === $AS->actor
                && is_array($AS->obj) && array_key_exists('type', $AS->obj) && ActivityStreams::is_an_actor($AS->obj['type'])
                && is_array($AS->tgt) && array_key_exists('type', $AS->tgt) && ActivityStreams::is_an_actor($AS->tgt['type'])
            ) {
                ActivityPub::move($AS->obj, $AS->tgt);
                return true;
            }
        }
        if (in_array($AS->type, ['Add', 'Remove'])) {
            // for writeable collections as target, it's best to provide an array and include both the type and the id in the target element.
            // If it's just a string id, we'll try to fetch the collection when we receive it and that's wasteful since we don't actually need
            // the contents.
            if (is_array($AS->obj) && isset($AS->tgt)) {
                // The boolean flag enables html cache of the item
                $this->item = Activity::decode_note($AS, true);
                return true;
            }
        }
        return false;
    }

}
