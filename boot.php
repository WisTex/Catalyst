<?php

use Zotlabs\Lib\Libzot;
use Zotlabs\Web\Session;
use Zotlabs\Web\HttpMeta;
use Zotlabs\Render\SmartyTemplate;
use Zotlabs\Render\Comanche;
use Zotlabs\Render\Theme;
use Zotlabs\Lib\DB_Upgrade;
use Zotlabs\Lib\System;
use Zotlabs\Daemon\Run;

/**
 * @file boot.php
 *
 * @brief This file defines some global constants and includes the central App class.
 */

define ( 'STD_VERSION',             '20.12.24' );
define ( 'ZOT_REVISION',            '6.0' );

define ( 'DB_UPDATE_VERSION',       1247 );

define ( 'PLATFORM_NAME',           'redmatrix' );
define ( 'PLATFORM_ARCHITECTURE',   'zap' );

define ( 'PROJECT_BASE',   __DIR__ );

// This configures the default state of activitypub at the project level.
define ( 'ACTIVITYPUB_ENABLED', true );

// composer autoloader for all namespaced Classes
require_once('vendor/autoload.php');

if (file_exists('addon/vendor/autoload.php')) {
	require_once('addon/vendor/autoload.php');
}
if (file_exists('addon/version.php')) {
	require_once('addon/version.php');
}

require_once('include/config.php');
require_once('include/network.php');
require_once('include/addon.php');
require_once('include/text.php');
require_once('include/datetime.php');
require_once('include/language.php');
require_once('include/nav.php');
require_once('include/permissions.php');
require_once('include/features.php');
require_once('include/taxonomy.php');
require_once('include/channel.php');
require_once('include/connections.php');
require_once('include/account.php');
require_once('include/zid.php');
require_once('include/xchan.php');
require_once('include/hubloc.php');
require_once('include/attach.php');
require_once('include/bbcode.php');
require_once('include/items.php');

/**
 * @brief Constant with a HTML line break.
 *
 * Contains a HTML line break (br) element and a real carriage return with line
 * feed for the source.
 * This can be used in HTML and JavaScript wherever a line break is required.
 */
define ( 'EOL',                    '<br>' . "\r\n"        );
define ( 'EMPTY_STR',              ''                     );
define ( 'ATOM_TIME',              'Y-m-d\\TH:i:s\\Z'     ); // aka ISO 8601 "Zulu"
define ( 'TEMPLATE_BUILD_PATH',    'cache/smarty3' );

//define ( 'USE_BEARCAPS',           true);

define ( 'DIRECTORY_MODE_NORMAL',      0x0000); // A directory client
define ( 'DIRECTORY_MODE_PRIMARY',     0x0001); // There can only be *one* primary directory server in a directory_realm.
define ( 'DIRECTORY_MODE_SECONDARY',   0x0002); // All other mirror directory servers
define ( 'DIRECTORY_MODE_STANDALONE',  0x0100); // A detached (off the grid) hub with itself as directory server.

// We will look for upstream directories whenever we make contact
// with other sites, but if this is a new installation and isn't
// a standalone hub, we need to seed the service with a starting
// point to go out and find the rest of the world.

define ( 'DIRECTORY_REALM',            'ZAP');

/**
 *
 * Image storage quality. Lower numbers save space at cost of image detail.
 * For ease of upgrade, please do not change here. Change jpeg quality with
 * App::$config['system']['jpeg_quality'] = n;
 * in .htconfig.php, where n is netween 1 and 100, and with very poor results
 * below about 50
 */
define ( 'JPEG_QUALITY',            100  );

/**
 * App::$config['system']['png_quality'] from 0 (uncompressed) to 9
 */
define ( 'PNG_QUALITY',             8  );

/**
 * Language detection parameters
 */
define ( 'LANGUAGE_DETECT_MIN_LENGTH',     128 );
define ( 'LANGUAGE_DETECT_MIN_CONFIDENCE', 0.01 );


define ('MAX_EVENT_REPEAT_COUNT', 512);

/**
 * Default permissions for file-based storage (webDAV, etc.)
 * These files will be owned by the webserver who will need write
 * access to the "storage" folder.
 * Ideally you should make this 700, however some hosted platforms
 * may not let you change ownership of this directory so we're
 * defaulting to both owner-write and group-write privilege.
 * This should work for most cases without modification.
 * Over-ride this in your .htconfig.php if you need something
 * either more or less restrictive.
 */

if (! defined('STORAGE_DEFAULT_PERMISSIONS')) {
	define ( 'STORAGE_DEFAULT_PERMISSIONS',   0770 );
}


/**
 *
 * An alternate way of limiting picture upload sizes. Specify the maximum pixel
 * length that pictures are allowed to be (for non-square pictures, it will apply
 * to the longest side). Pictures longer than this length will be resized to be
 * this length (on the longest side, the other side will be scaled appropriately).
 * Modify this value using
 *
 *    App::$config['system']['max_image_length'] = n;
 *
 * in .htconfig.php
 *
 * If you don't want to set a maximum length, set to -1. The default value is
 * defined by 'MAX_IMAGE_LENGTH' below.
 *
 */
define ( 'MAX_IMAGE_LENGTH',        -1  );


/**
 * log levels
 */

define ( 'LOGGER_NORMAL',          0 );
define ( 'LOGGER_TRACE',           1 );
define ( 'LOGGER_DEBUG',           2 );
define ( 'LOGGER_DATA',            3 );
define ( 'LOGGER_ALL',             4 );


/**
 * registration policies
 */

define ( 'REGISTER_CLOSED',        0 );
define ( 'REGISTER_APPROVE',       1 );
define ( 'REGISTER_OPEN',          2 );


/**
 * site access policy
 */

define ( 'ACCESS_PRIVATE',         0 );
define ( 'ACCESS_PAID',            1 );
define ( 'ACCESS_FREE',            2 );
define ( 'ACCESS_TIERED',          3 );

/**
 * DB update return values
 */

define ( 'UPDATE_SUCCESS', 0);
define ( 'UPDATE_FAILED',  1);


define ( 'CLIENT_MODE_NORMAL', 0x0000);
define ( 'CLIENT_MODE_LOAD',   0x0001);
define ( 'CLIENT_MODE_UPDATE', 0x0002);


/**
 *
 * Channel pageflags
 *
 */

define ( 'PAGE_NORMAL',            0x0000 );
define ( 'PAGE_HIDDEN',            0x0001 );
define ( 'PAGE_AUTOCONNECT',       0x0002 );
define ( 'PAGE_APPLICATION',       0x0004 );
define ( 'PAGE_ALLOWCODE',         0x0008 );
define ( 'PAGE_PREMIUM',           0x0010 );
define ( 'PAGE_ADULT',             0x0020 );
define ( 'PAGE_CENSORED',          0x0040 ); // Site admin has blocked this channel from appearing in casual search results and site feeds
define ( 'PAGE_SYSTEM',            0x1000 );
define ( 'PAGE_HUBADMIN',          0x2000 ); // set this to indicate a preferred admin channel rather than the
											 // default channel of any accounts with the admin role.
define ( 'PAGE_REMOVED',           0x8000 );


/**
 * Photo usage types
 */

define ( 'PHOTO_NORMAL',           0x0000 );
define ( 'PHOTO_PROFILE',          0x0001 );
define ( 'PHOTO_XCHAN',            0x0002 );
define ( 'PHOTO_THING',            0x0004 );
define ( 'PHOTO_COVER',            0x0010 );

define ( 'PHOTO_ADULT',            0x0008 );
define ( 'PHOTO_FLAG_OS',          0x4000 );


define ( 'PHOTO_RES_ORIG',              0 );
define ( 'PHOTO_RES_1024',              1 );  // rectangular 1024 max width or height, floating height if not (4:3)
define ( 'PHOTO_RES_640',               2 );  // to accomodate SMBC vertical comic strips without scrunching the width
define ( 'PHOTO_RES_320',               3 );  // accordingly

define ( 'PHOTO_RES_PROFILE_300',       4 );  // square 300 px
define ( 'PHOTO_RES_PROFILE_80',        5 );  // square 80 px
define ( 'PHOTO_RES_PROFILE_48',        6 );  // square 48 px

define ( 'PHOTO_RES_COVER_1200',        7 );  // 1200w x 435h (2.75:1)
define ( 'PHOTO_RES_COVER_850',         8 );  // 850w x 310h
define ( 'PHOTO_RES_COVER_425',        	9 );  // 425w x 160h


/**
 * Menu types
 */

define ( 'MENU_SYSTEM',          0x0001 );
define ( 'MENU_BOOKMARK',        0x0002 );

/**
 * Network and protocol family types
 */

define ( 'NETWORK_FRND',             'friendica-over-diaspora');    // Friendica, Mistpark, other DFRN implementations
define ( 'NETWORK_DFRN',             'dfrn');    // Friendica, Mistpark, other DFRN implementations
define ( 'NETWORK_ZOT',              'zot');     // Zot!
define ( 'NETWORK_ZOT6',             'zot6');

define ( 'NETWORK_OSTATUS',          'stat');    // status.net, identi.ca, GNU-social, other OStatus implementations
define ( 'NETWORK_GNUSOCIAL',        'gnusoc');    // status.net, identi.ca, GNU-social, other OStatus implementations
define ( 'NETWORK_FEED',             'rss');    // RSS/Atom feeds with no known "post/notify" protocol
define ( 'NETWORK_DIASPORA',         'diaspora');    // Diaspora
define ( 'NETWORK_ACTIVITYPUB',      'activitypub');
define ( 'NETWORK_MAIL',             'mail');    // IMAP/POP
define ( 'NETWORK_MAIL2',            'mai2');    // extended IMAP/POP
define ( 'NETWORK_FACEBOOK',         'face');    // Facebook API
define ( 'NETWORK_LINKEDIN',         'lnkd');    // LinkedIn
define ( 'NETWORK_XMPP',             'xmpp');    // XMPP
define ( 'NETWORK_MYSPACE',          'mysp');    // MySpace
define ( 'NETWORK_GPLUS',            'goog');    // Google+
define ( 'NETWORK_PHANTOM',          'unkn');    // Place holder


/**
 * Permissions
 */

