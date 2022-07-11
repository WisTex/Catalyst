<?php

/** @file */

namespace Code\Render;

use App;
use Code\Extend\Widget;
use Code\Lib\Menu;

require_once('include/security.php');


/**
 * @brief Comanche Page Description Language.
 *
 * Comanche is a markup language similar to bbcode with which to create elaborate
 * and complex web pages by assembling them from a series of components - some of
 * which are pre-built and others which can be defined on the fly. Comanche uses
 * a Page Decription Language to create these pages.
 *
 * Comanche primarily chooses what content will appear in various regions of the
 * page. The various regions have names and these names can change depending on
 * what layout template you choose.
 */
class Comanche
{

    public function parse($s, $pass = 0)
    {
        $matches = [];

        $cnt = preg_match_all("/\[comment\](.*?)\[\/comment\]/ism", $s, $matches, PREG_SET_ORDER);
        if ($cnt) {
            foreach ($matches as $mtch) {
                $s = str_replace($mtch[0], '', $s);
            }
        }

        /*
         * This section supports the "switch" statement of the form given by the following
         * example. The [default][/default] block must be the last in the arbitrary
         * list of cases. The first case that matches the switch variable is used
         * and the rest are not evaluated.
         *
         * [switch observer.language]
         * [case de]
         *   [block]german-content[/block]
         * [/case]
         * [case es]
         *   [block]spanish-content[/block]
         * [/case]
         * [default]
         *   [block]english-content[/block]
         * [/default]
         * [/switch]
         */

        $cnt = preg_match_all("/\[switch (.*?)\](.*?)\[default\](.*?)\[\/default\]\s*\[\/switch\]/ism", $s, $matches, PREG_SET_ORDER);
        if ($cnt) {
            foreach ($matches as $mtch) {
                $switch_done = 0;
                $switch_var = $this->get_condition_var($mtch[1]);
                $default = $mtch[3];
                $cases = [];
                $cntt = preg_match_all("/\[case (.*?)\](.*?)\[\/case\]/ism", $mtch[2], $cases, PREG_SET_ORDER);
                if ($cntt) {
                    foreach ($cases as $case) {
                        if ($case[1] === $switch_var) {
                            $switch_done = 1;
                            $s = str_replace($mtch[0], $case[2], $s);
                            break;
                        }
                    }
                    if ($switch_done === 0) {
                        $s = str_replace($mtch[0], $default, $s);
                    }
                }
            }
        }

        $cnt = preg_match_all("/\[if (.*?)\](.*?)\[else\](.*?)\[\/if\]/ism", $s, $matches, PREG_SET_ORDER);
        if ($cnt) {
            foreach ($matches as $mtch) {
                if ($this->test_condition($mtch[1])) {
                    $s = str_replace($mtch[0], $mtch[2], $s);
                } else {
                    $s = str_replace($mtch[0], $mtch[3], $s);
                }
            }
        } else {
            $cnt = preg_match_all("/\[if (.*?)\](.*?)\[\/if\]/ism", $s, $matches, PREG_SET_ORDER);
            if ($cnt) {
                foreach ($matches as $mtch) {
                    if ($this->test_condition($mtch[1])) {
                        $s = str_replace($mtch[0], $mtch[2], $s);
                    } else {
                        $s = str_replace($mtch[0], '', $s);
                    }
                }
            }
        }
        if ($pass == 0) {
            $this->parse_pass0($s);
        } else {
            $this->parse_pass1($s);
        }
    }

    public function parse_pass0($s)
    {

        $matches = null;

        $cnt = preg_match("/\[layout\](.*?)\[\/layout\]/ism", $s, $matches);
        if ($cnt) {
            App::$page['template'] = trim($matches[1]);
        }

        $cnt = preg_match("/\[template=(.*?)\](.*?)\[\/template\]/ism", $s, $matches);
        if ($cnt) {
            App::$page['template'] = trim($matches[2]);
            App::$page['template_style'] = trim($matches[2]) . '_' . $matches[1];
        }

        $cnt = preg_match("/\[template\](.*?)\[\/template\]/ism", $s, $matches);
        if ($cnt) {
            App::$page['template'] = trim($matches[1]);
        }

        $cnt = preg_match("/\[theme=(.*?)\](.*?)\[\/theme\]/ism", $s, $matches);
        if ($cnt) {
            App::$layout['schema'] = trim($matches[1]);
            App::$layout['theme'] = trim($matches[2]);
        }

        $cnt = preg_match("/\[theme\](.*?)\[\/theme\]/ism", $s, $matches);
        if ($cnt) {
            App::$layout['theme'] = trim($matches[1]);
        }

        $cnt = preg_match("/\[navbar\](.*?)\[\/navbar\]/ism", $s, $matches);
        if ($cnt) {
            App::$layout['navbar'] = trim($matches[1]);
        }

        $cnt = preg_match_all("/\[webpage\](.*?)\[\/webpage\]/ism", $s, $matches, PREG_SET_ORDER);
        if ($cnt) {
            // only the last webpage definition is used if there is more than one
            foreach ($matches as $mtch) {
                App::$layout['webpage'] = $this->webpage($a, $mtch[1]);
            }
        }
    }

