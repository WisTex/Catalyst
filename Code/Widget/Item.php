<?php

namespace Code\Widget;

use App;

require_once('include/security.php');

class Item implements WidgetInterface
{

    public function widget(array $arguments): string
    {

        $channel_id = 0;
        if (array_key_exists('channel_id', $arguments) && intval($arguments['channel_id'])) {
            $channel_id = intval($arguments['channel_id']);
        }
        if (!$channel_id) {
            $channel_id = App::$profile_uid;
        }
        if (!$channel_id) {
            return '';
        }


        if ((!$arguments['mid']) && (!$arguments['title'])) {
            return '';
        }

        if (!perm_is_allowed($channel_id, get_observer_hash(), 'view_pages')) {
            return '';
        }

        $sql_extra = item_permissions_sql($channel_id);

        if ($arguments['title']) {
            $r = q(
                "select item.* from item left join iconfig on item.id = iconfig.iid
				where item.uid = %d and iconfig.cat = 'system' and iconfig.v = '%s'
				and iconfig.k = 'WEBPAGE' and item_type = %d $sql_extra limit 1",
                intval($channel_id),
                dbesc($arguments['title']),
                intval(ITEM_TYPE_WEBPAGE)
            );
        } else {
            $r = q(
                "select * from item where mid = '%s' and uid = %d and item_type = "
                . intval(ITEM_TYPE_WEBPAGE) . " $sql_extra limit 1",
                dbesc($arguments['mid']),
                intval($channel_id)
            );
        }

        if (!$r) {
            return '';
        }

        xchan_query($r);
        $r = fetch_post_tags($r);

        $o = prepare_page($r[0]);
        return $o;
    }
}
