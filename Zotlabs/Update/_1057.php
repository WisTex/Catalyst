<?php

namespace Zotlabs\Update;

class _1057
{
    public function run()
    {
        $r = q("drop table intro");
        if ($r) {
            return UPDATE_SUCCESS;
        }
        return UPDATE_FAILED;
    }
}
