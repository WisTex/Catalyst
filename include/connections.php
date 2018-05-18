<?php /** @file */


function abook_store_lowlevel($arr) {

	$store = [
		'abook_account'     => ((array_key_exists('abook_account',$arr))     ? $arr['abook_account']     : 0),
		'abook_channel'     => ((array_key_exists('abook_channel',$arr))     ? $arr['abook_channel']     : 0),
		'abook_xchan'       => ((array_key_exists('abook_xchan',$arr))       ? $arr['abook_xchan']       : ''),
		'abook_my_perms'    => ((array_key_exists('abook_my_perms',$arr))    ? $arr['abook_my_perms']    : 0),
		'abook_their_perms' => ((array_key_exists('abook_their_perms',$arr)) ? $arr['abook_their_perms'] : 0),
		'abook_closeness'   => ((array_key_exists('abook_closeness',$arr))   ? $arr['abook_closeness']   : 99),
		'abook_created'     => ((array_key_exists('abook_created',$arr))     ? $arr['abook_created']     : NULL_DATE),
		'abook_updated'     => ((array_key_exists('abook_updated',$arr))     ? $arr['abook_updated']     : NULL_DATE),
		'abook_connected'   => ((array_key_exists('abook_connected',$arr))   ? $arr['abook_connected']   : NULL_DATE),
		'abook_dob'         => ((array_key_exists('abook_dob',$arr))         ? $arr['abook_dob']         : NULL_DATE),
		'abook_flags'       => ((array_key_exists('abook_flags',$arr))       ? $arr['abook_flags']       : 0),
		'abook_blocked'     => ((array_key_exists('abook_blocked',$arr))     ? $arr['abook_blocked']     : 0),
		'abook_ignored'     => ((array_key_exists('abook_ignored',$arr))     ? $arr['abook_ignored']     : 0),
		'abook_hidden'      => ((array_key_exists('abook_hidden',$arr))      ? $arr['abook_hidden']      : 0),
		'abook_archived'    => ((array_key_exists('abook_archived',$arr))    ? $arr['abook_archived']    : 0),
		'abook_pending'     => ((array_key_exists('abook_pending',$arr))     ? $arr['abook_pending']     : 0),
		'abook_unconnected' => ((array_key_exists('abook_unconnected',$arr)) ? $arr['abook_unconnected'] : 0),
		'abook_self'        => ((array_key_exists('abook_self',$arr))        ? $arr['abook_self']        : 0),
		'abook_feed'        => ((array_key_exists('abook_feed',$arr))        ? $arr['abook_feed']        : 0),
		'abook_not_here'    => ((array_key_exists('abook_not_here',$arr))    ? $arr['abook_not_here']    : 0),
		'abook_profile'     => ((array_key_exists('abook_profile',$arr))     ? $arr['abook_profile']     : ''),
		'abook_incl'        => ((array_key_exists('abook_incl',$arr))        ? $arr['abook_incl']        : ''),
		'abook_excl'        => ((array_key_exists('abook_excl',$arr))        ? $arr['abook_excl']        : ''),
		'abook_instance'    => ((array_key_exists('abook_instance',$arr))    ? $arr['abook_instance']    : '')
	];

	return create_table_from_array('abook',$store);

}


function rconnect_url($channel_id,$xchan) {

	if(! $xchan)
		return '';

	$r = q("select abook_id from abook where abook_channel = %d and abook_xchan = '%s' limit 1",
		intval($channel_id),
		dbesc($xchan)
	);

	if($r)
		return '';

	$r = q("select * from xchan where xchan_hash = '%s' limit 1",
		dbesc($xchan)
	);

	if(($r) && ($r[0]['xchan_follow']))
		return $r[0]['xchan_follow'];

	$r = q("select hubloc_url from hubloc where hubloc_hash = '%s' and hubloc_primary = 1 limit 1",
		dbesc($xchan)
	);

	if($r)
		return $r[0]['hubloc_url'] . '/follow?f=&url=%s';
	return '';

}

function abook_connections($channel_id, $sql_conditions = '') {
	$r = q("select * from abook left join xchan on abook_xchan = xchan_hash where abook_channel = %d
		and abook_self = 0 $sql_conditions",
		intval($channel_id)
	);
	return(($r) ? $r : array());
}	

function abook_self($channel_id) {
	$r = q("select * from abook left join xchan on abook_xchan = xchan_hash where abook_channel = %d
		and abook_self = 1 limit 1",
		intval($channel_id)
	);
	return(($r) ? $r[0] : array());
}	


