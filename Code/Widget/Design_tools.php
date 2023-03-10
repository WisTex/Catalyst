<?php

namespace Code\Widget;

use App;

class Design_tools implements WidgetInterface
{

    public function widget(array $arguments): string
    {

        if (perm_is_allowed(App::$profile['profile_uid'], get_observer_hash(), 'write_pages') || (App::$is_sys && is_site_admin())) {
            return design_tools();
        }

        return EMPTY_STR;
    }
}
