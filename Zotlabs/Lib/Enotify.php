<?php

namespace Zotlabs\Lib;

/**
 * @brief File with functions and a class for generating system and email notifications.
 */


class Enotify {

	/**
	 * @brief
	 *
	 * @param array $params an assoziative array with:
	 *  * \e string \b from_xchan sender xchan hash
	 *  * \e string \b to_xchan recipient xchan hash
	 *  * \e array \b item an assoziative array
	 *  * \e int \b type one of the NOTIFY_* constants from boot.php
	 *  * \e string \b link
	 *  * \e string \b parent_mid
	 *  * \e string \b otype
	 *  * \e string \b verb
	 *  * \e string \b activity
	 */


	static public function submit($params) {

		logger('notification: entry', LOGGER_DEBUG);

		// throw a small amount of entropy into the system to breakup duplicates arriving at the same precise instant.
		usleep(mt_rand(0, 10000));

		if ($params['from_xchan']) {
			$x = q("select * from xchan where xchan_hash = '%s' limit 1",
				dbesc($params['from_xchan'])
			);
		}
		if ($params['to_xchan']) {
			$y = q("select channel.*, account.* from channel left join account on channel_account_id = account_id
				where channel_hash = '%s' and channel_removed = 0 limit 1",
				dbesc($params['to_xchan'])
			);
		}
		if ($x & $y) {
			$sender = $x[0];
			$recip = $y[0];
		} else {
			logger('notification: no sender or recipient.');
			logger('sender: ' . $params['from_xchan']);
			logger('recip: ' . $params['to_xchan']);
			return;
		}

		// from here on everything is in the recipients language

		push_lang($recip['account_language']); // should probably have a channel language

		$banner     = t('$Projectname Notification');
		$product    = t('$projectname'); // PLATFORM_NAME;
		$siteurl    = z_root();
		$thanks     = t('Thank You,');
		$sitename   = get_config('system','sitename');
		$site_admin = sprintf( t('%s Administrator'), $sitename);
		$opt_out1   = sprintf( t('This email was sent by %1$s at %2$s.'), t('$Projectname'), \App::get_hostname());
		$opt_out2   = sprintf( t('To stop receiving these messages, please adjust your Notification Settings at %s'), z_root() . '/settings');		
		$hopt_out2  = sprintf( t('To stop receiving these messages, please adjust your %s.'), '<a href="' . z_root() . '/settings' . '">' . t('Notification Settings')  . '</a>');
		$sender_name = $product;
		$hostname = \App::get_hostname();
		if(strpos($hostname,':'))
			$hostname = substr($hostname,0,strpos($hostname,':'));

		// Do not translate 'noreply' as it must be a legal 7-bit email address

		$reply_email = get_config('system','reply_address');
		if(! $reply_email)
			$reply_email = 'noreply' . '@' . $hostname;

		$sender_email = get_config('system','from_email');
		if(! $sender_email)
			$sender_email = 'Administrator' . '@' . $hostname;
	
		$sender_name = get_config('system','from_email_name');
		if(! $sender_name)
			$sender_name = \Zotlabs\Lib\System::get_site_name();


		$additional_mail_header = "";

		if(array_key_exists('item', $params)) {
			require_once('include/conversation.php');
			// if it's a normal item...
			if (array_key_exists('verb', $params['item'])) {
				// localize_item() alters the original item so make a copy first
				$i = $params['item'];
				logger('calling localize');
				localize_item($i);
				$title = $i['title'];
				$body = $i['body'];
				$private = (($i['item_private']) || intval($i['item_obscured']));
			}
			else {
				$title = $params['item']['title'];
				$body = $params['item']['body'];
			}
			if($params['item']['created'] < datetime_convert('UTC','UTC','now - 1 month')) {
				logger('notification invoked for an old item which may have been refetched.',LOGGER_DEBUG,LOG_INFO);
				return;
			}
		} 
		else {
			$title = $body = '';
		}


	$always_show_in_notices = get_pconfig($recip['channel_id'],'system','always_show_in_notices');
	$vnotify = get_pconfig($recip['channel_id'],'system','vnotify');

	$salutation = $recip['channel_name'];

	// e.g. "your post", "David's photo", etc.
	$possess_desc = t('%s <!item_type!>');

	if ($params['type'] == NOTIFY_MAIL) {
		logger('notification: mail');
		$subject = 	sprintf( t('[$Projectname:Notify] New mail received at %s'),$sitename);

		$preamble = sprintf( t('%1$s sent you a new private message at %2$s.'), $sender['xchan_name'],$sitename);
		$epreamble = sprintf( t('%1$s sent you %2$s.'),'[zrl=' . $sender['xchan_url'] . ']' . $sender['xchan_name'] . '[/zrl]', '[zrl=$itemlink]' . t('a private message') . '[/zrl]');
		$sitelink = t('Please visit %s to view and/or reply to your private messages.');
		$tsitelink = sprintf( $sitelink, $siteurl . '/mail/' . $params['item']['id'] );
		$hsitelink = sprintf( $sitelink, '<a href="' . $siteurl . '/mail/' . $params['item']['id'] . '">' . $sitename . '</a>');
		$itemlink = $siteurl . '/mail/' . $params['item']['id'];
	}

	if ($params['type'] == NOTIFY_COMMENT) {
		//logger("notification: params = " . print_r($params, true), LOGGER_DEBUG);

		$moderated = (($params['item']['item_blocked'] == ITEM_MODERATED) ? true : false);

		$itemlink = $params['link'];

		$action = t('commented on');

		if(array_key_exists('item',$params) && in_array($params['item']['verb'], [ACTIVITY_LIKE, ACTIVITY_DISLIKE])) {

			if(! $always_show_in_notices || !($vnotify & VNOTIFY_LIKE)) {
				logger('notification: not a visible activity. Ignoring.');
				pop_lang();
				return;
			}

			if(activity_match($params['verb'], ACTIVITY_LIKE))
				$action = t('liked');

			if(activity_match($params['verb'], ACTIVITY_DISLIKE))
				$action = t('disliked');

		}

		$parent_mid = $params['parent_mid'];

		// Check to see if there was already a notify for this post.
		// If so don't create a second notification

		$p = null;
		$p = q("select id from notify where link = '%s' and uid = %d limit 1",
			dbesc($params['link']),
			intval($recip['channel_id'])
		);
		if ($p) {
			logger('notification: comment already notified');
			pop_lang();
			return;
		}
	

		// if it's a post figure out who's post it is.

		$p = null;

		if($params['otype'] === 'item' && $parent_mid) {
			$p = q("select * from item where mid = '%s' and uid = %d limit 1",
				dbesc($parent_mid),
				intval($recip['channel_id'])
			);
		}

		xchan_query($p);

		$item_post_type = item_post_type($p[0]);
//		$private = $p[0]['item_private'];
		$parent_id = $p[0]['id'];

		$parent_item = $p[0];

		//$possess_desc = str_replace('<!item_type!>',$possess_desc);

		// "a post"
		$dest_str = sprintf(t('%1$s %2$s [zrl=%3$s]a %4$s[/zrl]'),
			'[zrl=' . $sender['xchan_url'] . ']' . $sender['xchan_name'] . '[/zrl]',
			$action,
			$itemlink,
			$item_post_type);

		// "George Bull's post"
		if($p)
			$dest_str = sprintf(t('%1$s %2$s [zrl=%3$s]%4$s\'s %5$s[/zrl]'),
				'[zrl=' . $sender['xchan_url'] . ']' . $sender['xchan_name'] . '[/zrl]',
				$action,
				$itemlink,
				$p[0]['author']['xchan_name'],
				$item_post_type);
		
		// "your post"
		if($p[0]['owner']['xchan_name'] == $p[0]['author']['xchan_name'] && intval($p[0]['item_wall']))
			$dest_str = sprintf(t('%1$s %2$s [zrl=%3$s]your %4$s[/zrl]'),
				'[zrl=' . $sender['xchan_url'] . ']' . $sender['xchan_name'] . '[/zrl]',
				$action,
				$itemlink,
				$item_post_type);

		// Some mail softwares relies on subject field for threading.
		// So, we cannot have different subjects for notifications of the same thread.
		// Before this we have the name of the replier on the subject rendering 
		// differents subjects for messages on the same thread.

		if($moderated)
			$subject = sprintf( t('[$Projectname:Notify] Moderated Comment to conversation #%1$d by %2$s'), $parent_id, $sender['xchan_name']);
		else
			$subject = sprintf( t('[$Projectname:Notify] Comment to conversation #%1$d by %2$s'), $parent_id, $sender['xchan_name']);
		$preamble = sprintf( t('%1$s commented on an item/conversation you have been following.'), $sender['xchan_name']); 
		$epreamble = $dest_str; 

		$sitelink = t('Please visit %s to view and/or reply to the conversation.');
		$tsitelink = sprintf( $sitelink, $siteurl );
		$hsitelink = sprintf( $sitelink, '<a href="' . $siteurl . '">' . $sitename . '</a>');
		if($moderated) {
			$tsitelink .= "\n\n" . sprintf( t('Please visit %s to approve or reject this comment.'), z_root() . '/moderate' );
			$hsitelink .= "<br><br>" . sprintf( t('Please visit %s to approve or reject this comment.'), '<a href="' . z_root() . '/moderate">' . z_root() . '/moderate</a>' );
		}
		
	}

	if ($params['type'] == NOTIFY_LIKE) {
//		logger("notification: params = " . print_r($params, true), LOGGER_DEBUG);

		$itemlink =  $params['link'];

		if (array_key_exists('item',$params) && (! activity_match($params['item']['verb'],ACTIVITY_LIKE))) {
			if(! $always_show_in_notices  || !($vnotify & VNOTIFY_LIKE)) {
				logger('notification: not a visible activity. Ignoring.');
				pop_lang();
				return;
			}
		}

		$parent_mid = $params['parent_mid'];

		// Check to see if there was already a notify for this post.
		// If so don't create a second notification

		$p = null;
		$p = q("select id from notify where link = '%s' and uid = %d limit 1",
			dbesc($params['link']),
			intval($recip['channel_id'])
		);
		if ($p) {
			logger('notification: like already notified');
			pop_lang();
			return;
		}
	

		// if it's a post figure out who's post it is.

		$p = null;

		if($params['otype'] === 'item' && $parent_mid) {
			$p = q("select * from item where mid = '%s' and uid = %d limit 1",
				dbesc($parent_mid),
				intval($recip['channel_id'])
			);
		}

		xchan_query($p);


		$item_post_type = item_post_type($p[0]);
//		$private = $p[0]['item_private'];
		$parent_id = $p[0]['id'];

		$parent_item = $p[0];


		// "your post"
		if($p[0]['owner']['xchan_name'] == $p[0]['author']['xchan_name'] && intval($p[0]['item_wall']))
			$dest_str = sprintf(t('%1$s liked [zrl=%2$s]your %3$s[/zrl]'),
				'[zrl=' . $sender['xchan_url'] . ']' . $sender['xchan_name'] . '[/zrl]',
				$itemlink,
				$item_post_type);
		else {
			pop_lang();
			return;
		}

		// Some mail softwares relies on subject field for threading.
		// So, we cannot have different subjects for notifications of the same thread.
		// Before this we have the name of the replier on the subject rendering 
		// differents subjects for messages on the same thread.

		$subject = sprintf( t('[$Projectname:Notify] Like received to conversation #%1$d by %2$s'), $parent_id, $sender['xchan_name']);
		$preamble = sprintf( t('%1$s liked an item/conversation you created.'), $sender['xchan_name']); 
		$epreamble = $dest_str; 

		$sitelink = t('Please visit %s to view and/or reply to the conversation.');
		$tsitelink = sprintf( $sitelink, $siteurl );
		$hsitelink = sprintf( $sitelink, '<a href="' . $siteurl . '">' . $sitename . '</a>');
	}



	if($params['type'] == NOTIFY_WALL) {
		$subject = sprintf( t('[$Projectname:Notify] %s posted to your profile wall') , $sender['xchan_name']);

		$preamble = sprintf( t('%1$s posted to your profile wall at %2$s') , $sender['xchan_name'], $sitename);

		$epreamble = sprintf( t('%1$s posted to [zrl=%2$s]your wall[/zrl]') ,
			'[zrl=' . $sender['xchan_url'] . ']' . $sender['xchan_name'] . '[/zrl]',
			$params['link']); 

		$sitelink = t('Please visit %s to view and/or reply to the conversation.');
		$tsitelink = sprintf( $sitelink, $siteurl );
		$hsitelink = sprintf( $sitelink, '<a href="' . $siteurl . '">' . $sitename . '</a>');
		$itemlink =  $params['link'];
	}

	if ($params['type'] == NOTIFY_TAGSELF) {

		$p = null;
		$p = q("select id from notify where link = '%s' and uid = %d limit 1",
			dbesc($params['link']),
			intval($recip['channel_id'])
		);
		if ($p) {
			logger('enotify: tag: already notified about this post');
			pop_lang();
			return;
		}
	
		$subject =	sprintf( t('[$Projectname:Notify] %s tagged you') , $sender['xchan_name']);
		$preamble = sprintf( t('%1$s tagged you at %2$s') , $sender['xchan_name'], $sitename);
		$epreamble = sprintf( t('%1$s [zrl=%2$s]tagged you[/zrl].') ,
			'[zrl=' . $sender['xchan_url'] . ']' . $sender['xchan_name'] . '[/zrl]',
			$params['link']); 

		$sitelink = t('Please visit %s to view and/or reply to the conversation.');
		$tsitelink = sprintf( $sitelink, $siteurl );
		$hsitelink = sprintf( $sitelink, '<a href="' . $siteurl . '">' . $sitename . '</a>');
		$itemlink =  $params['link'];
	}

	if ($params['type'] == NOTIFY_POKE) {
		$subject =	sprintf( t('[$Projectname:Notify] %1$s poked you') , $sender['xchan_name']);
		$preamble = sprintf( t('%1$s poked you at %2$s') , $sender['xchan_name'], $sitename);
		$epreamble = sprintf( t('%1$s [zrl=%2$s]poked you[/zrl].') ,
			'[zrl=' . $sender['xchan_url'] . ']' . $sender['xchan_name'] . '[/zrl]',
			$params['link']); 

		$subject = str_replace('poked', t($params['activity']), $subject);
		$preamble = str_replace('poked', t($params['activity']), $preamble);
		$epreamble = str_replace('poked', t($params['activity']), $epreamble);

		$sitelink = t('Please visit %s to view and/or reply to the conversation.');
		$tsitelink = sprintf( $sitelink, $siteurl );
		$hsitelink = sprintf( $sitelink, '<a href="' . $siteurl . '">' . $sitename . '</a>');
		$itemlink =  $params['link'];
	}

	if ($params['type'] == NOTIFY_TAGSHARE) {
		$subject =	sprintf( t('[$Projectname:Notify] %s tagged your post') , $sender['xchan_name']);
		$preamble = sprintf( t('%1$s tagged your post at %2$s'),$sender['xchan_name'], $sitename);
		$epreamble = sprintf( t('%1$s tagged [zrl=%2$s]your post[/zrl]') ,
			'[zrl=' . $sender['xchan_url'] . ']' . $sender['xchan_name'] . '[/zrl]',
			$itemlink); 

		$sitelink = t('Please visit %s to view and/or reply to the conversation.');
		$tsitelink = sprintf( $sitelink, $siteurl );
		$hsitelink = sprintf( $sitelink, '<a href="' . $siteurl . '">' . $sitename . '</a>');
		$itemlink =  $params['link'];
	}

	if ($params['type'] == NOTIFY_INTRO) {
		$subject = sprintf( t('[$Projectname:Notify] Introduction received'));
		$preamble = sprintf( t('You\'ve received an new connection request from \'%1$s\' at %2$s'), $sender['xchan_name'], $sitename); 
		$epreamble = sprintf( t('You\'ve received [zrl=%1$s]a new connection request[/zrl] from %2$s.'),
			$siteurl . '/connections/ifpending',
			'[zrl=' . $sender['xchan_url'] . ']' . $sender['xchan_name'] . '[/zrl]'); 
		$body = sprintf( t('You may visit their profile at %s'),$sender['xchan_url']);

		$sitelink = t('Please visit %s to approve or reject the connection request.');
		$tsitelink = sprintf( $sitelink, $siteurl . '/connections/ifpending');
		$hsitelink = sprintf( $sitelink, '<a href="' . $siteurl . '/connections/ifpending">' . $sitename . '</a>');
		$itemlink = $params['link'];
	}

	if ($params['type'] == NOTIFY_SUGGEST) {
		$subject = sprintf( t('[$Projectname:Notify] Friend suggestion received'));
		$preamble = sprintf( t('You\'ve received a friend suggestion from \'%1$s\' at %2$s'), $sender['xchan_name'], $sitename); 
		$epreamble = sprintf( t('You\'ve received [zrl=%1$s]a friend suggestion[/zrl] for %2$s from %3$s.'),
			$itemlink,
			'[zrl=' . $params['item']['url'] . ']' . $params['item']['name'] . '[/zrl]',
			'[zrl=' . $sender['xchan_url'] . ']' . $sender['xchan_name'] . '[/zrl]'); 

		$body = t('Name:') . ' ' . $params['item']['name'] . "\n";
		$body .= t('Photo:') . ' ' . $params['item']['photo'] . "\n";
		$body .= sprintf( t('You may visit their profile at %s'),$params['item']['url']);

		$sitelink = t('Please visit %s to approve or reject the suggestion.');
		$tsitelink = sprintf( $sitelink, $siteurl );
		$hsitelink = sprintf( $sitelink, '<a href="' . $siteurl . '">' . $sitename . '</a>');
		$itemlink =  $params['link'];
	}

	if ($params['type'] == NOTIFY_CONFIRM) {
		// ?
	}

	if ($params['type'] == NOTIFY_SYSTEM) {
		// ?
	}

	$h = array(
		'params'    => $params,
		'subject'   => $subject,
		'preamble'  => $preamble,
		'epreamble' => $epreamble,
		'body'      => $body,
		'sitelink'  => $sitelink,
		'sitename'  => $sitename,
		'tsitelink' => $tsitelink,
		'hsitelink' => $hsitelink,
		'itemlink'  => $itemlink,
		'sender'    => $sender,
		'recipient' => $recip
	);

	call_hooks('enotify', $h);

	$subject   = $h['subject'];
	$preamble  = $h['preamble'];
	$epreamble = $h['epreamble'];
	$body      = $h['body'];
	$sitelink  = $h['sitelink'];
	$tsitelink = $h['tsitelink'];
	$hsitelink = $h['hsitelink'];
	$itemlink  = $h['itemlink']; 


	require_once('include/html2bbcode.php');

	do {
		$dups = false;
		$hash = random_string();
		$r = q("SELECT id FROM notify WHERE hash = '%s' LIMIT 1",
			dbesc($hash));
		if ($r)
			$dups = true;
	} while ($dups === true);


	$datarray = array();
	$datarray['hash']   = $hash;
	$datarray['sender_hash'] = $sender['xchan_hash'];
	$datarray['xname']   = $sender['xchan_name'];
	$datarray['url']    = $sender['xchan_url'];
	$datarray['photo']  = $sender['xchan_photo_s'];
	$datarray['created']   = datetime_convert();
	$datarray['aid']    = $recip['channel_account_id'];
	$datarray['uid']    = $recip['channel_id'];
	$datarray['link']   = $itemlink;
	$datarray['parent'] = $parent_mid;
	$datarray['parent_item'] = $parent_item;
	$datarray['ntype']   = $params['type'];
	$datarray['verb']   = $params['verb'];
	$datarray['otype']  = $params['otype'];
 	$datarray['abort']  = false;

	$datarray['item'] = $params['item'];

	call_hooks('enotify_store', $datarray);

	if ($datarray['abort']) {
		pop_lang();
		return;
	}


	// create notification entry in DB
	$seen = 0;

	// Mark some notifications as seen right away
	// Note! The notification have to be created, because they are used to send emails
	// So easiest solution to hide them from Notices is to mark them as seen right away.
	// Another option would be to not add them to the DB, and change how emails are handled 
	// (probably would be better that way)

	if (!$always_show_in_notices) {
		if (($params['type'] == NOTIFY_WALL) || ($params['type'] == NOTIFY_MAIL) || ($params['type'] == NOTIFY_INTRO)) {
			$seen = 1;
		}
	}

	$r = q("insert into notify (hash,xname,url,photo,created,msg,aid,uid,link,parent,seen,ntype,verb,otype)
		values('%s','%s','%s','%s','%s','%s',%d,%d,'%s','%s',%d,%d,'%s','%s')",
		dbesc($datarray['hash']),
		dbesc($datarray['xname']),
		dbesc($datarray['url']),
		dbesc($datarray['photo']),
		dbesc($datarray['created']),
		dbesc(''),      // will fill this in below after the record is created
		intval($datarray['aid']),
		intval($datarray['uid']),
		dbesc($datarray['link']),
		dbesc($datarray['parent']),
		intval($seen),
		intval($datarray['ntype']),
		dbesc($datarray['verb']),
		dbesc($datarray['otype'])
	);

	$r = q("select id from notify where hash = '%s' and uid = %d limit 1",
		dbesc($hash),
		intval($recip['channel_id'])
	);
	if ($r) {
		$notify_id = $r[0]['id'];
	} else {
		logger('notification not found.');
		pop_lang();
		return;
	}

	$itemlink = z_root() . '/notify/view/' . $notify_id;
	$msg = str_replace('$itemlink',$itemlink,$epreamble);

	// wretched hack, but we don't want to duplicate all the preamble variations and we also don't want to screw up a translation

	if ((\App::$language === 'en' || (! \App::$language)) && strpos($msg,', '))
		$msg = substr($msg,strpos($msg,', ')+1);	

	$r = q("update notify set msg = '%s' where id = %d and uid = %d",
		dbesc($msg),
		intval($notify_id),
		intval($datarray['uid'])
	);

	// send email notification if notification preferences permit

	require_once('bbcode.php');
	if ((intval($recip['channel_notifyflags']) & intval($params['type'])) || $params['type'] == NOTIFY_SYSTEM) {

		logger('notification: sending notification email');

		$hn = get_pconfig($recip['channel_id'],'system','email_notify_host');
		if($hn && (! stristr(\App::get_hostname(),$hn))) {
			// this isn't the email notification host
			pop_lang();
			return;
		}

		$textversion = strip_tags(html_entity_decode(bbcode(stripslashes(str_replace(array("\\r", "\\n"), array( "", "\n"), $body))),ENT_QUOTES,'UTF-8'));

		$htmlversion = bbcode(stripslashes(str_replace(array("\\r","\\n"), array("","<br />\n"),$body)));


		// use $_SESSION['zid_override'] to force zid() to use 
		// the recipient address instead of the current observer

		$_SESSION['zid_override'] = channel_reddress($recip);
		$_SESSION['zrl_override'] = z_root() . '/channel/' . $recip['channel_address'];
		
		$textversion = zidify_links($textversion);
		$htmlversion = zidify_links($htmlversion);

		// unset when done to revert to normal behaviour

		unset($_SESSION['zid_override']);
		unset($_SESSION['zrl_override']);

		$datarray = array();
		$datarray['banner']       = $banner;
		$datarray['product']      = $product;
		$datarray['preamble']     = $preamble;
		$datarray['sitename']     = $sitename;
		$datarray['siteurl']      = $siteurl;
		$datarray['type']         = $params['type'];
		$datarray['parent']       = $params['parent_mid'];
		$datarray['source_name']  = $sender['xchan_name'];
		$datarray['source_link']  = $sender['xchan_url'];
		$datarray['source_photo'] = $sender['xchan_photo_s'];
		$datarray['uid']          = $recip['channel_id'];
		$datarray['username']     = $recip['channel_name'];
		$datarray['hsitelink']    = $hsitelink;
		$datarray['tsitelink']    = $tsitelink;
		$datarray['hitemlink']    = '<a href="' . $itemlink . '">' . $itemlink . '</a>';
		$datarray['titemlink']    = $itemlink;
		$datarray['thanks']       = $thanks;
		$datarray['site_admin']   = $site_admin;
		$datarray['opt_out1']     = $opt_out1;
		$datarray['opt_out2']     = $opt_out2;
		$datarray['hopt_out2']    = $hopt_out2;
		$datarray['title']        = stripslashes($title);
		$datarray['htmlversion']  = $htmlversion;
		$datarray['textversion']  = $textversion;
		$datarray['subject']      = $subject;
		$datarray['headers']      = $additional_mail_header;
		$datarray['email_secure'] = false;

		call_hooks('enotify_mail', $datarray);

		// Default to private - don't disclose message contents over insecure channels (such as email)
		// Might be interesting to use GPG,PGP,S/MIME encryption instead
		// but we'll save that for a clever plugin developer to implement

		$private_activity = false;

		if (! $datarray['email_secure']) {
			switch ($params['type']) {
				case NOTIFY_WALL:
				case NOTIFY_TAGSELF:
				case NOTIFY_POKE:
				case NOTIFY_COMMENT:
					if (! $private)
						break;
					$private_activity = true;
				case NOTIFY_MAIL:
					$datarray['textversion'] = $datarray['htmlversion'] = $datarray['title'] = '';
					$datarray['subject'] = preg_replace('/' . preg_quote(t('[$Projectname:Notify]')) . '/','$0*',$datarray['subject']);
					break;
				default:
					break;
			}
		}

		if ($private_activity
			&& intval(get_pconfig($datarray['uid'], 'system', 'ignore_private_notifications'))) {

			pop_lang();
			return;
		}

		// load the template for private message notifications
		$tpl = get_markup_template('email_notify_html.tpl');
		$email_html_body = replace_macros($tpl,array(
			'$banner'       => $datarray['banner'],
			'$notify_icon'  => \Zotlabs\Lib\System::get_notify_icon(),
			'$product'      => $datarray['product'],
			'$preamble'     => $salutation . '<br><br>' . $datarray['preamble'],
			'$sitename'     => $datarray['sitename'],
			'$siteurl'      => $datarray['siteurl'],
			'$source_name'  => $datarray['source_name'],
			'$source_link'  => $datarray['source_link'],
			'$source_photo' => $datarray['source_photo'],
			'$username'     => $datarray['to_name'],
			'$hsitelink'    => $datarray['hsitelink'],
			'$hitemlink'    => $datarray['hitemlink'],
			'$thanks'       => $datarray['thanks'],
			'$site_admin'   => $datarray['site_admin'],
			'$opt_out1'     => $datarray['opt_out1'],
			'$opt_out2'     => $datarray['hopt_out2'],
			'$title'        => $datarray['title'],
			'$htmlversion'  => $datarray['htmlversion'],
		));

		// load the template for private message notifications
		$tpl = get_markup_template('email_notify_text.tpl');
		$email_text_body = replace_macros($tpl, array(
			'$banner'       => $datarray['banner'],
			'$product'      => $datarray['product'],
			'$preamble'     => $salutation . "\n\n" . $datarray['preamble'],
			'$sitename'     => $datarray['sitename'],
			'$siteurl'      => $datarray['siteurl'],
			'$source_name'  => $datarray['source_name'],
			'$source_link'  => $datarray['source_link'],
			'$source_photo' => $datarray['source_photo'],
			'$username'     => $datarray['to_name'],
			'$tsitelink'    => $datarray['tsitelink'],
			'$titemlink'    => $datarray['titemlink'],
			'$thanks'       => $datarray['thanks'],
			'$site_admin'   => $datarray['site_admin'],
			'$opt_out1'     => $datarray['opt_out1'],
			'$opt_out2'     => $datarray['opt_out2'],
			'$title'        => $datarray['title'],
			'$textversion'  => $datarray['textversion'],
		));

//		logger('text: ' . $email_text_body);

		// use the EmailNotification library to send the message

		self::send(array(
			'fromName'             => $sender_name,
			'fromEmail'            => $sender_email,
			'replyTo'              => $reply_email,
			'toEmail'              => $recip['account_email'],
			'messageSubject'       => $datarray['subject'],
			'htmlVersion'          => $email_html_body,
			'textVersion'          => $email_text_body,
			'additionalMailHeader' => $datarray['headers'],
		));
	}

	pop_lang();

}


