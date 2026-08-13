<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// login.php
require_once __DIR__ . '/../bootstrap.php';

if (is_logged_in()) redirect(FORUM_URL . '/index.php');

$error = '';
$step  = 'credentials';

// ─── 2FA-Step ──────────────────────────────────────────────────
if (!empty($_SESSION['pending_2fa_user'])) $step = 'twofa';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf(post_str('csrf'))) die('CSRF');

    // „Angemeldet bleiben" – wird durch beide Steps mitgeführt
    $rememberMe = !empty($_POST['remember_me']);

    if ($step === 'twofa') {
        $pendingId = (int) $_SESSION['pending_2fa_user'];
        $code      = post_str('code');
        $isBackup  = isset($_POST['use_backup']);

        $row = db_fetch('SELECT secret FROM user_2fa WHERE user_id = ? AND enabled = 1', [$pendingId]);
        if (!$row) {
            unset($_SESSION['pending_2fa_user']);
            $error = 'Sitzung abgelaufen.';
        } else {
            $ok = $isBackup ? verify_backup_code($pendingId, $code) : verify_totp($row['secret'], $code);
            if ($ok) {
                // Login abschließen
                $_SESSION['user_id'] = $pendingId;
                unset($_SESSION['pending_2fa_user']);
                $ip = client_ip();
                try {
                    db_execute('UPDATE users SET last_login = NOW(), last_ip = ? WHERE user_id = ?',
                        [$ip, $pendingId]);
                } catch (Exception $e) {}
                try { log_user_ip($pendingId, 'login', $ip); } catch (Exception $e) {}
                rate_limit_reset('login');

                // Remember-Me-Token erstellen (wenn Checkbox gesetzt war)
                if ($rememberMe) {
                    try { remember_create($pendingId); } catch (Exception $e) {}
                }

                flash('Willkommen zurück!', 'success');
                redirect(FORUM_URL . '/index.php');
            }
            $error = 'Ungültiger Code.';
        }
    } else {
        // ─── Credential-Step ──────────────────────────────────

        // CAPTCHA-Schicht 2: Verhaltens-Token
        if (!behavior_verify(post_str('_bt'))) {
            $error = 'Sicherheits-Prüfung fehlgeschlagen. Bitte versuche es erneut.';
            captcha_failed_attempt('login');
        }
        // CAPTCHA-Schicht 3: Sichtbares CAPTCHA wenn erforderlich
        elseif (captcha_required('login')) {
            $captchaToken  = post_str('_captcha_token');
            $captchaAnswer = post_str('_captcha_answer');
            if (!captcha_verify($captchaToken, $captchaAnswer)) {
                $error = 'Sicherheitsfrage falsch beantwortet.';
                captcha_failed_attempt('login');
            }
        }

        if ($error !== '') {
            // CAPTCHA-Fehler → Render mit aktualisiertem Captcha-Status
        } else {

        $maxAttempts = (int) get_setting('rate_limit_login', 5);
        if (!rate_limit_check('login', $maxAttempts, 900)) {
            $error = 'Zu viele Login-Versuche. Bitte warte ' . ceil(rate_limit_remaining('login') / 60) . ' Minuten.';
        } else {
            $u = db_fetch('SELECT * FROM users WHERE username = ? AND is_banned = 0 AND COALESCE(is_deleted, 0) = 0', [post_str('username')]);
            if ($u && !empty($u['password']) && password_verify(post_str('password'), $u['password'])) {
                // Admin-Approval-Check: noch nicht freigegebene Accounts können sich nicht einloggen
                if ((int) ($u['is_approved'] ?? 1) === 0) {
                    $error = 'Dein Konto wartet noch auf Freigabe durch das Admin-Team. Du erhältst eine E-Mail sobald freigeschaltet.';
                    // KEIN captcha_failed_attempt – legitimer Fall, nicht verdächtig
                }
                // 2FA erforderlich?
                elseif (user_has_2fa((int) $u['user_id'])) {
                    $_SESSION['pending_2fa_user'] = (int) $u['user_id'];
                    $step = 'twofa';
                } else {
                    $user = login_user(post_str('username'), post_str('password'));
                    if ($user) {
                        rate_limit_reset('login');
                        captcha_reset_attempts('login');

                        // Remember-Me-Token erstellen
                        if ($rememberMe) {
                            try { remember_create((int) $user['user_id']); } catch (Exception $e) {}
                        }

                        flash('Willkommen zurück, ' . $user['username'] . '!', 'success');
                        redirect(FORUM_URL . '/index.php');
                    }
                }
            } else {
                $error = 'Benutzername oder Passwort falsch.';
                captcha_failed_attempt('login');
            }
        }
        }  // end else (CAPTCHA-Block)
    }
}

render('login', [
    'title'        => 'Anmelden',
    'error'        => $error,
    'step'         => $step,
    'rememberMe'   => $rememberMe ?? false,
    'showCaptcha'  => captcha_required('login'),
    'captcha'      => captcha_required('login') ? captcha_generate() : null,
    'behaviorToken' => behavior_token(),
]);
