<?php

namespace Code\Update;

class _1071
{
    public function run()
    {
        $r = q("ALTER TABLE `updates` ADD `ud_addr` CHAR( 255 ) NOT NULL DEFAULT '',
ADD INDEX ( `ud_addr` ) ");
        if ($r) {
            return UPDATE_SUCCESS;
        }
        return UPDATE_FAILED;
    }
}