define ( 'PERMS_R_STREAM',         0x00001);
define ( 'PERMS_R_PROFILE',        0x00002);
define ( 'PERMS_R_PHOTOS',         0x00004);
define ( 'PERMS_R_ABOOK',          0x00008);

define ( 'PERMS_W_STREAM',         0x00010);
define ( 'PERMS_W_WALL',           0x00020);
define ( 'PERMS_W_TAGWALL',        0x00040);
define ( 'PERMS_W_COMMENT',        0x00080);
define ( 'PERMS_W_MAIL',           0x00100);
define ( 'PERMS_W_PHOTOS',         0x00200);
define ( 'PERMS_W_CHAT',           0x00400);
define ( 'PERMS_A_DELEGATE',       0x00800);

define ( 'PERMS_R_STORAGE',        0x01000);
define ( 'PERMS_W_STORAGE',        0x02000);
define ( 'PERMS_R_PAGES',          0x04000);
define ( 'PERMS_W_PAGES',          0x08000);
define ( 'PERMS_A_REPUBLISH',      0x10000);
define ( 'PERMS_W_LIKE',           0x20000);

// General channel permissions
                                        // 0 = Only you
define ( 'PERMS_PUBLIC'     , 0x0001 ); // anybody
define ( 'PERMS_NETWORK'    , 0x0002 ); // anybody in this network
define ( 'PERMS_SITE'       , 0x0004 ); // anybody on this site
define ( 'PERMS_CONTACTS'   , 0x0008 ); // any of my connections
define ( 'PERMS_SPECIFIC'   , 0x0080 ); // only specific connections
define ( 'PERMS_AUTHED'     , 0x0100 ); // anybody authenticated (could include visitors from other networks)
define ( 'PERMS_PENDING'    , 0x0200 ); // any connections including those who haven't yet been approved

// Address book flags

define ( 'ABOOK_FLAG_BLOCKED'    , 0x0001);
define ( 'ABOOK_FLAG_IGNORED'    , 0x0002);
define ( 'ABOOK_FLAG_HIDDEN'     , 0x0004);
define ( 'ABOOK_FLAG_ARCHIVED'   , 0x0008);
define ( 'ABOOK_FLAG_PENDING'    , 0x0010);
define ( 'ABOOK_FLAG_UNCONNECTED', 0x0020);
define ( 'ABOOK_FLAG_SELF'       , 0x0080);
define ( 'ABOOK_FLAG_FEED'       , 0x0100);
define ( 'ABOOK_FLAG_CENSORED'   , 0x0200);


define ( 'MAIL_DELETED',       0x0001);
define ( 'MAIL_REPLIED',       0x0002);
define ( 'MAIL_ISREPLY',       0x0004);
define ( 'MAIL_SEEN',          0x0008);
define ( 'MAIL_RECALLED',      0x0010);
define ( 'MAIL_OBSCURED',      0x0020);


define ( 'ATTACH_FLAG_DIR',    0x0001);
define ( 'ATTACH_FLAG_OS',     0x0002);


define ( 'MENU_ITEM_ZID',       0x0001);
define ( 'MENU_ITEM_NEWWIN',    0x0002);
define ( 'MENU_ITEM_CHATROOM',  0x0004);



define ( 'SITE_TYPE_ZOT',           0);
define ( 'SITE_TYPE_NOTZOT',        1);
define ( 'SITE_TYPE_UNKNOWN',       2);

/**
 * Poll/Survey types
 */

define ( 'POLL_SIMPLE_RATING',   0x0001);  // 1-5
define ( 'POLL_TENSCALE',        0x0002);  // 1-10
define ( 'POLL_MULTIPLE_CHOICE', 0x0004);
define ( 'POLL_OVERWRITE',       0x8000);  // If you vote twice remove the prior entry


define ( 'UPDATE_FLAGS_UPDATED',  0x0001);
define ( 'UPDATE_FLAGS_FORCED',   0x0002);
define ( 'UPDATE_FLAGS_CENSORED', 0x0004);
define ( 'UPDATE_FLAGS_DELETED',  0x1000);


define ( 'DROPITEM_NORMAL',      0);
define ( 'DROPITEM_PHASE1',      1);
define ( 'DROPITEM_PHASE2',      2);


/**
 * Maximum number of "people who like (or don't like) this"  that we will list by name
 */

define ( 'MAX_LIKERS',    10);

/**
 * Communication timeout
 */

define ( 'ZCURL_TIMEOUT' , (-1));


/**
 * email notification options
 */

define ( 'NOTIFY_INTRO',    0x0001 );
define ( 'NOTIFY_CONFIRM',  0x0002 );
define ( 'NOTIFY_WALL',     0x0004 );
define ( 'NOTIFY_COMMENT',  0x0008 );
define ( 'NOTIFY_MAIL',     0x0010 );
define ( 'NOTIFY_SUGGEST',  0x0020 );
define ( 'NOTIFY_PROFILE',  0x0040 );
define ( 'NOTIFY_TAGSELF',  0x0080 );
define ( 'NOTIFY_TAGSHARE', 0x0100 );
define ( 'NOTIFY_POKE',     0x0200 );
define ( 'NOTIFY_LIKE',     0x0400 );

define ( 'NOTIFY_SYSTEM',   0x8000 );

/**
 * visual notification options
 */

define ( 'VNOTIFY_NETWORK',    0x0001 );
define ( 'VNOTIFY_CHANNEL',    0x0002 );
define ( 'VNOTIFY_MAIL',       0x0004 );
define ( 'VNOTIFY_EVENT',      0x0008 );
define ( 'VNOTIFY_EVENTTODAY', 0x0010 );
define ( 'VNOTIFY_BIRTHDAY',   0x0020 );
define ( 'VNOTIFY_SYSTEM',     0x0040 );
define ( 'VNOTIFY_INFO',       0x0080 );
define ( 'VNOTIFY_ALERT',      0x0100 );
define ( 'VNOTIFY_INTRO',      0x0200 );
define ( 'VNOTIFY_REGISTER',   0x0400 );
define ( 'VNOTIFY_FILES',      0x0800 );
define ( 'VNOTIFY_PUBS',       0x1000 );
define ( 'VNOTIFY_LIKE',       0x2000 );
define ( 'VNOTIFY_FORUMS',     0x4000 );
define ( 'VNOTIFY_REPORTS',    0x8000 );


/**
 * Tag/term types
 */

define ( 'TERM_UNKNOWN',      0 );
define ( 'TERM_HASHTAG',      1 );
define ( 'TERM_MENTION',      2 );
define ( 'TERM_CATEGORY',     3 );
define ( 'TERM_PCATEGORY',    4 );
define ( 'TERM_FILE',         5 );
define ( 'TERM_SAVEDSEARCH',  6 );
define ( 'TERM_THING',        7 );
define ( 'TERM_BOOKMARK',     8 );
define ( 'TERM_HIERARCHY',    9 );
define ( 'TERM_COMMUNITYTAG', 10 );
define ( 'TERM_FORUM',        11 );
define ( 'TERM_EMOJI',        12 );

define ( 'TERM_OBJ_POST',    1 );
define ( 'TERM_OBJ_PHOTO',   2 );
define ( 'TERM_OBJ_PROFILE', 3 );
define ( 'TERM_OBJ_CHANNEL', 4 );
define ( 'TERM_OBJ_OBJECT',  5 );
define ( 'TERM_OBJ_THING',   6 );
define ( 'TERM_OBJ_APP',     7 );


/**
 * various namespaces we may need to parse
 */
define ( 'PROTOCOL_ZOT',              'http://purl.org/zot/protocol' );
define ( 'PROTOCOL_ZOT6',             'http://purl.org/zot/protocol/6.0' );
define ( 'NAMESPACE_ZOT',             'http://purl.org/zot' );
define ( 'NAMESPACE_DFRN' ,           'http://purl.org/macgirvin/dfrn/1.0' );
define ( 'NAMESPACE_THREAD' ,         'http://purl.org/syndication/thread/1.0' );
define ( 'NAMESPACE_TOMB' ,           'http://purl.org/atompub/tombstones/1.0' );
define ( 'NAMESPACE_ACTIVITY',        'http://activitystrea.ms/spec/1.0/' );
define ( 'NAMESPACE_ACTIVITY_SCHEMA', 'http://activitystrea.ms/schema/1.0/' );
define ( 'NAMESPACE_MEDIA',           'http://purl.org/syndication/atommedia' );
define ( 'NAMESPACE_SALMON_ME',       'http://salmon-protocol.org/ns/magic-env' );
define ( 'NAMESPACE_OSTATUSSUB',      'http://ostatus.org/schema/1.0/subscribe' );
define ( 'NAMESPACE_GEORSS',          'http://www.georss.org/georss' );
define ( 'NAMESPACE_POCO',            'http://portablecontacts.net/spec/1.0' );
define ( 'NAMESPACE_FEED',            'http://schemas.google.com/g/2010#updates-from' );
define ( 'NAMESPACE_OSTATUS',         'http://ostatus.org/schema/1.0' );
define ( 'NAMESPACE_STATUSNET',       'http://status.net/schema/api/1/' );
define ( 'NAMESPACE_ATOM1',           'http://www.w3.org/2005/Atom' );
define ( 'NAMESPACE_YMEDIA',          'http://search.yahoo.com/mrss/' );

// We should be using versioned jsonld contexts so that signatures will be slightly more reliable.
// Why signatures are unreliable by design is a problem nobody seems to care about
// "because it's a W3C standard". .

// Anyway, if you use versioned contexts, communication with Mastodon fails. Have not yet investigated
// the reason for the dependency but for the current time, use the standard non-versioned context.
//define ( 'ACTIVITYSTREAMS_JSONLD_REV', 'https://www.w3.org/ns/activitystreams-history/v1.8.jsonld' );

define ( 'ACTIVITYSTREAMS_JSONLD_REV', 'https://www.w3.org/ns/activitystreams' );

define ( 'ZOT_APSCHEMA_REV', '/apschema/v1.21' );

/**
 * activity stream defines
 */

define ( 'ACTIVITY_PUBLIC_INBOX',  'https://www.w3.org/ns/activitystreams#Public' );


