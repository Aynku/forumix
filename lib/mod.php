<?php
// lib/mod.php – Helper-Funktionen für den Mod-Bereich
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }

/**
 * Liefert die Board-IDs für die ein User Forum-spezifischer Moderator ist
 * (oder leeres Array wenn keine)
 */
function mod_for_boards(int $userId): array {
    try {
        $rows = db_fetchAll(
            'SELECT board_id FROM board_moderators WHERE user_id = ?',
            [$userId]
        );
        return array_map(fn($r) => (int) $r['board_id'], $rows);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Wartende Threads – optional gefiltert auf bestimmte Board-IDs
 */
function pending_threads_filtered(?array $boardIds): array {
    try {
        $where = '';
        $params = [];
        if ($boardIds !== null) {
            if (empty($boardIds)) return [];
            $place = implode(',', array_fill(0, count($boardIds), '?'));
            $where = " AND t.board_id IN ($place)";
            $params = $boardIds;
        }
        return db_fetchAll('
            SELECT t.thread_id, t.title, t.created_at, t.board_id,
                   u.user_id, u.username, u.avatar,
                   b.title AS board_title,
                   p.post_id, p.content
            FROM threads t
            JOIN users u ON u.user_id = t.user_id
            JOIN boards b ON b.board_id = t.board_id
            LEFT JOIN posts p ON p.thread_id = t.thread_id AND p.user_id = t.user_id
            WHERE COALESCE(t.is_approved, 1) = 0' . sichtbar_sql('t') . $where . '
            ORDER BY t.created_at ASC
        ', $params);
    } catch (Exception $e) { return []; }
}

/**
 * Wartende Posts (Antworten) – optional gefiltert auf Board-IDs
 */
function pending_posts_filtered(?array $boardIds): array {
    try {
        $where = '';
        $params = [];
        if ($boardIds !== null) {
            if (empty($boardIds)) return [];
            $place = implode(',', array_fill(0, count($boardIds), '?'));
            $where = " AND t.board_id IN ($place)";
            $params = $boardIds;
        }
        return db_fetchAll('
            SELECT p.post_id, p.content, p.created_at, p.thread_id,
                   t.title AS thread_title, t.board_id,
                   u.user_id, u.username, u.avatar,
                   b.title AS board_title
            FROM posts p
            JOIN threads t ON t.thread_id = p.thread_id
            JOIN boards b ON b.board_id = t.board_id
            JOIN users u ON u.user_id = p.user_id
            WHERE COALESCE(p.is_approved, 1) = 0' . sichtbar_sql('p') . '
              AND COALESCE(t.is_approved, 1) = 1
              AND p.post_id != COALESCE((SELECT MIN(post_id) FROM posts WHERE thread_id = t.thread_id), 0)' . $where . '
            ORDER BY p.created_at ASC
        ', $params);
    } catch (Exception $e) { return []; }
}

/**
 * Anzahl offener Reports (optional gefiltert auf Board-IDs)
 */
function mod_open_reports_count(?array $boardIds): int {
    try {
        if ($boardIds === null) {
            return db_count("SELECT COUNT(*) FROM reports WHERE status = 'open'");
        }
        if (empty($boardIds)) return 0;
        $place = implode(',', array_fill(0, count($boardIds), '?'));
        return db_count("
            SELECT COUNT(*) FROM reports r
            LEFT JOIN posts p ON p.post_id = r.post_id
            LEFT JOIN threads t ON t.thread_id = p.thread_id
            WHERE r.status = 'open' AND t.board_id IN ($place)
        ", $boardIds);
    } catch (Exception $e) { return 0; }
}

/**
 * Report-Liste, optional gefiltert
 */
function mod_reports_list(?array $boardIds): array {
    try {
        $where = "WHERE r.status = 'open'";
        $params = [];
        if ($boardIds !== null) {
            if (empty($boardIds)) return [];
            $place = implode(',', array_fill(0, count($boardIds), '?'));
            $where .= " AND t.board_id IN ($place)";
            $params = $boardIds;
        }
        $rows = db_fetchAll("
            SELECT r.*,
                   reporter.username AS reporter_name,
                   p.content AS post_content,
                   p.thread_id  AS thread_id,
                   t.title      AS thread_title,
                   t.board_id   AS board_id,
                   b.title      AS board_title,
                   pu.username  AS post_author
            FROM reports r
            LEFT JOIN users reporter ON reporter.user_id = r.reporter_id
            LEFT JOIN posts p   ON p.post_id    = r.post_id
            LEFT JOIN threads t ON t.thread_id  = p.thread_id
            LEFT JOIN boards b  ON b.board_id   = t.board_id
            LEFT JOIN users pu  ON pu.user_id   = p.user_id
            $where
            ORDER BY r.created_at DESC
            LIMIT 50
        ", $params);
        return $rows ?: [];
    } catch (Exception $e) { return []; }
}

/**
 * Eigene Mod-Aktionen (Log)
 */
function mod_log_entries(int $userId, int $limit = 50): array {
    try {
        return db_fetchAll(
            'SELECT ml.*, tu.username AS target_username
             FROM mod_log ml
             LEFT JOIN users tu ON tu.user_id = ml.target_id
             WHERE ml.mod_id = ?
             ORDER BY ml.created_at DESC
             LIMIT ?',
            [$userId, $limit]
        );
    } catch (Exception $e) { return []; }
}

/**
 * Letzte Aktionen für die Dashboard-Ansicht
 */
function mod_recent_actions(int $userId, int $limit = 10): array {
    return mod_log_entries($userId, $limit);
}
