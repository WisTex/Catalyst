<?php

namespace Code\Widget;

use App;
use Code\Lib\Features;
use Code\Render\Theme;


class Findpeople implements WidgetInterface
{
    public function widget(array $arguments): string
    {
        return self::findpeople_widget();
    }

    public static function findpeople_widget()
    {

        if (get_config('system', 'invitation_only') && defined('INVITE_WORKING')) {
            $x = get_pconfig(local_channel(), 'system', 'invites_remaining');
            if ($x || is_site_admin()) {
                App::$page['aside'] .= '<div class="side-link" id="side-invite-remain">'
                    . sprintf(tt('%d invitation available', '%d invitations available', $x), $x)
                    . '</div>' . $inv;
            }
        }

        $advanced_search = ((local_channel() && Features::enabled(local_channel(), 'advanced_dirsearch')) ? t('Advanced') : false);

        return replace_macros(Theme::get_template('peoplefind.tpl'), array(
            '$findpeople' => t('Find Channels'),
            '$desc' => t('Enter name or interest'),
            '$label' => t('Connect/Follow'),
            '$hint' => t('Examples: Robert Walsh, Fishing'),
            '$findthem' => t('Find'),
            '$suggest' => t('Channel Suggestions'),
            '$similar' => '', // FIXME and uncomment when mod/match working // t('Similar Interests'),
            '$random' => '', // t('Random Profile'),
            '$sites' => t('Communities'),
            '$inv' => '', // t('Invite Friends'),
            '$advanced_search' => $advanced_search,
            '$advanced_hint' => "\r\n" . t('Advanced example: name=fred and country=iceland'),
            '$loggedin' => local_channel()
        ));
    }
}