define ( 'ACTIVITY_POST',        'Create' );
define ( 'ACTIVITY_CREATE',      'Create' );
define ( 'ACTIVITY_UPDATE',      'Update' );
define ( 'ACTIVITY_LIKE',        'Like' );
define ( 'ACTIVITY_DISLIKE',     'Dislike' );
define ( 'ACTIVITY_SHARE',       'Announce' );
define ( 'ACTIVITY_FOLLOW',      'Follow' );
define ( 'ACTIVITY_UNFOLLOW',    'Unfollow');

define ( 'ACTIVITY_OBJ_COMMENT', 'Note' );
define ( 'ACTIVITY_OBJ_NOTE',    'Note' );
define ( 'ACTIVITY_OBJ_ARTICLE', 'Article' );
define ( 'ACTIVITY_OBJ_PERSON',  'Person' );
define ( 'ACTIVITY_OBJ_PHOTO',   'Image');
define ( 'ACTIVITY_OBJ_P_PHOTO', 'Icon' );
define ( 'ACTIVITY_OBJ_PROFILE', 'Profile');
define ( 'ACTIVITY_OBJ_EVENT',   'Event' );
define ( 'ACTIVITY_OBJ_POLL',    'Question');





define ( 'ACTIVITY_REACT',       NAMESPACE_ZOT   . '/activity/react' );
define ( 'ACTIVITY_AGREE',       NAMESPACE_ZOT   . '/activity/agree' );
define ( 'ACTIVITY_DISAGREE',    NAMESPACE_ZOT   . '/activity/disagree' );
define ( 'ACTIVITY_ABSTAIN',     NAMESPACE_ZOT   . '/activity/abstain' );
define ( 'ACTIVITY_ATTEND',      NAMESPACE_ZOT   . '/activity/attendyes' );
define ( 'ACTIVITY_ATTENDNO',    NAMESPACE_ZOT   . '/activity/attendno' );
define ( 'ACTIVITY_ATTENDMAYBE', NAMESPACE_ZOT   . '/activity/attendmaybe' );
define ( 'ACTIVITY_POLLRESPONSE', NAMESPACE_ZOT  . '/activity/pollresponse' );

define ( 'ACTIVITY_OBJ_HEART',   NAMESPACE_ZOT   . '/activity/heart' );

define ( 'ACTIVITY_FRIEND',      NAMESPACE_ACTIVITY_SCHEMA . 'make-friend' );
define ( 'ACTIVITY_REQ_FRIEND',  NAMESPACE_ACTIVITY_SCHEMA . 'request-friend' );
define ( 'ACTIVITY_UNFRIEND',    NAMESPACE_ACTIVITY_SCHEMA . 'remove-friend' );
define ( 'ACTIVITY_JOIN',        NAMESPACE_ACTIVITY_SCHEMA . 'join' );

define ( 'ACTIVITY_FAVORITE',    NAMESPACE_ACTIVITY_SCHEMA . 'favorite' );
//define ( 'ACTIVITY_CREATE',      NAMESPACE_ACTIVITY_SCHEMA . 'create' );
define ( 'ACTIVITY_DELETE',      NAMESPACE_ACTIVITY_SCHEMA . 'delete' );
define ( 'ACTIVITY_WIN',         NAMESPACE_ACTIVITY_SCHEMA . 'win' );
define ( 'ACTIVITY_LOSE',        NAMESPACE_ACTIVITY_SCHEMA . 'lose' );
define ( 'ACTIVITY_TIE',         NAMESPACE_ACTIVITY_SCHEMA . 'tie' );
define ( 'ACTIVITY_COMPLETE',    NAMESPACE_ACTIVITY_SCHEMA . 'complete' );
define ( 'ACTIVITY_TAG',         NAMESPACE_ACTIVITY_SCHEMA . 'tag' );

define ( 'ACTIVITY_POKE',        NAMESPACE_ZOT . '/activity/poke' );
define ( 'ACTIVITY_MOOD',        NAMESPACE_ZOT . '/activity/mood' );


define ( 'ACTIVITY_OBJ_ACTIVITY',NAMESPACE_ACTIVITY_SCHEMA . 'activity' );
define ( 'ACTIVITY_OBJ_ALBUM',   NAMESPACE_ACTIVITY_SCHEMA . 'photo-album' );
define ( 'ACTIVITY_OBJ_GROUP',   NAMESPACE_ACTIVITY_SCHEMA . 'group' );
define ( 'ACTIVITY_OBJ_GAME',    NAMESPACE_ACTIVITY_SCHEMA . 'game' );
define ( 'ACTIVITY_OBJ_WIKI',    NAMESPACE_ACTIVITY_SCHEMA . 'wiki' );
define ( 'ACTIVITY_OBJ_TAGTERM', NAMESPACE_ZOT  . '/activity/tagterm' );
define ( 'ACTIVITY_OBJ_THING',   NAMESPACE_ZOT  . '/activity/thing' );
define ( 'ACTIVITY_OBJ_LOCATION',NAMESPACE_ZOT  . '/activity/location' );
define ( 'ACTIVITY_OBJ_FILE',    NAMESPACE_ZOT  . '/activity/file' );
define ( 'ACTIVITY_OBJ_CARD',    NAMESPACE_ZOT  . '/activity/card' );

/**
 * Account Flags
 */

define ( 'ACCOUNT_OK',           0x0000 );
define ( 'ACCOUNT_UNVERIFIED',   0x0001 );
define ( 'ACCOUNT_BLOCKED',      0x0002 );
define ( 'ACCOUNT_EXPIRED',      0x0004 );
define ( 'ACCOUNT_REMOVED',      0x0008 );
define ( 'ACCOUNT_PENDING',      0x0010 );

/**
 * Account roles
 */

define ( 'ACCOUNT_ROLE_SYSTEM',    0x0002 );
define ( 'ACCOUNT_ROLE_DEVELOPER', 0x0004 );
define ( 'ACCOUNT_ROLE_ADMIN',     0x1000 );

/**
 * Item visibility
 */

define ( 'ITEM_VISIBLE',         0x0000);
define ( 'ITEM_HIDDEN',          0x0001);
define ( 'ITEM_BLOCKED',         0x0002);
define ( 'ITEM_MODERATED',       0x0004);
define ( 'ITEM_SPAM',            0x0008);
define ( 'ITEM_DELETED',         0x0010);
define ( 'ITEM_UNPUBLISHED',     0x0020);
define ( 'ITEM_WEBPAGE',         0x0040);	// is a static web page, not a conversational item
define ( 'ITEM_DELAYED_PUBLISH', 0x0080);
define ( 'ITEM_BUILDBLOCK',      0x0100);	// Named thusly to make sure nobody confuses this with ITEM_BLOCKED
define ( 'ITEM_PDL',			 0x0200);	// Page Description Language - e.g. Comanche
define ( 'ITEM_BUG',			 0x0400);	// Is a bug, can be used by the internal bug tracker
define ( 'ITEM_PENDING_REMOVE',  0x0800);   // deleted, notification period has lapsed
define ( 'ITEM_DOC',             0x1000);   // hubzilla only, define here so that item import does the right thing
define ( 'ITEM_CARD',            0x2000);
define ( 'ITEM_ARTICLE',         0x4000);


define ( 'ITEM_TYPE_POST',       0 );
define ( 'ITEM_TYPE_BLOCK',      1 );
define ( 'ITEM_TYPE_PDL',        2 );
define ( 'ITEM_TYPE_WEBPAGE',    3 );
define ( 'ITEM_TYPE_BUG',        4 );
define ( 'ITEM_TYPE_DOC',        5 );
define ( 'ITEM_TYPE_CARD',       6 );
define ( 'ITEM_TYPE_ARTICLE',    7 );
define ( 'ITEM_TYPE_MAIL',       8 );
define ( 'ITEM_TYPE_CUSTOM',     9 );
define ( 'ITEM_TYPE_REPORT',     10 );

define ( 'ITEM_IS_STICKY',       1000 );

define ( 'BLOCKTYPE_CHANNEL',    0 );
define ( 'BLOCKTYPE_SERVER',     1 );

define ( 'DBTYPE_MYSQL',    0 );
define ( 'DBTYPE_POSTGRES', 1 );


function sys_boot() {

	// this file may not exist
	
	@include('.htstartup.php');

	// our central App object

	App::init();

	/*
	 * Load the configuration file which contains our DB credentials.
	 * Ignore errors. If the file doesn't exist or is empty, we are running in
	 * installation mode.
	 */

	App::$install = ((file_exists('.htconfig.php') && filesize('.htconfig.php')) ? false : true);

	@include('.htconfig.php');

	// allow somebody to set some initial settings just in case they can't
	// install without special fiddling

	if(App::$install && file_exists('.htpreconfig.php'))
		@include('.htpreconfig.php');

	if(array_key_exists('default_timezone',get_defined_vars())) {
		App::$config['system']['timezone'] = $default_timezone;
	}

	App::$config['system']['server_role'] = 'pro';

	App::$timezone = ((App::$config['system']['timezone']) ? App::$config['system']['timezone'] : 'UTC');
	date_default_timezone_set(App::$timezone);


	if(! defined('DEFAULT_PLATFORM_ICON')) {
		define( 'DEFAULT_PLATFORM_ICON', '/images/z1-32.png' );
	}

	if(! defined('DEFAULT_NOTIFY_ICON')) {
		define( 'DEFAULT_NOTIFY_ICON', '/images/z1-64.png' );
	}


	/*
	 * Try to open the database;
	 */

	require_once('include/dba/dba_driver.php');

	if(! App::$install) {
		DBA::dba_factory($db_host, $db_port, $db_user, $db_pass, $db_data, $db_type, App::$install);
		if(! DBA::$dba->connected) {
			system_unavailable();
		}

		unset($db_host, $db_port, $db_user, $db_pass, $db_data, $db_type);

		/*
		 * Load configs from db. Overwrite configs from .htconfig.php
		 */

		load_config('system');
		load_config('feature');

		App::$session = new Session();
		App::$session->init();
		load_hooks();
		/**
		 * @hooks 'startup'
		 */
		$arr = [];
		call_hooks('startup',$arr);
	}
}


