<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// settings.php – Profil-Bearbeitung & Konto-Einstellungen (seit v0.18.27)
// Komplett getrennt von der reinen Anzeige unter profile.php
require_once __DIR__ . '/../bootstrap.php';

$cu = current_user();
if (!$cu) {
    flash('Du musst eingeloggt sein um die Einstellungen zu öffnen.', 'error');
    redirect(FORUM_URL . '/login.php');
}

$userId  = (int) $cu['user_id'];
$profile = db_fetch('SELECT * FROM users WHERE user_id = ?', [$userId]);
if (!$profile) {
    http_response_code(404);
    die('Benutzer nicht gefunden.');
}

$error = '';

// Welcher Tab? Nur ein UI-Hint, alle Sektionen sind auf einer Seite renderbar.
$activeTab = $_GET['tab'] ?? 'profile';
$validTabs = ['profile', 'security', 'sessions', 'notifications', 'preferences', 'danger'];
if (!in_array($activeTab, $validTabs, true)) $activeTab = 'profile';

// ─── Session-Verwaltung ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && in_array(post_str('session_action'), ['revoke_one', 'revoke_others'], true)) {
    if (!verify_csrf(post_str('csrf'))) die('CSRF-Fehler');

    if (post_str('session_action') === 'revoke_one') {
        $tokId = (int) post_str('token_id');
        $currentTok = remember_current_token_id();
        if ($tokId > 0 && $tokId !== $currentTok) {
            $tokens = remember_list_for_user($userId);
            $ownTokenIds = array_column($tokens, 'token_id');
            if (in_array($tokId, array_map('intval', $ownTokenIds), true)) {
                remember_destroy_one($tokId);
                flash('Sitzung wurde abgemeldet.', 'success');
            }
        }
    } elseif (post_str('session_action') === 'revoke_others') {
        $currentTok = remember_current_token_id();
        $tokens = remember_list_for_user($userId);
        foreach ($tokens as $t) {
            if ((int) $t['token_id'] !== $currentTok) {
                remember_destroy_one((int) $t['token_id']);
            }
        }
        flash('Alle anderen Geräte wurden abgemeldet.', 'success');
    }
    redirect(FORUM_URL . '/settings.php?tab=sessions');
}

// ─── 2FA aktivieren ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'enable_2fa') {
    if (!verify_csrf(post_str('csrf'))) die('CSRF-Fehler');
    $secret = post_str('secret');
    $code   = post_str('code');

    if (verify_totp($secret, $code)) {
        $codes = generate_backup_codes();
        db_execute(
            'INSERT INTO user_2fa (user_id, secret, enabled, backup_codes) VALUES (?, ?, 1, ?)
             ON DUPLICATE KEY UPDATE secret = VALUES(secret), enabled = 1, backup_codes = VALUES(backup_codes)',
            [$userId, $secret, json_encode($codes)]
        );
        // Backup-Codes über Session weiterreichen damit sie nach Redirect noch sichtbar sind
        $_SESSION['_2fa_new_backup_codes'] = $codes;
        flash('2FA aktiviert! Speichere deine Backup-Codes sicher ab — sie werden nur einmal angezeigt.', 'success');
        redirect(FORUM_URL . '/settings.php?tab=security');
    } else {
        $error = 'Falscher Code. Bitte erneut versuchen.';
        $activeTab = 'security';
    }
}

// ─── 2FA deaktivieren ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'disable_2fa') {
    if (!verify_csrf(post_str('csrf'))) die('CSRF-Fehler');
    $pw = post_str('password_confirm');
    if (password_verify($pw, $profile['password'])) {
        db_execute('DELETE FROM user_2fa WHERE user_id = ?', [$userId]);
        flash('2FA wurde deaktiviert.', 'success');
        redirect(FORUM_URL . '/settings.php?tab=security');
    } else {
        $error = 'Passwort falsch. 2FA bleibt aktiv.';
        $activeTab = 'security';
    }
}

