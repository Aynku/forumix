<?php
/**
 * lib/user_stats.php — Statistik-Helfer für User-Profile (v0.18.50)
 *
 * Diese Funktionen liefern aggregierte Werte für die Profil-Statistik-Sektion.
 * Sie sind read-only und respektieren die board_filter_sql()-Sichtbarkeits-Regeln
 * (gesperrte Foren werden nicht mitgezählt für den anfragenden User).
 *
 * Bestehende User-Stat-Helpers (aus anderen Dateien):
 *   - user_received_reactions(int)  → lib/reactions.php
 *   - user_achievement_count(int)   → lib/achievements.php
 *   - user_total_points(int)        → lib/achievements.php
 */
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }

/**
 * Anzahl der Threads die der User erstellt hat (sichtbar für anfragenden User).
 */
function user_threads_count(int $userId): int {
    try {
        return db_count(
            'SELECT COUNT(*) FROM threads t
             WHERE t.user_id = ? ' . board_filter_sql('t', 'board_id'),
            [$userId]
        );
    } catch (Exception $e) { return 0; }
}

/**
 * Anzahl der Reaktionen die der User VERGEBEN hat (anderen Posts gegeben).
 */
function user_given_reactions(int $userId): int {
    try {
        return db_count(
            'SELECT COUNT(*) FROM reactions WHERE user_id = ?',
            [$userId]
        );
    } catch (Exception $e) { return 0; }
}

/**
 * Anzahl der Posts des Users in den letzten N Tagen (für Aktivitäts-Level).
 */
function user_posts_in_last_days(int $userId, int $days = 30): int {
    try {
        return db_count(
            'SELECT COUNT(*) FROM posts p JOIN threads t ON t.thread_id = p.thread_id
             WHERE p.user_id = ?
               AND p.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) '
            . board_filter_sql('t', 'board_id'),
            [$userId, $days]
        );
    } catch (Exception $e) { return 0; }
}

/**
 * Berechnet ein Aktivitäts-Level basierend auf Posts der letzten 30 Tage.
 * Returns ['icon', 'label', 'color'] für die Anzeige.
 *
 * Schwellwerte sind bewusst niedrig — für ein kleines Forum mit wenig Traffic
 * sollen auch ein paar Posts schon "aktiv" sein.
 */
function user_activity_level(int $userId): array {
    $posts30 = user_posts_in_last_days($userId, 30);
    // v0.18.52: last_activity_at (Heartbeat bei jedem Pageview) statt last_login.
    // Wer eingeloggt mit Remember-Me das Forum nutzt aber nicht extra einloggt,
    // wäre sonst fälschlich als "inaktiv" markiert worden.
    // Fallback auf last_login wenn last_activity_at noch NULL ist (frisch nach Migration).
    $lastActive = db_fetch(
        'SELECT COALESCE(last_activity_at, last_login) AS ts FROM users WHERE user_id = ?',
        [$userId]
    );
    $loginAgo = null;
    if (!empty($lastActive['ts'])) {
        $loginAgo = (time() - strtotime((string) $lastActive['ts'])) / 86400;
    }

    if ($posts30 >= 10 || ($loginAgo !== null && $loginAgo < 7)) {
        return ['icon' => '🟢', 'label' => 'Aktiv', 'color' => '#4a9d4a'];
    }
    if ($posts30 >= 1 || ($loginAgo !== null && $loginAgo < 30)) {
        return ['icon' => '🟡', 'label' => 'Gelegentlich', 'color' => '#d4a017'];
    }
    if ($loginAgo !== null && $loginAgo < 90) {
        return ['icon' => '🟠', 'label' => 'Selten', 'color' => '#e07b00'];
    }
    return ['icon' => '⚫', 'label' => 'Inaktiv', 'color' => '#888'];
}

/**
 * Die Top-N Threads des Users sortiert nach erhaltenen Reaktionen
 * (über alle Posts im Thread, also auch Replies des Users).
 * Inkludiert nur den Thread-Eröffnungs-Post als Wertungs-Basis, damit's
 * nicht durch Replies des Users künstlich aufgebläht wird.
 */
function user_top_threads(int $userId, int $limit = 3): array {
    try {
        return db_fetchAll(
            'SELECT t.thread_id, t.title,
                    (SELECT COUNT(*) FROM reactions r
                     JOIN posts p2 ON p2.post_id = r.post_id
                     WHERE p2.thread_id = t.thread_id
                       AND p2.user_id = ?) AS reactions_total
             FROM threads t
             WHERE t.user_id = ? ' . board_filter_sql('t', 'board_id') . '
             ORDER BY reactions_total DESC, t.created_at DESC
             LIMIT ' . (int) $limit,
            [$userId, $userId]
        );
    } catch (Exception $e) { return []; }
}
