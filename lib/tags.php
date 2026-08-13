<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// lib/tags.php – Thread-Tags

function all_tags(): array {
    try { return db_fetchAll('SELECT * FROM tags ORDER BY name'); }
    catch (Exception $e) { return []; }
}

function thread_tags(int $threadId): array {
    try {
        return db_fetchAll('
            SELECT t.* FROM tags t
            JOIN thread_tags tt ON tt.tag_id = t.tag_id
            WHERE tt.thread_id = ?
            ORDER BY t.name
        ', [$threadId]);
    } catch (Exception $e) { return []; }
}

function set_thread_tags(int $threadId, array $tagIds): void {
    try {
        db_execute('DELETE FROM thread_tags WHERE thread_id = ?', [$threadId]);
        $tagIds = array_unique(array_map('intval', $tagIds));
        // Maximal 5 Tags pro Thread
        $tagIds = array_slice($tagIds, 0, 5);
        foreach ($tagIds as $id) {
            if ($id <= 0) continue;
            db_execute('INSERT IGNORE INTO thread_tags (thread_id, tag_id) VALUES (?, ?)', [$threadId, $id]);
        }
        // Usage-Counter neu berechnen
        recalc_tag_usage();
    } catch (Exception $e) {}
}

function recalc_tag_usage(): void {
    try {
        db_execute('
            UPDATE tags t SET usage_count = (
                SELECT COUNT(*) FROM thread_tags WHERE tag_id = t.tag_id
            )
        ');
    } catch (Exception $e) {}
}

function threads_by_tag(int $tagId, int $limit = 50): array {
    try {
        $boardFilter = board_filter_sql('t', 'board_id');
        return db_fetchAll('
            SELECT t.*, b.title AS board_title, b.board_id, u.username AS author
            FROM threads t
            JOIN thread_tags tt ON tt.thread_id = t.thread_id
            JOIN boards b       ON b.board_id = t.board_id
            JOIN users u        ON u.user_id  = t.user_id
            WHERE tt.tag_id = ? ' . $boardFilter . '
            ORDER BY t.is_sticky DESC, t.thread_id DESC
            LIMIT ?
        ', [$tagId, $limit]);
    } catch (Exception $e) { return []; }
}

function render_tag_chip(array $tag): string {
    return '<a href="' . e(FORUM_URL) . '/tag.php?slug=' . e($tag['slug']) . '" '
         . 'class="tag-chip" style="border-color:' . e($tag['color']) . ';color:' . e($tag['color']) . '">'
         . e($tag['description'] ? $tag['description'] . ' ' : '') . e($tag['name'])
         . '</a>';
}
