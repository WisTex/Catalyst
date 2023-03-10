<?php

/** @file */

namespace Code\Render;

use App;
use Code\Storage\Stdio;

class SmartyTemplate implements TemplateEngine
{

    public static $name = "smarty3";

    public function __construct()
    {

        // Cannot use get_config() here because it is called during installation when there is no DB.
        // FIXME: this may leak private information such as system pathnames.

        $basecompiledir = ((array_key_exists('smarty3_folder', App::$config['system']))
            ? App::$config['system']['smarty3_folder'] : '');
        if (! $basecompiledir) {
            $basecompiledir = str_replace('Code', '', dirname(__dir__)) . "/" . TEMPLATE_BUILD_PATH;
        }
        if (! is_dir($basecompiledir)) {
            Stdio::mkdir(TEMPLATE_BUILD_PATH, STORAGE_DEFAULT_PERMISSIONS, true);
            if (! is_dir($basecompiledir)) {
                echo "<b>ERROR:</b> folder $basecompiledir does not exist.";
                killme();
            }
        }
        if (! is_writable($basecompiledir)) {
            echo "<b>ERROR:</b> folder $basecompiledir must be writable by webserver.";
            killme();
        }
        App::$config['system']['smarty3_folder'] = $basecompiledir;
    }

    // TemplateEngine interface

    public function replace_macros($s, $r)
    {
        $template = '';

        // macro or macros available for use in all templates

        $r['$z_baseurl']     = z_root();

        if (gettype($s) === 'string') {
            $template = $s;
            $s = new SmartyInterface();
        }
        foreach ($r as $key => $value) {
            if ($key[0] === '$') {
                $key = substr($key, 1);
            }
            $s->assign($key, $value);
        }
        return $s->parsed($template);
    }

    public function get_template($file, $root = '')
    {
        $template_file = Theme::include($file, $root);
        if ($template_file) {
            $template = new SmartyInterface();
            $template->filename = $template_file;

            return $template;
        }
        return EMPTY_STR;
    }

    public function get_email_template($file, $root = '')
    {

        $lang = App::$language;
        if ($root != '' && !str_ends_with($root, '/')) {
            $root .= '/';
        }
        foreach ([ $root . "view/$lang/$file", $root . "view/en/$file", '' ] as $template_file) {
            if (is_file($template_file)) {
                break;
            }
        }
        if ($template_file == '') {
            $template_file = Theme::include($file, $root);
        }
        if ($template_file) {
            $template = new SmartyInterface();
            $template->filename = $template_file;
            return $template;
        }
        return EMPTY_STR;
    }
}