function vcard_from_xchan($xchan, $observer = null, $mode = '') {

	if(! $xchan) {
		if(App::$poi) {
			$xchan = App::$poi;
		}
		elseif(is_array(App::$profile) && App::$profile['channel_hash']) {
			$r = q("select * from xchan where xchan_hash = '%s' limit 1",
				dbesc(App::$profile['channel_hash'])
			);
			if($r)
				$xchan = $r[0];
		}
	}

	if(! $xchan)
		return;

	$connect = false;
	if(local_channel()) {
		$r = q("select * from abook where abook_xchan = '%s' and abook_channel = %d limit 1",
			dbesc($xchan['xchan_hash']),
			intval(local_channel())
		);
		if(! $r)
			$connect = t('Connect');
	}

	// don't provide a connect button for transient or one-way identities

	if(in_array($xchan['xchan_network'],['rss','anon','unknown']) || strpos($xchan['xchan_addr'],'guest:') === 0) {
		$connect = false;
	}

	if(array_key_exists('channel_id',$xchan))
		App::$profile_uid = $xchan['channel_id'];

	$url = (($observer) 
		? z_root() . '/magic?f=&owa=1&dest=' . $xchan['xchan_url'] . '&addr=' . $xchan['xchan_addr'] 
		: $xchan['xchan_url']
	);
					
	return replace_macros(get_markup_template('xchan_vcard.tpl'),array(
		'$name'    => $xchan['xchan_name'],
		'$photo'   => ((is_array(App::$profile) && array_key_exists('photo',App::$profile)) ? App::$profile['photo'] : $xchan['xchan_photo_l']),
		'$follow'  => (($xchan['xchan_addr']) ? $xchan['xchan_addr'] : $xchan['xchan_url']),
		'$link'    => zid($xchan['xchan_url']),
		'$connect' => $connect,
		'$newwin'  => (($mode === 'chanview') ? t('New window') : ''),
		'$newtit'  => t('Open the selected location in a different window or browser tab'),
		'$url'     => $url,
	));
}

function abook_toggle_flag($abook,$flag) {

	$field = '';

	switch($flag) {
		case ABOOK_FLAG_BLOCKED:
			$field = 'abook_blocked';
			break;
		case ABOOK_FLAG_IGNORED:
			$field = 'abook_ignored';
			break;
		case ABOOK_FLAG_HIDDEN:
			$field = 'abook_hidden';
			break;
		case ABOOK_FLAG_ARCHIVED:
			$field = 'abook_archived';
			break;
		case ABOOK_FLAG_PENDING:
			$field = 'abook_pending';
			break;
		case ABOOK_FLAG_UNCONNECTED:
			$field = 'abook_unconnected';
			break;
		case ABOOK_FLAG_SELF:
			$field = 'abook_self';
			break;
		case ABOOK_FLAG_FEED:
			$field = 'abook_feed';
			break;
		default:
			break;
	}
	if(! $field)
		return;

    $r = q("UPDATE abook set $field = (1 - $field) where abook_id = %d and abook_channel = %d",
			intval($abook['abook_id']),
			intval($abook['abook_channel'])
	);


	// if unsetting the archive bit, update the timestamps so we'll try to connect for an additional 30 days. 

	if(($flag === ABOOK_FLAG_ARCHIVED) && (intval($abook['abook_archived']))) {
		$r = q("update abook set abook_connected = '%s', abook_updated = '%s' 
			where abook_id = %d and abook_channel = %d",
			dbesc(datetime_convert()),
			dbesc(datetime_convert()),
			intval($abook['abook_id']),
			intval($abook['abook_channel'])
		);
	}

	return $r;

}



/**
 * mark any hubs "offline" that haven't been heard from in more than 30 days
 * Allow them to redeem themselves if they come back later.
 * Then go through all those that are newly marked and see if any other hubs
 * are attached to the controlling xchan that are still alive.
 * If not, they're dead (although they could come back some day).
 */


