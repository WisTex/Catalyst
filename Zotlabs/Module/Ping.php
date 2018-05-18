<?php

namespace Zotlabs\Module;

require_once('include/bbcode.php');

/**
 * @brief Ping Controller.
 *
 */
class Ping extends \Zotlabs\Web\Controller {

	/**
	 * @brief do several updates when pinged.
	 *
	 * This function does several tasks. Whenever called it checks for new messages,
	 * introductions, notifications, etc. and returns a json with the results.
	 *
	 * @result JSON
	 */
	function init() {

		$result = array();
		$notifs = array();

		$result['notify'] = 0;
		$result['home'] = 0;
		$result['network'] = 0;
		$result['intros'] = 0;
		$result['mail'] = 0;
		$result['register'] = 0;
		$result['events'] = 0;
		$result['events_today'] = 0;
		$result['birthdays'] = 0;
		$result['birthdays_today'] = 0;
		$result['all_events'] = 0;
		$result['all_events_today'] = 0;
		$result['notice'] = array();
		$result['info'] = array();
		$result['pubs'] = 0;
		$result['files'] = 0;

		if(! $_SESSION['static_loadtime'])
			$_SESSION['static_loadtime'] = datetime_convert();

		$t0 = dba_timer();

		header("content-type: application/json");

		$vnotify = false;

		$item_normal = item_normal();

		if(local_channel()) {
			$vnotify = get_pconfig(local_channel(),'system','vnotify');
			$evdays = intval(get_pconfig(local_channel(),'system','evdays'));
			$ob_hash = get_observer_hash();
		}

		// if unset show all visual notification types
		if($vnotify === false)
			$vnotify = (-1);
		if($evdays < 1)
			$evdays = 3;

		/**
		 * If you have several windows open to this site and switch to a different channel
		 * in one of them, the others may get into a confused state showing you a page or options
		 * on that page which were only valid under the old identity. You session has changed.
		 * Therefore we send a notification of this fact back to the browser where it is picked up
		 * in javascript and which reloads the page it is on so that it is valid under the context
		 * of the now current channel.
		 */

		$result['invalid'] = ((intval($_GET['uid'])) && (intval($_GET['uid']) != local_channel()) ? 1 : 0);

		/**
		 * Send all system messages (alerts) to the browser.
		 * Some are marked as informational and some represent
		 * errors or serious notifications. These typically
		 * will popup on the current page (no matter what page it is)
		 */

		if(x($_SESSION, 'sysmsg')){
			foreach ($_SESSION['sysmsg'] as $m){
				$result['notice'][] = array('message' => $m);
			}
			unset($_SESSION['sysmsg']);
		}
		if(x($_SESSION, 'sysmsg_info')){
			foreach ($_SESSION['sysmsg_info'] as $m){
				$result['info'][] = array('message' => $m);
			}
			unset($_SESSION['sysmsg_info']);
		}
		if(! ($vnotify & VNOTIFY_INFO))
			$result['info'] = array();
		if(! ($vnotify & VNOTIFY_ALERT))
			$result['notice'] = array();

		if(\App::$install) {
			echo json_encode($result);
			killme();
		}

		/**
		 * Update chat presence indication (if applicable)
		 */

		if(get_observer_hash() && (! $result['invalid'])) {
			$r = q("select cp_id, cp_room from chatpresence where cp_xchan = '%s' and cp_client = '%s' and cp_room = 0 limit 1",
				dbesc(get_observer_hash()),
				dbesc($_SERVER['REMOTE_ADDR'])
			);
			$basic_presence = false;
			if($r) {
				$basic_presence = true;
				q("update chatpresence set cp_last = '%s' where cp_id = %d",
					dbesc(datetime_convert()),
					intval($r[0]['cp_id'])
				);
			}
			if(! $basic_presence) {
				q("insert into chatpresence ( cp_xchan, cp_last, cp_status, cp_client)
					values( '%s', '%s', '%s', '%s' ) ",
					dbesc(get_observer_hash()),
					dbesc(datetime_convert()),
					dbesc('online'),
					dbesc($_SERVER['REMOTE_ADDR'])
				);
			}
		}

		/**
		 * Chatpresence continued... if somebody hasn't pinged recently, they've most likely left the page
		 * and shouldn't count as online anymore. We allow an expection for bots.
		 */

		q("delete from chatpresence where cp_last < %s - INTERVAL %s and cp_client != 'auto' ",
			db_utcnow(), db_quoteinterval('3 MINUTE')
		);


		$sql_extra = '';
		if(! ($vnotify & VNOTIFY_LIKE))
			$sql_extra = " AND verb NOT IN ('" . dbesc(ACTIVITY_LIKE) . "', '" . dbesc(ACTIVITY_DISLIKE) . "') ";

		$discover_tab_on = can_view_public_stream();

		$notify_pubs = ((local_channel()) ? ($vnotify & VNOTIFY_PUBS) && $discover_tab_on : $discover_tab_on);

		if($notify_pubs) {
			$sys = get_sys_channel();

			$pubs = q("SELECT count(id) as total from item
				WHERE uid = %d
				AND item_unseen = 1
				AND author_xchan != '%s'
				AND created > '" . datetime_convert('UTC','UTC',$_SESSION['static_loadtime']) . "'
				$item_normal
				$sql_extra",
				intval($sys['channel_id']),
				dbesc(get_observer_hash())
			);

			if($pubs)
				$result['pubs'] = intval($pubs[0]['total']);
		}



		if((argc() > 1) && (argv(1) === 'pubs') && ($notify_pubs)) {
			$sys = get_sys_channel();
			$result = array();

			$r = q("SELECT * FROM item
				WHERE uid = %d
				AND item_unseen = 1
				AND author_xchan != '%s'
				AND created > '" . datetime_convert('UTC','UTC',$_SESSION['static_loadtime']) . "'
				$item_normal
				$sql_extra
				ORDER BY created DESC
				LIMIT 300",
				intval($sys['channel_id']),
				dbesc(get_observer_hash())
			);

			if($r) {
				xchan_query($r);
				foreach($r as $rr) {
					$rr['llink'] = str_replace('display/', 'pubstream/?f=&mid=', $rr['llink']);
					$result[] = \Zotlabs\Lib\Enotify::format($rr);
				}
			}

//			logger('ping (network||home): ' . print_r($result, true), LOGGER_DATA);
			echo json_encode(array('notify' => $result));
			killme();
		}

		$t1 = dba_timer();

		if((! local_channel()) || ($result['invalid'])) {
			echo json_encode($result);
			killme();
		}

		/**
		 * Everything following is only permitted under the context of a locally authenticated site member.
		 */

		/**
		 * Handle "mark all xyz notifications read" requests.
		 */

		// mark all items read
		if(x($_REQUEST, 'markRead') && local_channel()) {
			switch($_REQUEST['markRead']) {
				case 'network':
					$r = q("UPDATE item SET item_unseen = 0 WHERE uid = %d AND item_unseen = 1",
						intval(local_channel())
					);
					break;
				case 'home':
					$r = q("UPDATE item SET item_unseen = 0 WHERE uid = %d AND item_unseen = 1 AND item_wall = 1",
						intval(local_channel())
					);
					break;
				case 'mail':
					$r = q("UPDATE mail SET mail_seen = 1 WHERE channel_id = %d AND mail_seen = 0",
						intval(local_channel())
					);
					break;
				case 'all_events':
					$r = q("UPDATE event SET dismissed = 1 WHERE uid = %d AND dismissed = 0 AND dtstart < '%s' AND dtstart > '%s' ",
						intval(local_channel()),
						dbesc(datetime_convert('UTC', date_default_timezone_get(), 'now + ' . intval($evdays) . ' days')),
						dbesc(datetime_convert('UTC', date_default_timezone_get(), 'now - 1 days'))
					);
					break;
				case 'notify':
					$r = q("update notify set seen = 1 where uid = %d",
						intval(local_channel())
					);
					break;
				case 'pubs':
					unset($_SESSION['static_loadtime']);
					break;
				default:
					break;
			}
		}

		if(x($_REQUEST, 'markItemRead') && local_channel()) {
			$r = q("UPDATE item SET item_unseen = 0 WHERE  uid = %d AND parent = %d",
				intval(local_channel()),
				intval($_REQUEST['markItemRead'])
			);
		}

		/**
		 * URL ping/something will return detail for "something", e.g. a json list with which to populate a notification
		 * dropdown menu.
		 */
		if(argc() > 1 && argv(1) === 'notify') {
			$t = q("SELECT * FROM notify WHERE uid = %d AND seen = 0 ORDER BY CREATED DESC",
				intval(local_channel())
			);

			if($t) {
				foreach($t as $tt) {
					$message = trim(strip_tags(bbcode($tt['msg'])));

					if(strpos($message, $tt['xname']) === 0)
						$message = substr($message, strlen($tt['xname']) + 1);


					$mid = basename($tt['link']);

					if(in_array($tt['verb'], [ACTIVITY_LIKE, ACTIVITY_DISLIKE])) {
						// we need the thread parent
						$r = q("select thr_parent from item where mid = '%s' and uid = %d limit 1",
							dbesc($mid),
							intval(local_channel())
						);

						$b64mid = ((strpos($r[0]['thr_parent'], 'b64.') === 0) ? $r[0]['thr_parent'] : 'b64.' . base64url_encode($r[0]['thr_parent']));
					}
					else {
						$b64mid = ((strpos($mid, 'b64.') === 0) ? $mid : 'b64.' . base64url_encode($mid));
					}

					$notifs[] = array(
						'notify_link' => z_root() . '/notify/view/' . $tt['id'],
						'name' => $tt['xname'],
						'url' => $tt['url'],
						'photo' => $tt['photo'],
						'when' => relative_date($tt['created']),
						'hclass' => (($tt['seen']) ? 'notify-seen' : 'notify-unseen'),
						'b64mid' => (($tt['otype'] == 'item') ? $b64mid : 'undefined'),
						'notify_id' => (($tt['otype'] == 'item') ? $tt['id'] : 'undefined'),
						'message' => $message
					);
				}
			}

			echo json_encode(array('notify' => $notifs));
			killme();
		}

		if(argc() > 1 && argv(1) === 'mail') {
			$channel = \App::get_channel();
			$t = q("select mail.*, xchan.* from mail left join xchan on xchan_hash = from_xchan
				where channel_id = %d and mail_seen = 0 and mail_deleted = 0
				and from_xchan != '%s' order by created desc limit 50",
				intval(local_channel()),
				dbesc($channel['channel_hash'])
			);

			if($t) {
				foreach($t as $zz) {
					$notifs[] = array(
						'notify_link' => z_root() . '/mail/' . $zz['id'],
						'name' => $zz['xchan_name'],
						'url' => $zz['xchan_url'],
						'photo' => $zz['xchan_photo_s'],
						'when' => relative_date($zz['created']),
						'hclass' => (intval($zz['mail_seen']) ? 'notify-seen' : 'notify-unseen'),
						'message' => t('sent you a private message'),
					);
				}
			}

			echo json_encode(array('notify' => $notifs));
			killme();
		}

		if(argc() > 1 && (argv(1) === 'network' || argv(1) === 'home')) {
			$result = array();

			$r = q("SELECT * FROM item 
				WHERE uid = %d
				AND item_unseen = 1
				AND author_xchan != '%s'
				$item_normal
				$sql_extra
				ORDER BY created DESC
				LIMIT 300",
				intval(local_channel()),
				dbesc($ob_hash)
			);

			if($r) {
				xchan_query($r);
				foreach($r as $item) {
					if((argv(1) === 'home') && (! intval($item['item_wall'])))
						continue;
					$result[] = \Zotlabs\Lib\Enotify::format($item);
				}
			}
//			logger('ping (network||home): ' . print_r($result, true), LOGGER_DATA);
			echo json_encode(array('notify' => $result));
			killme();
		}

		if(argc() > 1 && (argv(1) === 'intros')) {
			$result = array();

			$r = q("SELECT * FROM abook left join xchan on abook.abook_xchan = xchan.xchan_hash where abook_channel = %d and abook_pending = 1 and abook_self = 0 and abook_ignored = 0 and xchan_deleted = 0 and xchan_orphan = 0 ORDER BY abook_created DESC LIMIT 50",
				intval(local_channel())
			);

			if($r) {
				foreach($r as $rr) {
					$result[] = array(
						'notify_link' => z_root() . '/connections/ifpending',
						'name' => $rr['xchan_name'],
						'url' => $rr['xchan_url'],
						'photo' => $rr['xchan_photo_s'],
						'when' => relative_date($rr['abook_created']),
						'hclass' => ('notify-unseen'),
						'message' => t('added your channel')
					);
				}
			}
			logger('ping (intros): ' . print_r($result, true), LOGGER_DATA);
			echo json_encode(array('notify' => $result));
			killme();
		}

		if((argc() > 1 && (argv(1) === 'register')) && is_site_admin()) {
			$result = array();

			$r = q("SELECT account_email, account_created from account where (account_flags & %d) > 0",
				intval(ACCOUNT_PENDING)
			);
			if($r) {
				foreach($r as $rr) {
					$result[] = array(
						'notify_link' => z_root() . '/admin/accounts',
						'name' => $rr['account_email'],
						'url' => '',
						'photo' => z_root() . '/' . get_default_profile_photo(48),
						'when' => relative_date($rr['account_created']),
						'hclass' => ('notify-unseen'),
						'message' => t('requires approval')
					);
				}
			}
			logger('ping (register): ' . print_r($result, true), LOGGER_DATA);
			echo json_encode(array('notify' => $result));
			killme();
		}

		if(argc() > 1 && (argv(1) === 'all_events')) {
			$bd_format = t('g A l F d') ; // 8 AM Friday January 18

			$result = array();

			$r = q("SELECT * FROM event left join xchan on event_xchan = xchan_hash
				WHERE event.uid = %d AND dtstart < '%s' AND dtstart > '%s' and dismissed = 0
				and etype in ( 'event', 'birthday' )
				ORDER BY dtstart DESC LIMIT 1000",
				intval(local_channel()),
				dbesc(datetime_convert('UTC', date_default_timezone_get(), 'now + ' . intval($evdays) . ' days')),
				dbesc(datetime_convert('UTC', date_default_timezone_get(), 'now - 1 days'))
			);

			if($r) {
				foreach($r as $rr) {

					$strt = datetime_convert('UTC', (($rr['adjust']) ? date_default_timezone_get() : 'UTC'), $rr['dtstart']);
					$today = ((substr($strt, 0, 10) === datetime_convert('UTC', date_default_timezone_get(), 'now', 'Y-m-d')) ? true : false);
					$when = day_translate(datetime_convert('UTC', (($rr['adjust']) ? date_default_timezone_get() : 'UTC'), $rr['dtstart'], $bd_format)) . (($today) ?  ' ' . t('[today]') : '');

					$result[] = array(
						'notify_link' => z_root() . '/events', /// @FIXME this takes you to an edit page and it may not be yours, we really want to just view the single event  --> '/events/event/' . $rr['event_hash'],
						'name'        => $rr['xchan_name'],
						'url'         => $rr['xchan_url'],
						'photo'       => $rr['xchan_photo_s'],
						'when'        => $when,
						'hclass'       => ('notify-unseen'),
						'message'     => t('posted an event')
					);
				}
			}
			logger('ping (all_events): ' . print_r($result, true), LOGGER_DATA);
			echo json_encode(array('notify' => $result));
			killme();
		}

		if(argc() > 1 && (argv(1) === 'files')) {
			$result = array();

			$r = q("SELECT item.created, xchan.xchan_name, xchan.xchan_url, xchan.xchan_photo_s FROM item 
				LEFT JOIN xchan on author_xchan = xchan_hash
				WHERE item.verb = '%s'
				AND item.obj_type = '%s'
				AND item.uid = %d
				AND item.owner_xchan != '%s'
				AND item.item_unseen = 1",
				dbesc(ACTIVITY_POST),
				dbesc(ACTIVITY_OBJ_FILE),
				intval(local_channel()),
				dbesc($ob_hash)
			);
			if($r) {
				foreach($r as $rr) {
					$result[] = array(
						'notify_link' => z_root() . '/sharedwithme',
						'name' => $rr['xchan_name'],
						'url' => $rr['xchan_url'],
						'photo' => $rr['xchan_photo_s'],
						'when' => relative_date($rr['created']),
						'hclass' => ('notify-unseen'),
						'message' => t('shared a file with you')
					);
				}
			}
			logger('ping (files): ' . print_r($result, true), LOGGER_DATA);
			echo json_encode(array('notify' => $result));
			killme();
		}

		/**
		 * Normal ping - just the counts, no detail
		 */
		if($vnotify & VNOTIFY_SYSTEM) {
			$t = q("select count(*) as total from notify where uid = %d and seen = 0",
				intval(local_channel())
			);
			if($t)
				$result['notify'] = intval($t[0]['total']);
		}

		$t2 = dba_timer();

		if($vnotify & VNOTIFY_FILES) {
			$files = q("SELECT count(id) as total FROM item
				WHERE verb = '%s'
				AND obj_type = '%s'
				AND uid = %d
				AND owner_xchan != '%s'
				AND item_unseen = 1",
				dbesc(ACTIVITY_POST),
				dbesc(ACTIVITY_OBJ_FILE),
				intval(local_channel()),
				dbesc($ob_hash)
			);
			if($files)
				$result['files'] = intval($files[0]['total']);
		}

		$t3 = dba_timer();

		if($vnotify & (VNOTIFY_NETWORK|VNOTIFY_CHANNEL)) {

			$r = q("SELECT id, item_wall FROM item 
				WHERE uid = %d and item_unseen = 1 
				$item_normal
				$sql_extra
				AND author_xchan != '%s'",
				intval(local_channel()),
				dbesc($ob_hash)
			);

			if($r) {
				$arr = array('items' => $r);
				call_hooks('network_ping', $arr);

				foreach ($r as $it) {
					if(intval($it['item_wall']))
						$result['home'] ++;
					else
						$result['network'] ++;
				}
			}
		}
		if(! ($vnotify & VNOTIFY_NETWORK))
			$result['network'] = 0;
		if(! ($vnotify & VNOTIFY_CHANNEL))
			$result['home'] = 0;

		$t4 = dba_timer();

		if($vnotify & VNOTIFY_INTRO) {
			$intr = q("SELECT COUNT(abook.abook_id) AS total FROM abook left join xchan on abook.abook_xchan = xchan.xchan_hash where abook_channel = %d and abook_pending = 1 and abook_self = 0 and abook_ignored = 0 and xchan_deleted = 0 and xchan_orphan = 0 ",
				intval(local_channel())
			);

			$t5 = dba_timer();

			if($intr)
				$result['intros'] = intval($intr[0]['total']);
		}

		$t6 = dba_timer();
		$channel = \App::get_channel();

		if($vnotify & VNOTIFY_MAIL) {
			$mails = q("SELECT count(id) as total from mail
				WHERE channel_id = %d AND mail_seen = 0 and from_xchan != '%s' ",
				intval(local_channel()),
				dbesc($channel['channel_hash'])
			);
			if($mails)
				$result['mail'] = intval($mails[0]['total']);
		}

		if($vnotify & VNOTIFY_REGISTER) {
			if (\App::$config['system']['register_policy'] == REGISTER_APPROVE && is_site_admin()) {
				$regs = q("SELECT count(account_id) as total from account where (account_flags & %d) > 0",
					intval(ACCOUNT_PENDING)
				);
				if($regs)
					$result['register'] = intval($regs[0]['total']);
			}
		}

		$t7 = dba_timer();

		if($vnotify & (VNOTIFY_EVENT|VNOTIFY_EVENTTODAY|VNOTIFY_BIRTHDAY)) {
			$events = q("SELECT etype, dtstart, adjust FROM event
				WHERE event.uid = %d AND dtstart < '%s' AND dtstart > '%s' and dismissed = 0
				and etype in ( 'event', 'birthday' )
				ORDER BY dtstart ASC ",
					intval(local_channel()),
					dbesc(datetime_convert('UTC', date_default_timezone_get(), 'now + ' . intval($evdays) . ' days')),
					dbesc(datetime_convert('UTC', date_default_timezone_get(), 'now - 1 days'))
			);

			if($events) {
				$result['all_events'] = count($events);

				if($result['all_events']) {
					$str_now = datetime_convert('UTC', date_default_timezone_get(), 'now', 'Y-m-d');
					foreach($events as $x) {
						$bd = false;
						if($x['etype'] === 'birthday') {
							$result['birthdays'] ++;
							$bd = true;
						}
						else {
							$result['events'] ++;
						}
						if(datetime_convert('UTC', ((intval($x['adjust'])) ? date_default_timezone_get() : 'UTC'), $x['dtstart'], 'Y-m-d') === $str_now) {
							$result['all_events_today'] ++;
							if($bd)
								$result['birthdays_today'] ++;
							else
								$result['events_today'] ++;
						}
					}
				}
			}
		}
		if(! ($vnotify & VNOTIFY_EVENT))
			$result['all_events'] = $result['events'] = 0;
		if(! ($vnotify & VNOTIFY_EVENTTODAY))
			$result['all_events_today'] = $result['events_today'] = 0;
		if(! ($vnotify & VNOTIFY_BIRTHDAY))
			$result['birthdays'] = 0;

		$x = json_encode($result);

		$t8 = dba_timer();

//		logger('ping timer: ' . sprintf('%01.4f %01.4f %01.4f %01.4f %01.4f %01.4f %01.4f %01.4f',$t8 - $t7, $t7 - $t6, $t6 - $t5, $t5 - $t4, $t4 - $t3, $t3 - $t2, $t2 - $t1, $t1 - $t0));

		echo $x;
		killme();
	}

}
