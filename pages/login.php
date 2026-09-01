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
    if (!verify_csrf(post_str('csrf'))) error_page(400, t('error_csrf_title'), t('error_csrf_text'));

    // „Angemeldet bleiben" – wird durch beide Steps mitgeführt
    $rememberMe = !empty($_POST['remember_me']);

    if ($step === 'twofa') {
        $pendingId = (int) $_SESSION['pending_2fa_user'];
        $code      = post_str('code');
        $isBackup  = isset($_POST['use_backup']);

        // Eigene Bremse für den Code-Schritt, gezählt pro Konto
        // Nur noch die Existenz pruefen — das Geheimnis selbst braucht diese
        // Seite nicht mehr, verify_totp_for_user() holt es sich.
        $row = db_fetch('SELECT user_id FROM user_2fa WHERE user_id = ? AND enabled = 1', [$pendingId]);
        if (!rate_limit_check('login_2fa', 5, 900, 'user:' . $pendingId)) {
            unset($_SESSION['pending_2fa_user']);
            $step  = 'credentials';
            $error = t('login_2fa_too_many');
        } elseif (!$row) {
            unset($_SESSION['pending_2fa_user']);
            $error = t('session_expired');
        } else {
            // verify_totp_for_user() vermerkt den Verbrauch — sonst gilt derselbe
            // Code bis zu 90 Sekunden lang immer wieder.
            $ok = $isBackup ? verify_backup_code($pendingId, $code)
                            : verify_totp_for_user($pendingId, $code);
            if ($ok) {
                // Login abschließen; Session-ID erneuern wie in login_user()
                if (session_status() === PHP_SESSION_ACTIVE) {
                    unset($_SESSION['csrf_token']);
                    @session_regenerate_id(true);
                }
                $_SESSION['user_id'] = $pendingId;
                unset($_SESSION['pending_2fa_user']);
                rate_limit_reset('login_2fa', 'user:' . $pendingId);
                $ip = client_ip();
                try {
                    db_execute('UPDATE users SET last_login = NOW(), last_ip = ? WHERE user_id = ?',
                        [$ip, $pendingId]);
                } catch (Exception $e) {}
                try { log_user_ip($pendingId, 'login', $ip); } catch (Exception $e) {}
                // Auch hier nur die eigene Kontobremse, nicht die der Adresse.
                $angemeldet = db_fetch('SELECT username FROM users WHERE user_id = ?', [$pendingId]);
                if ($angemeldet) {
                    rate_limit_reset('login_konto', 'konto:' . mb_strtolower(trim((string) $angemeldet['username'])));
                }

                // Remember-Me-Token erstellen (wenn Checkbox gesetzt war)
                if ($rememberMe) {
                    try { remember_create($pendingId); } catch (Exception $e) {}
                }

                flash(t('login_welcome_back'), 'success');
                redirect(FORUM_URL . '/index.php');
            }
            $error = t('login_2fa_invalid');
        }
    } else {
        // ─── Credential-Step ──────────────────────────────────

        // CAPTCHA-Schicht 2: Verhaltens-Token
        if (!behavior_verify(post_str('_bt'))) {
            $error = t('captcha_behavior_failed');
            captcha_failed_attempt('login');
        }
        // CAPTCHA-Schicht 3: Sichtbares CAPTCHA wenn erforderlich
        elseif (captcha_required('login')) {
            $captchaToken  = post_str('_captcha_token');
            $captchaAnswer = post_str('_captcha_answer');
            if (!captcha_verify($captchaToken, $captchaAnswer)) {
                $error = t('captcha_wrong');
                captcha_failed_attempt('login');
            }
        }

        if ($error !== '') {
            // CAPTCHA-Fehler → Render mit aktualisiertem Captcha-Status
        } else {

        $maxAttempts = (int) get_setting('rate_limit_login', 5);
        // Zwei Bremsen statt einer:
        //
        // Nach IP, damit einer nicht hunderte Konten durchprobiert. Und nach
        // KONTO, damit das Durchprobieren eines Passworts nicht dadurch
        // weiterlaeuft, dass der Angreifer die Adresse wechselt — und damit
        // eine erfolgreiche Anmeldung nicht die Bremse fuer alle anderen
        // Konten derselben Adresse loescht. Das war der Fall: rate_limit_reset
        // ohne Schluessel raeumt den IP-Zaehler ab, also auch die Versuche
        // gegen jedes andere Konto.
        $kontoName  = post_str('username');
        $kontoBremse = 'konto:' . mb_strtolower(trim($kontoName));
        if (!rate_limit_check('login', $maxAttempts, 900)) {
            $error = tn('login_too_many', (int) ceil(rate_limit_remaining('login') / 60));
        } elseif ($kontoName !== '' && !rate_limit_check('login_konto', $maxAttempts, 900, $kontoBremse)) {
            $error = tn('login_too_many_account', (int) ceil(rate_limit_remaining('login_konto', $kontoBremse) / 60));
        } else {
            $u = db_fetch('SELECT * FROM users WHERE username = ? AND is_banned = 0 AND COALESCE(is_deleted, 0) = 0', [post_str('username')]);
            if ($u && !empty($u['password']) && password_verify(post_str('password'), $u['password'])) {
                // E-Mail-Bestätigung: der Schalter im Adminbereich verspricht
                // „User können sich erst einloggen, nachdem sie ihre E-Mail
                // bestätigt haben" — geprüft wurde es bisher nirgends.
                if ((string) get_setting('email_verification_required', '0') === '1'
                    && (int) ($u['email_verified'] ?? 1) === 0) {
                    $error = t('login_email_unverified');
                }
                // Admin-Approval-Check: noch nicht freigegebene Accounts können sich nicht einloggen
                elseif ((int) ($u['is_approved'] ?? 1) === 0) {
                    $error = t('login_not_approved');
                    // KEIN captcha_failed_attempt – legitimer Fall, nicht verdächtig
                }
                // 2FA erforderlich?
                elseif (user_has_2fa((int) $u['user_id'])) {
                    $_SESSION['pending_2fa_user'] = (int) $u['user_id'];
                    $step = 'twofa';
                } else {
                    $user = login_user(post_str('username'), post_str('password'));
                    if ($user) {
                        // Nur die eigene Kontobremse loesen. Der IP-Zaehler
                        // bleibt stehen: sonst raeumt ein Angreifer mit einem
                        // eigenen gueltigen Konto die Bremse fuer alle
                        // uebrigen Versuche derselben Adresse ab.
                        rate_limit_reset('login_konto', $kontoBremse);
                        captcha_reset_attempts('login');

                        // Remember-Me-Token erstellen
                        if ($rememberMe) {
                            try { remember_create((int) $user['user_id']); } catch (Exception $e) {}
                        }

                        flash(t('login_welcome_back_user', $user['username']), 'success');
                        redirect(FORUM_URL . '/index.php');
                    }
                }
            } else {
                $error = t('login_bad_credentials');
                captcha_failed_attempt('login');
            }
        }
        }  // end else (CAPTCHA-Block)
    }
}

render('login', [
    'title'        => t('login'),
    'error'        => $error,
    'step'         => $step,
    'rememberMe'   => $rememberMe ?? false,
    'showCaptcha'  => captcha_required('login'),
    'captcha'      => captcha_required('login') ? captcha_generate() : null,
    'behaviorToken' => behavior_token(),
]);