function mark_orphan_hubsxchans() {

	$dirmode = intval(get_config('system','directory_mode'));
	if($dirmode == DIRECTORY_MODE_NORMAL)
		return;

	$r = q("update hubloc set hubloc_error = 1 where hubloc_error = 0 
		and hubloc_network = 'zot' and hubloc_connected < %s - interval %s",
		db_utcnow(), db_quoteinterval('36 day')
	);

//	$realm = get_directory_realm();
//	if($realm == DIRECTORY_REALM) {
//		$r = q("select * from site where site_access != 0 and site_register !=0 and ( site_realm = '%s' or site_realm = '') order by rand()",
//			dbesc($realm)
//		);
//	}
//	else {
//		$r = q("select * from site where site_access != 0 and site_register !=0 and site_realm = '%s' order by rand()",
//			dbesc($realm)
//		);
//	}


	$r = q("select hubloc_id, hubloc_hash from hubloc where hubloc_error = 0 and hubloc_orphancheck = 0");

	if($r) {
		foreach($r as $rr) {

			// see if any other hublocs are still alive for this channel

			$x = q("select * from hubloc where hubloc_hash = '%s' and hubloc_error = 0",
				dbesc($rr['hubloc_hash'])
			);
			if($x) {

				// yes - if the xchan was marked as an orphan, undo it

				$y = q("update xchan set xchan_orphan = 0 where xchan_orphan = 1 and xchan_hash = '%s'",
					dbesc($rr['hubloc_hash'])
				);

			}
			else {

				// nope - mark the xchan as an orphan

				$y = q("update xchan set xchan_orphan = 1 where xchan_hash = '%s'",
					dbesc($rr['hubloc_hash'])
				);
			}

			// mark that we've checked this entry so we don't need to do it again

			$y = q("update hubloc set hubloc_orphancheck = 1 where hubloc_id = %d",
				dbesc($rr['hubloc_id'])
			);
		}
	}

}




function remove_all_xchan_resources($xchan, $channel_id = 0) {

	if(intval($channel_id)) {



	}
	else {

		$dirmode = intval(get_config('system','directory_mode'));


		$r = q("delete from photo where xchan = '%s'",
			dbesc($xchan)
		);
		$r = q("select resource_id, resource_type, uid, id from item where ( author_xchan = '%s' or owner_xchan = '%s' ) ",
			dbesc($xchan),
			dbesc($xchan)
		);
		if($r) {
			foreach($r as $rr) {
				drop_item($rr,false);
			}
		}
		$r = q("delete from event where event_xchan = '%s'",
			dbesc($xchan)
		);
		$r = q("delete from group_member where xchan = '%s'",
			dbesc($xchan)
		);
		$r = q("delete from mail where ( from_xchan = '%s' or to_xchan = '%s' )",
			dbesc($xchan),
			dbesc($xchan)
		);
		$r = q("delete from xlink where ( xlink_xchan = '%s' or xlink_link = '%s' )",
			dbesc($xchan),
			dbesc($xchan)
		);

		$r = q("delete from abook where abook_xchan = '%s'",
			dbesc($xchan)
		);


		if($dirmode === false || $dirmode == DIRECTORY_MODE_NORMAL) {

			$r = q("delete from xchan where xchan_hash = '%s'",
				dbesc($xchan)
			);
			$r = q("delete from hubloc where hubloc_hash = '%s'",
				dbesc($xchan)
			);

		}
		else {

			// directory servers need to keep the record around for sync purposes - mark it deleted

			$r = q("update hubloc set hubloc_deleted = 1 where hubloc_hash = '%s'",
				dbesc($xchan)
			);

			$r = q("update xchan set xchan_deleted = 1 where xchan_hash = '%s'",
				dbesc($xchan)
			);
		}
	}
}


function contact_remove($channel_id, $abook_id) {

	if((! $channel_id) || (! $abook_id))
		return false;

	logger('removing contact ' . $abook_id . ' for channel ' . $channel_id,LOGGER_DEBUG);


	$x = [ 'channel_id' => $channel_id, 'abook_id' => $abook_id ];
	call_hooks('connection_remove',$x);


	$archive = get_pconfig($channel_id, 'system','archive_removed_contacts');
	if($archive) {
		q("update abook set abook_archived = 1 where abook_id = %d and abook_channel = %d",
			intval($abook_id),
			intval($channel_id)
		);
		return true;
	}

	$r = q("select * from abook where abook_id = %d and abook_channel = %d limit 1",
		intval($abook_id),
		intval($channel_id)
	);

	if(! $r)
		return false;

	$abook = $r[0];

	if(intval($abook['abook_self']))
		return false;


	$r = q("select id from item where (owner_xchan = '%s' or author_xchan = '%s') and uid = %d",
		dbesc($abook['abook_xchan']),
		dbesc($abook['abook_xchan']),
		intval($channel_id)
	);
	if($r) {
		foreach($r as $rr) {
			drop_item($rr['id'],false);
		}
	}

	
	q("delete from abook where abook_id = %d and abook_channel = %d",
		intval($abook['abook_id']),
		intval($channel_id)
	);

	$r = q("delete from event where event_xchan = '%s' and uid = %d",
		dbesc($abook['abook_xchan']),
		intval($channel_id)
	);

	$r = q("delete from group_member where xchan = '%s' and uid = %d",
		dbesc($abook['abook_xchan']),
		intval($channel_id)
	);

	$r = q("delete from mail where ( from_xchan = '%s' or to_xchan = '%s' ) and channel_id = %d ",
		dbesc($abook['abook_xchan']),
		dbesc($abook['abook_xchan']),
		intval($channel_id)
	);

	$r = q("delete from abconfig where chan = %d and xchan = '%s'",
			intval($channel_id),
			dbesc($abook['abook_xchan'])
	);

	return true;
}



