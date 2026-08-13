<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// forgot.php – Passwort vergessen mit drei Wiederherstellungs-Methoden:
//   - E-Mail-Link (Standard, immer verfügbar)
//   - 2FA-Token (wenn User 2FA aktiv hat)
//   - Backup-Code (wenn User 2FA + Backup-Codes hat)
require_once __DIR__ . '/../bootstrap.php';

if (is_logged_in()) redirect(FORUM_URL . '/index.php');

$error  = '';
$sent   = false;
$method = $_POST['method'] ?? $_GET['method'] ?? 'email';
if (!in_array($method, ['email', '2fa', 'backup'], true)) $method = 'email';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf(post_str('csrf'))) die('CSRF');

    // CAPTCHA-Schicht 2: Verhaltens-Token
    if (!behavior_verify(post_str('_bt'))) {
        $error = 'Sicherheits-Prüfung fehlgeschlagen. Bitte versuche es erneut.';
        captcha_failed_attempt('forgot');
    }
    // CAPTCHA-Schicht 3: Sichtbares CAPTCHA wenn erforderlich
    elseif (captcha_required('forgot')) {
        if (!captcha_verify(post_str('_captcha_token'), post_str('_captcha_answer'))) {
            $error = 'Sicherheitsfrage falsch beantwortet.';
            captcha_failed_attempt('forgot');
        }
    }

    if ($error === '') {

        if ($method === 'email') {
            // ─── Methode 1: E-Mail-Link (wie bisher) ────────────
            if (!rate_limit_check('forgot', 3, 3600)) {
                $error = 'Zu viele Versuche. Bitte warte eine Stunde.';
            } elseif (!empty($_POST['website'])) {
                // Honeypot
                $sent = true;
                captcha_failed_attempt('forgot');
            } else {
                $email = post_str('email');
                $user  = db_fetch(
                    'SELECT user_id, username, email FROM users
                     WHERE email = ? AND is_banned = 0 AND COALESCE(is_deleted, 0) = 0',
                    [$email]
                );

                // Aus Sicherheitsgründen IMMER Erfolg melden (kein User-Enumeration)
                $sent = true;
                captcha_reset_attempts('forgot');

                if ($user) {
                    $token = create_password_reset((int) $user['user_id']);
                    if ($token) send_password_reset_email($user, $token);
                }
            }
        }

        elseif ($method === '2fa') {
            // ─── Methode 2: 2FA-Token ──────────────────────────
            if (!rate_limit_check('forgot_2fa', 3, 3600)) {
                $error = 'Zu viele Versuche. Bitte warte eine Stunde.';
            } else {
                $username = post_str('username');
                $code     = trim(post_str('code'));
                $user = db_fetch(
                    'SELECT user_id FROM users
                     WHERE username = ? AND is_banned = 0 AND COALESCE(is_deleted, 0) = 0',
                    [$username]
                );
                $ok = false;
                if ($user) {
                    $row = db_fetch('SELECT secret FROM user_2fa WHERE user_id = ? AND enabled = 1', [$user['user_id']]);
                    if ($row && verify_totp($row['secret'], $code)) {
                        $ok = true;
                    }
                }
                if ($ok) {
                    $token = create_password_reset((int) $user['user_id']);
                    captcha_reset_attempts('forgot');
                    if ($token) {
                        flash('Identität bestätigt. Lege jetzt ein neues Passwort fest.', 'success');
                        redirect(FORUM_URL . '/reset.php?token=' . urlencode($token));
                    }
                    $error = 'Token konnte nicht erstellt werden.';
                } else {
                    // Generische Fehlermeldung — kein User-Enumeration
                    $error = 'Benutzername oder Code nicht korrekt.';
                    captcha_failed_attempt('forgot');
                }
            }
        }

        elseif ($method === 'backup') {
            // ─── Methode 3: Backup-Code ────────────────────────
            if (!rate_limit_check('forgot_backup', 3, 3600)) {
                $error = 'Zu viele Versuche. Bitte warte eine Stunde.';
            } else {
                $username = post_str('username');
                $code     = trim(post_str('code'));
                $user = db_fetch(
                    'SELECT user_id FROM users
                     WHERE username = ? AND is_banned = 0 AND COALESCE(is_deleted, 0) = 0',
                    [$username]
                );
                $ok = false;
                if ($user) {
                    $ok = verify_backup_code((int) $user['user_id'], $code);
                }
                if ($ok) {
                    $token = create_password_reset((int) $user['user_id']);
                    captcha_reset_attempts('forgot');
                    if ($token) {
                        flash('Backup-Code akzeptiert. Lege jetzt ein neues Passwort fest. Hinweis: Der verwendete Backup-Code wurde verbraucht.', 'success');
                        redirect(FORUM_URL . '/reset.php?token=' . urlencode($token));
                    }
                    $error = 'Token konnte nicht erstellt werden.';
                } else {
                    $error = 'Benutzername oder Backup-Code nicht korrekt.';
                    captcha_failed_attempt('forgot');
                }
            }
        }
    }
}

render('forgot', [
    'title'         => 'Passwort vergessen',
    'error'         => $error,
    'sent'          => $sent,
    'method'        => $method,
    'showCaptcha'   => captcha_required('forgot'),
    'captcha'       => captcha_required('forgot') ? captcha_generate() : null,
    'behaviorToken' => behavior_token(),
]);
