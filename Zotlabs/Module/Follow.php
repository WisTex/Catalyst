<?php
namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\Libsync;
use Zotlabs\Lib\ActivityStreams;
use Zotlabs\Lib\Activity;
use Zotlabs\Lib\Libzot;
use Zotlabs\Web\HTTPSig;
use Zotlabs\Lib\LDSignatures;
use Zotlabs\Lib\Connect;
use Zotlabs\Daemon\Run;

class Follow extends Controller {

	function init() {
	
	
		if (ActivityStreams::is_as_request() && argc() >= 2) {
			$abook_id = intval(argv(1));
			if(! $abook_id)
				return;

			$r = q("select * from abook left join xchan on abook_xchan = xchan_hash where abook_id = %d",
				intval($abook_id)
			);
			if (! $r) {
				return;
			}

			$chan = channelx_by_n($r[0]['abook_channel']);

			if (! $chan) {
				http_status_exit(404, 'Not found');
			}
			
			$actor = Activity::encode_person($chan,true,true);
			if (! $actor) {
				http_status_exit(404, 'Not found');
			}

			// Pleroma requires a unique follow id for every follow and follow response
			// instead of our method of re-using the abook_id. This causes issues if they unfollow
			// and re-follow so md5 their follow id and slap it on the end so they don't simply discard our
			// subsequent accept/reject actions.
			
			$orig_follow = get_abconfig($chan['channel_id'],$r[0]['xchan_hash'],'activitypub','their_follow_id');

			as_return_and_die([
				'id'     => z_root() . '/follow/' . $r[0]['abook_id'] . (($orig_follow) ? '/' . md5($orig_follow) : EMPTY_STR),                                 
                'type'   => 'Follow',
                'actor'  => $actor,
				'object' => $r[0]['xchan_url']
			], $chan);

    	}



		$uid = local_channel();

		if (! $uid) {
			return;
		}

		$url = notags(trim(punify($_REQUEST['url'])));
		$return_url = $_SESSION['return_url'];
		$confirm = intval($_REQUEST['confirm']);
		$interactive = (($_REQUEST['interactive']) ? intval($_REQUEST['interactive']) : 1);	
		$channel = App::get_channel();

		$result = Connect::connect($channel,$url);
		
		if ($result['success'] == false) {

			if ((strpos($url,'http') === 0) || strpos($url,'bear:') === 0 || strpos($url,'x-zot:') === 0) {
				$n = Activity::fetch($url);
				if ($n) { 
					// set client flag to convert objects to implied activities
					$a = new ActivityStreams($n,null,true);
					if ($a->type === 'Announce' && is_array($a->obj)
						&& array_key_exists('object',$a->obj) && array_key_exists('actor',$a->obj)) {
						// This is a relayed/forwarded Activity (as opposed to a shared/boosted object)
						// Reparse the encapsulated Activity and use that instead
						logger('relayed activity',LOGGER_DEBUG);
						$a = new ActivityStreams($a->obj,null,true);
					}

					if ($a->is_valid()) {

						if (is_array($a->actor) && array_key_exists('id',$a->actor)) {
							Activity::actor_store($a->actor['id'],$a->actor);
						}

						// ActivityPub sourced items are cacheable
						$item = Activity::decode_note($a,true);
	
						if ($item) {
							Activity::store($channel,get_observer_hash(),$a,$item,false);
						}
					}
				}
			}
			
			$r = q("select * from item where mid = '%s' and uid = %d",
				dbesc($url),
				intval($uid)
			);
			if ($r) {
				if ($interactive) {
					goaway(z_root() . '/display/' . gen_link_id($url));
				}
				else {
					$result['success'] = true;
					json_return_and_die($result);
				}
			}



			if ($result['message']) {
				notice($result['message']);
			}
			if ($interactive) {
				goaway($return_url);
			}
			else {
				json_return_and_die($result);
			}
		}
	
		info( t('Connection added.') . EOL);
	
		$clone = array();
		foreach ($result['abook'] as $k => $v) {
			if (strpos($k,'abook_') === 0) {
				$clone[$k] = $v;
			}
		}
		unset($clone['abook_id']);
		unset($clone['abook_account']);
		unset($clone['abook_channel']);
	
		$abconfig = load_abconfig($channel['channel_id'],$clone['abook_xchan']);
		if ($abconfig) {
			$clone['abconfig'] = $abconfig;
		}
		Libsync::build_sync_packet(0, [ 'abook' => [ $clone ] ], true);
	
		$can_view_stream = their_perms_contains($channel['channel_id'],$clone['abook_xchan'],'view_stream');
	
		// If we can view their stream, pull in some posts
	
		if (($can_view_stream) || ($result['abook']['xchan_network'] === 'rss')) {
			Run::Summon([ 'Onepoll', $result['abook']['abook_id'] ]);
		}
	
		if ($interactive) {
			goaway(z_root() . '/connedit/' . $result['abook']['abook_id'] . '?follow=1');
		}
		else {
			json_return_and_die([ 'success' => true ]);
		}
	
	}
	
	function get() {
		if (! local_channel()) {
			return login();
		}
	}
}