function random_profile() {
	$randfunc = db_getfunc('rand');

	$checkrandom = get_config('randprofile','check'); // False by default
	$retryrandom = intval(get_config('randprofile','retry'));
	if($retryrandom == 0) $retryrandom = 5;

	for($i = 0; $i < $retryrandom; $i++) {

		$r = q("select xchan_url, xchan_hash from xchan left join hubloc on hubloc_hash = xchan_hash where
			xchan_hidden = 0 and xchan_system = 0 and
			xchan_network = 'zot' and xchan_deleted = 0 and
			hubloc_connected > %s - interval %s order by $randfunc limit 1",
			db_utcnow(),
			db_quoteinterval('30 day')
		);

		if(!$r) return ''; // Couldn't get a random channel

		if($checkrandom) {
			$x = z_fetch_url($r[0]['xchan_url']);
			if($x['success'])
				return $r[0]['xchan_hash'];
			else
				logger('Random channel turned out to be bad.');
		}
		else {
			return $r[0]['xchan_hash'];
		}

	}
	return '';
}

function update_vcard($arr,$vcard = null) {


	//	logger('update_vcard: ' . print_r($arr,true));

	$fn = $arr['fn'];

	
	// This isn't strictly correct and could be a cause for concern.
	// 'N' => array_reverse(explode(' ', $fn))


	// What we really want is 
	// 'N' => Adams;John;Quincy;Reverend,Dr.;III
	// which is a very difficult parsing problem especially if you allow
	// the surname to contain spaces. The only way to be sure to get it
	// right is to provide a form to input all the various fields and not 
	// try to extract it from the FN. 

	if(! $vcard) {
		$vcard = new \Sabre\VObject\Component\VCard([
			'FN' => $fn,
			'N' => array_reverse(explode(' ', $fn))
		]);
	}
	else {
		$vcard->FN = $fn;
		$vcard->N = array_reverse(explode(' ', $fn));
	}

	$org = $arr['org'];
	if($org) {
		$vcard->ORG = $org;
	}

	$title = $arr['title'];
	if($title) {
		$vcard->TITLE = $title;
	}

	$tel = $arr['tel'];
	$tel_type = $arr['tel_type'];
	if($tel) {
		$i = 0;
		foreach($tel as $item) {
			if($item) {
				$vcard->add('TEL', $item, ['type' => $tel_type[$i]]);
			}
			$i++;
		}
	}

	$email = $arr['email'];
	$email_type = $arr['email_type'];
	if($email) {
		$i = 0;
		foreach($email as $item) {
			if($item) {
				$vcard->add('EMAIL', $item, ['type' => $email_type[$i]]);
			}
			$i++;
		}
	}

	$impp = $arr['impp'];
	$impp_type = $arr['impp_type'];
	if($impp) {
		$i = 0;
		foreach($impp as $item) {
			if($item) {
				$vcard->add('IMPP', $item, ['type' => $impp_type[$i]]);
			}
			$i++;
		}
	}

	$url = $arr['url'];
	$url_type = $arr['url_type'];
	if($url) {
		$i = 0;
		foreach($url as $item) {
			if($item) {
				$vcard->add('URL', $item, ['type' => $url_type[$i]]);
			}
			$i++;
		}
	}

	$adr = $arr['adr'];
	$adr_type = $arr['adr_type'];

	if($adr) {
		$i = 0;
		foreach($adr as $item) {
			if($item) {
				$vcard->add('ADR', $item, ['type' => $adr_type[$i]]);
			}
			$i++;
		}
	}

	$note = $arr['note'];
	if($note) {
		$vcard->NOTE = $note;
	}

	return $vcard->serialize();

}