function startup() {
	error_reporting(E_ERROR | E_WARNING | E_PARSE);

	// Some hosting providers block/disable this
	@set_time_limit(0);

	if (function_exists ('ini_set')) {
		// This has to be quite large to deal with embedded private photos
		@ini_set('pcre.backtrack_limit', 500000);

		// Use cookies to store the session ID on the client side
		@ini_set('session.use_only_cookies', 1);

		// Disable transparent Session ID support
		@ini_set('session.use_trans_sid',    0);
	}
}


/**
 * class: App
 *
 * @brief Our main application structure for the life of this page.
 *
 * Primarily deals with the URL that got us here
 * and tries to make some sense of it, and
 * stores our page contents and config storage
 * and anything else that might need to be passed around
 * before we spit the page out.
 *
 */
class App {

	public  static $install    = false;           // true if we are installing the software
	public  static $account    = null;            // account record of the logged-in account
	public  static $channel    = null;            // channel record of the current channel of the logged-in account
	public  static $observer   = null;            // xchan record of the page observer
	public  static $profile_uid = 0;              // If applicable, the channel_id of the "page owner"
	public  static $poi        = null;            // "person of interest", generally a referenced connection
	private static $oauth_key  = null;            // consumer_id of oauth request, if used
	public  static $layout     = [];              // Comanche parsed template
	public  static $pdl        = null;            // Comanche page description
	private static $perms      = null;            // observer permissions
	private static $widgets    = [];              // widgets for this page
	public  static $config     = [];              // config cache
	
	public  static $override_intltext_templates = [];
	public  static $override_markup_templates = [];
	public  static $override_templateroot = null;
	public  static $override_helproot = null;
	public  static $override_helpfiles = [];

	public static  $session    = null;
	public static  $groups;
	public static  $language;
	public static  $langsave;
	public static  $rtl = false;
	public static  $plugins_admin;
	public static  $module_loaded = false;
	public static  $query_string;
	public static  $page;
	public static  $profile;
	public static  $user;
	public static  $cid;
	public static  $contact;
	public static  $contacts;
	public static  $content;
	public static  $data = [];
	public static  $error = false;
	public static  $emojitab = false;
	public static  $cmd;
	public static  $argv;
	public static  $argc;
	public static  $module;
	public static  $pager;
	public static  $strings;
	public static  $stringsave;   // used in push_lang() and pop_lang()
	public static  $hooks;
	public static  $timezone;
	public static  $interactive = true;
	public static  $plugins;
	private static $apps = [];
	public static  $identities;
	public static  $css_sources = [];
	public static  $js_sources = [];
	public static  $linkrel = [];
	public static  $theme_info = [];
	public static  $is_sys = false;
	public static  $nav_sel;
	public static  $comanche;
	public static  $httpheaders = null;
	public static  $httpsig = null;
	public static  $channel_links;
	public static  $category;

	// Allow themes to control internal parameters
	// by changing App values in theme.php

	public static  $sourcename = '';
	public static  $videowidth = 425;
	public static  $videoheight = 350;
	public static  $force_max_items = 0;
	public static  $theme_thread_allow = true;

	public static $meta;
	
	/**
	 * @brief An array for all theme-controllable parameters
	 *
	 * Mostly unimplemented yet. Only options 'template_engine' and
	 * beyond are used.
	 */
	private static $theme = [
		'sourcename'      => '',
		'videowidth'      => 425,
		'videoheight'     => 350,
		'force_max_items' => 0,
		'thread_allow'    => true,
		'stylesheet'      => '',
		'template_engine' => 'smarty3',
	];

	/**
	 * @brief An array of registered template engines ('name'=>'class name')
	 */
	public static $template_engines = [];
	/**
	 * @brief An array of instanced template engines ('name'=>'instance')
	 */
	public static $template_engine_instance = [];

	private static $ldelim = [
		'internal' => '',
		'smarty3' => '{{'
	];
	private static $rdelim = [
		'internal' => '',
		'smarty3' => '}}'
	];

	// These represent the URL which was used to access the page

	private static $scheme;
	private static $hostname;
	private static $path;

	// This is our standardised URL - regardless of what was used
	// to access the page

	private static $baseurl;

	/**
	 * App constructor.
	 */

	public static function init() {
		// we'll reset this after we read our config file
		date_default_timezone_set('UTC');

		self::$config = [
			'system' => []
		];
		self::$page = [];
		self::$pager= [];

		self::$query_string = '';


		startup();

		set_include_path(
			'include' . PATH_SEPARATOR
			. 'library' . PATH_SEPARATOR
			. '.'
		);

		self::$scheme = 'http';
		if(x($_SERVER,'HTTPS') && $_SERVER['HTTPS'])
			self::$scheme = 'https';
		elseif(x($_SERVER,'SERVER_PORT') && (intval($_SERVER['SERVER_PORT']) == 443))
			self::$scheme = 'https';

		if(x($_SERVER,'SERVER_NAME')) {
			self::$hostname = punify($_SERVER['SERVER_NAME']);

			if(x($_SERVER,'SERVER_PORT') && $_SERVER['SERVER_PORT'] != 80 && $_SERVER['SERVER_PORT'] != 443)
				self::$hostname .= ':' . $_SERVER['SERVER_PORT'];

			/*
			 * Figure out if we are running at the top of a domain
			 * or in a sub-directory and adjust accordingly
			 */
			$path = trim(dirname($_SERVER['SCRIPT_NAME']),'/\\');
			if(isset($path) && strlen($path) && ($path != self::$path))
				self::$path = $path;
		}

		if((x($_SERVER,'QUERY_STRING')) && substr($_SERVER['QUERY_STRING'], 0, 4) === "req=") {
			self::$query_string = str_replace(['<','>'],['&lt;','&gt;'],substr($_SERVER['QUERY_STRING'], 4));
			// removing trailing / - maybe a nginx problem
			if (substr(self::$query_string, 0, 1) == "/") {
				self::$query_string = substr(self::$query_string, 1);
			}
			// change the first & to ? 
			self::$query_string = preg_replace('/&/','?',self::$query_string,1);
		}

		if(x($_GET,'req'))
			self::$cmd = escape_tags(trim($_GET['req'],'/\\'));

		// unix style "homedir"

		if((substr(self::$cmd, 0, 1) === '~') || (substr(self::$cmd, 0, 1) === '@'))
			self::$cmd = 'channel/' . substr(self::$cmd, 1);

		/*
		 * Break the URL path into C style argc/argv style arguments for our
		 * modules. Given "http://example.com/module/arg1/arg2", self::$argc
		 * will be 3 (integer) and self::$argv will contain:
		 *   [0] => 'module'
		 *   [1] => 'arg1'
		 *   [2] => 'arg2'
		 *
		 * There will always be one argument. If provided a naked domain
		 * URL, self::$argv[0] is set to "home".
		 *
		 * If $argv[0] has a period in it, for example foo.json; rewrite
		 * to module = 'foo' and set $_REQUEST['module_format'] = 'json';
		 */

		self::$argv = explode('/', self::$cmd);

		self::$argc = count(self::$argv);
		if ((array_key_exists('0', self::$argv)) && strlen(self::$argv[0])) {
			if(strpos(self::$argv[0],'.')) {
				$_REQUEST['module_format'] = substr(self::$argv[0],strpos(self::$argv[0],'.')+1);
				self::$argv[0] =  substr(self::$argv[0],0,strpos(self::$argv[0],'.'));
			}

			self::$module = str_replace(".", "_", self::$argv[0]);
			self::$module = str_replace("-", "_", self::$module);
			if(strpos(self::$module,'_') === 0)
				self::$module = substr(self::$module,1);
		} else {
			self::$argc = 1;
			self::$argv = array('home');
			self::$module = 'home';
		}


		/*
		 * See if there is any page number information, and initialise
		 * pagination
		 */

		self::$pager['unset']     = ((array_key_exists('page',$_REQUEST)) ? false : true);
		self::$pager['page']      = ((x($_GET,'page') && intval($_GET['page']) > 0) ? intval($_GET['page']) : 1);
		self::$pager['itemspage'] = 60;
		self::$pager['start']     = (self::$pager['page'] * self::$pager['itemspage']) - self::$pager['itemspage'];
		self::$pager['total']     = 0;

		if (self::$pager['start'] < 0) {
			self::$pager['start'] = 0;
		}

		self::$meta = new HttpMeta();

		/*
		 * register template engines (probably just smarty, but this can be extended)
		 */

		self::register_template_engine(get_class(new SmartyTemplate));

	}

	public static function get_baseurl($ssl = false) {
		if(is_array(self::$config)
			&& array_key_exists('system',self::$config)
			&& is_array(self::$config['system'])
			&& array_key_exists('baseurl',self::$config['system'])
			&& strlen(self::$config['system']['baseurl'])) {
			// get_baseurl() is a heavily used function.
			// Do not use punify() here until we find a library that performs better than what we have now.
			// $url = punify(self::$config['system']['baseurl']);
			$url = self::$config['system']['baseurl'];
			$url = trim($url,'\\/');
			return $url;
		}

		$scheme = self::$scheme;

		self::$baseurl = $scheme . "://" . punify(self::$hostname) . ((isset(self::$path) && strlen(self::$path)) ? '/' . self::$path : '' );

		return self::$baseurl;
	}

	public static function set_baseurl($url) {
		if(is_array(self::$config)
			&& array_key_exists('system',self::$config)
			&& is_array(self::$config['system'])
			&& array_key_exists('baseurl',self::$config['system'])
			&& strlen(self::$config['system']['baseurl'])) {
			$url = punify(self::$config['system']['baseurl']);
			$url = trim($url,'\\/');
		}

		$parsed = @parse_url($url);

		self::$baseurl = $url;

		if($parsed !== false) {
			self::$scheme = $parsed['scheme'];

			self::$hostname = punify($parsed['host']);
			if(x($parsed,'port'))
				self::$hostname .= ':' . $parsed['port'];
			if(x($parsed,'path'))
				self::$path = trim($parsed['path'],'\\/');
		}
	}

	public static function get_scheme() {
		return self::$scheme;
	}

	public static function get_hostname() {
		return self::$hostname;
	}

	public static function set_hostname($h) {
		self::$hostname = $h;
	}

