<?php

namespace Code\Storage;

use App;
use Sabre;
use Sabre\DAV;
use Sabre\DAV\Browser\Plugin;
use Sabre\HTTP\Auth\Basic;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

/**
 * @brief Authentication backend class for DAV.
 *
 * This class also contains some data which is not necessary for authentication
 * like timezone settings.
 *
 * @extends Sabre\DAV\Auth\Backend\AbstractBasic
 *
 * @link http://github.com/friendica/red
 * @license http://opensource.org/unlicense.org
 */
class BasicAuth extends DAV\Auth\Backend\AbstractBasic
{

    /**
     * @brief This variable holds the currently logged-in channel_address.
     *
     * It is used for building path in filestorage/.
     *
     * @var string|null $channel_name
     */
    public $channel_name = null;
    /**
     * @brief channel_id of the current channel of the logged-in account.
     *
     * @var int $channel_id
     */
    public $channel_id = 0;
    /**
     * @brief channel_account_id of the current channel of the logged-in account.
     *
     * @var int $channel_account_id
     */
    public $channel_account_id = 0;
    /**
     * @brief channel_hash of the current channel of the logged-in account.
     *
     * @var string $channel_hash
     */
    public $channel_hash = '';
    /**
     * @brief Set in mod/cloud.php to observer_hash.
     *
     * @var string $observer
     */
    public $observer = '';
    /**
     *
     * @see Browser::set_writeable()
     * @var Sabre\DAV\Browser\Plugin $browser
     */
    public $browser;
    /**
     * @brief channel_id of the current visited path. Set in Directory::getDir().
     *
     * @var int $owner_id
     */
    public $owner_id = 0;
    /**
     * channel_name of the current visited path. Set in Directory::getDir().
     *
     * Used for creating the path in cloud/
     *
     * @var string $owner_nick
     */
    public $owner_nick = '';
    /**
     * Timezone from the visiting channel's channel_timezone.
     *
     * Used in @ref Browser
     *
     * @var string $timezone
     */
    protected $timezone = '';


    public $module_disabled = false;


    /**
     * @brief Validates a username and password.
     *
     *
     * @param string $username
     * @param string $password
     * @return bool
     * @see \\Sabre\\DAV\\Auth\\Backend\\AbstractBasic::validateUserPass
     */
    protected function validateUserPass($username, $password)
    {
        $channel = false;
        require_once('include/auth.php');
        $record = account_verify_password($username, $password);
        if ($record && $record['account']) {
            if ($record['channel'])
                $channel = $record['channel'];
            else {
                $r = q("SELECT * FROM channel WHERE channel_account_id = %d AND channel_id = %d LIMIT 1",
                    intval($record['account']['account_id']),
                    intval($record['account']['account_default_channel'])
                );
                if ($r)
                    $channel = $r[0];
            }
        }
        if ($channel && $this->check_module_access($channel['channel_id'])) {
            return $this->setAuthenticated($channel);
        }

        if ($this->module_disabled)
            $error = 'module not enabled for ' . $username;
        else
            $error = 'password failed for ' . $username;
        logger($error);
        log_failed_login($error);

        return false;
    }

    /**
     * @brief Sets variables and session parameters after successfull authentication.
     *
     * @param array $r
     *  Array with the values for the authenticated channel.
     * @return bool
     */
    protected function setAuthenticated($channel)
    {
        $this->channel_name = $channel['channel_address'];
        $this->channel_id = $channel['channel_id'];
        $this->channel_hash = $this->observer = $channel['channel_hash'];
        $this->channel_account_id = $channel['channel_account_id'];

        if ($this->observer) {
            $r = q("select * from xchan where xchan_hash = '%s' limit 1",
                dbesc($this->observer)
            );
            if ($r) {
                App::set_observer(array_shift($r));
            }
        }

        $_SESSION['uid'] = $channel['channel_id'];
        $_SESSION['account_id'] = $channel['channel_account_id'];
        $_SESSION['authenticated'] = true;
        return true;
    }

    /**
     * When this method is called, the backend must check if authentication was
     * successful.
     *
     * The returned value must be one of the following
     *
     * [true, "principals/username"]
     * [false, "reason for failure"]
     *
     * If authentication was successful, it's expected that the authentication
     * backend returns a so-called principal url.
     *
     * Examples of a principal url:
     *
     * principals/admin
     * principals/user1
     * principals/users/joe
     * principals/uid/123457
     *
     * If you don't use WebDAV ACL (RFC3744) we recommend that you simply
     * return a string such as:
     *
     * principals/users/[username]
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return array
     */
    public function check(RequestInterface $request, ResponseInterface $response)
    {

        if (local_channel()) {
            $this->setAuthenticated(App::get_channel());
            return [true, $this->principalPrefix . $this->channel_name];
        } elseif (remote_channel()) {
            return [true, $this->principalPrefix . $this->observer];
        }

        $auth = new Basic(
            $this->realm,
            $request,
            $response
        );

        $userpass = $auth->getCredentials();
        if (!$userpass) {
            return [false, "No 'Authorization: Basic' header found. Either the client didn't send one, or the server is misconfigured"];
        }
        if (!$this->validateUserPass($userpass[0], $userpass[1])) {
            return [false, "Username or password was incorrect"];
        }
        return [true, $this->principalPrefix . $userpass[0]];

    }

    protected function check_module_access($channel_id)
    {
        if ($channel_id && in_array(App::$module, ['dav', 'cdav', 'snap'])) {
            return true;
        }
        $this->module_disabled = true;
        return false;
    }

    /**
     * Sets the channel_name from the currently logged-in channel.
     *
     * @param string $name
     *  The channel's name
     */
    public function setCurrentUser($name)
    {
        $this->channel_name = $name;
    }

    /**
     * Returns information about the currently logged-in channel.
     *
     * If nobody is currently logged in, this method should return null.
     *
     * @return string|null
     * @see \\Sabre\\DAV\\Auth\\Backend\\AbstractBasic::getCurrentUser
     */
    public function getCurrentUser()
    {
        return $this->channel_name;
    }

    /**
     * @brief Sets the timezone from the channel in BasicAuth.
     *
     * Set in mod/cloud.php if the channel has a timezone set.
     *
     * @param string $timezone
     *  The channel's timezone.
     * @return void
     */
    public function setTimezone($timezone)
    {
        $this->timezone = $timezone;
    }

    /**
     * @brief Returns the timezone.
     *
     * @return string
     *  Return the channel's timezone.
     */
    public function getTimezone()
    {
        return $this->timezone;
    }

    /**
     * @brief Set browser plugin for SabreDAV.
     *
     * @param Plugin $browser
     * @see RedBrowser::set_writeable()
     */
    public function setBrowserPlugin($browser)
    {
        $this->browser = $browser;
    }

    /**
     * @brief Prints out all BasicAuth variables to logger().
     *
     * @return void
     */
    public function log()
    {
//		logger('channel_name ' . $this->channel_name, LOGGER_DATA);
//		logger('channel_id ' . $this->channel_id, LOGGER_DATA);
//		logger('channel_hash ' . $this->channel_hash, LOGGER_DATA);
//		logger('observer ' . $this->observer, LOGGER_DATA);
//		logger('owner_id ' . $this->owner_id, LOGGER_DATA);
//		logger('owner_nick ' . $this->owner_nick, LOGGER_DATA);
    }
}
