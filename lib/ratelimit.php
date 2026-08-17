<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// lib/ratelimit.php – Rate-Limiting via DB

/**
 * Prüft Rate-Limit. Erhöht Counter um 1. Gibt true zurück wenn OK,
 * false wenn Limit überschritten.
 *
 * @param string $action      Aktions-Bezeichner (z.B. 'login', 'register', 'post')
 * @param int    $maxAttempts Maximale Versuche im Zeitfenster
 * @param int    $windowSec   Zeitfenster in Sekunden
 * @param string $key         Identifikator (z.B. IP oder user_id) – default: IP
 */
function rate_limit_check(string $action, int $maxAttempts, int $windowSec, string $key = ''): bool {
    if ($key === '') $key = client_ip();
    $hash = hash('sha256', $key . '|' . $action);

    try {
        // Alte Einträge aufräumen (5% Wahrscheinlichkeit)
        if (mt_rand(1, 20) === 1) {
            db_execute('DELETE FROM rate_limits WHERE expires_at < NOW()');
        }

        // ERST hochzaehlen, DANN entscheiden. Vorher wurde gelesen, geprueft
        // und anschliessend erhoeht — zwischen Lesen und Erhoehen passen
        // beliebig viele parallele Anfragen, die alle denselben Zaehlerstand
        // sehen und alle durchkommen. Genau das umgeht jede Bremse, indem man
        // die Versuche gleichzeitig statt nacheinander schickt.
        //
        // Ein einzelnes INSERT ... ON DUPLICATE KEY UPDATE ist unteilbar: die
        // Zeile ist waehrenddessen gesperrt, jede Anfrage bekommt ihren
        // eigenen Zaehlerstand.
        db_execute(
            'INSERT INTO rate_limits (key_hash, action, count, expires_at)
             VALUES (?, ?, 1, DATE_ADD(NOW(), INTERVAL ? SECOND))
             ON DUPLICATE KEY UPDATE
                count      = IF(expires_at < NOW(), 1, count + 1),
                expires_at = IF(expires_at < NOW(), DATE_ADD(NOW(), INTERVAL ? SECOND), expires_at)',
            [$hash, $action, $windowSec, $windowSec]
        );

        $stand = db_count(
            'SELECT count FROM rate_limits WHERE key_hash = ? AND action = ?',
            [$hash, $action]
        );
        return $stand <= $maxAttempts;
    } catch (Exception $e) {
        // Bei Fehler nicht blockieren
        return true;
    }
}

/**
 * Setzt den Counter zurück (z.B. nach erfolgreichem Login).
 */
function rate_limit_reset(string $action, string $key = ''): void {
    if ($key === '') $key = client_ip();
    $hash = hash('sha256', $key . '|' . $action);
    try {
        db_execute('DELETE FROM rate_limits WHERE key_hash = ? AND action = ?', [$hash, $action]);
    } catch (Exception $e) {}
}

/**
 * Verbleibende Sekunden bis das Limit zurückgesetzt wird
 */
function rate_limit_remaining(string $action, string $key = ''): int {
    if ($key === '') $key = client_ip();
    $hash = hash('sha256', $key . '|' . $action);
    try {
        $r = db_fetch(
            'SELECT TIMESTAMPDIFF(SECOND, NOW(), expires_at) AS sec FROM rate_limits WHERE key_hash = ? AND action = ?',
            [$hash, $action]
        );
        return $r ? max(0, (int) $r['sec']) : 0;
    } catch (Exception $e) { return 0; }
}