// ─── Profil/Account-Bearbeitung ─────────────────────────
// ─── Passwort ändern (v0.18.48) ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'change_password') {
    if (!verify_csrf(post_str('csrf'))) die('CSRF-Fehler');
    $activeTab = 'security';

    $currentPw = post_str('current_password');
    $newPw     = post_str('new_password');
    $newPw2    = post_str('new_password_confirm');

    // Aktuelles Passwort prüfen
    $userRow = db_fetch('SELECT password FROM users WHERE user_id = ?', [$userId]);
    if (!$userRow || !password_verify($currentPw, (string) $userRow['password'])) {
        $error = 'Das aktuelle Passwort ist nicht korrekt.';
    } elseif (mb_strlen($newPw) < 6) {
        $error = 'Das neue Passwort muss mindestens 6 Zeichen lang sein.';
    } elseif ($newPw !== $newPw2) {
        $error = 'Die beiden neuen Passwörter stimmen nicht überein.';
    } elseif ($newPw === $currentPw) {
        $error = 'Das neue Passwort darf nicht identisch mit dem alten sein.';
    } else {
        // Alles gut → neues Passwort setzen
        $newHash = password_hash($newPw, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
        try {
            db_execute('UPDATE users SET password = ? WHERE user_id = ?', [$newHash, $userId]);

            // Andere Remember-Me-Tokens widerrufen (außer dem aktuellen)
            try {
                $currentTok = remember_current_token_id();
                $tokens = remember_list_for_user($userId);
                foreach ($tokens as $t) {
                    if ((string) $t['token_id'] !== (string) $currentTok) {
                        remember_destroy_one((int) $t['token_id']);
                    }
                }
            } catch (Exception $e) {}

            flash('Passwort erfolgreich geändert. Andere Geräte wurden abgemeldet.', 'success');
            redirect(FORUM_URL . '/settings.php?tab=security');
        } catch (Exception $e) {
            $error = 'Fehler beim Speichern des neuen Passworts.';
        }
    }
}

// ─── E-Mail-Änderung anfordern (v0.18.48) ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'request_email_change') {
    if (!verify_csrf(post_str('csrf'))) die('CSRF-Fehler');
    require_once __DIR__ . '/../lib/email_change.php';
    $activeTab = 'security';

    $newEmail  = post_str('new_email');
    $currentPw = post_str('current_password_for_email');

    // Aktuelles Passwort als Sicherheits-Bestätigung prüfen
    $userRow = db_fetch('SELECT password FROM users WHERE user_id = ?', [$userId]);
    if (!$userRow || !password_verify($currentPw, (string) $userRow['password'])) {
        $error = 'Das aktuelle Passwort ist nicht korrekt.';
    } else {
        $result = email_change_initiate($userId, $newEmail);
        if (!empty($result['ok'])) {
            flash('Bestätigungs-Mail an die neue Adresse gesendet. Bitte den Link in der Mail klicken — er ist 24 Stunden gültig.', 'success');
            redirect(FORUM_URL . '/settings.php?tab=security');
        } else {
            $error = $result['error'] ?? 'Unbekannter Fehler.';
        }
    }
}

