<?php
namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\Libsync;
use Zotlabs\Lib\AccessList;


class Lists extends Controller {

	function init() {
		if (! local_channel()) {
			notice( t('Permission denied.') . EOL);
			return;
		}

		App::$profile_uid = local_channel();
		nav_set_selected('Access Lists');
	}

	function post() {
	
		if (! local_channel()) {
			notice( t('Permission denied.') . EOL);
			return;
		}
	
		if ((argc() == 2) && (argv(1) === 'new')) {
			check_form_security_token_redirectOnErr('/lists/new', 'group_edit');
			
			$name = notags(trim($_POST['groupname']));
			$public = intval($_POST['public']);
			$r = AccessList::add(local_channel(),$name,$public);
			if ($r) {
				info( t('Access list created.') . EOL );
			}
			else {
				notice( t('Could not create access list.') . EOL );
			}
			goaway(z_root() . '/lists');
	
		}
		if ((argc() == 2) && (intval(argv(1)))) {
			check_form_security_token_redirectOnErr('/lists', 'group_edit');
			
			$r = q("SELECT * FROM pgrp WHERE id = %d AND uid = %d LIMIT 1",
				intval(argv(1)),
				intval(local_channel())
			);
			if (! $r) {
				notice( t('Access list not found.') . EOL );
				goaway(z_root() . '/connections');
	
			}
			$group = array_shift($r);
			$groupname = notags(trim($_POST['groupname']));
			$public = intval($_POST['public']);
	
			if ((strlen($groupname))  && (($groupname != $group['gname']) || ($public != $group['visible']))) {
				$r = q("UPDATE pgrp SET gname = '%s', visible = %d  WHERE uid = %d AND id = %d",
					dbesc($groupname),
					intval($public),
					intval(local_channel()),
					intval($group['id'])
				);
				if ($r) {
					info( t('Access list updated.') . EOL );
				}
				Libsync::build_sync_packet(local_channel(),null,true);
			}
	
			goaway(z_root() . '/lists/' . argv(1) . '/' . argv(2));
		}
		return;	
	}
	
