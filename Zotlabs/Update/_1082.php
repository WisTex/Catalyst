<?php

namespace Zotlabs\Update;

class _1082
{
    public function run()
    {
        $r = q("DROP TABLE `challenge` ");
        if ($r)
            return UPDATE_SUCCESS;
        return UPDATE_FAILED;
    }


}