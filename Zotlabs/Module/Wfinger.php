<?php
namespace Zotlabs\Module;

require_once('include/zot.php');


class Wfinger extends \Zotlabs\Web\Controller {

	function init() {
	
		// This is a public resource with relaxed CORS policy. Close the current login session.
		session_write_close();

		$result = [];
	
		$scheme = '';
	
		if(x($_SERVER,'HTTPS') && $_SERVER['HTTPS'])
			$scheme = 'https';
		elseif(x($_SERVER,'SERVER_PORT') && (intval($_SERVER['SERVER_PORT']) == 443))
			$scheme = 'https';
	
		if($scheme !== 'https') {
			header($_SERVER["SERVER_PROTOCOL"] . ' ' . 500 . ' ' . 'Webfinger requires HTTPS');
			killme();
		}
	
	
		$resource = $_REQUEST['resource'];

		logger('webfinger: ' . $resource,LOGGER_DEBUG);
	
		$root_resource  = false;

		if(strcasecmp(rtrim($resource,'/'),z_root()) === 0)
			$root_resource = true;

		$channel_target = null;
	
		if(($resource) && (! $root_resource)) {
	
			if(strpos($resource,'acct:') === 0) {
				$channel_nickname = punify(str_replace('acct:','',$resource));
				if(strpos($channel_nickname,'@') !== false) {
					$host = punify(substr($channel_nickname,strpos($channel_nickname,'@')+1));

					// If the webfinger address points off site, redirect to the correct site

					if(strcasecmp($host,\App::get_hostname())) {
						goaway('https://' . $host . '/.well-known/webfinger?f=&resource=' . $resource);
					}
					$channel_nickname = substr($channel_nickname,0,strpos($channel_nickname,'@'));
				}		
			}
			if(strpos($resource,'http') === 0) {
				$channel_nickname = str_replace('~','',basename($resource));
			}
	
			$r = q("select * from channel left join xchan on channel_hash = xchan_hash 
				where channel_address = '%s' limit 1",
				dbesc($channel_nickname)
			);
			if($r) {
				$channel_target = $r[0];
			}
		}


	
		header('Access-Control-Allow-Origin: *');
	

		if($root_resource) {
			$result['subject'] = $resource;
			$result['properties'] = [
					'https://w3id.org/security/v1#publicKeyPem' => get_config('system','pubkey')
			];
			$result['links'] = [
				[
					'rel'  => 'http://purl.org/openwebauth/v1',
					'type' => 'application/x-zot+json',
					'href' => z_root() . '/owa',
				],
			
				[ 
					'rel'  => 'http://purl.org/zot/protocol/6.0', 
					'type' => 'application/x-zot+json', 
					'href' => z_root(),
				],

			];
	
		}

		if($resource && $channel_target) {
	
			$h = q("select hubloc_addr from hubloc where hubloc_hash = '%s' and hubloc_deleted = 0",
				dbesc($channel_target['channel_hash'])
			);
	
			$result['subject'] = $resource;
	
			$aliases = array(
				z_root() . (($pchan) ? '/pchan/' : '/channel/') . $channel_target['channel_address'],
				z_root() . '/~' . $channel_target['channel_address']
				z_root() . '/@' . $channel_target['channel_address']

			);
	
			if($h) {
				foreach($h as $hh) {
					$aliases[] = 'acct:' . $hh['hubloc_addr'];
				}
			}
	
			$result['aliases'] = [];
	
			$result['properties'] = [
					'http://webfinger.net/ns/name'   => $channel_target['channel_name'],
					'http://xmlns.com/foaf/0.1/name' => $channel_target['channel_name'],
					'https://w3id.org/security/v1#publicKeyPem' => $channel_target['xchan_pubkey'],
					'http://purl.org/zot/federation' => 'zot6'
			];
	
			foreach($aliases as $alias) 
				if($alias != $resource)
					$result['aliases'][] = $alias;
	


			$result['links'] = [

				[
					'rel'  => 'http://webfinger.net/rel/avatar',
					'type' => $channel_target['xchan_photo_mimetype'],
					'href' => $channel_target['xchan_photo_l']	
				],

				[
					'rel'  => 'http://webfinger.net/rel/blog',
					'href' => z_root() . '/channel/' . $channel_target['channel_address'],
				],
	
				[ 
					'rel'  => 'http://purl.org/zot/protocol/6.0', 
					'type' => 'application/x-zot+json', 
					'href' => z_root() . '/channel/' . $channel_target['channel_address'],
				],

				[
					'rel'  => 'http://purl.org/openwebauth/v1',
					'type' => 'application/x-zot+json',
					'href' => z_root() . '/owa',
				],
			];
		}

		if(! $result) {
			header($_SERVER["SERVER_PROTOCOL"] . ' ' . 400 . ' ' . 'Bad Request');
			killme();
		}
	
		$arr = [ 'channel' => $channel_target, 'request' => $_REQUEST, 'result' => $result ];
		call_hooks('webfinger',$arr);


		json_return_and_die($arr['result'],'application/jrd+json');
	
	}
	
}
