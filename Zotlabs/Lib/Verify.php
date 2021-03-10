<?php

namespace Zotlabs\Lib;


class Verify {

	static function create($type,$channel_id,$token,$meta) {
		return q("insert into verify ( vtype, channel, token, meta, created ) values ( '%s', %d, '%s', '%s', '%s' )",
			dbesc($type),
			intval($channel_id),
			dbesc($token),
			dbesc($meta),
			dbesc(datetime_convert())
		);
	}

	static function match($type,$channel_id,$token,$meta) {
		$r = q("select id from verify where vtype = '%s' and channel = %d and token = '%s' and meta = '%s' limit 1",
			dbesc($type),
			intval($channel_id),
			dbesc($token),
			dbesc($meta)
		);
		if($r) {
			q("delete from verify where id = %d",
				intval($r[0]['id'])
			);
			return true;
		}
		return false;
	}

	static function get_meta($type,$channel_id,$token) {
		$r = q("select id, meta from verify where vtype = '%s' and channel = %d and token = '%s' limit 1",
			dbesc($type),
			intval($channel_id),
			dbesc($token)
		);
		if($r) {
			q("delete from verify where id = %d",
				intval($r[0]['id'])
			);
			return $r[0]['meta'];
		}
		return false;
	}

	/**
	 * @brief Purge entries of a verify-type older than interval.
	 *
	 * @param string $type Verify type
	 * @param string $interval SQL compatible time interval
	 */
	static function purge($type, $interval) {
		q("delete from verify where vtype = '%s' and created < ( %s - INTERVAL %s )",
			dbesc($type),
			db_utcnow(),
			db_quoteinterval($interval)
		);
	}

}