	public static function set_path($p) {
		self::$path = trim(trim($p), '/');
	}

	public static function get_path() {
		return self::$path;
	}

	public static function get_channel_links() {
		$s = '';
		$x = self::$channel_links;
		if ($x && is_array($x) && count($x)) {
			foreach ($x as $y) {
				if ($s) {
					$s .= ',';
				}
				$s .= '<' . $y['url'] . '>; rel="' . $y['rel'] . '"; type="' . $y['type'] . '"';
			}
		}
		return $s;
	}
	public static function set_account($acct) {
		self::$account = $acct;
	}

	public static function get_account() {
		return self::$account;
	}

	public static function set_channel($channel) {
		self::$channel = $channel;
	}

	public static function get_channel() {
		return self::$channel;
	}

	public static function set_observer($xchan) {
		self::$observer = $xchan;
	}


	public static function get_observer() {
		return self::$observer;
	}

	public static function set_perms($perms) {
		self::$perms = $perms;
	}

	public static function get_perms() {
		return self::$perms;
	}

	public static function set_oauth_key($consumer_id) {
		self::$oauth_key = $consumer_id;
	}

	public static function get_oauth_key() {
		return self::$oauth_key;
	}

	public static function get_apps() {
		return self::$apps;
	}

	public static function set_apps($arr) {
		self::$apps = $arr;
	}

	public static function set_groups($g) {
		self::$groups = $g;
	}

	public static function get_groups() {
		return self::$groups;
	}

	public static function set_pager_total($n) {
		self::$pager['total'] = intval($n);
	}

	public static function set_pager_itemspage($n) {
		self::$pager['itemspage'] = ((intval($n) > 0) ? intval($n) : 0);
		self::$pager['start'] = (self::$pager['page'] * self::$pager['itemspage']) - self::$pager['itemspage'];
	}

	public static function build_pagehead() {

		$user_scalable = ((local_channel()) ? get_pconfig(local_channel(),'system','user_scalable', 0) : 0);

		$preload_images = ((local_channel()) ? get_pconfig(local_channel(),'system','preload_images',0) : 0);

		$interval = ((local_channel()) ? get_pconfig(local_channel(),'system','update_interval',60000) : 60000);
		if ($interval < 10000) {
			$interval = 60000;
		}

		if (! x(self::$page,'title')) {
			self::$page['title'] = self::$config['system']['sitename'];
		}

		if (! self::$meta->get_field('og:title')) {
			self::$meta->set('og:title',self::$page['title']);
		}
		
		self::$meta->set('generator', System::get_platform_name());

		$i = head_get_icon();
		if (! $i) {
			$i = System::get_site_icon();
		}
		if ($i) {
			head_add_link(['rel' => 'shortcut icon', 'href' => $i ]);
		}

		$x = [ 'header' => '' ];
		/**
		 * @hooks build_pagehead
		 *   Called when creating the HTML page header.
		 *   * \e string \b header - Return the HTML header which should be added
		 */
		call_hooks('build_pagehead', $x);

		/* put the head template at the beginning of page['htmlhead']
		 * since the code added by the modules frequently depends on it
		 * being first
		 */

		self::$page['htmlhead'] = replace_macros(get_markup_template('head.tpl'),
			[
				'$preload_images'  => $preload_images,
				'$user_scalable'   => $user_scalable,
				'$query'           => urlencode(self::$query_string),
				'$baseurl'         => self::get_baseurl(),
				'$local_channel'   => local_channel(),
				'$metas'           => self::$meta->get(),
				'$plugins'         => $x['header'],
				'$update_interval' => $interval,
				'$head_css'        => head_get_css(),
				'$head_js'         => head_get_js(),
				'$linkrel'         => head_get_links(),
				'$js_strings'      => js_strings(),
				'$zid'             => get_my_address(),
				'$channel_id'      => self::$profile['uid']
			]
		) . self::$page['htmlhead'];

		// always put main.js at the end
		self::$page['htmlhead'] .= head_get_main_js();
	}

	/**
	* @brief Register template engine class.
	*
	* If $name is "", is used class static property $class::$name.
	*
	* @param string $class
	* @param string $name
	*/
	public static function register_template_engine($class, $name = '') {
		if (! $name) {
			$v = get_class_vars($class);
			if (x($v, "name")) {
				$name = $v['name'];
			}
		}
		if (! $name) {
			echo "template engine <tt>$class</tt> cannot be registered without a name.\n";
			killme();
		}
		self::$template_engines[$name] = $class;
	}

	/**
	* @brief Return template engine instance.
	*
	* If $name is not defined, return engine defined by theme, or default.
	*
	* @param string $name Template engine name
	*
	* @return object Template Engine instance
	*/
	public static function template_engine($name = '') {
		if ($name !== '') {
			$template_engine = $name;
		}
		else {
			$template_engine = 'smarty3';
			if (x(self::$theme, 'template_engine')) {
				$template_engine = self::$theme['template_engine'];
			}
		}

		if (isset(self::$template_engines[$template_engine])) {
			if (isset(self::$template_engine_instance[$template_engine])) {
				return self::$template_engine_instance[$template_engine];
			}
			else {
				$class = self::$template_engines[$template_engine];
				$obj = new $class;
				self::$template_engine_instance[$template_engine] = $obj;
				return $obj;
			}
		}

		// If we fell through to this step, it is considered fatal.
		
		echo "template engine <tt>$template_engine</tt> is not registered!\n";
		killme();
	}

	/**
	 * @brief Returns the active template engine.
	 *
	 * @return string
	 */
	public static function get_template_engine() {
		return self::$theme['template_engine'];
	}

	public static function set_template_engine($engine = 'smarty3') {
		self::$theme['template_engine'] = $engine;
	}

	public static function get_template_ldelim($engine = 'smarty3') {
		return self::$ldelim[$engine];
	}

	public static function get_template_rdelim($engine = 'smarty3') {
		return self::$rdelim[$engine];
	}

	public static function head_set_icon($icon) {
		self::$data['pageicon'] = $icon;
	}

	public static function head_get_icon() {
		$icon = self::$data['pageicon'];
		if ($icon && ! strpos($icon,'://')) {
			$icon = z_root() . $icon;
		}
		return $icon;
	}

} // End App class


/**
 * @brief Multi-purpose function to check variable state.
 *
 * Usage: x($var) or $x($array, 'key')
 *
 * returns false if variable/key is not set
 * if variable is set, returns 1 if has 'non-zero' value, otherwise returns 0.
 * e.g. x('') or x(0) returns 0;
 *
 * @param string|array $s variable to check
 * @param string $k key inside the array to check
 *
 * @return bool|int
 */
function x($s, $k = null) {
	if($k != null) {
		if((is_array($s)) && (array_key_exists($k, $s))) {
			if($s[$k])
				return (int) 1;
			return (int) 0;
		}
		return false;
	}
	else {
		if(isset($s)) {
			if($s) {
				return (int) 1;
			}
			return (int) 0;
		}
		return false;
	}
}


/**
 * @brief Called from db initialisation if db is dead.
 *
 * @ref include/system_unavailable.php will handle everything further.
 */
function system_unavailable() {
	include('include/system_unavailable.php');
	system_down();
	killme();
}


function clean_urls() {

	//	if(App::$config['system']['clean_urls'])
	return true;
	//	return false;
}

function z_path() {
	$base = z_root();
	if(! clean_urls())
		$base .= '/?q=';

	return $base;
}

/**
 * @brief Returns the baseurl.
 *
 * @see App::get_baseurl()
 *
 * @return string
 */
function z_root() {
	return App::get_baseurl();
}

/**
 * @brief Return absolute URL for given $path.
 *
 * @param string $path
 *
 * @return string
 */
function absurl($path) {
	if (strpos($path, '/') === 0)
		return z_path() . $path;

	return $path;
}

function os_mkdir($path, $mode = 0777, $recursive = false) {
	$oldumask = @umask(0);
	$result = @mkdir($path, $mode, $recursive);
	@umask($oldumask);
	return $result;
}


/**
 * @brief Recursively delete a directory.
 *
 * @param string $path
 * @return boolean
 */
function rrmdir($path) {
	if (is_dir($path) === true) {
		$files = array_diff(scandir($path), array('.', '..'));
		foreach ($files as $file) {
			rrmdir(realpath($path) . '/' . $file);
		}
		return rmdir($path);
	}
	elseif (is_file($path) === true) {
		return unlink($path);
	}

	return false;
}


/**
 * @brief Function to check if request was an AJAX (xmlhttprequest) request.
 *
 * @return boolean
 */
function is_ajax() {
	return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
}

function killme_if_ajax() {
	if (is_ajax()) {
		killme();
	}
}

/**
 * Primarily involved with database upgrade, but also sets the
 * base url for use in cmdline programs which don't have
 * $_SERVER variables, and synchronising the state of installed plugins.
 */
function check_config() {

	$saved = get_config('system','urlverify');
	if (! $saved) {
		set_config('system','urlverify', bin2hex(z_root()));
	}
	
	if(($saved) && ($saved != bin2hex(z_root()))) {
		// our URL changed. Do something.

		$oldurl = hex2bin($saved);
		logger('Baseurl changed!');

		$oldhost = substr($oldurl, strpos($oldurl, '//') + 2);
		$host = substr(z_root(), strpos(z_root(), '//') + 2);

		$is_ip_addr = ((preg_match("/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/",$host)) ? true : false);
		$was_ip_addr = ((preg_match("/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/",$oldhost)) ? true : false);
		// only change the url to an ip address if it was already an ip and not a dns name
		if((! $is_ip_addr) || ($is_ip_addr && $was_ip_addr)) {
			fix_system_urls($oldurl,z_root());
			set_config('system', 'urlverify', bin2hex(z_root()));
		}
		else
			logger('Attempt to change baseurl from a DNS name to an IP address was refused.');
	}

	// This will actually set the url to the one stored in .htconfig, and ignore what
	// we're passing - unless we are installing and it has never been set.

	App::set_baseurl(z_root());

	// Make sure each site has a system channel.  This is now created on install
	// so we just need to keep this around a couple of weeks until the hubs that
	// already exist have one
	$syschan_exists = get_sys_channel();
	if (! $syschan_exists)
		create_sys_channel();

	$x = new DB_Upgrade(DB_UPDATE_VERSION);

	plugins_sync();

	load_hooks();

	check_cron_broken();

}