	function get() {

		$change = false;
	
		logger('mod_lists: ' . \App::$cmd,LOGGER_DEBUG);
		
		if (! local_channel()) {
			notice( t('Permission denied') . EOL);
			return;
		}

		// Switch to text mode interface if we have more than 'n' contacts or group members
		$switchtotext = get_pconfig(local_channel(),'system','groupedit_image_limit');
		if ($switchtotext === false) {
			$switchtotext = get_config('system','groupedit_image_limit');
		}
		if ($switchtotext === false) {
			$switchtotext = 400;
		}


		if ((argc() == 1) || ((argc() == 2) && (argv(1) === 'new'))) {

			$new = (((argc() == 2) && (argv(1) === 'new')) ? true : false);

			$groups = q("SELECT id, gname FROM pgrp WHERE deleted = 0 AND uid = %d ORDER BY gname ASC",
				intval(local_channel())
			);

			$i = 0;
			foreach ($groups as $group) {
				$entries[$i]['name'] = $group['gname'];
				$entries[$i]['id'] = $group['id'];
				$entries[$i]['count'] = count(AccessList::members($group['id']));
				$i++;
			}

			$tpl = get_markup_template('privacy_groups.tpl');
			$o = replace_macros($tpl, [
				'$title' => t('Access Lists'),
				'$add_new_label' => t('Create access list'),
				'$new' => $new,

				// new group form
				'$gname' => array('groupname',t('Access list name')),
				'$public' => array('public',t('Members are visible to other channels'), false),
				'$form_security_token' => get_form_security_token("group_edit"),
				'$submit' => t('Submit'),

				// groups list
				'$title' => t('Access Lists'),
				'$name_label' => t('Name'),
				'$count_label' => t('Members'),
				'$entries' => $entries
			]);

			return $o;

		}




		$context = array('$submit' => t('Submit'));
		$tpl = get_markup_template('group_edit.tpl');
	
		if((argc() == 3) && (argv(1) === 'drop')) {
			check_form_security_token_redirectOnErr('/lists', 'group_drop', 't');
			
			if(intval(argv(2))) {
				$r = q("SELECT gname FROM pgrp WHERE id = %d AND uid = %d LIMIT 1",
					intval(argv(2)),
					intval(local_channel())
				);
				if($r) 
					$result = AccessList::remove(local_channel(),$r[0]['gname']);
				if($result)
					info( t('Access list removed.') . EOL);
				else
					notice( t('Unable to remove access list.') . EOL);
			}
			goaway(z_root() . '/lists');
			// NOTREACHED
		}
	
	
		if((argc() > 2) && intval(argv(1)) && argv(2)) {
	
			check_form_security_token_ForbiddenOnErr('group_member_change', 't');
	
			$r = q("SELECT abook_xchan from abook left join xchan on abook_xchan = xchan_hash where abook_xchan = '%s' and abook_channel = %d and xchan_deleted = 0 and abook_self = 0 and abook_blocked = 0 and abook_pending = 0 limit 1",
				dbesc(base64url_decode(argv(2))),
				intval(local_channel())
			);
			if(count($r))
				$change = base64url_decode(argv(2));
	
		}
	
		if((argc() > 1) && (intval(argv(1)))) {
	
			require_once('include/acl_selectors.php');
			$r = q("SELECT * FROM pgrp WHERE id = %d AND uid = %d AND deleted = 0 LIMIT 1",
				intval(argv(1)),
				intval(local_channel())
			);
			if(! $r) {
				notice( t('Access list not found.') . EOL );
				goaway(z_root() . '/connections');
			}
			$group = $r[0];
	
	
			$members = AccessList::members($group['id']);
	
			$preselected = array();
			if(count($members))	{
				foreach($members as $member)
					if(! in_array($member['xchan_hash'],$preselected))
						$preselected[] = $member['xchan_hash'];
			}
	
			if($change) {
	
				if(in_array($change,$preselected)) {
					AccessList::member_remove(local_channel(),$group['gname'],$change);
				}
				else {
					AccessList::member_add(local_channel(),$group['gname'],$change);
				}
	
				$members = AccessList::members($group['id']);
	
				$preselected = array();
				if(count($members))	{
					foreach($members as $member)
						$preselected[] = $member['xchan_hash'];
				}
			}

			$context = $context + array(
				'$title' => sprintf(t('Access List: %s'), $group['gname']),
				'$details_label' => t('Edit'),
				'$gname' => array('groupname',t('Access list name: '),$group['gname'], ''),
				'$gid' => $group['id'],
				'$drop' => $drop_txt,
				'$public' => array('public',t('Members are visible to other channels'), $group['visible'], ''),
				'$form_security_token_edit' => get_form_security_token('group_edit'),
				'$delete' => t('Delete access list'),
				'$form_security_token_drop' => get_form_security_token("group_drop"),
			);
	
		}
	
		if(! isset($group))
			return;
	
		$groupeditor = array(
			'label_members' => t('List members'),
			'members' => array(),
			'label_contacts' => t('Not in this list'),
			'contacts' => array(),
		);
			
		$sec_token = addslashes(get_form_security_token('group_member_change'));
		$textmode = (($switchtotext && (count($members) > $switchtotext)) ? true : 'card');
		foreach($members as $member) {
			if($member['xchan_url']) {
				$member['archived'] = (intval($member['abook_archived']) ? true : false);
				$member['click'] = 'groupChangeMember(' . $group['id'] . ',\'' . base64url_encode($member['xchan_hash']) . '\',\'' . $sec_token . '\'); return false;';
				$groupeditor['members'][] = micropro($member,true,'mpgroup', $textmode);
			}
			else
				AccessList::member_remove(local_channel(),$group['gname'],$member['xchan_hash']);
		}
	
		$r = q("SELECT abook.*, xchan.* FROM abook left join xchan on abook_xchan = xchan_hash WHERE abook_channel = %d AND abook_self = 0 and abook_blocked = 0 and abook_pending = 0 and xchan_deleted = 0 order by xchan_name asc",
			intval(local_channel())
		);
	
		if(count($r)) {
			$textmode = (($switchtotext && (count($r) > $switchtotext)) ? true : 'card');
			foreach($r as $member) {
				if(! in_array($member['xchan_hash'],$preselected)) {
					$member['archived'] = (intval($member['abook_archived']) ? true : false);
					$member['click'] = 'groupChangeMember(' . $group['id'] . ',\'' . base64url_encode($member['xchan_hash']) . '\',\'' . $sec_token . '\'); return false;';
					$groupeditor['contacts'][] = micropro($member,true,'mpall', $textmode);
				}
			}
		}
	
		$context['$groupeditor'] = $groupeditor;
		$context['$desc'] = t('Click a channel to toggle membership');
	
		if($change) {
			$tpl = get_markup_template('groupeditor.tpl');
			echo replace_macros($tpl, $context);
			killme();
		}
		
		return replace_macros($tpl, $context);
	
	}
	
	
}
