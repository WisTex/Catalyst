<?php

namespace Zotlabs\Module\Admin;


class Security {

	function post() {
		check_form_security_token_redirectOnErr('/admin/security', 'admin_security');
	
		$allowed_email        = ((x($_POST,'allowed_email'))	    ? notags(trim($_POST['allowed_email']))		: '');
		$not_allowed_email    = ((x($_POST,'not_allowed_email'))	? notags(trim($_POST['not_allowed_email']))		: '');

		set_config('system','allowed_email', $allowed_email);
		set_config('system','not_allowed_email', $not_allowed_email);	

		$block_public         = ((x($_POST,'block_public'))		? True	: False);
		set_config('system','block_public',$block_public);

		$block_public_search  = ((x($_POST,'block_public_search'))		? 1	: 0);
		set_config('system','block_public_search',$block_public_search);

		$localdir_hide         = ((x($_POST,'localdir_hide'))	? 1	: 0);
		set_config('system','localdir_hide',$localdir_hide);

		$cloud_noroot         = ((x($_POST,'cloud_noroot'))		? 1	: 0);
		set_config('system','cloud_disable_siteroot',1 - $cloud_noroot);

		$cloud_disksize       = ((x($_POST,'cloud_disksize'))	? 1	: 0);
		set_config('system','cloud_report_disksize',$cloud_disksize);

		$ws = $this->trim_array_elems(explode("\n",$_POST['whitelisted_sites']));
		set_config('system','whitelisted_sites',$ws);
	
		$bs = $this->trim_array_elems(explode("\n",$_POST['blacklisted_sites']));
		set_config('system','blacklisted_sites',$bs);
	
		$wc = $this->trim_array_elems(explode("\n",$_POST['whitelisted_channels']));
		set_config('system','whitelisted_channels',$wc);
	
		$bc = $this->trim_array_elems(explode("\n",$_POST['blacklisted_channels']));
		set_config('system','blacklisted_channels',$bc);

		$ws = $this->trim_array_elems(explode("\n",$_POST['pubstream_whitelisted_sites']));
		set_config('system','pubstream_whitelisted_sites',$ws);
	
		$bs = $this->trim_array_elems(explode("\n",$_POST['pubstream_blacklisted_sites']));
		set_config('system','pubstream_blacklisted_sites',$bs);
	
		$wc = $this->trim_array_elems(explode("\n",$_POST['pubstream_whitelisted_channels']));
		set_config('system','pubstream_whitelisted_channels',$wc);
	
		$bc = $this->trim_array_elems(explode("\n",$_POST['pubstream_blacklisted_channels']));
		set_config('system','pubstream_blacklisted_channels',$bc);

		$embed_sslonly         = ((x($_POST,'embed_sslonly'))		? True	: False);
		set_config('system','embed_sslonly',$embed_sslonly);
	
		$we = $this->trim_array_elems(explode("\n",$_POST['embed_allow']));
		set_config('system','embed_allow',$we);
	
		$be = $this->trim_array_elems(explode("\n",$_POST['embed_deny']));
		set_config('system','embed_deny',$be);
	
		$ts = ((x($_POST,'transport_security')) ? True : False);
		set_config('system','transport_security_header',$ts);

		$cs = ((x($_POST,'content_security')) ? True : False);
		set_config('system','content_security_policy',$cs);

		goaway(z_root() . '/admin/security');
	}
	
	