function fix_system_urls($oldurl, $newurl) {


	logger('fix_system_urls: renaming ' . $oldurl . '  to ' . $newurl);

	// Basically a site rename, but this can happen if you change from http to https for instance - even if the site name didn't change
	// This should fix URL changes on our site, but other sites will end up with orphan hublocs which they will try to contact and will
	// cause wasted communications.
	// What we need to do after fixing this up is to send a revocation of the old URL to every other site that we communicate with so
	// that they can clean up their hubloc tables (this includes directories).
	// It's a very expensive operation so you don't want to have to do it often or after your site gets to be large.

	$r = q("select xchan.*, hubloc.* from xchan left join hubloc on xchan_hash = hubloc_hash where hubloc_url like '%s'",
		dbesc($oldurl . '%')
	);

	if($r) {
		foreach($r as $rv) {
			$channel_address = substr($rv['hubloc_addr'],0,strpos($rv['hubloc_addr'],'@'));

			// get the associated channel. If we don't have a local channel, do nothing for this entry.

			$c = q("select * from channel where channel_hash = '%s' limit 1",
				dbesc($rv['hubloc_hash'])
			);
			if(! $c)
				continue;

			$parsed = @parse_url($newurl);
			if(! $parsed)
				continue;
			$newhost = $parsed['host'];

			// sometimes parse_url returns unexpected results.

			if(strpos($newhost,'/') !== false)
				$newhost = substr($newhost,0,strpos($newhost,'/'));

			$rhs = $newhost . (($parsed['port']) ? ':' . $parsed['port'] : '');

			// paths aren't going to work. You have to be at the (sub)domain root
			// . (($parsed['path']) ? $parsed['path'] : '');

			// The xchan_url might point to another nomadic identity clone

			$replace_xchan_url = ((strpos($rv['xchan_url'],$oldurl) !== false) ? true : false);

			$x = q("update xchan set xchan_addr = '%s', xchan_url = '%s', xchan_connurl = '%s', xchan_follow = '%s', xchan_connpage = '%s', xchan_photo_l = '%s', xchan_photo_m = '%s', xchan_photo_s = '%s', xchan_photo_date = '%s' where xchan_hash = '%s'",
				dbesc($channel_address . '@' . $rhs),
				dbesc(($replace_xchan_url) ? str_replace($oldurl,$newurl,$rv['xchan_url']) : $rv['xchan_url']),
				dbesc(str_replace($oldurl,$newurl,$rv['xchan_connurl'])),
				dbesc(str_replace($oldurl,$newurl,$rv['xchan_follow'])),
				dbesc(str_replace($oldurl,$newurl,$rv['xchan_connpage'])),
				dbesc(str_replace($oldurl,$newurl,$rv['xchan_photo_l'])),
				dbesc(str_replace($oldurl,$newurl,$rv['xchan_photo_m'])),
				dbesc(str_replace($oldurl,$newurl,$rv['xchan_photo_s'])),
				dbesc(datetime_convert()),
				dbesc($rv['xchan_hash'])
			);

			$y = q("update hubloc set hubloc_addr = '%s', hubloc_url = '%s', hubloc_id_url = '%s', hubloc_url_sig = '%s', hubloc_host = '%s', hubloc_callback = '%s' where hubloc_hash = '%s' and hubloc_url = '%s'",
				dbesc($channel_address . '@' . $rhs),
				dbesc($newurl),
				dbesc(str_replace($oldurl,$newurl,$rv['hubloc_id_url'])),
				dbesc(Libzot::sign($newurl,$c[0]['channel_prvkey'])),
				dbesc($newhost),
				dbesc($newurl . '/post'),
				dbesc($rv['xchan_hash']),
				dbesc($oldurl)
			);

			$z = q("update profile set photo = '%s', thumb = '%s' where uid = %d",
				dbesc(str_replace($oldurl,$newurl,$rv['xchan_photo_l'])),
				dbesc(str_replace($oldurl,$newurl,$rv['xchan_photo_m'])),
				intval($c[0]['channel_id'])
			);

			$m = q("select abook_id, abook_instance from abook where abook_instance like '%s' and abook_channel = %d",
				dbesc('%' . $oldurl . '%'),
				intval($c[0]['channel_id'])
			);
			if($m) {
				foreach($m as $mm) {
					q("update abook set abook_instance = '%s' where abook_id = %d",
						dbesc(str_replace($oldurl,$newurl,$mm['abook_instance'])),
						intval($mm['abook_id'])
					);
				}
			}

			Run::Summon( [ 'Notifier', 'refresh_all', $c[0]['channel_id'] ]);
		}
	}


	// fix links in apps

	$a = q("select id, app_url, app_photo from app where app_url like '%s' OR app_photo like '%s'",
		dbesc('%' . $oldurl . '%'),
		dbesc('%' . $oldurl . '%')
	);
	if($a) {
		foreach($a as $aa) {
			q("update app set app_url = '%s', app_photo = '%s' where id = %d",
				dbesc(str_replace($oldurl,$newurl,$aa['app_url'])),
				dbesc(str_replace($oldurl,$newurl,$aa['app_photo'])),
				intval($aa['id'])
			);
		}
	}

	// now replace any remote xchans whose photos are stored locally (which will be most if not all remote xchans)

	$r = q("select * from xchan where xchan_photo_l like '%s'",
		dbesc($oldurl . '%')
	);

	if($r) {
		foreach($r as $rv) {
			$x = q("update xchan set xchan_photo_l = '%s', xchan_photo_m = '%s', xchan_photo_s = '%s' where xchan_hash = '%s'",
				dbesc(str_replace($oldurl,$newurl,$rv['xchan_photo_l'])),
				dbesc(str_replace($oldurl,$newurl,$rv['xchan_photo_m'])),
				dbesc(str_replace($oldurl,$newurl,$rv['xchan_photo_s'])),
				dbesc($rv['xchan_hash'])
			);
		}
	}
}


/**
 * @brief Wrapper for adding a login box.
 *
 * If $register == true provide a registration link. This will most always depend
 * on the value of App::$config['system']['register_policy'].
 * Returns the complete html for inserting into the page
 *
 * @param boolean $register (optional) default false
 * @param string $form_id (optional) default \e main-login
 * @param boolean $hiddens (optional) default false
 * @param boolean $login_page (optional) default true
 * @return string Parsed HTML code.
 */
function login($register = false, $form_id = 'main-login', $hiddens = false, $login_page = true) {

	$o = '';
	$reg = null;

	// Here's the current description of how the register link works (2018-05-15)

	// Register links are enabled on the site home page and login page and navbar. 
	// They are not shown by default on other pages which may require login.

	// system.register_link can over-ride the default behaviour and redirect to an arbitrary
	// webpage for paid/custom or organisational registrations, regardless of whether
	// registration is allowed.

	// system.register_link may or may not be the same destination as system.sellpage

	// system.sellpage is the destination linked from the /pubsites page on other sites. If 
	// system.sellpage is not set, the 'register' link in /pubsites will go to 'register' on your
	// site. 
	
	// If system.register_link is set to the word 'none', no registration link will be shown on
	// your site.


	// If the site supports SSL and this isn't a secure connection, reload the page using https
	
	if (intval($_SERVER['SERVER_PORT']) === 80 && strpos(z_root(), 'https://') !== false) {
		goaway(z_root() . '/' . App::$query_string);
	}

	$register_policy = get_config('system','register_policy');

	$reglink = get_config('system', 'register_link', z_root() . '/register');

	if($reglink !== 'none') {
		$reg = [
			'title' => t('Create an account to access services and applications'),
			'desc'  => t('Register'),
			'link'  => $reglink
		];
	}

	$dest_url = z_root() . '/' . App::$query_string;

	if(local_channel()) {
		$tpl = get_markup_template('logout.tpl');
	}
	else {
		$tpl = get_markup_template('login.tpl');
		if(strlen(App::$query_string))
			$_SESSION['login_return_url'] = App::$query_string;
	}

	$o .= replace_macros($tpl, [
		'$dest_url'     => $dest_url,
		'$login_page'   => $login_page,
		'$logout'       => t('Logout'),
		'$login'        => t('Login'),
		'$remote_login' => t('Remote Authentication'),
		'$form_id'      => $form_id,
		'$lname'        => [ 'username', t('Login/Email') , '', '' ],
		'$lpassword'    => [ 'password', t('Password'), '', '' ],
		'$remember_me'  => [ (($login_page) ? 'remember' : 'remember_me'), t('Remember me'), '', '', [ t('No'),t('Yes') ] ],
		'$hiddens'      => $hiddens,
		'$register'     => $reg,
		'$lostpass'     => t('Forgot your password?'),
		'$lostlink'     => t('Password Reset'),
	]);

	/**
	 * @hooks login_hook
	 *   Called when generating the login form.
	 *   * \e string with parsed HTML
	 */
	call_hooks('login_hook', $o);

	return $o;
}


/**
 * @brief Used to end the current process, after saving session state.
 */
function killme() {

	register_shutdown_function('shutdown');
	exit;
}

/**
 * @brief Redirect to another URL and terminate this process.
 */
function goaway($s) {
	header("Location: $s");
	killme();
}

function shutdown() {

}

/**
 * @brief Returns the entity id of locally logged in account or false.
 *
 * Returns numeric account_id if authenticated or false. It is possible to be
 * authenticated and not connected to a channel.
 *
 * @return int|bool account_id or false
 */

function get_account_id() {

	if(intval($_SESSION['account_id']))
		return intval($_SESSION['account_id']);

	if(App::$account)
		return intval(App::$account['account_id']);

	return false;
}

/**
 * @brief Returns the entity id (channel_id) of locally logged in channel or false.
 *
 * Returns authenticated numeric channel_id if authenticated and connected to
 * a channel or 0. Sometimes referred to as $uid in the code.
 *
 * Before 2.1 this function was called local_user().
 *
 * @since 2.1
 * @return int|bool channel_id or false
 */

