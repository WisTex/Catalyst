<?php
namespace Zotlabs\Module;


class Search extends \Zotlabs\Web\Controller {

	function init() {
		if(x($_REQUEST,'search'))
			\App::$data['search'] = $_REQUEST['search'];
	}
	
	
	function get($update = 0, $load = false) {
	
		if((get_config('system','block_public')) || (get_config('system','block_public_search'))) {
			if ((! local_channel()) && (! remote_channel())) {
				notice( t('Public access denied.') . EOL);
				return;
			}
		}
	
		if($load)
			$_SESSION['loadtime'] = datetime_convert();
	
		nav_set_selected('Search');
	
		require_once("include/bbcode.php");
		require_once('include/security.php');
		require_once('include/conversation.php');
		require_once('include/items.php');
	
		$format = (($_REQUEST['format']) ? $_REQUEST['format'] : '');
		if($format !== '') {
			$update = $load = 1;
		}
	
		$observer = \App::get_observer();
		$observer_hash = (($observer) ? $observer['xchan_hash'] : '');
	
		$o = '<div id="live-search"></div>' . "\r\n";
	
	        $o = '<div class="generic-content-wrapper-styled">' . "\r\n";
	
		$o .= '<h3>' . t('Search') . '</h3>';
	
		if(x(\App::$data,'search'))
			$search = trim(\App::$data['search']);
		else
			$search = ((x($_GET,'search')) ? trim(rawurldecode($_GET['search'])) : '');
	
		$tag = false;
		if(x($_GET,'tag')) {
			$tag = true;
			$search = ((x($_GET,'tag')) ? trim(rawurldecode($_GET['tag'])) : '');
		}

		$static = ((array_key_exists('static',$_REQUEST)) ? intval($_REQUEST['static']) : 0);
	
		$o .= search($search,'search-box','/search',((local_channel()) ? true : false));
	
		if(strpos($search,'#') === 0) {
			$tag = true;
			$search = substr($search,1);
		}
		if(strpos($search,'@') === 0) {
			$search = substr($search,1);
			goaway(z_root() . '/directory' . '?f=1&navsearch=1&search=' . $search);
		}
		if(strpos($search,'!') === 0) {
			$search = substr($search,1);
			goaway(z_root() . '/directory' . '?f=1&navsearch=1&search=' . $search);
		}
		if(strpos($search,'?') === 0) {
			$search = substr($search,1);
			goaway(z_root() . '/help' . '?f=1&navsearch=1&search=' . $search);
		}
	
		// look for a naked webbie
		if(strpos($search,'@') !== false) {
			goaway(z_root() . '/directory' . '?f=1&navsearch=1&search=' . $search);
		}
	
		if(! $search)
			return $o;
	
		if($tag) {
			$wildtag = str_replace('*','%',$search);
			$sql_extra = sprintf(" AND item.id IN (select oid from term where otype = %d and ttype in ( %d , %d) and term like '%s') ",
				intval(TERM_OBJ_POST),
				intval(TERM_HASHTAG),
				intval(TERM_COMMUNITYTAG),
				dbesc(protect_sprintf($wildtag))
			);
		}
		else {
			$regstr = db_getfunc('REGEXP');
			$sql_extra = sprintf(" AND (item.title $regstr '%s' OR item.body $regstr '%s') ", dbesc(protect_sprintf(preg_quote($search))), dbesc(protect_sprintf(preg_quote($search))));
		}
	
		// Here is the way permissions work in the search module...
		// Only public posts can be shown
		// OR your own posts if you are a logged in member
		// No items will be shown if the member has a blocked profile wall. 
	

		if((! $update) && (! $load)) {
	
			$static  = ((local_channel()) ? channel_manual_conv_update(local_channel()) : 0);


			// This is ugly, but we can't pass the profile_uid through the session to the ajax updater,
			// because browser prefetching might change it on us. We have to deliver it with the page.
	
			$o .= '<div id="live-search"></div>' . "\r\n";
			$o .= "<script> var profile_uid = " . ((intval(local_channel())) ? local_channel() : (-1))
				. "; var netargs = '?f='; var profile_page = " . \App::$pager['page'] . "; </script>\r\n";
	
			\App::$page['htmlhead'] .= replace_macros(get_markup_template("build_query.tpl"),array(
				'$baseurl' => z_root(),
				'$pgtype' => 'search',
				'$uid' => ((\App::$profile['profile_uid']) ? \App::$profile['profile_uid'] : '0'),
				'$gid' => '0',
				'$cid' => '0',
				'$cmin' => '0',
				'$cmax' => '0',
				'$star' => '0',
				'$liked' => '0',
				'$conv' => '0',
				'$spam' => '0',
				'$fh' => '0',
				'$nouveau' => '0',
				'$wall' => '0',
				'$static' => $static,
				'$list' => ((x($_REQUEST,'list')) ? intval($_REQUEST['list']) : 0),
				'$page' => ((\App::$pager['page'] != 1) ? \App::$pager['page'] : 1),
				'$search' => (($tag) ? urlencode('#') : '') . $search,
				'$xchan' => '',
				'$order' => '',
				'$file' => '',
				'$cats' => '',
				'$tags' => '',
				'$mid' => '',
				'$verb' => '',
				'$net' => '',
				'$dend' => '',
				'$dbegin' => ''
			));
	
	
		}
	
		$item_normal = item_normal_search();
		$pub_sql = public_permissions_sql($observer_hash);
	
		require_once('include/channel.php');
	
		$sys = get_sys_channel();
	
		if(($update) && ($load)) {
			$itemspage = get_pconfig(local_channel(),'system','itemspage');
			\App::set_pager_itemspage(((intval($itemspage)) ? $itemspage : 20));
			$pager_sql = sprintf(" LIMIT %d OFFSET %d ", intval(\App::$pager['itemspage']), intval(\App::$pager['start']));
	
			// in case somebody turned off public access to sys channel content with permissions
	
			if(! perm_is_allowed($sys['channel_id'],$observer_hash,'view_stream'))
				$sys['xchan_hash'] .= 'disabled';
	
			if($load) {
				$r = null;
						
				if(local_channel()) {
					$r = q("SELECT mid, MAX(id) as item_id from item
						WHERE ((( item.allow_cid = ''  AND item.allow_gid = '' AND item.deny_cid  = '' AND item.deny_gid  = '' AND item_private = 0 ) 
						OR ( item.uid = %d )) OR item.owner_xchan = '%s' )
						$item_normal
						$sql_extra
						group by mid, created order by created desc $pager_sql ",
						intval(local_channel()),
						dbesc($sys['xchan_hash'])
					);
				}
				if($r === null) {
					$r = q("SELECT mid, MAX(id) as item_id from item
						WHERE (((( item.allow_cid = ''  AND item.allow_gid = '' AND item.deny_cid  = ''
						AND item.deny_gid  = '' AND item_private = 0 )
						and owner_xchan in ( " . stream_perms_xchans(($observer) ? (PERMS_NETWORK|PERMS_PUBLIC) : PERMS_PUBLIC) . " ))
							$pub_sql ) OR owner_xchan = '%s')
						$item_normal
						$sql_extra 
						group by mid, created order by created desc $pager_sql",
						dbesc($sys['xchan_hash'])
					);
				}
				if($r) {
					$str = ids_to_querystr($r,'item_id');
					$r = q("select *, id as item_id from item where id in ( " . $str . ") order by created desc ");
				}
			}
			else {
				$r = array();
			}
		



		}
	
		if($r) {
			xchan_query($r);
			$items = fetch_post_tags($r,true);
		} else {
			$items = array();
		}
	
	
		if($format == 'json') {
			$result = array();
			require_once('include/conversation.php');
			foreach($items as $item) {
				$item['html'] = zidify_links(bbcode($item['body']));
				$x = encode_item($item);
				$x['html'] = prepare_text($item['body'],$item['mimetype']);
				$result[] = $x;
			}
			json_return_and_die(array('success' => true,'messages' => $result));
		}
	
		if($tag) 
			$o .= '<h2>' . sprintf( t('Items tagged with: %s'),htmlspecialchars($search, ENT_COMPAT,'UTF-8')) . '</h2>';
		else
			$o .= '<h2>' . sprintf( t('Search results for: %s'),htmlspecialchars($search, ENT_COMPAT,'UTF-8')) . '</h2>';
	
		$o .= conversation($items,'search',$update,'client');
	
		$o .= '</div>';
	
		return $o;
	}
	
	
}
