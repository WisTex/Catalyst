<?php

namespace Code\Widget;

use App;
use Code\Render\Theme;


require_once('include/attach.php');

class Portfolio implements WidgetInterface
{

    public function widget(array $args): string
    {


        $owner_uid = App::$profile_uid;
        $sql_extra = permissions_sql($owner_uid);


        if (!perm_is_allowed($owner_uid, get_observer_hash(), 'view_storage')) {
            return '';
        }

        if ($args['album']) {
            $album = $args['album'];
        }
        if ($args['title']) {
            $title = $args['title'];
        }
        if (array_key_exists('mode', $args) && isset($args['mode'])) {
            $mode = $args['mode'];
        } else {
            $mode = '';
        }
        if (array_key_exists('count', $args) && isset($args['count'])) {
            $count = $args['count'];
        } else {
            $count = '';
        }


        /**
         * This may return incorrect permissions if you have multiple directories of the same name.
         * It is a limitation of the photo table using a name for a photo album instead of a folder hash
         */

        if ($album) {
            $x = q(
                "select hash from attach where filename = '%s' and uid = %d limit 1",
                dbesc($album),
                intval($owner_uid)
            );
            if ($x) {
                $y = attach_can_view_folder($owner_uid, get_observer_hash(), $x[0]['hash']);
                if (!$y) {
                    return '';
                }
            }
        }

        $order = 'DESC';

        $r = q(
            "SELECT p.resource_id, p.id, p.filename, p.mimetype, p.imgscale, p.description, p.created FROM photo p INNER JOIN
			(SELECT resource_id, max(imgscale) imgscale FROM photo WHERE uid = %d AND album = '%s' AND imgscale <= 4 AND photo_usage IN ( %d, %d ) $sql_extra GROUP BY resource_id) ph
			ON (p.resource_id = ph.resource_id AND p.imgscale = ph.imgscale)
			ORDER BY created $order ",
            intval($owner_uid),
            dbesc($album),
            intval(PHOTO_NORMAL),
            intval(PHOTO_PROFILE)
        );

        //edit album name
        $album_edit = null;

        $photos = [];
        if ($r) {
            $twist = 'rotright';
            foreach ($r as $rr) {
                if ($twist == 'rotright') {
                    $twist = 'rotleft';
                } else {
                    $twist = 'rotright';
                }

                $ext = $phototypes[$rr['mimetype']];

                $imgalt_e = $rr['filename'];
                $desc_e = $rr['description'];

                $imagelink = (z_root() . '/photos/' . App::$profile['channel_address'] . '/image/' . $rr['resource_id']);


                $photos[] = array(
                    'id' => $rr['id'],
                    'twist' => ' ' . $twist . rand(2, 4),
                    'link' => $imagelink,
                    'title' => t('View Photo'),
                    'src' => z_root() . '/photo/' . $rr['resource_id'] . '-' . $rr['imgscale'] . '.' . $ext,
                    'fullsrc' => z_root() . '/photo/' . $rr['resource_id'] . '-' . '1' . '.' . $ext,
                    'resource_id' => $rr['resource_id'],
                    'alt' => $imgalt_e,
                    'desc' => $desc_e,
                    'ext' => $ext,
                    'hash' => $rr['resource_id'],
                    'unknown' => t('Unknown')
                );
            }
        }


        $tpl = Theme::get_template('photo_album_portfolio.tpl');
        $o .= replace_macros($tpl, array(
            '$photos' => $photos,
            '$mode' => $mode,
            '$count' => $count,
            '$album' => (($title) ? $title : $album),
            '$album_id' => rand(),
            '$album_edit' => array(t('Edit Album'), $album_edit),
            '$can_post' => false,
            '$upload' => array(t('Upload'), z_root() . '/photos/' . App::$profile['channel_address'] . '/upload/' . bin2hex($album)),
            '$order' => false,
            '$upload_form' => $upload_form,
            '$usage' => $usage_message
        ));

        return $o;
    }
}
