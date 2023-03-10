<?php

namespace Code\Lib;

use App;
use Code\Lib\Apps;
use Code\Lib\Chatroom;
use Code\Lib\Channel;
use Code\Lib\System;
use Code\Lib\Features;
use Code\Lib\Menu;
use Code\Lib\Head;
use Code\Render\Theme;
use Code\Extend\Hook;

require_once('include/security.php');
require_once('include/conversation.php');


class Navbar {

    public static function render($template = 'default')
    {

        /**
         *
         * Build page header and site navigation bars
         *
         */

        if (! isset(App::$page['nav'])) {
            App::$page['nav'] = EMPTY_STR;
        }
        if (! isset(App::$page['htmlhead'])) {
            App::$page['htmlhead'] = EMPTY_STR;
        }

        $site_channel = Channel::get_system();

    
        App::$page['htmlhead'] .= '<script>$(document).ready(function() { $("#nav-search-text").search_autocomplete(\'' . z_root() . '/acloader' . '\');});</script>';

        $is_owner = (((local_channel()) && ((App::$profile_uid == local_channel()) || (App::$profile_uid == 0))) ? true : false);

        if (local_channel()) {
            $channel = App::get_channel();
            $observer = App::get_observer();
            $prof = q(
                "select id from profile where uid = %d and is_default = 1",
                intval($channel['channel_id'])
            );

            if (! (isset($_SESSION['delegate']) && $_SESSION['delegate'])) {
                $chans = q(
                    "select channel_name, channel_id from channel left join pconfig on channel_id = pconfig.uid where channel_account_id = %d and channel_removed = 0 and pconfig.cat = 'system' and pconfig.k = 'include_in_menu' and pconfig.v = '1' order by channel_name ",
                    intval(get_account_id())
                );
                if (is_site_admin() && intval(get_pconfig($site_channel['channel_id'], 'system', 'include_in_menu'))) {
                    $chans = array_merge([$site_channel], $chans);
                }
            }

            $sitelocation = (($is_owner) ? '' : App::$profile['reddress']);
        } elseif (remote_channel()) {
            $observer = App::get_observer();
            $sitelocation = ((App::$profile['reddress']) ? App::$profile['reddress'] : '@' . App::get_hostname());
        }



        $channel_apps[] = ((isset(App::$profile)) ? self::channel_apps($is_owner, App::$profile['channel_address']) : []);

        $site_icon = System::get_site_icon();

        $banner = EMPTY_STR;
  
        App::$page['header'] .= replace_macros(Theme::get_template('hdr.tpl'), [
            //we could additionally use this to display important system notifications e.g. for updates
        ]);


        // nav links: array of array('href', 'text', 'extra css classes', 'title')
        $nav = [];

        if (can_view_public_stream()) {
            $nav['pubs'] = true;
        }

        /**
         * Display login or logout
         */

        $nav['usermenu'] = [];
        $userinfo = null;
        $nav['loginmenu'] = [];

        if ($observer) {
            $userinfo = [
                'icon' => $observer['xchan_photo_m'] . '?rev=' . strtotime($observer['xchan_photo_date']),
                'name' => $observer['xchan_addr'],
            ];
        } elseif (! $_SESSION['authenticated']) {
            $nav['remote_login'] = Channel::remote_login();
            $nav['loginmenu'][] = ['rmagic',t('Remote authentication'),'',t('Click to authenticate to your home hub'),'rmagic_nav_btn'];
        }

        if (local_channel()) {
            if (! (isset($_SESSION['delegate']) && $_SESSION['delegate'])) {
                $nav['manage'] = ['manage', t('Channels'), "", t('Manage your channels'),'manage_nav_btn'];
            }

            $nav['safe'] = ['safe', t('Safe Mode'), ((get_safemode()) ? t('(is on)') : t('(is off)')) , t('Content filtering'),'safe_nav_btn'];


            if ($chans && count($chans) > 0) {
                $nav['channels'] = $chans;
            }

            $nav['logout'] = ['logout',t('Logout'), "", t('End this session'),'logout_nav_btn'];

            // user menu
            $nav['usermenu'][] = ['profile/' . $channel['channel_address'], t('View Profile'), ((App::$nav_sel['raw_name'] == 'Profile') ? 'active' : ''), t('Your profile page'),'profile_nav_btn'];

        } else {
            if (! get_account_id()) {
                if (App::$module === 'channel') {
                    $nav['login'] = login(true, 'navbar-login', false, false);
                    $nav['loginmenu'][] = ['login',t('Login'),'',t('Sign in'),''];
                } else {
                    $nav['login'] = login(true, 'navbar-login', false, false);
                    $nav['loginmenu'][] = ['login',t('Login'),'',t('Sign in'),'login_nav_btn'];
                    App::$page['content'] .= replace_macros(
                        Theme::get_template('nav_login.tpl'),
                        [
                            '$nav' => $nav,
                            'userinfo' => $userinfo
                        ]
                    );
                }
            } else {
                $nav['alogout'] = ['logout',t('Logout'), "", t('End this session'),'logout_nav_btn'];
            }
        }

        $my_url = Channel::get_my_url();
        if (! $my_url) {
            $observer = App::get_observer();
            $my_url = (($observer) ? $observer['xchan_url'] : '');
        }

        $homelink_arr = parse_url($my_url);
        $homelink = $homelink_arr['scheme'] . '://' . $homelink_arr['host'];

        if (! $is_owner) {
            $nav['rusermenu'] = [
                $homelink,
                t('Take me home'),
                'logout',
                ((local_channel()) ? t('Logout') : t('Log me out of this site'))
            ];
        }

        if (((get_config('system', 'register_policy') == REGISTER_OPEN) || (get_config('system', 'register_policy') == REGISTER_APPROVE)) && (! $_SESSION['authenticated'])) {
            $nav['register'] = ['register',t('Register'), "", t('Create an account'),'register_nav_btn'];
        }

        if (! get_config('system', 'hide_help', true)) {
            $help_url = z_root() . '/help?f=&cmd=' . App::$cmd;
            $context_help = '';
            $enable_context_help = ((intval(get_config('system', 'enable_context_help')) === 1 || get_config('system', 'enable_context_help') === false) ? true : false);
            if ($enable_context_help === true) {
                require_once('include/help.php');
                $context_help = load_context_help();
                //point directly to /help if $context_help is empty - this can be removed once we have context help for all modules
                $enable_context_help = (($context_help) ? true : false);
            }
            $nav['help'] = [$help_url, t('Help'), "", t('Help and documentation'), 'help_nav_btn', $context_help, $enable_context_help];
        }


        $search_form_action = 'search';


        $nav['search'] = ['search', t('Search'), "", t('Search site @name, #tag, ?doc, content'), $search_form_action];

        /**
         * Admin page
         */
        if (is_site_admin()) {
            $nav['admin'] = ['admin/', t('Admin'), "", t('Site Setup and Configuration'),'admin_nav_btn'];
        }

        $x = ['nav' => $nav, 'usermenu' => $userinfo];

        Hook::call('nav', $x);

        if (App::$profile_uid && App::$nav_sel['raw_name']) {
            $active_app = q(
                "SELECT app_url FROM app WHERE app_channel = %d AND app_name = '%s' LIMIT 1",
                intval(App::$profile_uid),
                dbesc(App::$nav_sel['raw_name'])
            );

            if ($active_app) {
                $url = $active_app[0]['app_url'];
            }
        }

        $pinned_list = [];
        $syslist = [];

        //app bin
        if ($is_owner) {
            if (get_pconfig(local_channel(), 'system', 'import_system_apps') !== datetime_convert('UTC', 'UTC', 'now', 'Y-m-d')) {
                Apps::import_system_apps();
                set_pconfig(local_channel(), 'system', 'import_system_apps', datetime_convert('UTC', 'UTC', 'now', 'Y-m-d'));
            }

            $list = Apps::app_list(local_channel(), false, [ 'nav_pinned_app' ]);
            if ($list) {
                foreach ($list as $li) {
                    $pinned_list[] = Apps::app_encode($li);
                }
            }
            Apps::translate_system_apps($pinned_list);

            usort($pinned_list, 'Code\\Lib\\Apps::app_name_compare');

            $pinned_list = Apps::app_order(local_channel(), $pinned_list, 'nav_pinned_app');


            $syslist = [];
            $list = Apps::app_list(local_channel(), false, [ 'nav_featured_app' ]);

            if ($list) {
                foreach ($list as $li) {
                    $syslist[] = Apps::app_encode($li);
                }
            }
            Apps::translate_system_apps($syslist);
        } else {
            $syslist = Apps::get_system_apps(true);
        }

        usort($syslist, 'Code\\Lib\\Apps::app_name_compare');

        $syslist = Apps::app_order(local_channel(), $syslist, 'nav_featured_app');


        if ($pinned_list) {
            foreach ($pinned_list as $app) {
                if (App::$nav_sel['name'] == $app['name']) {
                    $app['active'] = true;
                }

                if ($is_owner) {
                    $navbar_apps[] = Apps::app_render($app, 'navbar');
                } elseif (! $is_owner && strpos($app['requires'], 'local_channel') === false) {
                    $navbar_apps[] = Apps::app_render($app, 'navbar');
                }
            }
        }

        if ($syslist) {
            foreach ($syslist as $app) {
                if (App::$nav_sel['name'] == $app['name']) {
                    $app['active'] = true;
                }

                if ($is_owner) {
                    $nav_apps[] = Apps::app_render($app, 'nav');
                } elseif (! $is_owner && strpos($app['requires'], 'local_channel') === false) {
                    $nav_apps[] = Apps::app_render($app, 'nav');
                }
            }
        }

        $c = Theme::include('navbar_' . purify_filename($template) . '.css');
        $tpl = Theme::get_template('navbar_' . purify_filename($template) . '.tpl');

        if ($c && $tpl) {
            Head::add_css('navbar_' . $template . '.css');
        }

        if (! $tpl) {
            $tpl = Theme::get_template('navbar_default.tpl');
        }

        App::$page['nav'] .= replace_macros($tpl, [
            '$baseurl' => z_root(),
            '$site_home' => Channel::url($site_channel),
            '$project_icon' => $site_icon,
            '$fulldocs' => t('Help'),
            '$sitelocation' => $sitelocation,
            '$nav' => $x['nav'],
            '$banner' =>  $banner,
            '$emptynotifications' => t('Loading'),
            '$userinfo' => $x['usermenu'],
            '$localuser' => local_channel(),
            '$is_owner' => $is_owner,
            '$sel' => App::$nav_sel,
            '$asidetitle' => t('Side Panel'),
            '$help' => t('@name, #tag, ?doc, content'),
            '$pleasewait' => t('Please wait...'),
            '$nav_apps' => ((isset($nav_apps)) ? $nav_apps : []),
            '$navbar_apps' => ((isset($navbar_apps)) ? $navbar_apps : []),
            '$channel_menu' => get_pconfig(App::$profile_uid, 'system', 'channel_menu', get_config('system', 'channel_menu')),
            '$channel_thumb' => ((App::$profile) ? App::$profile['thumb'] : ''),
            '$channel_apps' => ((isset($channel_apps)) ? $channel_apps : []),
            '$manageapps' => t('Installed Apps'),
            '$appstitle' => t('Apps'),
            '$addapps' => t('Available Apps'),
            '$orderapps' => t('Arrange Apps'),
            '$sysapps_toggle' => t('Toggle System Apps'),
            '$notificationstitle' => t('Notifications'),
            '$url' => ((isset($url) && $url) ? $url : App::$cmd)
        ]);

        if (x($_SESSION, 'reload_avatar') && $observer) {
            // The avatar has been changed on the server but the browser doesn't know that,
            // force the browser to reload the image from the server instead of its cache.
            $tpl = Theme::get_template('force_image_reload.tpl');

            App::$page['nav'] .= replace_macros($tpl, [
                '$imgUrl' => $observer['xchan_photo_m']
            ]);
            unset($_SESSION['reload_avatar']);
        }

        Hook::call('page_header', App::$page['nav']);
    }

