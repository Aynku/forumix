<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// lib/auth.php

if (session_status() === PHP_SESSION_NONE) {
    // HTTPS-Erkennung: hinter Reverse-Proxy schaut PHP auf $_SERVER['HTTPS'] —
    // bei nginx vor Apache kann das fehlen, daher auch X-Forwarded-Proto prüfen
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443');

    // Sitzungs-IDs, die der Besucher selbst mitbringt, nicht uebernehmen.
    // Ohne das koennte jemand einem Opfer eine ID unterschieben und danach
    // dessen angemeldete Sitzung mitbenutzen (Session Fixation).
    ini_set('session.use_strict_mode', '1');

    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function current_user(): ?array {
    if (!isset($_SESSION['user_id'])) return null;
    try {
        // Mit is_approved-Check (Schema v0.18.24+)
        $u = db_fetch(
            'SELECT * FROM users
             WHERE user_id = ?
               AND is_banned = 0
               AND COALESCE(is_deleted, 0) = 0
               AND COALESCE(is_approved, 1) = 1',
            [$_SESSION['user_id']]
        );
    } catch (PDOException $e) {
        // Fallback ohne is_approved-Spalte (vor Migration v18_24)
        $u = db_fetch(
            'SELECT * FROM users
             WHERE user_id = ?
               AND is_banned = 0
               AND COALESCE(is_deleted, 0) = 0',
            [$_SESSION['user_id']]
        );
    }
    return $u ?: null;
}

function register_user(string $username, string $email, string $password, int $isApproved = 1) {
    $existing = db_count('SELECT COUNT(*) FROM users WHERE username = ? OR email = ?', [$username, $email]);
    if ($existing > 0) return false;

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
    $ip   = client_ip();

    // is_approved gehoert in das INSERT, nicht in ein nachgelagertes UPDATE:
    // die Spalte steht im Schema auf DEFAULT 1, das Konto entstuende sonst
    // freigegeben und muesste nachtraeglich gesperrt werden. Schlaegt das
    // UPDATE fehl, bliebe ein freies Konto zurueck, das in der Freigabeliste
    // nicht einmal auftaucht.
    try {
        $userId = db_execute(
            'INSERT INTO users (username, email, password, registration_ip, last_ip, is_approved) VALUES (?, ?, ?, ?, ?, ?)',
            [$username, $email, $hash, $ip, $ip, $isApproved]
        );
    } catch (PDOException $e) {
        $userId = db_execute(
            'INSERT INTO users (username, email, password) VALUES (?, ?, ?)',
            [$username, $email, $hash]
        );
    }

    try { log_user_ip($userId, 'register', $ip); } catch (Exception $e) {}
    return $userId;
}

function login_user(string $username, string $password) {
    $u = db_fetch('SELECT * FROM users WHERE username = ? AND is_banned = 0 AND COALESCE(is_deleted, 0) = 0', [$username]);
    if (!$u || empty($u['password']) || !password_verify($password, $u['password'])) return false;
    // Wenn is_approved-Spalte existiert und auf 0 steht: kein Login möglich
    if (array_key_exists('is_approved', $u) && (int) $u['is_approved'] === 0) return false;

    // Schutz vor Session-Fixation: Neue Session-ID nach Login
    if (session_status() === PHP_SESSION_ACTIVE) {
        @session_regenerate_id(true);
    }

    $_SESSION['user_id'] = $u['user_id'];
    $ip = client_ip();

    try {
        db_execute('UPDATE users SET last_login = NOW(), last_ip = ? WHERE user_id = ?',
            [$ip, $u['user_id']]);
    } catch (PDOException $e) {
        db_execute('UPDATE users SET last_login = NOW() WHERE user_id = ?', [$u['user_id']]);
    }

    try { log_user_ip($u['user_id'], 'login', $ip); } catch (Exception $e) {}

    return $u;
}

function logout_user(): void {
    // Online-Eintrag der aktuellen Session entfernen
    try {
        $sid = session_id();
        if ($sid) {
            db_execute('DELETE FROM user_online WHERE session_id = ?', [$sid]);
        }
    } catch (Exception $e) {}

    $_SESSION = [];   // löscht auch $_SESSION['unlocked_boards'] und alles andere
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function is_logged_in(): bool { return current_user() !== null; }
function is_admin(): bool     {
    $u = current_user();
    // Owner zählt automatisch als Admin (Owner > Admin in der Hierarchie)
    return $u && ((bool) $u['is_admin'] || !empty($u['is_owner']));
}
function is_mod(): bool       {
    $u = current_user();
    // Owner und Admin zählen automatisch auch als Mod
    return $u && ((bool) $u['is_mod'] || (bool) $u['is_admin'] || !empty($u['is_owner']));
}
function is_owner(): bool     { $u = current_user(); return $u && (bool) ($u['is_owner'] ?? 0); }

/**
 * Liefert für einen User die Rolle als (color, title, weight)-Triplet.
 * Hierarchie: Banned > Owner > Admin > Mod > normales Mitglied.
 *
 * Wird überall benutzt wo Usernames farbig nach Rolle dargestellt werden
 * (Forum-Mod-Liste, „Wer ist online", Profil-Badge etc.). Spart Code-Duplikation
 * und stellt sicher dass Owner überall gleich dargestellt werden.
 */
function user_role_style(array $u): array {
    if (!empty($u['is_deleted'])) {
        return ['color' => 'var(--text-faint)', 'title' => 'Gelöschter Benutzer', 'weight' => 400];
    }
    if (!empty($u['is_banned'])) {
        return ['color' => '#dc2626', 'title' => 'Gesperrt', 'weight' => 500];
    }
    if (!empty($u['is_owner'])) {
        return ['color' => '#7c3aed', 'title' => 'Inhaber', 'weight' => 700];
    }
    if (!empty($u['is_admin'])) {
        return ['color' => '#dc2626', 'title' => 'Administrator', 'weight' => 700];
    }
    if (!empty($u['is_mod'])) {
        return ['color' => '#f59e0b', 'title' => 'Moderator', 'weight' => 700];
    }
    return ['color' => 'var(--text-base)', 'title' => '', 'weight' => 500];
}

/**
 * Prüft ob ein bestimmter User der Owner ist
 */
function user_is_owner(?array $user): bool {
    return $user && !empty($user['is_owner']);
}

/**
 * Prüft ob $cu eine bestimmte Aktion auf $target ausführen darf.
 * Owner darf alles (außer sich selbst entziehen).
 * Niemand darf den Owner editieren/sperren/demoten.
 */
function can_modify_user(array $cu, array $target): bool {
    // Niemand darf den Owner ändern (auch der Owner selbst nicht – Schutz vor Versehen)
    if (user_is_owner($target)) return false;
    // Sonst: nur Admins/Owner dürfen
    return !empty($cu['is_admin']) || !empty($cu['is_owner']);
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: ' . FORUM_URL . '/login.php');
        exit;
    }
}
function require_admin(): void {
    if (!is_admin()) error_page(403, 'Zugriff verweigert', 'Dieser Bereich ist Administratoren vorbehalten.');
}
function require_mod(): void {
    if (!is_mod()) error_page(403, 'Zugriff verweigert', 'Dieser Bereich ist dem Moderationsteam vorbehalten.');
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function verify_csrf(string $token): bool {
    // Ohne Token in der Sitzung ist nichts zu vergleichen — sonst waere
    // hash_equals('', '') wahr und ein leeres csrf= wuerde die Pruefung
    // bestehen. Der Fall tritt real auf: eine Sitzung, die per
    // remember_try_login() entsteht, hat noch keinen Token, weil ihn nur
    // render() erzeugt.
    if (empty($_SESSION['csrf_token']) || $token === '') return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}
