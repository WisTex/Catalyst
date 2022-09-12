<?php

namespace Code\Access;

/**
 * @brief AccessControl class which represents individual content ACLs.
 *
 * A class to hold an AccessControl object with allowed and denied contacts and
 * groups.
 *
 * After evaluating @ref ::Code::Access::PermissionLimits "PermissionLimits"
 * and @ref ::Code::Lib::Permcat "Permcat"s individual content ACLs are evaluated.
 * These answer the question "Can Joe view *this* album/photo?".
 */
class AccessControl
{
    /**
     * @brief Allow contacts
     * @var string
     */
    protected string $allow_cid;
    /**
     * @brief Allow groups
     * @var string
     */
    protected string $allow_gid;
    /**
     * @brief Deny contacts
     * @var string
     */
    protected string $deny_cid;
    /**
     * @brief Deny groups
     * @var string
     */
    protected string $deny_gid;
    /**
     * @brief Indicates if we are using the default constructor values or
     * values that have been set explicitly.
     * @var bool
     */
    protected bool $explicit;


    /**
     * @brief Constructor for AccessList class.
     *
     * @note The array to pass to the constructor is different from the array
     * that you provide to the set() or set_from_array() functions.
     *
     * @param array $channel A channel array, where these entries are evaluated:
     *   * \e string \b channel_allow_cid => string of allowed xchan_hash
     *   * \e string \b channel_allow_gid => string of allowed group_id
     *   * \e string \b channel_deny_cid => string of denied xchan_hash
     *   * \e string \b channel_deny_gid => string of denied group_id
     */
    public function __construct(mixed $channel)
    {
        if ($channel) {
            $this->allow_cid = $channel['channel_allow_cid'];
            $this->allow_gid = $channel['channel_allow_gid'];
            $this->deny_cid = $channel['channel_deny_cid'];
            $this->deny_gid = $channel['channel_deny_gid'];
        } else {
            $this->allow_cid = '';
            $this->allow_gid = '';
            $this->deny_cid = '';
            $this->deny_gid = '';
        }

        $this->explicit = false;
    }

    /**
     * @brief Determine if we are using the default constructor values
     * or values that have been set explicitly.
     *
     * @return bool
     */
    public function get_explicit(): bool
    {
        return $this->explicit;
    }

    /**
     * @brief Set access list from strings such as those in already
     * existing stored data items.
     *
     * @note The array to pass to this set function is different from the array
     * that you provide to the constructor or set_from_array().
     *
     * @param array $arr
     *   * \e string \b allow_cid => string of allowed xchan_hash
     *   * \e string \b allow_gid => string of allowed group_id
     *   * \e string \b deny_cid  => string of denied xchan_hash
     *   * \e string \b deny_gid  => string of denied group_id
     * @param bool $explicit (optional) default true
     */
    public function set(array $arr, bool $explicit = true): void
    {
        $this->allow_cid = (array_key_exists('allow_cid', $arr)) ? $arr['allow_cid'] : '';
        $this->allow_gid = (array_key_exists('allow_gid', $arr)) ? $arr['allow_gid'] : '';
        $this->deny_cid = (array_key_exists('deny_cid', $arr)) ? $arr['deny_cid'] : '';
        $this->deny_gid = (array_key_exists('deny_gid', $arr)) ? $arr['deny_gid'] : '';

        $this->explicit = $explicit;
    }

    /**
     * @brief Return an array consisting of the current access list components
     * where the elements are directly storable.
     *
     * @return array An associative array with:
     *   * \e string \b allow_cid => string of allowed xchan_hash
     *   * \e string \b allow_gid => string of allowed group_id
     *   * \e string \b deny_cid  => string of denied xchan_hash
     *   * \e string \b deny_gid  => string of denied group_id
     */
    public function get(): array
    {
        return [
            'allow_cid' => $this->allow_cid,
            'allow_gid' => $this->allow_gid,
            'deny_cid' => $this->deny_cid,
            'deny_gid' => $this->deny_gid,
        ];
    }

    /**
     * @brief Set access list components from arrays, such as those provided by
     * acl_selector().
     *
     * For convenience, a string (or non-array) input is assumed to be a
     * comma-separated list and auto-converted into an array.
     *
     * @note The array to pass to this set function is different from the array
     * that you provide to the constructor or set().
     *
     * @param array $arr An associative array with:
     *   * \e array|string \b contact_allow => array of xchan_hash or comma-seperated string
     *   * \e array|string \b group_allow   => array of group_id or comma-seperated string
     *   * \e array|string \b contact_deny  => array of xchan_hash or comma-seperated string
     *   * \e array|string \b group_deny    => array of group_id or comma-seperated string
     * @param bool $explicit (optional) default true
     */
    public function set_from_array(array $arr, bool $explicit = true): void
    {
        $this->allow_cid = perms2str((is_array($arr['contact_allow']))
            ? $arr['contact_allow'] : explode(',', $arr['contact_allow']));
        $this->allow_gid = perms2str((is_array($arr['group_allow']))
            ? $arr['group_allow'] : explode(',', $arr['group_allow']));
        $this->deny_cid = perms2str((is_array($arr['contact_deny']))
            ? $arr['contact_deny'] : explode(',', $arr['contact_deny']));
        $this->deny_gid = perms2str((is_array($arr['group_deny']))
            ? $arr['group_deny'] : explode(',', $arr['group_deny']));

        $this->explicit = $explicit;
    }

    /**
     * @brief Returns true if any access lists component is set.
     *
     * @return bool Return true if any of allow_* deny_* values is set.
     */
    public function is_private(): bool
    {
        return $this->allow_cid || $this->allow_gid || $this->deny_cid || $this->deny_gid;
    }
}
