<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// lib/remember.php – „Angemeldet bleiben" mit sicheren DB-Tokens

/**
 * Token-Format: <selector>:<validator>
 * - selector: 16 Byte hex, sucht den Datenbankeintrag (kein Hash, da lookup key)
 * - validator: 32 Byte hex, wird gehashed gespeichert (constant-time compare beim prüfen)
 *
 * Dieses Split-Token-Pattern (Selector + Validator) ist eine bewährte
 * Best-Practice gegen Timing-Attacks bei der Token-Suche.
 */

const REMEMBER_COOKIE_NAME = 'forum_remember';
const REMEMBER_DAYS        = 30;
const REMEMBER_REFRESH_DAYS = 7;  // Token wird nach 7 Tagen Nutzung erneuert (rolling)

/**
 * Erzeugt einen neuen Token für $userId, speichert ihn in der DB,
 * und setzt den Cookie. Wird beim Login mit "Angemeldet bleiben" aufgerufen.
 */
function remember_create(int $userId): void {
    $selector  = bin2hex(random_bytes(16));   // 32 hex chars
    $validator = bin2hex(random_bytes(32));   // 64 hex chars
    $hash      = hash('sha256', $validator);
    $expires   = date('Y-m-d H:i:s', time() + REMEMBER_DAYS * 86400);

    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $ip = function_exists('client_ip') ? client_ip() : ($_SERVER['REMOTE_ADDR'] ?? '');

    try {
        db_execute(
            'INSERT INTO remember_tokens (user_id, selector, validator_hash, user_agent, ip_address, expires_at)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$userId, $selector, $hash, $ua, $ip, $expires]
        );
    } catch (Exception $e) {
        // Wenn die Tabelle fehlt (Migration nicht gelaufen), still überspringen
        error_log('remember_create: ' . $e->getMessage());
        return;
    }

    _remember_set_cookie($selector . ':' . $validator, REMEMBER_DAYS * 86400);
}

/**
 * Prüft den Remember-Me-Cookie. Wenn gültig: loggt den User automatisch ein.
 * Return: User-Array oder null
 */
function remember_try_login(): ?array {
    if (isset($_SESSION['user_id'])) return null; // schon eingeloggt
    if (empty($_COOKIE[REMEMBER_COOKIE_NAME])) return null;

    $cookieValue = $_COOKIE[REMEMBER_COOKIE_NAME];
    if (!str_contains($cookieValue, ':')) {
        _remember_clear_cookie();
        return null;
    }
    [$selector, $validator] = explode(':', $cookieValue, 2);

    try {
        $row = db_fetch(
            'SELECT * FROM remember_tokens WHERE selector = ? AND expires_at > NOW()',
            [$selector]
        );
    } catch (Exception $e) {
        return null;
    }

    if (!$row) {
        _remember_clear_cookie();
        return null;
    }

    // Constant-time compare gegen Timing-Attacks
    $hash = hash('sha256', $validator);
    if (!hash_equals($row['validator_hash'], $hash)) {
        // Selector gefunden, aber Validator falsch → potentieller Angriff!
        // → ALLE Tokens dieses Users löschen (defense in depth)
        try {
            db_execute('DELETE FROM remember_tokens WHERE user_id = ?', [$row['user_id']]);
        } catch (Exception $e) {}
        _remember_clear_cookie();
        return null;
    }

    // Token gültig → User laden
    try {
        $user = db_fetch('SELECT * FROM users WHERE user_id = ? AND is_banned = 0', [$row['user_id']]);
    } catch (Exception $e) {
        return null;
    }
    if (!$user) {
        // User gelöscht oder gebannt → Token entfernen
        try { db_execute('DELETE FROM remember_tokens WHERE token_id = ?', [$row['token_id']]); } catch (Exception $e) {}
        _remember_clear_cookie();
        return null;
    }

    // last_used aktualisieren
    try {
        db_execute('UPDATE remember_tokens SET last_used = NOW() WHERE token_id = ?', [$row['token_id']]);
    } catch (Exception $e) {}

    // Rolling-Renewal: Token erneuern wenn schon älter als REMEMBER_REFRESH_DAYS genutzt
    $refreshAfter = strtotime($row['created_at']) + REMEMBER_REFRESH_DAYS * 86400;
    if (time() > $refreshAfter) {
        remember_destroy_one($row['token_id']);
        remember_create((int) $user['user_id']);
    }

    // Session setzen
    $_SESSION['user_id']  = (int) $user['user_id'];
    $_SESSION['login_at'] = time();

    return $user;
}

