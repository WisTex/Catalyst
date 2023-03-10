<?php

namespace Code\Web;

use Code\Web\SessionHandler;
use Code\Extend\Hook;


/**
 *
 * @brief This file includes session related functions.
 *
 * Session management functions. These provide database storage of PHP
 * session info.
 */


class Session
{

    private $handler = null;
    private $session_started = false;
    private $custom_handler = false;

    public function init()
    {

        $gc_probability = 50;

        ini_set('session.gc_probability', $gc_probability);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_httponly', 1);

        $this->custom_handler = boolval(get_config('system', 'session_custom', false));

        /*
         * Set our session storage functions.
         */

        if ($this->custom_handler) {
           /* Custom handler (files, memached, redis..) */

            $session_save_handler = strval(get_config('system', 'session_save_handler', null));
            $session_save_path = strval(get_config('system', 'session_save_path', null));
            if ($session_save_handler && $session_save_path) {
                ini_set('session.save_handler', $session_save_handler);
                ini_set('session.save_path', $session_save_path);
            } else {
                logger('Session save handler or path not set.', LOGGER_NORMAL, LOG_ERR);
            }
        } else {
            $handler = new SessionHandler();

            $this->handler = $handler;

            $x = session_set_save_handler($handler, false);
            if (! $x) {
                logger('Session save handler initialisation failed.', LOGGER_NORMAL, LOG_ERR);
            }
        }

        // Force cookies to be secure (https only) if this site is SSL enabled.
        // Must be done before session_start().

        $arr = session_get_cookie_params();

        // Note when setting cookies: set the domain to false which creates a single domain
        // cookie. If you use a hostname it will create a .domain.com wildcard which will
        // have some nasty side effects if you have any other subdomains running the same software.

		session_set_cookie_params([
			'lifetime' => ((isset($arr['lifetime'])) ? $arr['lifetime'] : 0),
			'path' => ((isset($arr['path'])) ? $arr['path'] : '/'),
			'domain' => (($arr['domain']) ? $arr['domain'] : false),
			'secure' => ((isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on') ? true : false),
			'httponly' => ((isset($arr['httponly'])) ? $arr['httponly'] : true),
			'samesite' => 'None'
		]);

    
        register_shutdown_function('session_write_close');
    }

    public function start()
    {
        session_start();
        $this->session_started = true;
    }

    /**
     * @brief Resets the current session.
     *
     * @return void
     */

    public function nuke()
    {
        $this->new_cookie(0); // 0 means delete on browser exit
        if ($_SESSION && count($_SESSION)) {
            foreach ($_SESSION as $k => $v) {
                unset($_SESSION[$k]);
            }
        }
    }

    public function new_cookie($xtime)
    {

        $newxtime = (($xtime > 0) ? (time() + $xtime) : 0);

        $old_sid = session_id();

        $arr = session_get_cookie_params();

        if (($this->handler || $this->custom_handler) && $this->session_started) {
            session_regenerate_id(true);

            // force SessionHandler record creation with the new session_id
            // which occurs as a side effect of read()
            if (! $this->custom_handler) {
                $this->handler->read(session_id());
            }
        } else {
            logger('no session handler');
        }

        if (x($_COOKIE, 'jsdisabled')) {
			setcookie(
				'jsdisabled',
				$_COOKIE['jsdisabled'],
				[
					'expires' => $newxtime,
					'path' => '/',
					'domain' => false,
					'secure' => ((isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on') ? true : false),
					'httponly' => ((isset($arr['httponly'])) ? $arr['httponly'] : true),
					'samesite' => 'None'
				]
			);
		}    

		setcookie(
			session_name(),
			session_id(),
			[
				'expires' => $newxtime,
				'path' => '/',
				'domain' => false,
				'secure' => ((isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on') ? true : false),
				'httponly' => ((isset($arr['httponly'])) ? $arr['httponly'] : true),
				'samesite' => 'None'
			]
		);

        $arr = array('expire' => $xtime);
        Hook::call('new_cookie', $arr);
    }

    public function extend_cookie()
    {

        $arr = session_get_cookie_params();

        // if there's a long-term cookie, extend it

        $xtime = (($_SESSION['remember_me']) ? (60 * 60 * 24 * 365) : 0 );

        if ($xtime) {
			setcookie(
				session_name(),
				session_id(),
				[
					'expires' => time() + $xtime,
					'path' => '/',
					'domain' => false,
					'secure' => ((isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on') ? true : false),
					'httponly' => ((isset($arr['httponly'])) ? $arr['httponly'] : true),
					'samesite' => 'None'
				]
			);
		}

        $arr = array('expire' => $xtime);
        Hook::call('extend_cookie', $arr);
    }
}