// ─── E-Mail-Änderung abbrechen (v0.18.48) ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'cancel_email_change') {
    if (!verify_csrf(post_str('csrf'))) die('CSRF-Fehler');
    require_once __DIR__ . '/../lib/email_change.php';

    email_change_cancel($userId);
    flash('E-Mail-Änderungs-Anfrage wurde abgebrochen.', 'info');
    redirect(FORUM_URL . '/settings.php?tab=security');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'save_profile') {
    if (!verify_csrf(post_str('csrf'))) die('CSRF-Fehler');

    // Tab-spezifische Forms (v0.18.45 fix): Wenn ein Feld NICHT im POST steht,
    // bestehenden DB-Wert behalten statt mit '' überschreiben.
    // E-Mail und Passwort haben EIGENE Action-Handler (change_password,
    // request_email_change) seit v0.18.48 — werden hier NICHT mehr behandelt.

    // E-Mail: IMMER bestehende verwenden — Änderung läuft über
    // request_email_change-Action mit Doppel-Opt-In (v0.18.48)
    $email = $profile['email'];

    // Signatur: bestehende behalten wenn nicht gesendet (leer ist ok — User darf löschen)
    $sig = isset($_POST['signature']) ? (string) $_POST['signature'] : ($profile['signature'] ?? '');

    $newAvatar = $profile['avatar'];

    // Avatar entfernen?
    if (!empty($_POST['delete_avatar']) && $profile['avatar']) {
        if (file_exists(AVATAR_DIR . $profile['avatar'])) {
            @unlink(AVATAR_DIR . $profile['avatar']);
        }
        $newAvatar = null;
    }

    // Avatar-Upload
    if (!empty($_FILES['avatar']['name'])) {
        $file = $_FILES['avatar'];
        $mime = mime_content_type($file['tmp_name']) ?: '';
        if ($file['size'] > MAX_AVATAR_SIZE) {
            $error = 'Avatar zu groß (max. 512 KB).';
        } elseif (!isset(UPLOAD_IMAGE_EXTENSIONS[$mime])) {
            $error = 'Ungültiges Bildformat (JPEG/PNG/GIF/WebP).';
        } else {
            // Endung IMMER aus dem geprüften Inhaltstyp, nie aus dem
            // hochgeladenen Dateinamen: sonst genügt ein echtes Bild namens
            // "x.php", um eine ausführbare Datei in uploads/ abzulegen.
            $ext  = UPLOAD_IMAGE_EXTENSIONS[$mime];
            $name = 'avatar_' . $userId . '_' . time() . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], AVATAR_DIR . $name)) {
                if ($profile['avatar'] && file_exists(AVATAR_DIR . $profile['avatar'])) {
                    @unlink(AVATAR_DIR . $profile['avatar']);
                }
                $newAvatar = $name;
            }
        }
    }

    // Banner entfernen?
    $newBanner = $profile['profile_banner'] ?? null;
    if (!empty($_POST['delete_banner']) && $newBanner) {
        if (file_exists(AVATAR_DIR . $newBanner)) {
            @unlink(AVATAR_DIR . $newBanner);
        }
        $newBanner = null;
    }

    // Banner-Upload
    if (!empty($_FILES['banner']['name']) && empty($error)) {
        $file = $_FILES['banner'];
        $mime = mime_content_type($file['tmp_name']) ?: '';
        if ($file['size'] > 2 * 1024 * 1024) {
            $error = 'Banner zu groß (max. 2 MB).';
        } elseif (!isset(UPLOAD_IMAGE_EXTENSIONS[$mime])) {
            $error = 'Ungültiges Bildformat (JPEG/PNG/GIF/WebP).';
        } else {
            // Endung aus dem Inhaltstyp — siehe Avatar-Upload oben.
            $ext  = UPLOAD_IMAGE_EXTENSIONS[$mime];
            $name = 'banner_' . $userId . '_' . time() . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], AVATAR_DIR . $name)) {
                if ($newBanner && file_exists(AVATAR_DIR . $newBanner)) {
                    @unlink(AVATAR_DIR . $newBanner);
                }
                $newBanner = $name;
            }
        }
    }

    // Passwort: wird seit v0.18.48 über change_password-Action gehandhabt.
    // Hier nicht mehr behandeln — $pwHash bleibt leer.
    $pwHash = '';

    if (!$error) {
        $language = 'de';  // Mehrsprachigkeit deaktiviert in v0.18.35 (siehe CHANGELOG)

        // ── Tab-spezifische Felder defensiv lesen (v0.18.45 fix) ───────────
        // Wenn ein Feld nicht im POST ist → bestehenden DB-Wert behalten
        // statt mit Default zu überschreiben. So zerstört der Profil-Tab nicht
        // die Theme-Einstellung (und umgekehrt).

        // Theme (Darstellung-Tab)
        if (isset($_POST['theme'])) {
            $theme = post_str('theme', 'auto');
            if (!in_array($theme, ['auto','dark','light'], true)) $theme = 'auto';
        } else {
            $theme = $profile['theme'] ?? 'auto';
        }

        // E-Mail-Benachrichtigungen (Benachrichtigungen-Tab, v0.18.46):
        // Eigenes Hidden-Feld 'notifications_tab' im Tab-Form, damit wir
        // wissen ob die Checkbox bewusst leer abgesendet wurde (= unchecked = 0)
        // oder ob das Tab gar nicht gesendet wurde (= unverändert).
        if (isset($_POST['notifications_tab'])) {
            $notifyEmail = isset($_POST['notify_email']) ? 1 : 0;
        } else {
            $notifyEmail = (int) ($profile['notify_email'] ?? 0);
        }

        // ── Erweiterte Profil-Felder (v0.18.31) ──────────────
        // Nur lesen wenn der Profil-Tab abgesendet wurde. Erkennung: das Feld
        // 'profile_gender' ist im Profil-Tab da (als select, immer mit Wert).
        $schema = profile_field_schema();
        $profileTabSubmitted = isset($_POST['profile_gender']);

        if ($profileTabSubmitted) {
            $gender     = post_str('profile_gender');
            if (!isset($schema['gender']['options'][$gender])) $gender = '';
            $birthday   = post_str('profile_birthday');
            if ($birthday !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday)) $birthday = '';
            $location   = mb_substr(post_str('profile_location'),   0, 100);
            $occupation = mb_substr(post_str('profile_occupation'), 0, 100);
            $interests  = mb_substr(post_str('profile_interests'),  0, 500);
            $about      = post_str('profile_about');

            // Privacy-Map zusammenbauen
            $privacyInput = [];
            foreach (array_keys($schema) as $key) {
                $privacyInput[$key] = post_str('privacy_' . $key, 'public');
            }
            $privacyJson = profile_privacy_serialize($privacyInput);
        } else {
            // Profil-Tab wurde nicht abgesendet → bestehende Werte behalten
            $gender      = $profile['profile_gender']     ?? '';
            $birthday    = $profile['profile_birthday']   ?? '';
            $location    = $profile['profile_location']   ?? '';
            $occupation  = $profile['profile_occupation'] ?? '';
            $interests   = $profile['profile_interests']  ?? '';
            $about       = $profile['profile_about']      ?? '';
            $privacyJson = $profile['profile_privacy']    ?? '{}';
        }

        try {
            if ($pwHash) {
                db_execute('UPDATE users SET email=?, signature=?, avatar=?, profile_banner=?, password=?, language=?, theme=?, notify_email=?,
                            profile_gender=?, profile_birthday=?, profile_location=?, profile_occupation=?, profile_interests=?, profile_about=?, profile_privacy=?
                            WHERE user_id=?',
                    [$email, $sig, $newAvatar, $newBanner, $pwHash, $language, $theme, $notifyEmail,
                     $gender !== '' ? $gender : null,
                     $birthday !== '' ? $birthday : null,
                     $location !== '' ? $location : null,
                     $occupation !== '' ? $occupation : null,
                     $interests !== '' ? $interests : null,
                     $about !== '' ? $about : null,
                     $privacyJson,
                     $userId]);
            } else {
                db_execute('UPDATE users SET email=?, signature=?, avatar=?, profile_banner=?, language=?, theme=?, notify_email=?,
                            profile_gender=?, profile_birthday=?, profile_location=?, profile_occupation=?, profile_interests=?, profile_about=?, profile_privacy=?
                            WHERE user_id=?',
                    [$email, $sig, $newAvatar, $newBanner, $language, $theme, $notifyEmail,
                     $gender !== '' ? $gender : null,
                     $birthday !== '' ? $birthday : null,
                     $location !== '' ? $location : null,
                     $occupation !== '' ? $occupation : null,
                     $interests !== '' ? $interests : null,
                     $about !== '' ? $about : null,
                     $privacyJson,
                     $userId]);
            }
        } catch (PDOException $e) {
            // Fallback: ohne die neuen Spalten (vor Migration v18_31)
            try {
                if ($pwHash) {
                    db_execute('UPDATE users SET email=?, signature=?, avatar=?, profile_banner=?, password=?, language=?, theme=?, notify_email=? WHERE user_id=?',
                        [$email, $sig, $newAvatar, $newBanner, $pwHash, $language, $theme, $notifyEmail, $userId]);
                } else {
                    db_execute('UPDATE users SET email=?, signature=?, avatar=?, profile_banner=?, language=?, theme=?, notify_email=? WHERE user_id=?',
                        [$email, $sig, $newAvatar, $newBanner, $language, $theme, $notifyEmail, $userId]);
                }
            } catch (PDOException $e2) {
                // 2. Fallback: noch ältere Schema-Version
                if ($pwHash) {
                    db_execute('UPDATE users SET email = ?, signature = ?, avatar = ?, password = ? WHERE user_id = ?',
                        [$email, $sig, $newAvatar, $pwHash, $userId]);
                } else {
                    db_execute('UPDATE users SET email = ?, signature = ?, avatar = ? WHERE user_id = ?',
                        [$email, $sig, $newAvatar, $userId]);
                }
            }
        }

        // Sprach-Cookie aktualisieren
        setcookie('forum_lang', $language, time() + 60 * 60 * 24 * 365, '/');

        // Bei Passwort-Änderung: andere Remember-Tokens widerrufen
        if ($pwHash) {
            try {
                $currentTok = remember_current_token_id();
                $tokens = remember_list_for_user($userId);
                foreach ($tokens as $t) {
                    if ((int) $t['token_id'] !== $currentTok) {
                        remember_destroy_one((int) $t['token_id']);
                    }
                }
            } catch (Exception $e) {}
        }

        try { check_achievements($userId); } catch (Exception $e) {}
        flash('Einstellungen gespeichert.', 'success');
        redirect(FORUM_URL . '/settings.php?tab=' . $activeTab);
    }

    $profile = db_fetch('SELECT * FROM users WHERE user_id = ?', [$userId]);
}

// Aktive Sessions
$sessions = [];
$currentSessionId = null;
try {
    $sessions = remember_list_for_user($userId);
    $currentSessionId = remember_current_token_id();
} catch (Exception $e) {}

// 2FA-Status + Setup-Daten
$has2fa = user_has_2fa($userId);
$newSecret = $has2fa ? '' : generate_2fa_secret();
$otpUrl    = $newSecret ? twofa_otpauth_url($profile['username'], $newSecret) : '';

// Frisch generierte Backup-Codes (einmalig nach Aktivierung sichtbar)
$newBackupCodes = $_SESSION['_2fa_new_backup_codes'] ?? [];
unset($_SESSION['_2fa_new_backup_codes']);

render('settings', [
    'title'            => 'Einstellungen',
    'profile'          => $profile,
    'sessions'         => $sessions,
    'currentSessionId' => $currentSessionId,
    'activeTab'        => $activeTab,
    'error'            => $error,
    'has2fa'           => $has2fa,
    'newSecret'        => $newSecret,
    'otpUrl'           => $otpUrl,
    'newBackupCodes'   => $newBackupCodes,
    'breadcrumb'       => [
        ['title' => '⚙️ Einstellungen', 'url' => FORUM_URL . '/settings.php'],
    ],
]);