	/**
	 * @brief Send a multipart/alternative message with Text and HTML versions.
	 *
	 * @param array $params an assoziative array with:
	 *  * \e string \b fromName        name of the sender
	 *  * \e string \b fromEmail       email of the sender
	 *  * \e string \b replyTo         replyTo address to direct responses
	 *  * \e string \b toEmail         destination email address
	 *  * \e string \b messageSubject  subject of the message
	 *  * \e string \b htmlVersion     html version of the message
	 *  * \e string \b textVersion     text only version of the message
	 *  * \e string \b additionalMailHeader  additions to the smtp mail header
	 */
	static public function send($params) {

		$params['sent']   = false;
		$params['result'] = false;

		call_hooks('email_send', $params);

		if($params['sent']) {
			logger("notification: enotify::send (addon) returns " . (($params['result']) ? 'success' : 'failure'), LOGGER_DEBUG);
			return $params['result'];
		}

		$fromName = email_header_encode(html_entity_decode($params['fromName'],ENT_QUOTES,'UTF-8'),'UTF-8'); 
		$messageSubject = email_header_encode(html_entity_decode($params['messageSubject'],ENT_QUOTES,'UTF-8'),'UTF-8');

		// generate a mime boundary
		$mimeBoundary = rand(0, 9) . "-"
				.rand(100000000, 999999999) . "-"
				.rand(100000000, 999999999) . "=:"
				.rand(10000, 99999);

		// generate a multipart/alternative message header
		$messageHeader =
			$params['additionalMailHeader'] .
			"From: $fromName <{$params['fromEmail']}>\n" .
			"Reply-To: $fromName <{$params['replyTo']}>\n" .
			"MIME-Version: 1.0\n" .
			"Content-Type: multipart/alternative; boundary=\"{$mimeBoundary}\"";

		// assemble the final multipart message body with the text and html types included
		$textBody = chunk_split(base64_encode($params['textVersion']));
		$htmlBody = chunk_split(base64_encode($params['htmlVersion']));

		$multipartMessageBody =
			"--" . $mimeBoundary . "\n" .					// plain text section
			"Content-Type: text/plain; charset=UTF-8\n" .
			"Content-Transfer-Encoding: base64\n\n" .
			$textBody . "\n" .
			"--" . $mimeBoundary . "\n" .					// text/html section
			"Content-Type: text/html; charset=UTF-8\n" .
			"Content-Transfer-Encoding: base64\n\n" .
			$htmlBody . "\n" .
			"--" . $mimeBoundary . "--\n";					// message ending

		// send the message
		$res = mail(
			$params['toEmail'],								// send to address
			$messageSubject,								// subject
			$multipartMessageBody,							// message body
			$messageHeader									// message headers
		);
		logger("notification: enotify::send returns " . (($res) ? 'success' : 'failure'), LOGGER_DEBUG);
		return $res;
	}