    public function parse_pass1($s)
    {
        $cnt = preg_match_all("/\[region=(.*?)\](.*?)\[\/region\]/ism", $s, $matches, PREG_SET_ORDER);
        if ($cnt) {
            foreach ($matches as $mtch) {
                App::$layout['region_' . $mtch[1]] = $this->region($mtch[2], $mtch[1]);
            }
        }
    }

    /**
     * @brief Replace conditional variables with real values.
     *
     * Currently supported condition variables:
     *   * $config.xxx.yyy - get_config with cat = xxx and k = yyy
     *   * $request - request uri for this page
     *   * $observer.language - viewer's preferred language (closest match)
     *   * $observer.address - xchan_addr or false
     *   * $observer.name - xchan_name or false
     *   * $observer - xchan_hash of observer or empty string
     *   * $local_channel - logged in channel_id or false
     *
     * @param string $v The conditional variable name
     * @return string|bool
     */
    public function get_condition_var($v)
    {
        if ($v) {
            $x = explode('.', $v);
            if ($x[0] == 'config') {
                return get_config($x[1], $x[2]);
            } elseif ($x[0] === 'request') {
                return $_SERVER['REQUEST_URI'];
            } elseif ($x[0] === 'local_channel') {
                return local_channel();
            } elseif ($x[0] === 'observer') {
                if (count($x) > 1) {
                    if ($x[1] == 'language') {
                        return App::$language;
                    }
                    $y = App::get_observer();
                    if (!$y) {
                        return false;
                    }
                    if ($x[1] == 'address') {
                        return $y['xchan_addr'];
                    } elseif ($x[1] == 'name') {
                        return $y['xchan_name'];
                    } elseif ($x[1] == 'webname') {
                        return substr($y['xchan_addr'], 0, strpos($y['xchan_addr'], '@'));
                    }
                    return false;
                }
                return get_observer_hash();
            } else {
                return false;
            }
        }
        return false;
    }

