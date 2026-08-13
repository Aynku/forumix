<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// lib/bookmarks.php

function toggle_bookmark(int $userId, int $threadId, string $note = ''): string {
    try {
        $exists = db_count(
            'SELECT COUNT(*) FROM bookmarks WHERE user_id = ? AND thread_id = ?',
            [$userId, $threadId]
        );
        if ($exists > 0) {
            db_execute(
                'DELETE FROM bookmarks WHERE user_id = ? AND thread_id = ?',
                [$userId, $threadId]
            );
            return 'removed';
        }
        db_execute(
            'INSERT INTO bookmarks (user_id, thread_id, note) VALUES (?, ?, ?)',
            [$userId, $threadId, $note]
        );
        try { check_achievements($userId); } catch (Exception $e) {}
        return 'added';
    } catch (Exception $e) {
        return 'error';
    }
}

function is_bookmarked(int $userId, int $threadId): bool {
    if ($userId <= 0) return false;
    try {
        return db_count(
            'SELECT COUNT(*) FROM bookmarks WHERE user_id = ? AND thread_id = ?',
            [$userId, $threadId]
        ) > 0;
    } catch (Exception $e) { return false; }
}

function user_bookmarks(int $userId, int $limit = 50): array {
    try {
        return db_fetchAll('
            SELECT bm.*, t.title, t.reply_count, t.view_count,
                   b.title AS board_title, b.board_id,
                   u.username AS author, u.user_id AS author_id
            FROM bookmarks bm
            JOIN threads t ON t.thread_id = bm.thread_id
            JOIN boards  b ON b.board_id  = t.board_id
            JOIN users   u ON u.user_id   = t.user_id
            WHERE bm.user_id = ? ' . board_filter_sql('b') . '
            ORDER BY bm.created_at DESC
            LIMIT ?
        ', [$userId, $limit]);
    } catch (Exception $e) { return []; }
}

function bookmark_count(int $userId): int {
    try {
        return db_count('SELECT COUNT(*) FROM bookmarks WHERE user_id = ?', [$userId]);
    } catch (Exception $e) { return 0; }
}
