<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// register.php
require_once __DIR__ . '/../bootstrap.php';

if (is_logged_in()) redirect(FORUM_URL . '/index.php');

if ((string) get_setting('registration_enabled', '1') !== '1') {
    flash(t('register_disabled'), 'error');
    redirect(FORUM_URL . '/login.php');
}

$error    = '';
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf(post_str('csrf'))) error_page(400, t('error_csrf_title'), t('error_csrf_text'));

    // CAPTCHA-Schicht 2: Verhaltens-Token
    if (!behavior_verify(post_str('_bt'))) {
        $error = t('captcha_behavior_failed');
        captcha_failed_attempt('register');
    }
    // CAPTCHA-Schicht 3: Sichtbares CAPTCHA wenn erforderlich
    elseif (captcha_required('register')) {
        $captchaToken  = post_str('_captcha_token');
        $captchaAnswer = post_str('_captcha_answer');
        if (!captcha_verify($captchaToken, $captchaAnswer)) {
            $error = t('captcha_wrong');
            captcha_failed_attempt('register');
        }
    }

    if ($error !== '') {
        // CAPTCHA-Fehler → fällt durch zum Render
    } else {

    $maxReg = (int) get_setting('rate_limit_register', 3);
    if (!rate_limit_check('register', $maxReg, 3600)) {
        $error = t('register_too_many');
    } elseif (!empty($_POST['website'])) {
        // Honeypot ausgelöst - Bot
        $error = t('register_failed');
        captcha_failed_attempt('register');
    } else {
        $un  = post_str('username');
        $em  = post_str('email');
        $pw  = post_str('password');
        $pw2 = post_str('password2');
        $minUn = (int) get_setting('min_username_length', 3);
        $minPw = (int) get_setting('min_password_length', 6);

        $formData = ['username' => $un, 'email' => $em];

        if (mb_strlen($un) < $minUn) {
            $error = tn('register_username_min', (int) $minUn);
        } elseif (mb_strlen($un) > 30) {
            $error = t('register_username_max');
        } elseif (!preg_match('/^[\p{L}\p{N} ._-]+$/u', $un)) {
            // Namen landen auch in HTML, das erst im Browser gebaut wird
            $error = t('register_username_chars');
        } elseif (preg_match('/^gel(ö|oe)schter\s*benutzer/i', $un)) {
            $error = t('register_username_reserved');
        } elseif (!filter_var($em, FILTER_VALIDATE_EMAIL)) {
            $error = t('error_bad_email');
        } elseif (mb_strlen($pw) < $minPw) {
            $error = tn('register_password_min', (int) $minPw);
        } elseif ($pw !== $pw2) {
            $error = t('error_passwords_differ');
        } elseif ((string) get_setting('registration_terms_required', '1') === '1' && empty($_POST['accept_terms'])) {
            $error = t('register_terms_required');
        } else {
            $approvalRequired = user_approval_required();
            $emailRequired    = (string) get_setting('email_verification_required', '0') === '1';

            // Einladung nur PRÜFEN, eingelöst wird sie nach dem Anlegen —
            // invitation_consume() braucht die user_id.
            $inviteCode   = strtoupper(trim(post_str('invite_code')));
            $inv          = null;
            $skipApproval = false;
            if ($inviteCode !== '' && $approvalRequired) {
                $inv = invitation_find_by_code($inviteCode);
                if (invitation_status($inv, $em) === 'valid') {
                    $skipApproval = true;
                }
                // Ungültige Codes werden still ignoriert – User landet in der Queue
                // (kein Error, sonst könnte man Codes durchprobieren)
            }

            $isApproved = ($approvalRequired && !$skipApproval) ? 0 : 1;

            $uid = register_user($un, $em, $pw, $isApproved);
            if ($uid === false) {
                $error = t('register_taken');
            } else {
                captcha_reset_attempts('register');

                // Einlösen. Klappt es nicht, geht es in die sichere Richtung:
                // das Konto wandert in die Warteschlange statt durchzurutschen.
                if ($skipApproval && $inv) {
                    if (!invitation_consume((int) $inv['invitation_id'], (int) $uid)) {
                        try { db_execute('UPDATE users SET is_approved = 0 WHERE user_id = ?', [$uid]); }
                        catch (Exception $e) {}
                        $isApproved = 0;
                    }
                }

                if ($isApproved === 0) {
                    try {
                        $newUser = db_fetch('SELECT * FROM users WHERE user_id = ?', [$uid]);
                        if ($newUser) send_admin_new_registration_email($newUser);
                    } catch (Exception $e) {}
                }

                // E-Mail-Verifizierung?
                if ($emailRequired) {
                    $token = create_email_verification((int) $uid, $em);
                    if ($token) {
                        $user = db_fetch('SELECT * FROM users WHERE user_id = ?', [$uid]);
                        send_verification_email($user, $token);
                    }
                    if ($skipApproval) {
                        flash(t('register_done_invite_verify'), 'success');
                    } elseif ($approvalRequired) {
                        flash(t('register_done_verify_approve'), 'info');
                    } else {
                        flash(t('register_done_verify'), 'info');
                    }
                    redirect(FORUM_URL . '/login.php');
                } elseif ($approvalRequired && !$skipApproval) {
                    flash(t('register_done_approve'), 'info');
                    redirect(FORUM_URL . '/login.php');
                } else {
                    login_user($un, $pw);
                    if ($skipApproval) {
                        flash(t('register_welcome_invite', $un), 'success');
                    } else {
                        flash('Willkommen, ' . $un . '!', 'success');
                    }
                    redirect(FORUM_URL . '/index.php');
                }
            }
        }
    }
    }  // end else (CAPTCHA-Block)
}

render('register', [
    'title'         => t('register'),
    'error'         => $error,
    'formData'      => $formData,
    'showCaptcha'   => captcha_required('register'),
    'captcha'       => captcha_required('register') ? captcha_generate() : null,
    'behaviorToken' => behavior_token(),
    'inviteCode'    => trim($_GET['invite'] ?? $_POST['invite_code'] ?? ''),
    'approvalRequired' => user_approval_required(),
    'termsRequired' => (string) get_setting('registration_terms_required', '1') === '1',
    'acceptTerms'   => !empty($_POST['accept_terms']),
]);
