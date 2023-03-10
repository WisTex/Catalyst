<?php

namespace Code\Lib;

class LibBlock
{

    public static $cache = [];
    public static $empty = [];

    // This limits the number of DB queries for fetch_by_entity to once per page load.

    public static function fetch_from_cache($channel_id, $entity)
    {
        if (!isset(self::$cache[$channel_id])) {
            if (!isset(self::$empty[$channel_id])) {
                self::$cache[$channel_id] = self::fetch($channel_id);
                if (!self::$cache[$channel_id]) {
                    self::$empty[$channel_id] = true;
                }
            }
        }
        if (isset(self::$cache[$channel_id]) && self::$cache[$channel_id] && is_array(self::$cache[$channel_id])) {
            foreach (self::$cache[$channel_id] as $entry) {
                if (is_array($entry) && strcasecmp($entry['block_entity'], $entity) === 0) {
                    return $entry;
                }
            }
        }
        return false;
    }


    public static function store($arr)
    {

        $arr['block_entity'] = trim($arr['block_entity']);

        if (!$arr['block_entity']) {
            return false;
        }

        $arr['block_channel_id'] = ((array_key_exists('block_channel_id', $arr)) ? intval($arr['block_channel_id']) : 0);
        $arr['block_type'] = ((array_key_exists('block_type', $arr)) ? intval($arr['block_type']) : BLOCKTYPE_CHANNEL);
        $arr['block_comment'] = ((array_key_exists('block_comment', $arr)) ? escape_tags(trim($arr['block_comment'])) : EMPTY_STR);

        if (!intval($arr['block_id'])) {
            $r = q(
                "select * from block where block_channel_id = %d and block_entity = '%s' and block_type = %d limit 1",
                intval($arr['block_channel_id']),
                dbesc($arr['block_entity']),
                intval($arr['block_type'])
            );
            if ($r) {
                $arr['block_id'] = $r[0]['block_id'];
            }
        }

        if (intval($arr['block_id'])) {
            return q(
                "UPDATE block set block_channel_id = %d, block_entity = '%s', block_type = %d, block_comment = '%s' where block_id = %d",
                intval($arr['block_channel_id']),
                dbesc($arr['block_entity']),
                intval($arr['block_type']),
                dbesc($arr['block_comment']),
                intval($arr['block_id'])
            );
        } else {
            return create_table_from_array('block', $arr);
        }
    }

    public static function remove($channel_id, $entity): bool
    {
        return (bool) q(
            "delete from block where block_channel_id = %d and block_entity = '%s'",
            intval($channel_id),
            dbesc($entity)
        );
    }

    public static function fetch_by_id($channel_id, $id)
    {
        if (!intval($channel_id)) {
            return false;
        }
        $r = q(
            "select * from block where block_channel_id = %d and block_id = %d ",
            intval($channel_id),
            intval($id)
        );
        return (($r) ? array_shift($r) : false);
    }


    public static function fetch_by_entity($channel_id, $entity)
    {
        if (!intval($channel_id)) {
            return false;
        }

        return self::fetch_from_cache($channel_id, $entity);
    }

    public static function fetch($channel_id, $type = false): array
    {
        if (!intval($channel_id)) {
            return [];
        }

        $sql_extra = (($type === false) ? '' : " and block_type = " . intval($type));

        $blocks = q(
            "select * from block where block_channel_id = %d $sql_extra",
            intval($channel_id)
        );
        return ($blocks) ?: [];
    }

    public static function check_clones($channel_id, $identity) {

        $blocked = false;
        $blocking = LibBlock::fetch($channel_id, BLOCKTYPE_SERVER);
        if ($blocking) {
            $hubs = q("select hubloc_url from hubloc where hubloc_hash = '%s' and hubloc_deleted = 0",
                dbesc($identity)
            );
            if ($hubs) {
                foreach ($hubs as $hub) {
                    foreach ($blocking as $block) {
                        if (str_contains($hub['hubloc_url'], $block['block_entity'])) {
                            $blocked = true;
                        }
                    }
                }
            }
        }
        return $blocked;
    }
}
