<?php /** @file */

namespace Zotlabs\Lib;

use Zotlabs\Lib\Libzot;
use Zotlabs\Web\HTTPSig;

class Queue {

	static function update($id, $add_priority = 0) {

		logger('queue: requeue item ' . $id,LOGGER_DEBUG);
		$x = q("select outq_created, outq_posturl from outq where outq_hash = '%s' limit 1",
			dbesc($id)
		);
		if(! $x)
			return;


		$y = q("select min(outq_created) as earliest from outq where outq_posturl = '%s'",
			dbesc($x[0]['outq_posturl'])
		);

		// look for the oldest queue entry with this destination URL. If it's older than a couple of days,
		// the destination is considered to be down and only scheduled once an hour, regardless of the
		// age of the current queue item.

		$might_be_down = false;

		if($y)
			$might_be_down = ((datetime_convert('UTC','UTC',$y[0]['earliest']) < datetime_convert('UTC','UTC','now - 2 days')) ? true : false);


		// Set all other records for this destination way into the future. 
		// The queue delivers by destination. We'll keep one queue item for
		// this destination (this one) with a shorter delivery. If we succeed
		// once, we'll try to deliver everything for that destination.
		// The delivery will be set to at most once per hour, and if the 
		// queue item is less than 12 hours old, we'll schedule for fifteen
		// minutes. 

		$r = q("UPDATE outq SET outq_scheduled = '%s' WHERE outq_posturl = '%s'",
			dbesc(datetime_convert('UTC','UTC','now + 5 days')),
			dbesc($x[0]['outq_posturl'])
		);
 
		$since = datetime_convert('UTC','UTC',$x[0]['outq_created']);

		if(($might_be_down) || ($since < datetime_convert('UTC','UTC','now - 12 hour'))) {
			$next = datetime_convert('UTC','UTC','now + 1 hour');
		}
		else {
			$next = datetime_convert('UTC','UTC','now + ' . intval($add_priority) . ' minutes');
		}

		q("UPDATE outq SET outq_updated = '%s', 
			outq_priority = outq_priority + %d, 
			outq_scheduled = '%s' 
			WHERE outq_hash = '%s'",

			dbesc(datetime_convert()),
			intval($add_priority),
			dbesc($next),
			dbesc($id)
		);
	}


	static function remove($id,$channel_id = 0) {
		logger('queue: remove queue item ' . $id,LOGGER_DEBUG);
		$sql_extra = (($channel_id) ? " and outq_channel = " . intval($channel_id) . " " : '');
		
		q("DELETE FROM outq WHERE outq_hash = '%s' $sql_extra",
			dbesc($id)
		);
	}


	static function remove_by_posturl($posturl) {
		logger('queue: remove queue posturl ' . $posturl,LOGGER_DEBUG);
		
		q("DELETE FROM outq WHERE outq_posturl = '%s' ",
			dbesc($posturl)
		);
	}



	static function set_delivered($id,$channel = 0) {
		logger('queue: set delivered ' . $id,LOGGER_DEBUG);
		$sql_extra = (($channel_id) ? " and outq_channel = " . intval($channel_id) . " " : '');

		// Set the next scheduled run date so far in the future that it will be expired
		// long before it ever makes it back into the delivery chain. 

		q("update outq set outq_delivered = 1, outq_updated = '%s', outq_scheduled = '%s' where outq_hash = '%s' $sql_extra ",
			dbesc(datetime_convert()),
			dbesc(datetime_convert('UTC','UTC','now + 5 days')),
			dbesc($id)
		);
	}



