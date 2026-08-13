<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// lib/captcha.php – Adaptive CAPTCHA-System
//
// Architektur:
//   Schicht 1 (passive): Honeypot-Felder (bereits in den Forms)
//   Schicht 2 (versteckt): Verhaltens-Token + Min-Submit-Zeit
//   Schicht 3 (sichtbar): Mathe-CAPTCHA bei Verdacht oder zu vielen Fehlversuchen

const CAPTCHA_ATTEMPT_TTL = 3600;  // 1 Stunde Tracking-Fenster

// ─── Helper ──────────────────────────────────────────────────────────────

function captcha_enabled(): bool {
    try {
        return (string) get_setting('captcha_enabled') === '1';
    } catch (Exception $e) {
        return true;  // im Zweifel: an
    }
}

function captcha_threshold(): int {
    try {
        return max(1, (int) get_setting('captcha_threshold'));
    } catch (Exception $e) {
        return 3;
    }
}

function captcha_min_time_ms(): int {
    try {
        return max(0, (int) get_setting('captcha_min_time'));
    } catch (Exception $e) {
        return 1500;
    }
}

function captcha_ip_hash(): string {
    return hash('sha256', client_ip() . '|captcha');
}

// ─── Fehlversuche tracken ────────────────────────────────────────────────

/**
 * Liefert die Anzahl der Fehlversuche für eine Aktion (z.B. 'login').
 */
function captcha_get_attempts(string $action): int {
    try {
        // Aufräumen
        db_execute('DELETE FROM captcha_attempts WHERE expires_at < NOW()');
        $row = db_fetch(
            'SELECT attempts FROM captcha_attempts WHERE ip_hash = ? AND action = ?',
            [captcha_ip_hash(), $action]
        );
        return $row ? (int) $row['attempts'] : 0;
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Zählt einen Fehlversuch hoch (z.B. nach falschem Passwort).
 */
function captcha_failed_attempt(string $action): void {
    try {
        $expires = date('Y-m-d H:i:s', time() + CAPTCHA_ATTEMPT_TTL);
        db_execute(
            'INSERT INTO captcha_attempts (ip_hash, action, attempts, expires_at)
             VALUES (?, ?, 1, ?)
             ON DUPLICATE KEY UPDATE
                attempts = attempts + 1,
                expires_at = VALUES(expires_at)',
            [captcha_ip_hash(), $action, $expires]
        );
    } catch (Exception $e) {}
}

/**
 * Setzt Fehlversuche zurück (z.B. nach erfolgreichem Login).
 */
function captcha_reset_attempts(string $action): void {
    try {
        db_execute(
            'DELETE FROM captcha_attempts WHERE ip_hash = ? AND action = ?',
            [captcha_ip_hash(), $action]
        );
    } catch (Exception $e) {}
}

/**
 * Soll für diese Action+IP ein sichtbares CAPTCHA gezeigt werden?
 */
function captcha_required(string $action): bool {
    if (!captcha_enabled()) return false;
    return captcha_get_attempts($action) >= captcha_threshold();
}

// ─── Mathe-CAPTCHA ───────────────────────────────────────────────────────

/**
 * Generiert eine einfache Mathe-Aufgabe und speichert die Lösung in der Session.
 *
 * @return array{question:string, token:string}
 */
function captcha_generate(): array {
    $a = random_int(1, 9);
    $b = random_int(1, 9);
    $op = random_int(0, 2);  // 0=+, 1=-, 2=*

    switch ($op) {
        case 1:
            // Subtraktion: Ergebnis positiv halten
            if ($b > $a) [$a, $b] = [$b, $a];
            $result   = $a - $b;
            $opSymbol = '−';
            break;
        case 2:
            // Multiplikation klein halten
            $a = random_int(1, 5);
            $b = random_int(1, 5);
            $result   = $a * $b;
            $opSymbol = '×';
            break;
        default:
            $result   = $a + $b;
            $opSymbol = '+';
    }

    $token = bin2hex(random_bytes(16));
    if (!isset($_SESSION['captcha_solutions'])) {
        $_SESSION['captcha_solutions'] = [];
    }
    // Maximal 5 offene CAPTCHAs pro Session
    if (count($_SESSION['captcha_solutions']) > 5) {
        $_SESSION['captcha_solutions'] = array_slice(
            $_SESSION['captcha_solutions'], -5, null, true
        );
    }
    $_SESSION['captcha_solutions'][$token] = [
        'result'  => $result,
        'expires' => time() + 600,  // 10 Minuten gültig
    ];

    return [
        'question' => "{$a} {$opSymbol} {$b}",
        'token'    => $token,
    ];
}

/**
 * Prüft eine CAPTCHA-Antwort.
 */
function captcha_verify(string $token, string $userAnswer): bool {
    if (empty($_SESSION['captcha_solutions'][$token])) return false;
    $sol = $_SESSION['captcha_solutions'][$token];
    // Token verbrauchen — auch bei Fehler (one-shot)
    unset($_SESSION['captcha_solutions'][$token]);

    if (time() > $sol['expires']) return false;
    return (int) trim($userAnswer) === (int) $sol['result'];
}

// ─── Verhaltens-Token (Schicht 2) ────────────────────────────────────────

/**
 * Generiert ein signiertes Token mit Zeitstempel.
 * Wird in versteckte Form-Felder eingebaut. Beim Submit prüfen wir:
 *   - Token ist gültig signiert
 *   - Mindest-Zeit zwischen Generierung und Submit
 */
function behavior_token(): string {
    $time = time();
    $payload = $time . '|' . session_id();
    $sig = hash_hmac('sha256', $payload, _behavior_secret());
    return $time . '.' . $sig;
}

/**
 * Verifiziert ein Verhaltens-Token. Liefert true wenn alles ok.
 */
function behavior_verify(string $token): bool {
    if (!captcha_enabled()) return true;  // Feature deaktiviert? immer ok

    $parts = explode('.', $token, 2);
    if (count($parts) !== 2) return false;
    [$time, $sig] = $parts;
    if (!ctype_digit($time)) return false;

    $time = (int) $time;
    $age  = (time() - $time) * 1000;  // ms

    // Token zu alt? (max 30 min)
    if ($age > 30 * 60 * 1000) return false;

    // Token zu jung? (Bot-Erkennung: instant submit)
    if ($age < captcha_min_time_ms()) return false;

    // Signatur prüfen
    $payload = $time . '|' . session_id();
    $expected = hash_hmac('sha256', $payload, _behavior_secret());
    return hash_equals($expected, $sig);
}

/**
 * Liefert ein Secret für die Token-Signatur. Bevorzugt aus config,
 * Fallback aus DB-Setting, Fallback auf SESSION-Wert (instabil!).
 */
function _behavior_secret(): string {
    if (defined('FORUM_SECRET') && FORUM_SECRET) return FORUM_SECRET;
    try {
        $s = (string) get_setting('forum_secret');
        if ($s) return $s;
    } catch (Exception $e) {}
    // Notfall-Fallback: Session-basiert (besser als nichts)
    if (empty($_SESSION['_behavior_secret'])) {
        $_SESSION['_behavior_secret'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['_behavior_secret'];
}