/**
 * Löscht den aktuellen Remember-Token (bei Logout).
 */
function remember_destroy_current(): void {
    if (empty($_COOKIE[REMEMBER_COOKIE_NAME])) {
        _remember_clear_cookie();
        return;
    }
    $cookieValue = $_COOKIE[REMEMBER_COOKIE_NAME];
    if (str_contains($cookieValue, ':')) {
        [$selector,] = explode(':', $cookieValue, 2);
        try {
            db_execute('DELETE FROM remember_tokens WHERE selector = ?', [$selector]);
        } catch (Exception $e) {}
    }
    _remember_clear_cookie();
}

/** Löscht einen einzelnen Token per ID */
function remember_destroy_one(int $tokenId): void {
    try {
        db_execute('DELETE FROM remember_tokens WHERE token_id = ?', [$tokenId]);
    } catch (Exception $e) {}
}

/**
 * Löscht ALLE Remember-Tokens eines Users (z.B. "alle Geräte abmelden" oder Passwort-Reset).
 */
function remember_destroy_all_for_user(int $userId): void {
    try {
        db_execute('DELETE FROM remember_tokens WHERE user_id = ?', [$userId]);
    } catch (Exception $e) {}
}

/**
 * Holt alle aktiven Tokens eines Users (für „Aktive Sessions" im Profil).
 */
function remember_list_for_user(int $userId): array {
    try {
        return db_fetchAll(
            'SELECT token_id, selector, user_agent, ip_address, created_at, last_used, expires_at
             FROM remember_tokens WHERE user_id = ? AND expires_at > NOW()
             ORDER BY last_used DESC',
            [$userId]
        );
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Prüft welcher der Tokens der aktuelle ist (für „Diese Sitzung"-Markierung).
 */
function remember_current_token_id(): ?int {
    if (empty($_COOKIE[REMEMBER_COOKIE_NAME])) return null;
    $cookieValue = $_COOKIE[REMEMBER_COOKIE_NAME];
    if (!str_contains($cookieValue, ':')) return null;
    [$selector,] = explode(':', $cookieValue, 2);
    try {
        $row = db_fetch('SELECT token_id FROM remember_tokens WHERE selector = ?', [$selector]);
        return $row ? (int) $row['token_id'] : null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Abgelaufene Tokens aufräumen (1% Chance pro Request – pragmatisch).
 */
function remember_garbage_collect(): void {
    if (random_int(0, 99) !== 0) return;
    try {
        db_execute('DELETE FROM remember_tokens WHERE expires_at < NOW()');
    } catch (Exception $e) {}
}

// ── intern ─────────────────────────────────────────────────────────

function _remember_set_cookie(string $value, int $maxAge): void {
    $secure = !empty($_SERVER['HTTPS']);
    setcookie(REMEMBER_COOKIE_NAME, $value, [
        'expires'  => time() + $maxAge,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[REMEMBER_COOKIE_NAME] = $value; // damit gleicher Request keinen Loop bekommt
}

function _remember_clear_cookie(): void {
    setcookie(REMEMBER_COOKIE_NAME, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE[REMEMBER_COOKIE_NAME]);
}

/**
 * Hübschen Klartext-Namen aus User-Agent ableiten (für Anzeige).
 */
function remember_format_user_agent(string $ua): string {
    if ($ua === '') return 'Unbekannt';

    $browser = 'Browser';
    if    (stripos($ua, 'Edg/') !== false)        $browser = 'Edge';
    elseif(stripos($ua, 'OPR/') !== false || stripos($ua, 'Opera') !== false) $browser = 'Opera';
    elseif(stripos($ua, 'Chrome') !== false)       $browser = 'Chrome';
    elseif(stripos($ua, 'Firefox') !== false)      $browser = 'Firefox';
    elseif(stripos($ua, 'Safari') !== false)       $browser = 'Safari';

    $os = 'Unbekanntes OS';
    if    (stripos($ua, 'Windows') !== false) $os = 'Windows';
    elseif(stripos($ua, 'Mac OS X') !== false || stripos($ua, 'Macintosh') !== false) $os = 'macOS';
    elseif(stripos($ua, 'Android') !== false) $os = 'Android';
    elseif(stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) $os = 'iOS';
    elseif(stripos($ua, 'Linux') !== false)   $os = 'Linux';

    return $browser . ' auf ' . $os;
}