	static function insert($arr) {

		// do not queue anything with no destination

		if(! (array_key_exists('posturl',$arr) && trim($arr['posturl']))) {
			return false;
		}

		$x = q("insert into outq ( outq_hash, outq_account, outq_channel, outq_driver, outq_posturl, outq_async, outq_priority,
			outq_created, outq_updated, outq_scheduled, outq_notify, outq_msg ) 
			values ( '%s', %d, %d, '%s', '%s', %d, %d, '%s', '%s', '%s', '%s', '%s' )",
			dbesc($arr['hash']),
			intval($arr['account_id']),
			intval($arr['channel_id']),
			dbesc(($arr['driver']) ? $arr['driver'] : 'zot6'),
			dbesc($arr['posturl']),
			intval(1),
			intval(($arr['priority']) ? $arr['priority'] : 0),
			dbesc(datetime_convert()),
			dbesc(datetime_convert()),
			dbesc(datetime_convert()),
			dbesc($arr['notify']),
			dbesc(($arr['msg']) ? $arr['msg'] : '')
		);
		return $x;

	}



	static function deliver($outq, $immediate = false) {

		$base = null;
		$h = parse_url($outq['outq_posturl']);
		if($h !== false) 
			$base = $h['scheme'] . '://' . $h['host'] . (($h['port']) ? ':' . $h['port'] : '');

		if(($base) && ($base !== z_root()) && ($immediate)) {
			$y = q("select site_update, site_dead from site where site_url = '%s' ",
				dbesc($base)
			);
			if($y) {
				if(intval($y[0]['site_dead'])) {
					self::remove_by_posturl($outq['outq_posturl']);
					logger('dead site ignored ' . $base);
					return;
				}
				if($y[0]['site_update'] < datetime_convert('UTC','UTC','now - 1 month')) {
					self::update($outq['outq_hash'],10);
					logger('immediate delivery deferred for site ' . $base);
					return;
				}
			}
			else {

				// zot sites should all have a site record, unless they've been dead for as long as
				// your site has existed. Since we don't know for sure what these sites are,
				// call them unknown

				site_store_lowlevel( 
					[
						'site_url'    => $base,
						'site_update' => datetime_convert(),
						'site_dead'   => 0,
						'site_type'   => intval(($outq['outq_driver'] === 'post') ? SITE_TYPE_NOTZOT : SITE_TYPE_UNKNOWN),
						'site_crypto' => ''
					]
				);
			}
		}

		$arr = array('outq' => $outq, 'base' => $base, 'handled' => false, 'immediate' => $immediate);
		call_hooks('queue_deliver',$arr);
		if($arr['handled'])
			return;

		// "post" queue driver - used for diaspora and friendica-over-diaspora communications.

		if($outq['outq_driver'] === 'post') {
			$result = z_post_url($outq['outq_posturl'],$outq['outq_msg']);
			if($result['success'] && $result['return_code'] < 300) {
				logger('deliver: queue post success to ' . $outq['outq_posturl'], LOGGER_DEBUG);
				if($base) {
					q("update site set site_update = '%s', site_dead = 0 where site_url = '%s' ",
						dbesc(datetime_convert()),
						dbesc($base)
					);
				}
				q("update dreport set dreport_result = '%s', dreport_time = '%s' where dreport_queue = '%s'",
					dbesc('accepted for delivery'),
					dbesc(datetime_convert()),
					dbesc($outq['outq_hash'])
				);
				self::remove($outq['outq_hash']);

				// server is responding - see if anything else is going to this destination and is piled up 
				// and try to send some more. We're relying on the fact that do_delivery() results in an 
				// immediate delivery otherwise we could get into a queue loop. 

				if(! $immediate) {
					$x = q("select outq_hash from outq where outq_posturl = '%s' and outq_delivered = 0",
						dbesc($outq['outq_posturl'])
					);
	
					$piled_up = array();
					if($x) {
						foreach($x as $xx) {
							 $piled_up[] = $xx['outq_hash'];
						}
					}
					if($piled_up) {
						// call do_delivery() with the force flag
						do_delivery($piled_up, true);
					}
				}
			}
			else {
				logger('deliver: queue post returned ' . $result['return_code'] 
					. ' from ' . $outq['outq_posturl'],LOGGER_DEBUG);
					self::update($outq['outq_hash'],10);
			}
			return;
		}


		if($outq['outq_driver'] === 'activitypub') {

			$channel = channelx_by_n($outq['outq_channel']);

			$retries = 0;

			$headers = [];
			$headers['Content-Type'] = 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"' ;
			$ret = $outq['outq_msg'];
			logger('ActivityPub send: ' . jindent($ret), LOGGER_DATA);
			$headers['Digest'] = HTTPSig::generate_digest_header($ret);
			$headers['(request-target)'] = 'post ' . get_request_string($outq['outq_posturl']);

			$xhead = HTTPSig::create_sig($headers,$channel['channel_prvkey'],channel_url($channel));
			if(strpos($outq['outq_posturl'],'http') !== 0) {
				logger('bad url: ' . $outq['outq_posturl']);
				self::remove($outq['outq_hash']);
			}

			$result = z_post_url($outq['outq_posturl'],$outq['outq_msg'],$retries,[ 'headers' => $xhead ]);

			if($result['success'] && $result['return_code'] < 300) {
				logger('deliver: queue post success to ' . $outq['outq_posturl'], LOGGER_DEBUG);
				if($base) {
					q("update site set site_update = '%s', site_dead = 0 where site_url = '%s' ",
						dbesc(datetime_convert()),
						dbesc($base)
					);
				}
				q("update dreport set dreport_result = '%s', dreport_time = '%s' where dreport_queue = '%s'",
					dbesc('accepted for delivery'),
					dbesc(datetime_convert()),
					dbesc($outq['outq_hash'])
				);
				self::remove($outq['outq_hash']);

				// server is responding - see if anything else is going to this destination and is piled up 
				// and try to send some more. We're relying on the fact that do_delivery() results in an 
				// immediate delivery otherwise we could get into a queue loop. 

				if(! $immediate) {
					$x = q("select outq_hash from outq where outq_posturl = '%s' and outq_delivered = 0",
						dbesc($outq['outq_posturl'])
					);

					$piled_up = array();
					if($x) {
						foreach($x as $xx) {
							 $piled_up[] = $xx['outq_hash'];
						}
					}
					if($piled_up) {
						do_delivery($piled_up,true);
					}
				}
			}
			else {
				logger('deliver: queue post returned ' . $result['return_code'] 
					. ' from ' . $outq['outq_posturl'],LOGGER_DEBUG);
					self::update($outq['outq_hash'],10);
			}
			return;
		}

		// normal zot delivery

		logger('deliver: dest: ' . $outq['outq_posturl'], LOGGER_DEBUG);


		if($outq['outq_posturl'] === z_root() . '/zot') {
			// local delivery
			$zot = new \Zotlabs\Zot6\Receiver(new \Zotlabs\Zot6\Zot6Handler(),$outq['outq_notify']);
			$result = $zot->run(true);
			logger('returned_json: ' . json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), LOGGER_DATA);
			logger('deliver: local zot delivery succeeded to ' . $outq['outq_posturl']);
			Libzot::process_response($outq['outq_posturl'],[ 'success' => true, 'body' => json_encode($result) ], $outq);

			if(! $immediate) {
				$x = q("select outq_hash from outq where outq_posturl = '%s' and outq_delivered = 0",
					dbesc($outq['outq_posturl'])
				);

				$piled_up = array();
				if($x) {
					foreach($x as $xx) {
						 $piled_up[] = $xx['outq_hash'];
					}
				}
				if($piled_up) {
					do_delivery($piled_up,true);
				}
			}
		}
		else {
			logger('remote');
			$channel = null;

			if($outq['outq_channel']) {
				$channel = channelx_by_n($outq['outq_channel']);
			}

			$host_crypto = null;

			if($channel && $base) {
				$h = q("select hubloc_sitekey, site_crypto from hubloc left join site on hubloc_url = site_url where site_url = '%s' and hubloc_network = 'zot6' order by hubloc_id desc limit 1",
					dbesc($base)
				);
				if($h) {
					$host_crypto = $h[0];
				}
			}

			$msg = $outq['outq_notify'];

			$result = Libzot::zot($outq['outq_posturl'],$msg,$channel,$host_crypto);

			if($result['success']) {
				logger('deliver: remote zot delivery succeeded to ' . $outq['outq_posturl']);
				Libzot::process_response($outq['outq_posturl'],$result, $outq);
			}
			else {
				logger('deliver: remote zot delivery failed to ' . $outq['outq_posturl']);
				logger('deliver: remote zot delivery fail data: ' . print_r($result,true), LOGGER_DATA);
				self::update($outq['outq_hash'],10);
			}
		}
		return;
	}
}