    /*
     * Set a menu item in navbar as selected
     *
     */
    public static function set_selected($item)
    {
        App::$nav_sel['raw_name'] = $item;
        $item = ['name' => $item];
        Apps::translate_system_apps($item);
        App::$nav_sel['name'] = $item['name'];
    }

    public static function channel_apps($is_owner = false, $nickname = null)
    {

        // Don't provide any channel apps if we're running as the sys channel

        if (App::$is_sys) {
            return '';
        }


        $channel = App::get_channel();

        if ($channel && is_null($nickname)) {
            $nickname = $channel['channel_address'];
        }

        $uid = ((App::$profile['profile_uid']) ? App::$profile['profile_uid'] : local_channel());
        $account_id = ((App::$profile['profile_uid']) ? App::$profile['channel_account_id'] : App::$channel['channel_account_id']);

        if (! get_pconfig($uid, 'system', 'channelapps', '1')) {
            return '';
        }

        if ($uid == local_channel()) {
            return '';
        } else {
            $cal_link = '/cal/' . $nickname;
        }

        if (x($_GET, 'tab')) {
            $tab = notags(trim($_GET['tab']));
        }

        $url = z_root() . '/channel/' . $nickname;
        $pr  = z_root() . '/profile/' . $nickname;

        $tabs = [
            [
                'label' => t('Channel'),
                'url'   => $url,
                'sel'   => ((argv(0) == 'channel') ? 'active' : ''),
                'title' => t('Status Messages and Posts'),
                'id'    => 'status-tab',
                'icon'  => 'home'
            ],
        ];

        $p = get_all_perms($uid, get_observer_hash());

        if ($p['view_profile']) {
            $tabs[] = [
                'label' => t('About'),
                'url'   => $pr,
                'sel'   => ((argv(0) == 'profile') ? 'active' : ''),
                'title' => t('Profile Details'),
                'id'    => 'profile-tab',
                'icon'  => 'user'
            ];
        }
        if ($p['view_storage']) {
            $tabs[] = [
                'label' => t('Photos'),
                'url'   => z_root() . '/photos/' . $nickname,
                'sel'   => ((argv(0) == 'photos') ? 'active' : ''),
                'title' => t('Photo Albums'),
                'id'    => 'photo-tab',
                'icon'  => 'photo'
            ];
            $tabs[] = [
                'label' => t('Files'),
                'url'   => z_root() . '/cloud/' . $nickname,
                'sel'   => ((argv(0) == 'cloud' || argv(0) == 'sharedwithme') ? 'active' : ''),
                'title' => t('Files and Storage'),
                'id'    => 'files-tab',
                'icon'  => 'folder-open'
            ];
        }

        if ($p['view_stream'] && $cal_link) {
            $tabs[] = [
                'label' => t('Calendar'),
                'url'   => z_root() . $cal_link,
                'sel'   => ((argv(0) == 'cal') ? 'active' : ''),
                'title' => t('Calendar'),
                'id'    => 'event-tab',
                'icon'  => 'calendar'
            ];
        }


        if ($p['chat'] && Apps::system_app_installed($uid, 'Chatrooms')) {
            $has_chats = Chatroom::list_count($uid);
            if ($has_chats) {
                $tabs[] = [
                    'label' => t('Chatrooms'),
                    'url'   => z_root() . '/chat/' . $nickname,
                    'sel'   => ((argv(0) == 'chat') ? 'active' : '' ),
                    'title' => t('Chatrooms'),
                    'id'    => 'chat-tab',
                    'icon'  => 'comments-o'
                ];
            }
        }

        $arr = ['is_owner' => $is_owner, 'nickname' => $nickname, 'tab' => (($tab) ? $tab : false), 'tabs' => $tabs];

        Hook::call('channel_apps', $arr);

        return replace_macros(
            Theme::get_template('profile_tabs.tpl'),
            [
                '$tabs'  => $arr['tabs'],
                '$name'  => App::$profile['channel_name'],
                '$thumb' => App::$profile['thumb'],
            ]
        );
    }


}
