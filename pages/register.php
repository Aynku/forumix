<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// register.php
require_once __DIR__ . '/../bootstrap.php';

if (is_logged_in()) redirect(FORUM_URL . '/index.php');

if ((string) get_setting('registration_enabled', '1') !== '1') {
    flash('Registrierungen sind aktuell deaktiviert.', 'error');
    redirect(FORUM_URL . '/login.php');
}

$error    = '';
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf(post_str('csrf'))) die('CSRF');

    // CAPTCHA-Schicht 2: Verhaltens-Token
    if (!behavior_verify(post_str('_bt'))) {
        $error = 'Sicherheits-Prüfung fehlgeschlagen. Bitte versuche es erneut.';
        captcha_failed_attempt('register');
    }
    // CAPTCHA-Schicht 3: Sichtbares CAPTCHA wenn erforderlich
    elseif (captcha_required('register')) {
        $captchaToken  = post_str('_captcha_token');
        $captchaAnswer = post_str('_captcha_answer');
        if (!captcha_verify($captchaToken, $captchaAnswer)) {
            $error = 'Sicherheitsfrage falsch beantwortet.';
            captcha_failed_attempt('register');
        }
    }

    if ($error !== '') {
        // CAPTCHA-Fehler → fällt durch zum Render
    } else {

    $maxReg = (int) get_setting('rate_limit_register', 3);
    if (!rate_limit_check('register', $maxReg, 3600)) {
        $error = 'Zu viele Registrierungs-Versuche. Bitte später erneut versuchen.';
    } elseif (!empty($_POST['website'])) {
        // Honeypot ausgelöst - Bot
        $error = 'Registrierung fehlgeschlagen.';
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
            $error = 'Benutzername mindestens ' . $minUn . ' Zeichen.';
        } elseif (preg_match('/^gel(ö|oe)schter\s*benutzer/i', $un)) {
            $error = 'Dieser Benutzername ist reserviert.';
        } elseif (!filter_var($em, FILTER_VALIDATE_EMAIL)) {
            $error = 'Ungültige E-Mail-Adresse.';
        } elseif (mb_strlen($pw) < $minPw) {
            $error = 'Passwort mindestens ' . $minPw . ' Zeichen.';
        } elseif ($pw !== $pw2) {
            $error = 'Passwörter stimmen nicht überein.';
        } elseif ((string) get_setting('registration_terms_required', '1') === '1' && empty($_POST['accept_terms'])) {
            $error = 'Bitte stimme den Nutzungsbedingungen zu, um fortzufahren.';
        } else {
            $uid = register_user($un, $em, $pw);
            if ($uid === false) {
                $error = 'Benutzername oder E-Mail bereits vergeben.';
            } else {
                captcha_reset_attempts('register');

                $approvalRequired = user_approval_required();
                $emailRequired    = (string) get_setting('email_verification_required', '0') === '1';

                // ── Invite-Code prüfen (überspringt Approval) ──
                $inviteCode = strtoupper(trim(post_str('invite_code')));
                $skipApproval = false;
                if ($inviteCode !== '' && $approvalRequired) {
                    $inv = invitation_find_by_code($inviteCode);
                    $status = invitation_status($inv, $em);
                    if ($status === 'valid') {
                        if (invitation_consume((int) $inv['invitation_id'], (int) $uid)) {
                            $skipApproval = true;
                        }
                    }
                    // Ungültige Codes werden still ignoriert – User landet in der Queue
                    // (kein Error, sonst könnte man Codes durchprobieren)
                }

                // Wenn Admin-Approval aktiv UND kein gültiger Invite: is_approved = 0
                if ($approvalRequired && !$skipApproval) {
                    try {
                        db_execute('UPDATE users SET is_approved = 0 WHERE user_id = ?', [$uid]);
                    } catch (Exception $e) {}
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
                        flash('Konto erstellt! Dein Einladungs-Code wurde akzeptiert. Bitte bestätige zuerst deine E-Mail-Adresse über den Link.', 'success');
                    } elseif ($approvalRequired) {
                        flash('Konto erstellt! Bitte bestätige zuerst deine E-Mail-Adresse über den Link – danach wartet der Account noch auf Admin-Freigabe. Du wirst per E-Mail benachrichtigt sobald freigeschaltet.', 'info');
                    } else {
                        flash('Konto erstellt! Bitte bestätige zuerst deine E-Mail-Adresse über den Link, den wir dir geschickt haben.', 'info');
                    }
                    redirect(FORUM_URL . '/login.php');
                } elseif ($approvalRequired && !$skipApproval) {
                    flash('Konto erstellt! Dein Account wartet jetzt auf Admin-Freigabe. Du wirst per E-Mail benachrichtigt sobald freigeschaltet.', 'info');
                    redirect(FORUM_URL . '/login.php');
                } else {
                    login_user($un, $pw);
                    if ($skipApproval) {
                        flash('Willkommen, ' . $un . '! Dein Einladungs-Code wurde akzeptiert. 🎉', 'success');
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
    'title'         => 'Registrieren',
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
