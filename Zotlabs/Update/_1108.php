<?php

namespace Zotlabs\Update;

class _1108
{
    public function run()
    {
        $r = q("ALTER TABLE `app` ADD `app_addr` CHAR( 255 ) NOT NULL DEFAULT '',
ADD `app_price` CHAR( 255 ) NOT NULL DEFAULT '',
ADD `app_page` CHAR( 255 ) NOT NULL DEFAULT '',
ADD INDEX ( `app_price` )");
        if ($r)
            return UPDATE_SUCCESS;
        return UPDATE_FAILED;
    }


}