<?php

namespace Code\Module;

use Code\Web\Controller;
use Code\Lib\Url;

class Sslify extends Controller
{

    public function init()
    {
        $url = $_REQUEST['url'];
        if ($url) {
            $x = Url::get($url);
            if ($x['success']) {
                $h = explode("\n", $x['header']);
                foreach ($h as $l) {
                    list($k, $v) = array_map("trim", explode(":", trim($l), 2));
                    $hdrs[strtolower($k)] = $v;
                }
                if (array_key_exists('content-type', $hdrs)) {
                    $type = $hdrs['content-type'];
                    header('Content-Type: ' . $type);
                }

                echo $x['body'];
                killme();
            }
        }
        killme();
    }
}
