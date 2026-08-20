<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// lib/reads.php – Gelesen/Ungelesen-Tracking

/**
 * Markiert einen Thread als gelesen für den aktuellen User.
 */
function mark_thread_read(int $userId, int $threadId): void {
    if ($userId <= 0) return;
    try {
        db_execute(
            'INSERT INTO thread_reads (user_id, thread_id, read_at) VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE read_at = NOW()',
            [$userId, $threadId]
        );
    } catch (Exception $e) {}
}

/**
 * Map: thread_id => is_unread (für eine Liste von Threads).
 * Effizient via JOIN/Subquery statt N+1.
 */
function unread_status_for_threads(int $userId, array $threadIds): array {
    if ($userId <= 0 || empty($threadIds)) return [];

    // Map: thread_id => bool
    $result = [];
    foreach ($threadIds as $id) $result[(int) $id] = true; // Default: ungelesen

    try {
        $placeholders = implode(',', array_fill(0, count($threadIds), '?'));
        $params = array_merge([$userId], $threadIds);
        $rows = db_fetchAll("
            SELECT t.thread_id, p.created_at AS last_post_at, tr.read_at
            FROM threads t
            LEFT JOIN posts p ON p.post_id = t.last_post_id' . sichtbar_sql('p') . '
            LEFT JOIN thread_reads tr ON tr.thread_id = t.thread_id AND tr.user_id = ?
            WHERE t.thread_id IN ($placeholders)
        ", $params);

        foreach ($rows as $r) {
            $tid = (int) $r['thread_id'];
            // Ungelesen, wenn nie gelesen ODER last_post_at > read_at
            if (!$r['read_at']) {
                $result[$tid] = true;
            } elseif ($r['last_post_at'] && strtotime($r['last_post_at']) > strtotime($r['read_at'])) {
                $result[$tid] = true;
            } else {
                $result[$tid] = false;
            }
        }
    } catch (Exception $e) {}

    return $result;
}

/**
 * Alle Threads als gelesen markieren
 */
function mark_all_threads_read(int $userId): int {
    if ($userId <= 0) return 0;
    try {
        $threads = db_fetchAll('SELECT thread_id FROM threads WHERE 1 = 1' . sichtbar_sql());
        $count = 0;
        foreach ($threads as $t) {
            mark_thread_read($userId, (int) $t['thread_id']);
            $count++;
        }
        return $count;
    } catch (Exception $e) { return 0; }
}
