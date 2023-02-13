<?php

namespace Code\Module;

use App;
use Code\Web\Controller;
use Code\Lib\System;
use Code\Lib\Navbar;
use Code\Render\Theme;


require_once('include/help.php');

/**
 * You can create local site resources in doc/site
 */
class Help extends Controller
{

    public function get()
    {
        Navbar::set_selected('Help');

        if ($_REQUEST['search']) {
            $o = '<div id="help-content" class="generic-content-wrapper">';
            $o .= '<div class="section-title-wrapper">';
            $o .= '<h2>' . t('Documentation Search') . ' - ' . htmlspecialchars($_REQUEST['search']) . '</h2>';
            $o .= '</div>';
            $o .= '<div class="section-content-wrapper">';

            $r = search_doc_files($_REQUEST['search']);
            if ($r) {
                $o .= '<ul class="help-searchlist">';
                foreach ($r as $rr) {
                    $dirname = dirname($rr['v']);
                    $fname = basename($rr['v']);
                    $fname = substr($fname, 0, strrpos($fname, '.'));
                    $path = trim(substr($dirname, 4), '/');

                    $o .= '<li><a href="help/' . (($path) ? $path . '/' : '') . $fname . '" >' . ucwords(str_replace('_', ' ', notags($fname))) . '</a><br>'
                        . '<b><i>' . 'help/' . (($path) ? $path . '/' : '') . $fname . '</i></b><br>'
                        . '...' . str_replace('$Projectname', System::get_platform_name(), $rr['text']) . '...<br><br></li>';
                }
                $o .= '</ul>';
                $o .= '</div>';
                $o .= '</div>';
            }

            return $o;
        }


        if (argc() > 2 && argv(argc() - 2) === 'assets') {
            $path = '';
            for ($x = 1; $x < argc(); $x++) {
                if (strlen($path)) {
                    $path .= '/';
                }
                $path .= argv($x);
            }
            $realpath = 'doc/' . $path;
            //Set the content-type header as appropriate
            $imageInfo = getimagesize($realpath);
            switch ($imageInfo[2]) {
                case IMAGETYPE_JPEG:
                    header("Content-Type: image/jpeg");
                    break;
                case IMAGETYPE_GIF:
                    header("Content-Type: image/gif");
                    break;
                case IMAGETYPE_PNG:
                    header("Content-Type: image/png");
                    break;
                default:
                    break;
            }
            header("Content-Length: " . filesize($realpath));

            // dump the picture and stop the script
            readfile($realpath);
            killme();
        }
        $heading = '';


        if (argc() === 1) {
            $cmd = 'doc';
        }
        else {
            $cmd = str_replace('help/', 'doc/', App::$cmd);
        }

        if (! is_dir($cmd)) {
            $content = get_help_content();
        }
        else {
            $files = self::listdir($cmd);

            if ($files) {
                usort($files, ['self', 'usort_basename']);
                foreach ($files as $file) {
                    $language = '';

                    if ((!strpos($file, '/site/')) && file_exists(str_replace('doc/', 'doc/site/', $file))) {
                        $file = str_replace('doc/', 'doc/site/', $file);
                    }

                    if (!is_dir($file) && !str_contains(z_mime_content_type($file), 'text')) {
                        continue;
                    }
                    if (strpos($file, 'README')) {
                        continue;
                    }
                    if (preg_match('/\/(..|..-..)\//', $file, $matches)) {
                        $language = $matches[1];

                        if ($language === substr(App::$language, 0, 2)) {
                            $language = '';
                        }
                    }

                    $link = str_replace(['doc/', '.mc', '.txt'], ['help/', '', ''], $file);
                    $displayName = str_replace('_', ' ', $link);
                    if (str_contains($link, '/global/') || str_contains($link, '/media/')) {
                        continue;
                    }
                    $content .= '<div class="nav-pills"><a href="' . $link . '">' . ucfirst(basename($displayName)) . '</a>' . (($language) ? " [$language]" : '') . '</div>' . EOL;
                }
            }
        }

        return replace_macros(Theme::get_template('help.tpl'), [
            '$title' => t('$Projectname Documentation'),
            '$tocHeading' => t('Contents'),
            '$content' => $content,
            '$heading' => $heading,
            '$language' => $language
        ]);
    }

    public static function usort_basename($a,$b) {
        return strcasecmp(basename($a), basename($b));
    }

    public static function listdir($path)
    {
        $results = [];
        $handle = opendir($path);
        if (!$handle) {
            return $results;
        }
        if ($path === 'doc') {
            return ['doc/guide', 'doc/develop', 'doc/site'];
        }
        while (false !== ($file = readdir($handle))) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $fileName = $path . '/' . $file;
            if (is_dir($fileName)) {
                $results = array_merge($results, self::listdir($fileName));
            } else {
                $results[] = $fileName;
            }
        }
        closedir($handle);
        return $results;
    }
}