function local_channel() {
	if(session_id()
		&& array_key_exists('authenticated',$_SESSION) && $_SESSION['authenticated']
		&& array_key_exists('uid',$_SESSION) && intval($_SESSION['uid']))
		return intval($_SESSION['uid']);

	return false;
}

/**
 * @brief Returns a xchan_hash (visitor_id) of remote authenticated visitor
 * or false.
 *
 * Returns authenticated string hash of Red global identifier (xchan_hash), if
 * authenticated via remote auth, or an empty string.
 *
 * Before 2.1 this function was called remote_user().
 *
 * @since 2.1
 * @return string|bool visitor_id or false
 */
function remote_channel() {
	if(session_id()
		&& array_key_exists('authenticated',$_SESSION) && $_SESSION['authenticated']
		&& array_key_exists('visitor_id',$_SESSION) && $_SESSION['visitor_id'])
		return $_SESSION['visitor_id'];

	return false;
}


function can_view_public_stream() {

	if (observer_prohibited(true)) {
		return false;
	}

	if (! (intval(get_config('system','open_pubstream',1)))) {
		if (! local_channel()) {
			return false;
		}
	}

	$site_firehose = ((intval(get_config('system','site_firehose',0))) ? true : false);
	$net_firehose  = ((get_config('system','disable_discover_tab',1)) ? false : true);

	if (! ($site_firehose || $net_firehose)) {
		return false;
	}

	return true;
}


/**
 * @brief Show an error or alert text on next page load.
 *
 * Contents of $s are displayed prominently on the page the next time
 * a page is loaded. Usually used for errors or alerts.
 *
 * For informational text use info().
 *
 * @param string $s Text to display
 */
function notice($s) {
	if(! session_id())
		return;

	if(! x($_SESSION, 'sysmsg')) $_SESSION['sysmsg'] = [];

	// ignore duplicated error messages which haven't yet been displayed
	// - typically seen as multiple 'permission denied' messages
	// as a result of auto-reloading a protected page with &JS=1

	if(in_array($s, $_SESSION['sysmsg']))
		return;

	if(App::$interactive) {
		$_SESSION['sysmsg'][] = $s;
	}
}

/**
 * @brief Show an information text on next page load.
 *
 * Contents of $s are displayed prominently on the page the next time a page is
 * loaded. Usually used for information.
 *
 * For error and alerts use notice().
 *
 * @param string $s Text to display
 */
function info($s) {
	if(! session_id())
		return;
	if(! x($_SESSION, 'sysmsg_info'))
		$_SESSION['sysmsg_info'] = [];

	if(in_array($s, $_SESSION['sysmsg_info']))
		return;

	if(App::$interactive)
		$_SESSION['sysmsg_info'][] = $s;
}

/**
 * @brief Wrapper around config to limit the text length of an incoming message.
 *
 * @return int
 */
function get_max_import_size() {
	return(intval(get_config('system', 'max_import_size')));
}


/**
 * @brief Wrap calls to proc_close(proc_open()) and call hook
 * so plugins can take part in process :)
 *
 * args:
 * $cmd program to run
 *  next args are passed as $cmd command line
 *
 * e.g.:
 * @code{.php}proc_run("ls", "-la", "/tmp");@endcode
 *
 * $cmd and string args are surrounded with ""
 */
function proc_run() {

	$args = func_get_args();

	if(! count($args))
		return;

	$args = flatten_array_recursive($args);

	$arr =  [
		'args' => $args,
		'run_cmd' => true
	];

	/**
	 * @hooks proc_run
	 *   Called when invoking PHP sub processes.
	 *   * \e array \b args
	 *   * \e boolean \b run_cmd
	 */

	call_hooks('proc_run', $arr);

	if (! $arr['run_cmd'])
		return;

	if (count($args) && $args[0] === 'php')
		$args[0] = ((x(App::$config,'system')) && (x(App::$config['system'],'php_path')) && (strlen(App::$config['system']['php_path'])) ? App::$config['system']['php_path'] : 'php');


	$args = array_map('escapeshellarg',$args);
	$cmdline = implode($args," ");

	if (is_windows()) {
		$cwd = getcwd();
		$cmd = "cmd /c start \"title\" /D \"$cwd\" /b $cmdline";
		proc_close(proc_open($cmd, [], $foo));
	}
	else {
		if (get_config('system','use_proc_open'))
			proc_close(proc_open($cmdline ." &", [], $foo));
		else
			exec($cmdline . ' > /dev/null &');
	}
}

/**
 * @brief Checks if we are running on M$ Windows.
 *
 * @return bool true if we run on M$ Windows
 *
 * It's possible you might be able to run on WAMP or XAMPP, and this
 * has been accomplished, but is not officially supported. Good luck.
 *
 */
