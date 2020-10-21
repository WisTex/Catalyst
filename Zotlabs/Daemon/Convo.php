<?php 

namespace Zotlabs\Daemon;

use Zotlabs\Lib\Activity;
use Zotlabs\Lib\ActivityStreams;
use Zotlabs\Lib\ASCollection;

class Convo {

	static public function run($argc,$argv) {

		logger('convo invoked: ' . print_r($argv,true));

		if($argc != 4) {
			killme();
		}

		$id = $argv[1];
		$channel_id = intval($argv[2]);
		$contact_hash = $argv[3];
		
		$channel = channelx_by_n($channel_id);
		if (! $channel) {
			killme();
		}

		$r = q("SELECT abook.*, xchan.* FROM abook left join xchan on abook_xchan = xchan_hash
			WHERE abook_channel = %d and abook_xchan = '%s' LIMIT 1",
			intval($channel_id),
			dbesc($contact_hash)
		);
		if (! $r) {
			killme();
		}
		
		$contact = array_shift($r);

		$obj = new ASCollection($id, $channel);

		$messages = $obj->get();

		if ($messages) {	
			foreach ($messages as $message) {
				if (is_string($message)) {
					$message = Activity::fetch($message,$channel);
				}
				// set client flag because comments will probably just be objects and not full blown activities
				// and that lets us use implied_create
				$AS = new ActivityStreams($message, null, true);
				if ($AS->is_valid() && is_array($AS->obj)) {
					$item = Activity::decode_note($AS,true);
					Activity::store($channel,$contact['abook_xchan'],$AS,$item);
				}
			}
		}
	}
}
