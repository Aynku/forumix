<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// lib/reports.php – Meldungs-System

function report_reasons(): array {
    return [
        'spam'      => '🚫 Spam',
        'offensive' => '😡 Beleidigend',
        'illegal'   => '⚖️ Rechtswidrig',
        'adult'     => '🔞 Nicht jugendfrei',
        'off_topic' => '💬 Off-Topic',
        'other'     => '❓ Sonstiges',
    ];
}

function create_report(int $postId, int $reporterId, string $reason, string $comment = ''): bool {
    if (!isset(report_reasons()[$reason])) return false;
    try {
        // Doppelte Meldungen vermeiden (selber User, selber Post, offen)
        $existing = db_count(
            "SELECT COUNT(*) FROM reports WHERE post_id = ? AND reporter_id = ? AND status = 'open'",
            [$postId, $reporterId]
        );
        if ($existing > 0) return false;

        db_execute(
            'INSERT INTO reports (post_id, reporter_id, reason, comment) VALUES (?, ?, ?, ?)',
            [$postId, $reporterId, $reason, mb_substr($comment, 0, 1000)]
        );

        // Alle Mods/Admins benachrichtigen
        try {
            $mods = db_fetchAll('SELECT user_id FROM users WHERE is_mod = 1 OR is_admin = 1 OR COALESCE(is_owner, 0) = 1');
            foreach ($mods as $m) {
                if ($m['user_id'] == $reporterId) continue;
                $post = db_fetch('SELECT thread_id FROM posts WHERE post_id = ?', [$postId]);
                create_notification((int) $m['user_id'], 'report', [
                    'from_user_id' => $reporterId,
                    'thread_id'    => $post['thread_id'] ?? null,
                    'post_id'      => $postId,
                ]);
            }
        } catch (Exception $e) {}

        return true;
    } catch (Exception $e) { return false; }
}

function open_reports_count(): int {
    try {
        return db_count("SELECT COUNT(*) FROM reports WHERE status = 'open'");
    } catch (Exception $e) { return 0; }
}

function list_reports(string $status = 'open', int $limit = 100): array {
    try {
        return db_fetchAll('
            SELECT r.*,
                   p.content    AS post_content,
                   p.user_id    AS author_id,
                   p.thread_id,
                   t.title      AS thread_title,
                   au.username  AS author_name,
                   ru.username  AS reporter_name,
                   mu.username  AS reviewer_name
            FROM reports r
            JOIN posts p     ON p.post_id    = r.post_id
            JOIN threads t   ON t.thread_id  = p.thread_id
            JOIN users au    ON au.user_id   = p.user_id
            JOIN users ru    ON ru.user_id   = r.reporter_id
            LEFT JOIN users mu ON mu.user_id = r.reviewed_by
            WHERE r.status = ?
            ORDER BY r.created_at DESC
            LIMIT ?
        ', [$status, $limit]);
    } catch (Exception $e) { return []; }
}

function resolve_report(int $reportId, int $modId, string $newStatus): bool {
    if (!in_array($newStatus, ['reviewed', 'dismissed', 'actioned'], true)) return false;
    try {
        db_execute(
            'UPDATE reports SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE report_id = ?',
            [$newStatus, $modId, $reportId]
        );
        return true;
    } catch (Exception $e) { return false; }
}
