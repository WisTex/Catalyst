<?php
namespace Zotlabs\Module;

use App;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\Libprofile;

class Hcard extends Controller {

	function init() {
	
	   if(argc() > 1)
	        $which = argv(1);
	    else {
	        notice( t('Requested profile is not available.') . EOL );
	        App::$error = 404;
	        return;
	    }
	
		logger('hcard_request: ' . $which, LOGGER_DEBUG);

	    $profile = '';
	    $channel = App::get_channel();
	
	    if((local_channel()) && (argc() > 2) && (argv(2) === 'view')) {
	        $which = $channel['channel_address'];
	        $profile = argv(1);
	        $r = q("select profile_guid from profile where id = %d and uid = %d limit 1",
	            intval($profile),
	            intval(local_channel())
	        );
	        if(! $r)
	            $profile = '';
	        $profile = $r[0]['profile_guid'];
	    }
	
		head_add_link( [ 
			'rel'   => 'alternate', 
			'type'  => 'application/atom+xml',
			'title' => t('Posts and comments'),
			'href'  => z_root() . '/feed/' . $which
		]);

		head_add_link( [ 
			'rel'   => 'alternate', 
			'type'  => 'application/atom+xml',
			'title' => t('Only posts'),
			'href'  => z_root() . '/feed/' . $which . '?f=&top=1'
		]);

	
	    if(! $profile) {
	        $x = q("select channel_id as profile_uid from channel where channel_address = '%s' limit 1",
	            dbesc(argv(1))
	        );
	        if($x) {
	            App::$profile = $x[0];
	        }
	    }
	
		Libprofile::load($which,$profile);
	
	
	}
	
	
	function get() {

		$x = new \Zotlabs\Widget\Profile();	
		return $x->widget([]);
	
	}
	
	
	
}
