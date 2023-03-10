<?php

namespace Code\Lib;

use App;
use Code\Access\PermissionLimits;
use Code\Access\Permissions;

/**
 * Encapsulates information the ACL dialog requires to describe
 * permission settings for an item with an empty ACL.
 * i.e the caption, icon, and tooltip for the no-ACL option in the ACL dialog.
 */
class PermissionDescription
{

    private $global_perm;
    private $channel_perm;
    private $fallback_description;

    /**
     * Constructor is private.
     * Use static methods fromGlobalPermission(), fromStandalonePermission(),
     * or fromDescription() to create instances.
     *
     * @internal
     * @param int $global_perm
     * @param int $channel_perm
     * @param string $description (optional) default empty
     */
    private function __construct($global_perm, $channel_perm, $description = '')
    {
        $this->global_perm  = $global_perm;
        $this->channel_perm = $channel_perm;
        $this->fallback_description = ($description  == '') ? t('Visible to your default audience') : $description;
    }

    /**
     * If the interpretation of an empty ACL can't be summarised with a global default permission
     * or a specific permission setting then use this method and describe what it means instead.
     * Remember to localize the description first.
     *
     * @param  string $description - the localized caption for the no-ACL option in the ACL dialog.
     * @return PermissionDescription
     */
    public static function fromDescription($description)
    {
        return new PermissionDescription('', 0x80000, $description);
    }

    /**
     * Use this method only if the interpretation of an empty ACL doesn't fall back to a global
     * default permission. You should pass one of the constants from boot.php - PERMS_PUBLIC,
     * PERMS_NETWORK etc.
     *
     * @param int $perm - a single enumerated constant permission - PERMS_PUBLIC, PERMS_NETWORK etc.
     * @return PermissionDescription
     */
    public static function fromStandalonePermission($perm)
    {

        $result = new PermissionDescription('', $perm);

        $checkPerm = $result->get_permission_description();
        if ($checkPerm == $result->fallback_description) {
            $result = null;
            logger('null PermissionDescription from unknown standalone permission: ' . $perm, LOGGER_DEBUG, LOG_ERR);
        }

        return $result;
    }

    /**
     * This is the preferred way to create a PermissionDescription, as it provides the most details.
     * Use this method if you know an empty ACL will result in one of the global default permissions
     * being used, such as channel_r_stream (for which you would pass 'view_stream').
     *
     * @param  string $permname - a key for the global perms array from get_perms() in permissions.php,
     *         e.g. 'view_stream', 'view_profile', etc.
     * @return PermissionDescription
     */
    public static function fromGlobalPermission($permname)
    {

        $result = null;

        $global_perms = Permissions::Perms();

        if (array_key_exists($permname, $global_perms)) {
            $channelPerm = PermissionLimits::Get(App::$channel['channel_id'], $permname);

            $result = new PermissionDescription('', $channelPerm);
        } else {
            // The acl dialog can handle null arguments, but it shouldn't happen
            logger('null PermissionDescription from unknown global permission: ' . $permname, LOGGER_DEBUG, LOG_ERR);
        }

        return $result;
    }

    /**
     * Gets a localized description of the permission, or a generic message if the permission
     * is unknown.
     *
     * @return string description
     */
    public function get_permission_description()
    {

        switch ($this->channel_perm) {
            case 0:
                return t('Only me');
            case PERMS_PUBLIC:
                return t('Public');
            case PERMS_NETWORK:
                return t('Anybody in the $Projectname network');
            case PERMS_SITE:
                return sprintf(t('Any account on %s'), App::get_hostname());
            case PERMS_CONTACTS:
                return t('Any of my connections');
            case PERMS_SPECIFIC:
                return t('Only connections I specifically allow');
            case PERMS_AUTHED:
                return t('Anybody authenticated (could include visitors from other networks)');
            case PERMS_PENDING:
                return t('Any connections including those who haven\'t yet been approved');
            default:
                return $this->fallback_description;
        }
    }

    /**
     * Returns an icon css class name if an appropriate one is available, e.g. "fa-globe" for Public,
     * otherwise returns empty string.
     *
     * @return string icon css class name (often FontAwesome)
     */
    public function get_permission_icon()
    {

        switch ($this->channel_perm) {
            case 0:
                return 'fa-eye-slash';
            case PERMS_PUBLIC:
                return 'fa-globe';
            case PERMS_NETWORK:
                return 'fa-share-alt-square'; // fa-share-alt-square is very similiar to the hubzilla logo, but we should create our own logo class to use
            case PERMS_SITE:
                return 'fa-sitemap';
            case PERMS_CONTACTS:
                return 'fa-group';
            case PERMS_SPECIFIC:
                return 'fa-list';
            case PERMS_AUTHED:
                return '';
            case PERMS_PENDING:
                return '';
            default:
                return '';
        }
    }
}
