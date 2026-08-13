<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// lib/edits.php – Beitragshistorie

function record_post_edit(int $postId, int $editorId, string $oldContent, string $reason = ''): void {
    try {
        db_execute(
            'INSERT INTO post_edits (post_id, editor_id, old_content, reason) VALUES (?, ?, ?, ?)',
            [$postId, $editorId, $oldContent, mb_substr($reason, 0, 255)]
        );
    } catch (Exception $e) {}
}

function post_edit_history(int $postId): array {
    try {
        return db_fetchAll('
            SELECT pe.*, u.username AS editor_name
            FROM post_edits pe JOIN users u ON u.user_id = pe.editor_id
            WHERE pe.post_id = ?
            ORDER BY pe.created_at DESC
        ', [$postId]);
    } catch (Exception $e) { return []; }
}

function post_edit_count(int $postId): int {
    try {
        return db_count('SELECT COUNT(*) FROM post_edits WHERE post_id = ?', [$postId]);
    } catch (Exception $e) { return 0; }
}
