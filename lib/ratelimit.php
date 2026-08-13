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

        $row = db_fetch(
            'SELECT count, expires_at FROM rate_limits WHERE key_hash = ? AND action = ?',
            [$hash, $action]
        );

        if (!$row || strtotime($row['expires_at']) < time()) {
            // Neuer Eintrag oder abgelaufen
            db_execute(
                'INSERT INTO rate_limits (key_hash, action, count, expires_at)
                 VALUES (?, ?, 1, DATE_ADD(NOW(), INTERVAL ? SECOND))
                 ON DUPLICATE KEY UPDATE count = 1, expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND)',
                [$hash, $action, $windowSec, $windowSec]
            );
            return true;
        }

        if ((int) $row['count'] >= $maxAttempts) return false;

        db_execute(
            'UPDATE rate_limits SET count = count + 1 WHERE key_hash = ? AND action = ?',
            [$hash, $action]
        );
        return true;
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