    /**
     * @brief Test for Conditional Execution conditions.
     *
     * This is extensible. The first version of variable testing supports tests of the forms:
     *
     * - [if $config.system.foo ~= baz] which will check if get_config('system','foo') contains the string 'baz';
     * - [if $config.system.foo == baz] which will check if get_config('system','foo') is the string 'baz';
     * - [if $config.system.foo != baz] which will check if get_config('system','foo') is not the string 'baz';
     * - [if $config.system.foo >= 3] which will check if get_config('system','foo') is greater than or equal to 3;
     * - [if $config.system.foo > 3] which will check if get_config('system','foo') is greater than 3;
     * - [if $config.system.foo <= 3] which will check if get_config('system','foo') is less than or equal to 3;
     * - [if $config.system.foo < 3] which will check if get_config('system','foo') is less than 3;
     *
     * - [if $config.system.foo {} baz] which will check if 'baz' is an array element in get_config('system','foo')
     * - [if $config.system.foo {*} baz] which will check if 'baz' is an array key in get_config('system','foo')
     * - [if $config.system.foo] which will check for a return of a true condition for get_config('system','foo');
     * - [if !$config.system.foo] which will check for a return of a false condition for get_config('system','foo');
     *
     * The values 0, '', an empty array, and an unset value will all evaluate to false.
     *
     * @param int|string $s
     * @return bool
     */
    public function test_condition($s)
    {

        if (preg_match('/[\$](.*?)\s\~\=\s(.*?)$/', $s, $matches)) {
            $x = $this->get_condition_var($matches[1]);
            if (stripos($x, trim($matches[2])) !== false) {
                return true;
            }
            return false;
        }

        if (preg_match('/[\$](.*?)\s\=\=\s(.*?)$/', $s, $matches)) {
            $x = $this->get_condition_var($matches[1]);
            if ($x == trim($matches[2])) {
                return true;
            }
            return false;
        }

        if (preg_match('/[\$](.*?)\s\!\=\s(.*?)$/', $s, $matches)) {
            $x = $this->get_condition_var($matches[1]);
            if ($x != trim($matches[2])) {
                return true;
            }
            return false;
        }

        if (preg_match('/[\$](.*?)\s\>\=\s(.*?)$/', $s, $matches)) {
            $x = $this->get_condition_var($matches[1]);
            if ($x >= trim($matches[2])) {
                return true;
            }
            return false;
        }

        if (preg_match('/[\$](.*?)\s\<\=\s(.*?)$/', $s, $matches)) {
            $x = $this->get_condition_var($matches[1]);
            if ($x <= trim($matches[2])) {
                return true;
            }
            return false;
        }

        if (preg_match('/[\$](.*?)\s\>\s(.*?)$/', $s, $matches)) {
            $x = $this->get_condition_var($matches[1]);
            if ($x > trim($matches[2])) {
                return true;
            }
            return false;
        }

        if (preg_match('/[\$](.*?)\s\>\s(.*?)$/', $s, $matches)) {
            $x = $this->get_condition_var($matches[1]);
            if ($x < trim($matches[2])) {
                return true;
            }
            return false;
        }

        if (preg_match('/[\$](.*?)\s\{\}\s(.*?)$/', $s, $matches)) {
            $x = $this->get_condition_var($matches[1]);
            if (is_array($x) && in_array(trim($matches[2]), $x)) {
                return true;
            }
            return false;
        }

        if (preg_match('/[\$](.*?)\s\{\*\}\s(.*?)$/', $s, $matches)) {
            $x = $this->get_condition_var($matches[1]);
            if (is_array($x) && array_key_exists(trim($matches[2]), $x)) {
                return true;
            }
            return false;
        }

        if (preg_match('/[\!\$](.*?)$/', $s, $matches)) {
            $x = $this->get_condition_var($matches[1]);
            if (!$x) {
                return true;
            }
            return false;
        }

    
        if (preg_match('/[\$](.*?)$/', $s, $matches)) {
            $x = $this->get_condition_var($matches[1]);
            if ($x) {
                return true;
            }
            return false;
        }
        return false;
    }

    /**
     * @brief Return rendered menu for current channel_id.
     *
     * @param string $s
     * @param string $class (optional) default empty
     * @return string
     * @see menu_render()
     */
    public function menu($s, $class = '')
    {

        $channel_id = $this->get_channel_id();
        $name = $s;

        $cnt = preg_match_all("/\[var=(.*?)\](.*?)\[\/var\]/ism", $s, $matches, PREG_SET_ORDER);
        if ($cnt) {
            foreach ($matches as $mtch) {
                $var[$mtch[1]] = $mtch[2];
                $name = str_replace($mtch[0], '', $name);
            }
        }

        if ($channel_id) {
            $m = Menu::fetch($name, $channel_id, get_observer_hash());
            return Menu::render($m, $class, $edit = false, $var);
        }
    }


    public function replace_region($match)
    {
        if (array_key_exists($match[1], App::$page)) {
            return App::$page[$match[1]];
        }
    }

    /**
     * @brief Returns the channel_id of the profile owner of the page.
     *
     * Returns the channel_id of the profile owner of the page, or the local_channel
     * if there is no profile owner. Otherwise returns 0.
     *
     * @return int channel_id
     */
    public function get_channel_id()
    {
        $channel_id = ((is_array(App::$profile)) ? App::$profile['profile_uid'] : 0);

        if ((!$channel_id) && (local_channel())) {
            $channel_id = local_channel();
        }
        return $channel_id;
    }

