<?php

namespace Code\Module\Settings;

use App;
use Code\Extend\Hook;
use Code\Render\Theme;

class Account
{

    public function post()
    {
        check_form_security_token_redirectOnErr('/settings/account', 'settings_account');

        Hook::call('account_settings_post', $_POST);

        $errs = [];

        $email = ((x($_POST, 'email')) ? trim(notags($_POST['email'])) : '');

        $account = App::get_account();
        if ($email != $account['account_email']) {
            if (!validate_email($email)) {
                $errs[] = t('Not valid email.');
            }
            $adm = trim(get_config('system', 'admin_email'));
            if (($adm) && (strcasecmp($email, $adm) == 0)) {
                $errs[] = t('Protected email address. Cannot change to that email.');
                $email = App::$account['account_email'];
            }
            if (!$errs) {
                $r = q(
                    "update account set account_email = '%s' where account_id = %d",
                    dbesc($email),
                    intval($account['account_id'])
                );
                if (!$r) {
                    $errs[] = t('System failure storing new email. Please try again.');
                }
            }
        }

        if ($errs) {
            foreach ($errs as $err) {
                notice($err . EOL);
            }
            $errs = [];
        }


        if ((x($_POST, 'npassword')) || (x($_POST, 'confirm'))) {
            $origpass = trim($_POST['origpass']);

            require_once('include/auth.php');
            if (!account_verify_password($email, $origpass)) {
                $errs[] = t('Password verification failed.');
            }

            $newpass = trim($_POST['npassword']);
            $confirm = trim($_POST['confirm']);

            if ($newpass != $confirm) {
                $errs[] = t('Passwords do not match. Password unchanged.');
            }

            if ((!x($newpass)) || (!x($confirm))) {
                $errs[] = t('Empty passwords are not allowed. Password unchanged.');
            }

            if (!$errs) {
                $salt = random_string(32);
                $password_encoded = hash('whirlpool', $salt . $newpass);
                $r = q(
                    "update account set account_salt = '%s', account_password = '%s', account_password_changed = '%s' 
					where account_id = %d",
                    dbesc($salt),
                    dbesc($password_encoded),
                    dbesc(datetime_convert()),
                    intval(get_account_id())
                );
                if ($r) {
                    info(t('Password changed.') . EOL);
                } else {
                    $errs[] = t('Password update failed. Please try again.');
                }
            }
        }


        if ($errs) {
            foreach ($errs as $err) {
                notice($err . EOL);
            }
        }
        goaway(z_root() . '/settings/account');
    }


    public function get()
    {
        $account_settings = "";

        Hook::call('account_settings', $account_settings);

        $email = App::$account['account_email'];
        $tpl = Theme::get_template("settings_account.tpl");
        return replace_macros($tpl, [
            '$form_security_token' => get_form_security_token("settings_account"),
            '$title' => t('Account Settings'),
            '$origpass' => ['origpass', t('Current Password'), ' ', ''],
            '$password1' => ['npassword', t('Enter New Password'), '', ''],
            '$password2' => ['confirm', t('Confirm New Password'), '', t('Leave password fields blank unless changing')],
            '$submit' => t('Submit'),
            '$email' => ['email', t('Email Address:'), $email, ''],
            '$removeme' => t('Remove Account'),
            '$removeaccount' => t('Remove this account including all its channels'),
            '$account_settings' => $account_settings
        ]);
    }
}
