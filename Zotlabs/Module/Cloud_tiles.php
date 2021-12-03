<?php

namespace Zotlabs\Module;

use Zotlabs\Web\Controller;

class Cloud_tiles extends Controller
{

    public function init()
    {

        if (intval($_SESSION['cloud_tiles'])) {
            $_SESSION['cloud_tiles'] = 0;
        } else {
            $_SESSION['cloud_tiles'] = 1;
        }

        if (local_channel()) {
            set_pconfig(local_channel(), 'system', 'cloud_tiles', $_SESSION['cloud_tiles']);
        }

        goaway(z_root() . '/' . hex2bin(argv(1)));
    }
}