	static public function format($item) {

		$ret = '';

		require_once('include/conversation.php');

		// Call localize_item to get a one line status for activities. 
		// This should set $item['localized'] to indicate we have a brief summary.
		// and perhaps $item['shortlocalized'] for an even briefer summary

		localize_item($item);

		if($item['shortlocalize']) {
			$itemem_text = $item['shortlocalize'];
		}
		elseif($item['localize']) {
			$itemem_text = $item['localize'];
		}
		else {
			$itemem_text = (($item['item_thread_top'])
				? t('created a new post')
				: sprintf( t('commented on %s\'s post'), $item['owner']['xchan_name']));
		}

		$edit = false;

		if($item['edited'] > $item['created']) {
			if($item['item_thread_top']) {
				$itemem_text = sprintf( t('edited a post dated %s'), relative_date($item['created']));
				$edit = true;
			}
			else {
				$itemem_text = sprintf( t('edited a comment dated %s'), relative_date($item['created']));
				$edit = true;
			}
		}


		// convert this logic into a json array just like the system notifications

		return array(
			'notify_link' => $item['llink'],
			'name' => $item['author']['xchan_name'],
			'url' => $item['author']['xchan_url'],
			'photo' => $item['author']['xchan_photo_s'],
			'when' => relative_date(($edit)? $item['edited'] : $item['created']), 
			'class' => (intval($item['item_unseen']) ? 'notify-unseen' : 'notify-seen'),
			'b64mid' => ((in_array($item['verb'], [ACTIVITY_LIKE, ACTIVITY_DISLIKE])) ? 'b64.' . base64url_encode($item['thr_parent']) : 'b64.' . base64url_encode($item['mid'])),
			'notify_id' => 'undefined',
			'thread_top' => (($item['item_thread_top']) ? true : false),
			'message' => strip_tags(bbcode($itemem_text))
		);

	}

}
