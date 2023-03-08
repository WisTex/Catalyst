<?php

namespace Code\Module;

use App;
use Code\Render\Theme;
use Code\Web\Controller;
use OTPHP\TOTP;

class Totp_check extends Controller
{

    public function post()
    {
        $retval = ['status' => false];

        $account = App::get_account();
        if (!$account) {
            json_return_and_die($retval);
        }
        $secret = $account['account_external'];
        $input = (isset($_POST['totp_code'])) ? trim($_POST['totp_code']) : '';

        if ($secret && $input) {
            $otp = TOTP::create($secret); // create TOTP object from the secret.
            if ($otp->verify($_POST['totp_code']) || $input === $secret ) {
                logger('otp_success');
                $_SESSION['2FA_VERIFIED'] = true;
                $retval['status'] = true;
                json_return_and_die($retval);
            }
            logger('otp_fail');
        }
        json_return_and_die($retval);
    }

    public function get() {
        $account = App::get_account();
        if (!$account) {
            return t('Account not found.');
        }

        return replace_macros(Theme::get_template('totp.tpl'),
            [
                '$header' => t('Multifactor Verification'),
                '$desc'   => t('Please enter the verification key from your authenticator app'),
                '$success' => t('Success!'),
                '$fail' => t('Invalid code, please try again.'),
                '$maxfails' => t('Too many invalid codes...'),
                '$submit' => t('Verify')
            ]
        );
    }
}