    /**
     * @brief Returns a parsed block.
     *
     * @param string $s
     * @param string $class (optional) default empty
     * @return string parsed HTML of block
     */
    public function block($s, $class = '')
    {
        $var = [];
        $matches = [];
        $name = $s;
        $class = (($class) ? $class : 'bblock widget');

        $cnt = preg_match_all("/\[var=(.*?)\](.*?)\[\/var\]/ism", $s, $matches, PREG_SET_ORDER);
        if ($cnt) {
            foreach ($matches as $mtch) {
                $var[$mtch[1]] = $mtch[2];
                $name = str_replace($mtch[0], '', $name);
            }
        }

        $o = '';
        $channel_id = $this->get_channel_id();

        if ($channel_id) {
            $r = q(
                "select * from item inner join iconfig on iconfig.iid = item.id and item.uid = %d
				and iconfig.cat = 'system' and iconfig.k = 'BUILDBLOCK' and iconfig.v = '%s' limit 1",
                intval($channel_id),
                dbesc($name)
            );

            if ($r) {
                //check for eventual menus in the block and parse them
                $cnt = preg_match_all("/\[menu\](.*?)\[\/menu\]/ism", $r[0]['body'], $matches, PREG_SET_ORDER);
                if ($cnt) {
                    foreach ($matches as $mtch) {
                        $r[0]['body'] = str_replace($mtch[0], $this->menu(trim($mtch[1])), $r[0]['body']);
                    }
                }
                $cnt = preg_match_all("/\[menu=(.*?)\](.*?)\[\/menu\]/ism", $r[0]['body'], $matches, PREG_SET_ORDER);
                if ($cnt) {
                    foreach ($matches as $mtch) {
                        $r[0]['body'] = str_replace($mtch[0], $this->menu(trim($mtch[2]), $mtch[1]), $r[0]['body']);
                    }
                }

                // emit the block
                $o .= (($var['wrap'] == 'none') ? '' : '<div class="' . $class . '">');

                if ($r[0]['title'] && trim($r[0]['body']) != '$content') {
                    $o .= '<h3>' . $r[0]['title'] . '</h3>';
                }

                if (trim($r[0]['body']) === '$content') {
                    $o .= App::$page['content'];
                } else {
                    $o .= prepare_text($r[0]['body'], $r[0]['mimetype']);
                }

                $o .= (($var['wrap'] == 'none') ? '' : '</div>');
            }
        }

        return $o;
    }

    /**
     * @brief Include JS depending on framework.
     *
     * @param string $s
     * @return string
     */
    public function js($s)
    {

        switch ($s) {
            case 'jquery':
                $path = 'view/js/jquery.js';
                break;
            case 'bootstrap':
                $path = 'vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js';
                break;
            case 'foundation':
                $path = 'library/foundation/js/foundation.js';
                $init = "\r\n" . '<script>$(document).ready(function() { $(document).foundation(); });</script>';
                break;
        }

        $ret = '<script src="' . z_root() . '/' . $path . '" ></script>';
        if ($init) {
            $ret .= $init;
        }
        return $ret;
    }

    /**
     * @brief Include CSS depending on framework.
     *
     * @param string $s
     * @return string
     */
    public function css($s)
    {

        switch ($s) {
            case 'bootstrap':
                $path = 'vendor/twbs/bootstrap/dist/css/bootstrap.min.css';
                break;
            case 'foundation':
                $path = 'library/foundation/css/foundation.min.css';
                break;
        }

        $ret = '<link rel="stylesheet" href="' . z_root() . '/' . $path . '" type="text/css" media="screen">';

        return $ret;
    }

    /**
     * This doesn't really belong in Comanche, but it could also be argued that it is the perfect place.
     * We need to be able to select what kind of template and decoration to use for the webpage at the heart of our content.
     * For now we'll allow an '[authored]' element which defaults to name and date, or 'none' to remove these, and perhaps
     * 'full' to provide a social network style profile photo.
     *
     * But leave it open to have richer templating options and perhaps ultimately discard this one, once we have a better idea
     * of what template and webpage options we might desire.
     *
     * @param[in,out] array $a
     * @param string $s
     * @return array
     */
    public function webpage(&$a, $s)
    {
        $ret = [];
        $matches = [];

        $cnt = preg_match_all("/\[authored\](.*?)\[\/authored\]/ism", $s, $matches, PREG_SET_ORDER);
        if ($cnt) {
            foreach ($matches as $mtch) {
                $ret['authored'] = $mtch[1];
            }
        }

        return $ret;
    }