function get_vcard_array($vc,$id) {

	$photo = '';
	if($vc->PHOTO) {
		$photo_value = strtolower($vc->PHOTO->getValueType()); // binary or uri
		if($photo_value === 'binary') {
			$photo_type = strtolower($vc->PHOTO['TYPE']); // mime jpeg, png or gif
			$photo = 'data:image/' . $photo_type . ';base64,' . base64_encode((string)$vc->PHOTO);
		}
		else {
			$url = parse_url((string)$vc->PHOTO);
			$photo = 'data:' . $url['path'];
		}
	}

	$fn = '';
	if($vc->FN) {
		$fn = (string) escape_tags($vc->FN);
	}

	$org = '';
	if($vc->ORG) {
		$org = (string) escape_tags($vc->ORG);
	}

	$title = '';
	if($vc->TITLE) {
		$title = (string) escape_tags($vc->TITLE);
	}

	$tels = [];
	if($vc->TEL) {
		foreach($vc->TEL as $tel) {
			$type = (($tel['TYPE']) ? vcard_translate_type((string)$tel['TYPE']) : '');
			$tels[] = [
				'type' => $type,
				'nr' => (string) escape_tags($tel)
			];
		}
	}
	$emails = [];
	if($vc->EMAIL) {
		foreach($vc->EMAIL as $email) {
			$type = (($email['TYPE']) ? vcard_translate_type((string)$email['TYPE']) : '');
			$emails[] = [
				'type' => $type,
				'address' => (string) escape_tags($email)
			];
		}
	}

	$impps = [];
	if($vc->IMPP) {
		foreach($vc->IMPP as $impp) {
			$type = (($impp['TYPE']) ? vcard_translate_type((string)$impp['TYPE']) : '');
			$impps[] = [
				'type' => $type,
				'address' => (string) escape_tags($impp)
			];
		}
	}

	$urls = [];
	if($vc->URL) {
		foreach($vc->URL as $url) {
			$type = (($url['TYPE']) ? vcard_translate_type((string)$url['TYPE']) : '');
			$urls[] = [
				'type' => $type,
				'address' => (string) escape_tags($url)
			];
		}
	}

	$adrs = [];
	if($vc->ADR) {
		foreach($vc->ADR as $adr) {
			$type = (($adr['TYPE']) ? vcard_translate_type((string)$adr['TYPE']) : '');
			$entry = [
				'type' => $type,
				'address' => $adr->getParts()
			];

			if(is_array($entry['address'])) {
				array_walk($entry['address'],'array_escape_tags');
			}
			else { 
				$entry['address'] = (string) escape_tags($entry['address']);
			}

			$adrs[] = $entry;
				
		}
	}

	$note = '';
	if($vc->NOTE) {
		$note = (string) escape_tags($vc->NOTE);
	}

	$card = [
		'id'     => $id,
		'photo'  => $photo,
		'fn'     => $fn,
		'org'    => $org,
		'title'  => $title,
		'tels'   => $tels,
		'emails' => $emails,
		'impps'  => $impps,
		'urls'   => $urls,
		'adrs'   => $adrs,
		'note'   => $note
	];

	return $card;

}


function vcard_translate_type($type) {

	if(!$type)
		return;

	$type = strtoupper($type);

	$map = [
		'CELL' => t('Mobile'),
		'HOME' => t('Home'),
		'HOME,VOICE' => t('Home, Voice'),
		'HOME,FAX' => t('Home, Fax'),
		'WORK' => t('Work'),
		'WORK,VOICE' => t('Work, Voice'),
		'WORK,FAX' => t('Work, Fax'),
		'OTHER' => t('Other')
	];

	if (array_key_exists($type, $map)) {
		return [$type, $map[$type]];
	}
	else {
		return [$type, t('Other') . ' (' . $type . ')'];
	}
}


function vcard_query(&$r) {

	$arr = [];

	if($r && is_array($r) && count($r)) {
		$uid = $r[0]['abook_channel'];
		foreach($r as $rv) {
			if($rv['abook_xchan'] && (! in_array("'" . dbesc($rv['abook_xchan']) . "'",$arr)))
				$arr[] = "'" . dbesc($rv['abook_xchan']) . "'";
		}
	}

	if($arr) {
		$a = q("select * from abconfig where chan = %d and xchan in (" . protect_sprintf(implode(',', $arr)) . ") and cat = 'system' and k = 'vcard'",
			intval($uid)
		);
		if($a) {
			foreach($a as $av) {
				for($x = 0; $x < count($r); $x ++) {
					if($r[$x]['abook_xchan'] == $av['xchan']) {		
						$vctmp = \Sabre\VObject\Reader::read($av['v']);
						$r[$x]['vcard'] = (($vctmp) ? get_vcard_array($vctmp,$r[$x]['abook_id']) : [] );
					}
				}
			}
		}
	}
}