	function get() {
	
		$whitesites = get_config('system','whitelisted_sites');
		$whitesites_str = ((is_array($whitesites)) ? implode("\n",$whitesites) : '');
	
		$blacksites = get_config('system','blacklisted_sites');
		$blacksites_str = ((is_array($blacksites)) ? implode("\n",$blacksites) : '');
	
	
		$whitechannels = get_config('system','whitelisted_channels');
		$whitechannels_str = ((is_array($whitechannels)) ? implode("\n",$whitechannels) : '');
	
		$blackchannels = get_config('system','blacklisted_channels');
		$blackchannels_str = ((is_array($blackchannels)) ? implode("\n",$blackchannels) : '');

		$pswhitesites = get_config('system','pubstream_whitelisted_sites');
		$pswhitesites_str = ((is_array($pswhitesites)) ? implode("\n",$pswhitesites) : '');
	
		$psblacksites = get_config('system','pubstream_blacklisted_sites');
		$psblacksites_str = ((is_array($psblacksites)) ? implode("\n",$psblacksites) : '');
	
	
		$pswhitechannels = get_config('system','pubstream_whitelisted_channels');
		$pswhitechannels_str = ((is_array($pswhitechannels)) ? implode("\n",$pswhitechannels) : '');
	
		$psblackchannels = get_config('system','pubstream_blacklisted_channels');
		$psblackchannels_str = ((is_array($psblackchannels)) ? implode("\n",$psblackchannels) : '');

		$whiteembeds = get_config('system','embed_allow');
		$whiteembeds_str = ((is_array($whiteembeds)) ? implode("\n",$whiteembeds) : '');
	
		$blackembeds = get_config('system','embed_deny');
		$blackembeds_str = ((is_array($blackembeds)) ? implode("\n",$blackembeds) : '');
	
		$embed_coop = intval(get_config('system','embed_coop'));
	
		if((! $whiteembeds) && (! $blackembeds)) {
			$embedhelp1 = t("By default, unfiltered HTML is allowed in embedded media. This is inherently insecure.");
		}

		$embedhelp2 = t("The recommended setting is to only allow unfiltered HTML from the following sites:"); 
		$embedhelp3 = t("https://youtube.com/<br />https://www.youtube.com/<br />https://youtu.be/<br />https://vimeo.com/<br />https://soundcloud.com/<br />");
		$embedhelp4 = t("All other embedded content will be filtered, <strong>unless</strong> embedded content from that site is explicitly blocked.");
	
		$t = get_markup_template('admin_security.tpl');
		return replace_macros($t, array(
			'$title' => t('Administration'),
			'$page' => t('Security'),
			'$form_security_token' => get_form_security_token('admin_security'),
	        '$block_public'     => array('block_public', t("Block public"), get_config('system','block_public'), t("Check to block public access to all otherwise public personal pages on this site unless you are currently authenticated.")),
	        '$block_public_search'     => array('block_public_search', t("Block public search"), get_config('system','block_public_search', 1), t("Prevent access to search content unless you are currently authenticated.")),
			'$localdir_hide'     => [ 'localdir_hide', t('Hide local directory'), intval(get_config('system','localdir_hide')), t('Only use the global directory') ], 
			'$cloud_noroot'     => [ 'cloud_noroot', t('Provide a cloud root directory'), 1 - intval(get_config('system','cloud_disable_siteroot')), t('The cloud root directory lists all channel names which provide public files') ], 
			'$cloud_disksize'     => [ 'cloud_disksize', t('Show total disk space available to cloud uploads'), intval(get_config('system','cloud_report_disksize')), '' ],
			'$transport_security' => array('transport_security', t('Set "Transport Security" HTTP header'),intval(get_config('system','transport_security_header')),''),
			'$content_security' => array('content_security', t('Set "Content Security Policy" HTTP header'),intval(get_config('system','content_security_policy')),''),
			'$allowed_email'	=> array('allowed_email', t("Allowed email domains"), get_config('system','allowed_email'), t("Comma separated list of domains which are allowed in email addresses for registrations to this site. Wildcards are accepted. Empty to allow any domains")),
			'$not_allowed_email'	=> array('not_allowed_email', t("Not allowed email domains"), get_config('system','not_allowed_email'), t("Comma separated list of domains which are not allowed in email addresses for registrations to this site. Wildcards are accepted. Empty to allow any domains, unless allowed domains have been defined.")),
			'$whitelisted_sites' => array('whitelisted_sites', t('Allow communications only from these sites'), $whitesites_str, t('One site per line. Leave empty to allow communication from anywhere by default')),
			'$blacklisted_sites' => array('blacklisted_sites', t('Block communications from these sites'), $blacksites_str, ''),
			'$whitelisted_channels' => array('whitelisted_channels', t('Allow communications only from these channels'), $whitechannels_str, t('One channel (hash) per line. Leave empty to allow from any channel by default')),
			'$blacklisted_channels' => array('blacklisted_channels', t('Block communications from these channels'), $blackchannels_str, ''),

			'$pswhitelisted_sites' => array('pubstream_whitelisted_sites', t('Allow public stream communications only from these sites'), $pswhitesites_str, t('One site per line. Leave empty to allow communication from anywhere by default')),
			'$psblacklisted_sites' => array('pubstream_blacklisted_sites', t('Block public stream communications from these sites'), $psblacksites_str, ''),
			'$pswhitelisted_channels' => array('pubstream_whitelisted_channels', t('Allow public stream communications only from these channels'), $pswhitechannels_str, t('One channel (hash) per line. Leave empty to allow from any channel by default')),
			'$psblacklisted_channels' => array('pubstream_blacklisted_channels', t('Block public stream communications from these channels'), $psblackchannels_str, ''),


			'$embed_sslonly' => array('embed_sslonly',t('Only allow embeds from secure (SSL) websites and links.'), intval(get_config('system','embed_sslonly')),''),
			'$embed_allow' => array('embed_allow', t('Allow unfiltered embedded HTML content only from these domains'), $whiteembeds_str, t('One site per line. By default embedded content is filtered.')),
			'$embed_deny' => array('embed_deny', t('Block embedded HTML from these domains'), $blackembeds_str, ''),
	
//	        '$embed_coop'     => array('embed_coop', t('Cooperative embed security'), $embed_coop, t('Enable to share embed security with other compatible sites/hubs')),

			'$submit' => t('Submit')
		));
	}


	function trim_array_elems($arr) {
		$narr = array();
	
		if($arr && is_array($arr)) {
			for($x = 0; $x < count($arr); $x ++) {
				$y = trim($arr[$x]);
				if($y)
					$narr[] = $y;
			}
		}
		return $narr;
	}
	
	
}