    /**
     * @brief Render a widget.
     *
     * @param string $name
     * @param string $text
     */
    public function widget($name, $text)
    {
        $vars = [];
        $matches = [];

        $cnt = preg_match_all("/\[var=(.*?)\](.*?)\[\/var\]/ism", $text, $matches, PREG_SET_ORDER);
        if ($cnt) {
            foreach ($matches as $mtch) {
                $vars[$mtch[1]] = $mtch[2];
            }
        }

        if (!purify_filename($name)) {
            return '';
        }

        $clsname = ucfirst($name);
        $nsname = "\\Code\\Widget\\" . $clsname;

        $found = false;
        $widgets = Widget::get();
        if ($widgets) {
            foreach ($widgets as $widget) {
                if (is_array($widget) && strtolower($widget[1]) === strtolower($name) && file_exists($widget[0])) {
                    require_once($widget[0]);
                    $found = true;
                }
            }
        }

        if (!$found) {
            if (file_exists('Code/SiteWidget/' . $clsname . '.php')) {
                require_once('Code/SiteWidget/' . $clsname . '.php');
            } elseif (file_exists('widget/' . $clsname . '/' . $clsname . '.php')) {
                require_once('widget/' . $clsname . '/' . $clsname . '.php');
            } elseif (file_exists('Code/Widget/' . $clsname . '.php')) {
                require_once('Code/Widget/' . $clsname . '.php');
            } else {
                $pth = Theme::include($clsname . '.php');
                if ($pth) {
                    require_once($pth);
                }
            }
        }

        if (class_exists($nsname)) {
            $x = new $nsname();
            $f = 'widget';
            if (method_exists($x, $f)) {
                return $x->$f($vars);
            }
        }

        $func = 'widget_' . trim($name);

        if (!function_exists($func)) {
            if (file_exists('widget/' . trim($name) . '.php')) {
                require_once('widget/' . trim($name) . '.php');
            } elseif (file_exists('widget/' . trim($name) . '/' . trim($name) . '.php')) {
                require_once('widget/' . trim($name) . '/' . trim($name) . '.php');
            }
            if (!function_exists($func)) {
                $theme_widget = $func . '.php';
                if (Theme::include($theme_widget)) {
                    require_once(Theme::include($theme_widget));
                }
            }
        }

        if (function_exists($func)) {
            return $func($vars);
        }
    }


    public function region($s, $region_name)
    {

        $s = str_replace('$region', $region_name, $s);

        $matches = [];

        $cnt = preg_match_all("/\[menu\](.*?)\[\/menu\]/ism", $s, $matches, PREG_SET_ORDER);
        if ($cnt) {
            foreach ($matches as $mtch) {
                $s = str_replace($mtch[0], $this->menu(trim($mtch[1])), $s);
            }
        }

        // menu class e.g. [menu=horizontal]my_menu[/menu] or [menu=tabbed]my_menu[/menu]
        // allows different menu renderings to be applied

        $cnt = preg_match_all("/\[menu=(.*?)\](.*?)\[\/menu\]/ism", $s, $matches, PREG_SET_ORDER);
        if ($cnt) {
            foreach ($matches as $mtch) {
                $s = str_replace($mtch[0], $this->menu(trim($mtch[2]), $mtch[1]), $s);
            }
        }
        $cnt = preg_match_all("/\[block\](.*?)\[\/block\]/ism", $s, $matches, PREG_SET_ORDER);
        if ($cnt) {
            foreach ($matches as $mtch) {
                $s = str_replace($mtch[0], $this->block(trim($mtch[1])), $s);
            }
        }

        $cnt = preg_match_all("/\[block=(.*?)\](.*?)\[\/block\]/ism", $s, $matches, PREG_SET_ORDER);
        if ($cnt) {
            foreach ($matches as $mtch) {
                $s = str_replace($mtch[0], $this->block(trim($mtch[2]), trim($mtch[1])), $s);
            }
        }

        $cnt = preg_match_all("/\[js\](.*?)\[\/js\]/ism", $s, $matches, PREG_SET_ORDER);
        if ($cnt) {
            foreach ($matches as $mtch) {
                $s = str_replace($mtch[0], $this->js(trim($mtch[1])), $s);
            }
        }

        $cnt = preg_match_all("/\[css\](.*?)\[\/css\]/ism", $s, $matches, PREG_SET_ORDER);
        if ($cnt) {
            foreach ($matches as $mtch) {
                $s = str_replace($mtch[0], $this->css(trim($mtch[1])), $s);
            }
        }

        $cnt = preg_match_all("/\[widget=(.*?)\](.*?)\[\/widget\]/ism", $s, $matches, PREG_SET_ORDER);
        if ($cnt) {
            foreach ($matches as $mtch) {
                $s = str_replace($mtch[0], $this->widget(trim($mtch[1]), $mtch[2]), $s);
            }
        }

        return $s;
    }


    /**
     * @brief Registers a page template/variant for use by Comanche selectors.
     *
     * @param array $arr
     *    'template' => template name
     *    'variant' => array(
     *           'name' => variant name
     *           'desc' => text description
     *           'regions' => array(
     *               'name' => name
     *               'desc' => text description
     *           )
     *    )
     */
    public function register_page_template($arr)
    {
        App::$page_layouts[$arr['template']] = array($arr['variant']);
        return;
    }
}