function is_windows() {
	return ((strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? true : false);
}

/**
 * @brief Check if current user has admin role.
 *
 * Check if the current user has ACCOUNT_ROLE_ADMIN.
 *
 * @return bool true if user is an admin
 */
function is_site_admin() {

	if(! session_id())
		return false;

	if($_SESSION['delegate'])
		return false;

	if((intval($_SESSION['authenticated']))
		&& (is_array(App::$account))
		&& (App::$account['account_roles'] & ACCOUNT_ROLE_ADMIN))
		return true;

	return false;
}

/**
 * @brief Check if current user has developer role.
 *
 * Check if the current user has ACCOUNT_ROLE_DEVELOPER.
 *
 * @return bool true if user is a developer
 */
function is_developer() {

	if(! session_id())
		return false;

	if((intval($_SESSION['authenticated']))
		&& (is_array(App::$account))
		&& (App::$account['account_roles'] & ACCOUNT_ROLE_DEVELOPER))
		return true;

	return false;
}


function load_contact_links($uid) {

	$ret = [];

	if(! $uid || x(App::$contacts,'empty'))
		return;

//	logger('load_contact_links');

	$r = q("SELECT abook_id, abook_flags, abook_my_perms, abook_their_perms, xchan_hash, xchan_photo_m, xchan_name, xchan_url, xchan_network from abook left join xchan on abook_xchan = xchan_hash where abook_channel = %d ",
		intval($uid)
	);
	if($r) {
		foreach($r as $rv) {
			$ret[$rv['xchan_hash']] = $rv;
		}
	}
	else
		$ret['empty'] = true;

	App::$contacts = $ret;
}


/**
 * @brief Returns querystring as string from a mapped array.
 *
 * @param array $params mapped array with query parameters
 * @param string $name of parameter, default null
 *
 * @return string
 */
function build_querystring($params, $name = null) {
	$ret = '';
	foreach($params as $key => $val) {
		if(is_array($val)) {
			if($name === null) {
				$ret .= build_querystring($val, $key);
			} else {
				$ret .= build_querystring($val, $name . "[$key]");
			}
		} else {
			$val = urlencode($val);
			if($name != null) {
				$ret .= $name . "[$key]" . "=$val&";
			} else {
				$ret .= "$key=$val&";
			}
		}
	}

	return $ret;
}


/**
 * @brief Much better way of dealing with c-style args.
 */
function argc() {
	return App::$argc;
}

function argv($x) {
	if(array_key_exists($x,App::$argv))
		return App::$argv[$x];

	return '';
}

function dba_timer() {
	return microtime(true);
}

/**
 * @brief Returns xchan_hash from the observer.
 *
 * Observer can be a local or remote channel.
 *
 * @return string xchan_hash from observer, otherwise empty string if no observer
 */
function get_observer_hash() {
	$observer = App::get_observer();
	if (is_array($observer)) {
		return $observer['xchan_hash'];
	}
	return '';
}

/**
 * @brief Returns the complete URL of the current page, e.g.: http(s)://something.com/network
 *
 * Taken from http://webcheatsheet.com/php/get_current_page_url.php
 *
 * @return string
 */
function curPageURL() {
	$pageURL = 'http';
	if ($_SERVER["HTTPS"] == "on") {
		$pageURL .= "s";
	}
	$pageURL .= "://";
	if ($_SERVER["SERVER_PORT"] != "80" && $_SERVER["SERVER_PORT"] != "443") {
		$pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
	} else {
		$pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
	}

	return $pageURL;
}

/**
 * @brief Returns a custom navigation by name???
 *
 * If no $navname provided load default page['nav']
 *
 * @todo not fully implemented yet
 *
 * @param string $navname
 *
 * @return mixed
 */
function get_custom_nav($navname) {
	if (! $navname)
		return App::$page['nav'];
	// load custom nav menu by name here
}

/**
 * @brief Loads a page definition file for a module.
 *
 * If there is no parsed Comanche template already load a module's pdl file
 * and parse it with Comanche.
 *
 */
function load_pdl() {

	App::$comanche = new Comanche();

	if (! count(App::$layout)) {

		$arr = [
			'module' => App::$module,
			'layout' => ''
		];
		/**
		 * @hooks load_pdl
		 *   Called when we load a PDL file or description.
		 *   * \e string \b module
		 *   * \e string \b layout
		 */
		call_hooks('load_pdl', $arr);
		$layout = $arr['layout'];

		$n = 'mod_' . App::$module . '.pdl' ;
		$u = App::$comanche->get_channel_id();
		if($u)
			$s = get_pconfig($u, 'system', $n);
		if(! $s)
			$s = $layout;

		if((! $s) && (($p = theme_include($n)) != ''))
			$s = @file_get_contents($p);
		elseif(file_exists('addon/'. App::$module . '/' . $n))
			$s = @file_get_contents('addon/'. App::$module . '/' . $n);

		$arr = [
			'module' => App::$module,
			'layout' => $s
		];
		call_hooks('alter_pdl',$arr);
		$s = $arr['layout'];

		if($s) {
			App::$comanche->parse($s);
			App::$pdl = $s;
		}
	}
}


function exec_pdl() {
	if(App::$pdl) {
		App::$comanche->parse(App::$pdl,1);
	}
}


/**
 * @brief build the page.
 *
 * Build the page - now that we have all the components
 *
 */
function construct_page() {

	exec_pdl();

	$comanche = ((count(App::$layout)) ? true : false);

	require_once(theme_include('theme_init.php'));

	$installing = false;

	$uid = ((App::$profile_uid) ? App::$profile_uid : local_channel());

	$navbar = get_config('system','navbar','default');
	if($uid) {
		$navbar = get_pconfig($uid,'system','navbar',$navbar);
	}

	if($comanche && App::$layout['navbar']) {
		$navbar = App::$layout['navbar'];
	}

	if (App::$module == 'setup') {
		$installing = true;
	} else {
		nav($navbar);
	}


	$current_theme = Theme::current();

	// logger('current_theme: ' . print_r($current_theme,true));
	// Theme::debug();

	if (($p = theme_include($current_theme[0] . '.js')) != '')
		head_add_js('/' . $p);

	if (($p = theme_include('mod_' . App::$module . '.php')) != '')
		require_once($p);

	require_once('include/js_strings.php');

	if (x(App::$page, 'template_style'))
		head_add_css(App::$page['template_style'] . '.css');
	else
		head_add_css(((x(App::$page, 'template')) ? App::$page['template'] : 'default' ) . '.css');

	if (($p = theme_include('mod_' . App::$module . '.css')) != '')
		head_add_css('mod_' . App::$module . '.css');

	head_add_css(Theme::url($installing));

	if (($p = theme_include('mod_' . App::$module . '.js')) != '')
		head_add_js('mod_' . App::$module . '.js');

	App::build_pagehead();

	if(App::$page['pdl_content']) {
		App::$page['content'] = App::$comanche->region(App::$page['content']);
	}

	// Let's say we have a comanche declaration '[region=nav][/region][region=content]$nav $content[/region]'.
	// The text 'region=' identifies a section of the layout by that name. So what we want to do here is leave
	// App::$page['nav'] empty and put the default content from App::$page['nav'] and App::$page['section']
	// into a new region called App::$data['content']. It is presumed that the chosen layout file for this comanche page
	// has a '<content>' element instead of a '<section>'.

	// This way the Comanche layout can include any existing content, alter the layout by adding stuff around it or changing the
	// layout completely with a new layout definition, or replace/remove existing content.

	if($comanche) {
		$arr = [
				'module' => App::$module,
				'layout' => App::$layout
		];
		/**
		 * @hooks construct_page
		 *   General purpose hook to provide content to certain page regions.
		 *   Called when constructing the Comanche page.
		 *   * \e string \b module
		 *   * \e string \b layout
		 */
		call_hooks('construct_page', $arr);
		App::$layout = $arr['layout'];

		foreach(App::$layout as $k => $v) {
			if((strpos($k, 'region_') === 0) && strlen($v)) {
				if(strpos($v, '$region_') !== false) {
					$v = preg_replace_callback('/\$region_([a-zA-Z0-9]+)/ism', array(App::$comanche,'replace_region'), $v);
				}

				// And a couple of convenience macros
				if(strpos($v, '$htmlhead') !== false) {
					$v = str_replace('$htmlhead', App::$page['htmlhead'], $v);
				}
				if(strpos($v, '$nav') !== false) {
					$v = str_replace('$nav', App::$page['nav'], $v);
				}
				if(strpos($v, '$content') !== false) {
					$v = str_replace('$content', App::$page['content'], $v);
				}

				App::$page[substr($k, 7)] = $v;
			}
		}
	}

	$page    = App::$page;
	$profile = App::$profile;

	// There's some experimental support for right-to-left text in the view/php/default.php page template.
	// In v1.9 we started providing direction preference in the per language hstrings.php file
	// This requires somebody with fluency in a RTL language to make happen

	$page['direction'] = 0; // ((App::$rtl) ? 1 : 0);

	header("Content-type: text/html; charset=utf-8");

	// security headers - see https://securityheaders.io

	if(App::get_scheme() === 'https' && App::$config['system']['transport_security_header'])
		header("Strict-Transport-Security: max-age=31536000");

	if(App::$config['system']['content_security_policy']) {
		$cspsettings = Array (
			'script-src' => Array ("'self'","'unsafe-inline'","'unsafe-eval'"),
			'style-src' => Array ("'self'","'unsafe-inline'")
		);
		call_hooks('content_security_policy',$cspsettings);

		// Legitimate CSP directives (cxref: https://content-security-policy.com/)
		$validcspdirectives=Array(
			"default-src", "script-src", "style-src",
			"img-src", "connect-src", "font-src",
			"object-src", "media-src", 'frame-src',
			'sandbox', 'report-uri', 'child-src',
			'form-action', 'frame-ancestors', 'plugin-types'
		);
		$cspheader = "Content-Security-Policy:";
		foreach ($cspsettings as $cspdirective => $csp) {
			if (!in_array($cspdirective,$validcspdirectives)) {
                                logger("INVALID CSP DIRECTIVE: ".$cspdirective,LOGGER_DEBUG);
				continue;
			}
			$cspsettingsarray=array_unique($cspsettings[$cspdirective]);
			$cspsetpolicy = implode(' ',$cspsettingsarray);
			if ($cspsetpolicy) {
				$cspheader .= " ".$cspdirective." ".$cspsetpolicy.";";
			}
		}
		header($cspheader);
	}

	if(App::$config['system']['x_security_headers']) {
		header("X-Frame-Options: SAMEORIGIN");
		header("X-Xss-Protection: 1; mode=block;");
		header("X-Content-Type-Options: nosniff");
	}

	if(App::$config['system']['public_key_pins']) {
		header("Public-Key-Pins: " . App::$config['system']['public_key_pins']);
	}

	require_once(theme_include(
		((x(App::$page, 'template')) ? App::$page['template'] : 'default' ) . '.php' )
	);
}

/**
 * @brief Returns appplication root directory.
 *
 * @return string
 */
function appdirpath() {
	return dirname(__FILE__);
}

/**
 * @brief Set a pageicon.
 *
 * @param string $icon
 */
function head_set_icon($icon) {

	App::$data['pageicon'] = $icon;

}

/**
 * @brief Get the pageicon.
 *
 * @return string absolut path to pageicon
 */
function head_get_icon() {

	$icon = App::$data['pageicon'];
	if($icon && ! strpos($icon, '://'))
		$icon = z_root() . $icon;

	return $icon;
}

/**
 * @brief Return the Realm of the directory.
 *
 * @return string
 */
function get_directory_realm() {
	if($x = get_config('system', 'directory_realm'))
		return $x;

	return DIRECTORY_REALM;
}

/**
 * @brief Return relative date of last completed poller execution.
 *
 * @return string relative date of last completed poller execution
 */
function get_poller_runtime() {
	$t = get_config('system', 'lastpoll');

	return relative_date($t);
}

function z_get_upload_dir() {
	$upload_dir = get_config('system','uploaddir');
	if(! $upload_dir)
		$upload_dir = ini_get('upload_tmp_dir');
	if(! $upload_dir)
		$upload_dir = sys_get_temp_dir();

	return $upload_dir;
}

function z_get_temp_dir() {
	$temp_dir = get_config('system','tempdir');
	if(! $temp_dir)
		$temp_dir = sys_get_temp_dir();

	return $upload_dir;
}


/**
 * @brief Check if server certificate is valid.
 *
 * Notify admin if not.
 */
function z_check_cert() {
	if(strpos(z_root(), 'https://') !== false) {
		$x = z_fetch_url(z_root() . '/siteinfo.json');
		if(! $x['success']) {
			$recurse = 0;
			$y = z_fetch_url(z_root() . '/siteinfo.json', false, $recurse, ['novalidate' => true]);
			if($y['success'])
				cert_bad_email();
		}
	}
}


/**
 * @brief Send email to admin if server has an invalid certificate.
 *
 * If a hub is available over https it must have a publicly valid certificate.
 */
function cert_bad_email() {
	return z_mail(
		[
			'toEmail'        => App::$config['system']['admin_email'],
			'messageSubject' => sprintf(t('[$Projectname] Website SSL error for %s'), App::get_hostname()),
			'textVersion'    => replace_macros(get_intltext_template('cert_bad_eml.tpl'),
				[
					'$sitename' => App::$config['system']['sitename'],
					'$siteurl'  => z_root(),
					'$error'    => t('Website SSL certificate is not valid. Please correct.')
				]
			)
		]
	);

}



/**
 * @brief Send warnings every 3-5 days if cron is not running.
 */
function check_cron_broken() {

	$d = get_config('system','lastcron');

	if((! $d) || ($d < datetime_convert('UTC','UTC','now - 4 hours'))) {
		Run::Summon(array('Cron'));
		set_config('system','lastcron',datetime_convert());
	}

	$t = get_config('system','lastcroncheck');
	if(! $t) {
		// never checked before. Start the timer.
		set_config('system','lastcroncheck',datetime_convert());
		return;
	}

	if($t > datetime_convert('UTC','UTC','now - 3 days')) {
		// Wait for 3 days before we do anything so as not to swamp the admin with messages
		return;
	}

	set_config('system','lastcroncheck',datetime_convert());

	if(($d) && ($d > datetime_convert('UTC','UTC','now - 3 days'))) {
		// Scheduled tasks have run successfully in the last 3 days.
		return;
	}

	return z_mail(
		[
			'toEmail'        => App::$config['system']['admin_email'],
			'messageSubject' => sprintf(t('[$Projectname] Cron tasks not running on %s'), App::get_hostname()),
			'textVersion'    => replace_macros(get_intltext_template('cron_bad_eml.tpl'),
				[
					'$sitename' => App::$config['system']['sitename'],
					'$siteurl'  =>  z_root(),
					'$error'    => t('Cron/Scheduled tasks not running.'),
					'$lastdate' => (($d)? $d : t('never'))
				]
			)
		]
	);
}


/**
 * @brief
 *
 * @param boolean $allow_account (optional) default false
 * @return boolean
 */
function observer_prohibited($allow_account = false) {
	if($allow_account) {
		return (((get_config('system', 'block_public')) && (! get_account_id()) && (! remote_channel())) ? true : false );
	}
	return (((get_config('system', 'block_public')) && (! local_channel()) && (! remote_channel())) ? true : false );
}

function get_safemode() {
	if (! array_key_exists('safemode', $_SESSION)) {
		$_SESSION['safemode'] = 1;
	}
	return intval($_SESSION['safemode']);
}

function supported_imagetype($x) {
	return in_array($x, [ IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP ]);
}